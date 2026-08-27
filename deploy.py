#!/usr/bin/env python3
import os
import sys
import zipfile
import ftplib
import urllib.request
import urllib.parse
import re
import time
import io
import ssl
import base64
import secrets
import getpass
import argparse

EXCLUDE_FILES = {
    'deploy.py',
    'deploy.zip',
    'composer.phar',
    'test_ftp.py',
    'npm-debug.log',
    'yarn-error.log',
    '.phpunit.result.cache',
    '.deploy_timestamp',
    '.deploy_url',
    # The server's .env is built from the host's own credentials and uploaded
    # separately. Shipping the local one would overwrite the remote database
    # settings and APP_KEY, and would publish FTP_PASS to the web directory.
    '.env',
    '.env.production',
    '.env.backup',
    '.env.local',
}

PROD_ENV_FILE = '.env.production'
BACKUP_DIR = '.deploy-backups'

# The hosting panel writes its own key names into backend/.env; Laravel reads
# different ones. Translate instead of asking for credentials already on the server.
HOSTING_DB_ALIASES = {
    'DB_NAME': 'DB_DATABASE',
    'DB_USER': 'DB_USERNAME',
    'DB_PASS': 'DB_PASSWORD',
}

# Values the server owns. Anything here beats the local working copy.
REMOTE_OWNED_KEYS = {'CMS_SYSTEM_PASS', 'CMS_ADMIN_PASS'}

# Deploy credentials have no business sitting inside the web directory.
ENV_KEYS_NOT_ON_REMOTE = {'FTP_HOST', 'FTP_USER', 'FTP_PASS', 'FTP_REMOTE_DIR'}

ENV_PRODUCTION_FORCED = {
    'APP_ENV': 'production',
    'APP_DEBUG': 'false',
}

DB_PROMPTS = [
    ('DB_CONNECTION', 'Database driver', 'mysql', False, False),
    ('DB_HOST', 'Database host', 'localhost', False, False),
    ('DB_PORT', 'Database port', '3306', False, False),
    ('DB_DATABASE', 'Database name', '', True, False),
    ('DB_USERNAME', 'Database user', '', True, False),
    ('DB_PASSWORD', 'Database password', '', True, True),
]

DEFAULT_INDEX_FILES = {'index.html', 'index.htm', 'default.html', 'default.htm'}

# composer.json requires php ^8.2 (Laravel 12). The remote scripts refuse to run
# artisan under anything older rather than producing a confusing failure halfway in.
PHP_MINIMUM = '8.2'

# Printed as the first line by every generated script. A 200 that does not carry
# this marker is somebody else's page (a Plesk placeholder, a catch-all rewrite),
# not our trigger, and must not be mistaken for a successful run.
DEPLOY_MARKER = 'DEPLOY-SCRIPT-START'

# Masked whenever a summary is printed.
SECRET_KEY_HINTS = ('PASS', 'SECRET', 'KEY', 'TOKEN')


def parse_args(argv=None):
    """
    Ask only where the answer genuinely changes between runs, and only where the
    script cannot work it out for itself.

    Credentials are asked once and remembered. The packaging mode is not a question
    on a first deploy — there is nothing on the server to send a delta against — but
    it is one on every deploy after that. Migration and seeding are asked because the
    right answer depends on what the deploy contains, and their defaults follow
    whether this is an install or an update.

    Every question also has a flag, so a scripted run never blocks.
    """
    p = argparse.ArgumentParser(
        description="Deploy Laravel Ecommerce Core lên hosting qua FTP.",
        epilog="Không truyền cờ nào thì script tự hỏi những gì nó chưa biết.",
    )
    mode = p.add_mutually_exclusive_group()
    mode.add_argument('--incremental', action='store_true',
                      help="Chỉ đẩy file đã đổi kể từ lần deploy trước (bỏ qua câu hỏi chế độ).")
    mode.add_argument('--full', action='store_true',
                      help="Nén ZIP và đẩy toàn bộ (bỏ qua câu hỏi chế độ).")

    migrate = p.add_mutually_exclusive_group()
    migrate.add_argument('--migrate', action='store_true', help="Chạy migration, không hỏi.")
    migrate.add_argument('--no-migrate', action='store_true', help="Không chạy migration, không hỏi.")

    seed = p.add_mutually_exclusive_group()
    seed.add_argument('--seed', action='store_true', help="Chạy DatabaseSeeder, không hỏi.")
    seed.add_argument('--no-seed', action='store_true', help="Không seed, không hỏi.")

    p.add_argument('--keep-zip', action='store_true',
                   help="Giữ deploy.zip trên server sau khi giải nén (mặc định là xoá).")
    p.add_argument('--reuse-zip', action='store_true',
                   help="Dùng deploy.zip đã có sẵn trên server thay vì nén và upload lại.")
    p.add_argument('--domain', default=None,
                   help="Domain website remote. Mặc định lấy từ .deploy_url hoặc APP_URL.")
    p.add_argument('--keep-default-index', action='store_true',
                   help="Giữ index.html mặc định của panel (mặc định là xoá, có backup).")
    p.add_argument('--reuse-local-app-key', action='store_true',
                   help="Dùng APP_KEY của máy local khi server chưa có key. Chỉ đúng khi bạn "
                        "import dump local đã chứa dữ liệu mã hoá.")
    p.add_argument('-y', '--yes', action='store_true',
                   help="Không hỏi gì; dùng mặc định, thiếu thông tin thì báo lỗi và dừng.")
    return p.parse_args(argv)


def prompt_yes_no(question, default, args):
    if args.yes:
        return default
    suffix = '[C/k]' if default else '[c/K]'
    answer = input(f'{question} {suffix}: ').strip().lower()
    if answer == '':
        return default
    return answer in ('c', 'co', 'có', 'y', 'yes')


def prompt_choice(question, options, default_index, args):
    """options: list of (label, value)."""
    if args.yes:
        return options[default_index][1]
    print(question)
    for i, (label, _) in enumerate(options, 1):
        marker = ' (mặc định)' if i - 1 == default_index else ''
        print(f'  {i}. {label}{marker}')
    answer = input(f'Chọn [{default_index + 1}]: ').strip()
    if answer == '':
        return options[default_index][1]
    if answer.isdigit() and 1 <= int(answer) <= len(options):
        return options[int(answer) - 1][1]
    print('    (không hợp lệ, dùng mặc định)')
    return options[default_index][1]


def write_env_values(values):
    """Update or append keys in .env, leaving the rest of the file untouched."""
    path = '.env'
    with open(path) as f:
        lines = f.read().splitlines()

    remaining = dict(values)
    out = []
    for line in lines:
        match = re.match(r'^\s*([A-Z0-9_]+)\s*=', line)
        if match and match.group(1) in remaining:
            key = match.group(1)
            out.append(f'{key}={env_value_literal(remaining.pop(key))}')
        else:
            out.append(line)

    if remaining:
        out.extend(['', '# Thông tin FTP do deploy.py lưu lại'])
        out.extend(f'{k}={env_value_literal(v)}' for k, v in remaining.items())

    with open(path, 'w') as f:
        f.write('\n'.join(out).rstrip('\n') + '\n')


def resolve_ftp_credentials(env, args):
    """
    Ask for the connection once, then never again: the answers go into .env, which
    is where every later run reads them from. Nothing FTP-related is ever uploaded
    to the server, so keeping them there is safe.
    """
    required = [
        ('FTP_HOST', 'FTP host', '', False),
        ('FTP_USER', 'FTP user', '', False),
        ('FTP_PASS', 'FTP password', '', True),
        ('FTP_REMOTE_DIR', 'Thư mục trên host', 'httpdocs/backend', False),
    ]
    missing = [row for row in required if not env.get(row[0])]
    if not missing:
        return

    if args.yes:
        print('[-] Thiếu thông tin FTP và đang chạy với --yes: '
              + ', '.join(row[0] for row in missing))
        sys.exit(1)

    print('[?] Chưa có thông tin FTP. Nhập một lần, các lần sau script tự dùng lại.')
    captured = {}
    for key, label, default, secret in missing:
        while True:
            hint = f' [{default}]' if default else ''
            value = (getpass.getpass(f'    {label}{hint}: ') if secret
                     else input(f'    {label}{hint}: ')).strip() or default
            if value:
                break
            print('    (bắt buộc)')
        env[key] = value
        captured[key] = value

    write_env_values(captured)
    print(f'[+] Đã lưu {", ".join(captured)} vào .env.')


def server_has_application(ftp, remote_dir):
    """
    A first deploy is decided by the server, not by a local marker file: a fresh
    clone has no .deploy_timestamp even when the site is long since live, and an
    incremental push against an empty directory would ship a broken half-app.
    """
    try:
        ftp.cwd('/' + remote_dir.strip('/'))
        return 'artisan' in [name.split('/')[-1] for name in ftp.nlst()]
    except Exception:
        return False


def mask(key, value):
    if any(hint in key.upper() for hint in SECRET_KEY_HINTS):
        return '<hidden>'
    return value

def print_banner():
    print("=" * 60)
    print("         Laravel Ecommerce Core - Deploy Script")
    print("=" * 60)

def normalize_domain(value):
    """
    A bare hostname is not a URL: urlopen() raises "unknown url type" before it
    ever reaches the server, and a scheme-less APP_URL breaks every route() and
    MediaUrl the application generates. Default to https, which is what the
    hosting redirects to anyway.
    """
    value = (value or "").strip().rstrip("/")
    if not value:
        return value
    if not re.match(r'^https?://', value, re.IGNORECASE):
        value = "https://" + value
    return value


def parse_env_text(text):
    env = {}
    for line in text.splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, val = line.split("=", 1)
        env[key.strip()] = val.strip().strip('"').strip("'")
    return env


def load_env():
    if not os.path.exists(".env"):
        print("[-] Error: .env file not found. Run setup or create .env first.")
        sys.exit(1)
    with open(".env", "r") as f:
        return parse_env_text(f.read())


def env_value_literal(value):
    """
    Quote anything dotenv could misread. The hosting-generated password contains
    a '#', which is exactly the character that turns into an inline comment.
    """
    value = "" if value is None else str(value)
    if value and re.search(r'[\s#"\'\\$]', value):
        return '"' + value.replace("\\", "\\\\").replace('"', '\\"') + '"'
    return value


def generate_app_key():
    # Same shape as `artisan key:generate` for the AES-256-CBC cipher.
    return "base64:" + base64.b64encode(secrets.token_bytes(32)).decode()


def render_env_file(base_text, final_env):
    """Rewrite the local .env structure with production values, keeping comments."""
    seen = set()
    out = []
    for line in base_text.splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or "=" not in stripped:
            out.append(line)
            continue
        key = stripped.split("=", 1)[0].strip()
        if key in ENV_KEYS_NOT_ON_REMOTE or key not in final_env or key in seen:
            continue
        out.append(f"{key}={env_value_literal(final_env[key])}")
        seen.add(key)

    extra = [k for k in final_env if k not in seen and k not in ENV_KEYS_NOT_ON_REMOTE]
    if extra:
        out.extend(["", "# Added by deploy.py"])
        out.extend(f"{k}={env_value_literal(final_env[k])}" for k in extra)
    return "\n".join(out).rstrip("\n") + "\n"


def ftp_read_text(ftp, remote_dir, filename):
    """Read a small remote file into memory. Returns None when it is absent."""
    buf = io.BytesIO()
    try:
        ftp.cwd("/" + remote_dir.strip("/"))
        ftp.retrbinary(f"RETR {filename}", buf.write)
    except ftplib.error_perm:
        return None
    except Exception as e:
        print(f"[!] Could not read remote {filename}: {e}")
        return None
    return buf.getvalue().decode("utf-8", errors="replace")


def backup_remote_env(text, remote_dir):
    os.makedirs(BACKUP_DIR, exist_ok=True)
    stamp = time.strftime("%Y%m%d-%H%M%S")
    name = f"env-{remote_dir.strip('/').replace('/', '_')}-{stamp}.bak"
    path = os.path.join(BACKUP_DIR, name)
    with open(path, "w") as f:
        f.write(text)
    os.chmod(path, 0o600)
    print(f"[+] Backed up the server's existing .env to {path}")


def resolve_app_key(remote_env, prod_env, local_env, args):
    """
    APP_KEY must never change once encrypted rows exist. `payment_methods.settings`
    and `payment_transactions.payload` use the EncryptedJson cast, which swallows a
    DecryptException and returns an empty array — a rotated key does not raise, it
    silently blanks every payment gateway's configuration.

    No prompt: an existing key always wins, and a fresh install always wants a fresh
    key. Reusing the local key is the rare case, so it is an explicit flag.
    """
    for source, env in (("server", remote_env), (PROD_ENV_FILE, prod_env)):
        if env.get("APP_KEY"):
            print(f"[+] APP_KEY: giữ nguyên key sẵn có ({source}).")
            return env["APP_KEY"], False

    if args.reuse_local_app_key and local_env.get("APP_KEY"):
        print("[+] APP_KEY: dùng lại key của máy local (--reuse-local-app-key).")
        return local_env["APP_KEY"], True

    print(f"[+] APP_KEY: sinh key mới và ghim vào {PROD_ENV_FILE}.")
    return generate_app_key(), True


def build_remote_env(local_env, remote_env, prod_env, domain, app_key):
    final = dict(local_env)
    final.update(prod_env)

    for hosting_key, laravel_key in HOSTING_DB_ALIASES.items():
        if remote_env.get(hosting_key):
            final[laravel_key] = remote_env[hosting_key]

    # Credentials already on the server outrank the working copy: they belong to
    # the host, and the local values describe a developer machine.
    for key, value in remote_env.items():
        if key in HOSTING_DB_ALIASES:
            continue
        if key.startswith("DB_") or key in REMOTE_OWNED_KEYS:
            final[key] = value

    final["APP_KEY"] = app_key
    final["APP_URL"] = domain
    final.update(ENV_PRODUCTION_FORCED)
    for key in ENV_KEYS_NOT_ON_REMOTE:
        final.pop(key, None)
    return final


def persist_prod_env(prod_env, app_key, key_is_new, prompted):
    updated = dict(prod_env)
    if key_is_new:
        updated["APP_KEY"] = app_key
    updated.update(prompted)
    header = [
        "# Written by deploy.py. Production-only overrides; never commit this file.",
        "# APP_KEY is pinned here on purpose: rotating it makes existing encrypted",
        "# payment settings decrypt to an empty array without raising an error.",
        "",
    ]
    with open(PROD_ENV_FILE, "w") as f:
        f.write("\n".join(header))
        f.write("\n".join(f"{k}={env_value_literal(v)}" for k, v in updated.items()))
        f.write("\n")
    os.chmod(PROD_ENV_FILE, 0o600)
    print(f"[+] Saved {PROD_ENV_FILE} (chmod 600); the next deploy will not ask again.")


def sync_remote_env(ftp, remote_dir, local_env, domain, args):
    """
    Build the server's .env and upload it only when it would actually change.

    Nothing here asks for confirmation: the previous file is backed up first, so the
    write is reversible, and by construction the server's own DB credentials and
    APP_KEY always win — this can add or correct application config, never replace
    the host's secrets. The only unattended question is a credential nobody has.
    """
    print("-" * 60)
    print("[*] Đồng bộ .env production ...")
    with open(".env", "r") as f:
        local_text = f.read()

    remote_raw = ftp_read_text(ftp, remote_dir, ".env")
    remote_env = parse_env_text(remote_raw) if remote_raw else {}
    if not remote_raw:
        print("[!] Server chưa có .env; sẽ tạo mới.")

    prod_env = {}
    if os.path.exists(PROD_ENV_FILE):
        with open(PROD_ENV_FILE, "r") as f:
            prod_env = parse_env_text(f.read())

    app_key, key_is_new = resolve_app_key(remote_env, prod_env, local_env, args)
    final = build_remote_env(local_env, remote_env, prod_env, domain, app_key)

    missing = [row for row in DB_PROMPTS if row[3] and not final.get(row[0])]
    if missing and args.yes:
        print("[-] Thiếu thông tin database và đang chạy với --yes: "
              + ", ".join(row[0] for row in missing))
        print(f"[-] Điền các khoá đó vào {PROD_ENV_FILE} rồi chạy lại.")
        sys.exit(1)

    prompted = {}
    if missing:
        print("[?] Server chưa có thông tin database. Lấy trong panel Plesk rồi điền "
              "(chỉ hỏi một lần, lần sau tự dùng lại):")
    for key, label, default, required, secret in DB_PROMPTS:
        if final.get(key):
            continue
        while True:
            hint = f" [{default}]" if default else ""
            answer = (getpass.getpass(f"    {label}{hint}: ") if secret
                      else input(f"    {label}{hint}: ")).strip()
            answer = answer or default
            if answer or not required:
                break
            print("    (bắt buộc)")
        final[key] = answer
        prompted[key] = answer

    if key_is_new or prompted:
        persist_prod_env(prod_env, app_key, key_is_new, prompted)

    new_text = render_env_file(local_text, final)
    if remote_raw is not None and new_text == remote_raw:
        print("[+] .env trên server đã đúng; không cần ghi lại.")
        return

    changed = [k for k, v in final.items() if remote_env.get(k) != v]
    if changed:
        print(f"[*] {len(changed)} khoá thay đổi:")
        for key in sorted(changed):
            before = remote_env.get(key)
            print(f"    {key}: {mask(key, before) if before is not None else '(chưa có)'}"
                  f" -> {mask(key, final[key])}")

    if remote_raw:
        backup_remote_env(remote_raw, remote_dir)
    ftp_ensure_dir(ftp, remote_dir)
    ftp.storbinary("STOR .env", io.BytesIO(new_text.encode("utf-8")))
    print("[+] Đã ghi .env lên server.")


def remove_default_index(ftp, remote_dir, args):
    """
    Plesk drops a placeholder page into whatever it considers the document root,
    and DirectoryIndex lists index.html before index.php — so a leftover placeholder
    shadows Laravel's front controller and the site keeps serving "Domain Default
    page" after a perfectly good deploy.

    Both plausible roots are swept: the account's top folder, and the application's
    own public/ once the panel has been repointed at it.

    Deleted without asking, but only for the handful of known placeholder filenames
    and only after the file is copied into the local backup directory — so the
    action is reversible, which is what makes it safe to do unattended. Pass
    --keep-default-index to skip.
    """
    if args.keep_default_index:
        return

    top = remote_dir.strip("/").split("/")[0]
    targets = [top, remote_dir.strip("/") + "/public"]

    for directory in dict.fromkeys(targets):
        try:
            ftp.cwd("/" + directory)
            names = ftp.nlst()
        except Exception:
            continue

        for name in [n.split("/")[-1] for n in names]:
            if name.lower() not in DEFAULT_INDEX_FILES:
                continue
            try:
                buf = io.BytesIO()
                ftp.retrbinary(f"RETR {name}", buf.write)
                os.makedirs(BACKUP_DIR, exist_ok=True)
                backup = os.path.join(
                    BACKUP_DIR,
                    f"{directory.replace('/', '_')}-{name}-{time.strftime('%Y%m%d-%H%M%S')}.bak",
                )
                with open(backup, "wb") as f:
                    f.write(buf.getvalue())
                ftp.delete(name)
                print(f"[+] Đã xoá trang mặc định /{directory}/{name} (backup: {backup})")
            except Exception as e:
                print(f"[-] Không xoá được /{directory}/{name}: {e}")


def probe_secret_exposure(domain, remote_dir):
    """
    The application directory must sit outside the document root. When it does not,
    .env is downloadable over HTTP, so check rather than assume.

    deploy.zip is checked too: a kept package is a complete copy of the source, and
    one left behind by a failed run is just as readable.
    """
    suffix = remote_dir.strip("/").split("/")[1:]
    prefixes = [""] + (["/" + "/".join(suffix)] if suffix else [])

    # (path, how to recognise the real file rather than a catch-all 200 page)
    targets = []
    for prefix in prefixes:
        targets.append((prefix + "/.env", lambda body: b"APP_KEY" in body))
        targets.append((prefix + "/deploy.zip", lambda body: body.startswith(b"PK")))

    ssl_context = ssl._create_unverified_context()
    exposed = []
    for path, looks_real in targets:
        url = domain.rstrip("/") + path
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "deploy-check"})
            with urllib.request.urlopen(req, timeout=15, context=ssl_context) as resp:
                if resp.status == 200 and looks_real(resp.read(4096)):
                    exposed.append(url)
        except Exception:
            pass

    if not exposed:
        print("[+] Exposure check passed: .env va deploy.zip khong doc duoc qua HTTP.")
        return

    print("!" * 60)
    print("[!!!] FILE NHAY CAM DOC DUOC QUA HTTP:")
    for url in exposed:
        print(f"      {url}")
    print(f"      Tro document root cua Plesk vao {remote_dir.strip('/')}/public,")
    print("      sau do doi DB_PASSWORD trong panel va sinh lai APP_KEY.")
    print("!" * 60)


def should_exclude(rel_path):
    path_parts = rel_path.replace('\\', '/').split('/')
    
    # Core directories to skip entirely
    skip_entirely = {'.git', '.github', '.idea', '.vscode', 'node_modules', 'tests', BACKUP_DIR}
    for p in path_parts:
        if p in skip_entirely:
            return 'entirely'
            
    # Directories where we keep the folder structure but skip files inside
    skip_contents = {
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'bootstrap/cache'
    }
    normalized_path = '/'.join(path_parts)
    for sk in skip_contents:
        if normalized_path == sk or normalized_path.startswith(sk + '/'):
            return 'contents'
            
    return 'none'

def build_zip_package(zip_name="deploy.zip"):
    print("[*] Creating deployment package...")
    count = 0
    with zipfile.ZipFile(zip_name, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk('.'):
            rel_root = os.path.relpath(root, '.')
            if rel_root == '.':
                rel_root = ''
                
            exclude_status = should_exclude(rel_root)
            if exclude_status == 'entirely':
                dirs[:] = []  # Don't recurse
                continue
                
            # Create directory entry in zip
            if rel_root:
                zipf.write(root, rel_root)
                
            if exclude_status == 'contents':
                continue
                
            for file in files:
                if file in EXCLUDE_FILES or file.endswith('.log') or file.endswith('.tmp'):
                    continue
                file_path = os.path.join(root, file)
                arcname = os.path.join(rel_root, file) if rel_root else file
                zipf.write(file_path, arcname)
                count += 1
                if count % 200 == 0:
                    print(f"    Added {count} files...")
                    
    print(f"[+] Package created: {zip_name} (Total files: {count})")
    size_mb = os.path.getsize(zip_name) / (1024 * 1024)
    print(f"[+] Package size: {size_mb:.2f} MB")
    return zip_name

def get_changed_files(last_deploy_time):
    print("[*] Checking for modified/new files since last deployment (excluding vendor)...")
    changed_files = []
    for root, dirs, files in os.walk('.'):
        rel_root = os.path.relpath(root, '.')
        if rel_root == '.':
            rel_root = ''
            
        # Skip vendor directory entirely for incremental deploy
        path_parts = rel_root.replace('\\', '/').split('/')
        if 'vendor' in path_parts:
            dirs[:] = []  # Don't recurse
            continue
            
        exclude_status = should_exclude(rel_root)
        if exclude_status == 'entirely':
            dirs[:] = []  # Don't recurse
            continue
            
        if exclude_status == 'contents':
            continue
            
        for file in files:
            if file in EXCLUDE_FILES or file.endswith('.log') or file.endswith('.tmp'):
                continue
            file_path = os.path.join(root, file)
            arcname = os.path.join(rel_root, file) if rel_root else file
            
            try:
                mtime = os.path.getmtime(file_path)
                if mtime > last_deploy_time:
                    changed_files.append(arcname)
            except Exception:
                pass
    return changed_files

def ftp_connect(host, user, password):
    print(f"[*] Connecting to FTP server at {host}...")
    try:
        ftp = ftplib.FTP(host)
        ftp.login(user, password)
        print("[+] Logged in successfully!")
        return ftp
    except Exception as e:
        print(f"[-] FTP connection failed: {e}")
        sys.exit(1)

def ftp_ensure_dir(ftp, remote_path):
    ftp.cwd("/")
    parts = remote_path.strip('/').replace('\\', '/').split('/')
    for part in parts:
        if not part:
            continue
        try:
            ftp.cwd(part)
        except ftplib.error_perm:
            print(f"[*] Creating remote directory: {part} (inside {ftp.pwd()})")
            try:
                ftp.mkd(part)
                ftp.cwd(part)
            except Exception as e:
                print(f"[-] Failed to create directory {part}: {e}")
                sys.exit(1)

ensured_dirs = set()

def ftp_upload_file_path(ftp, remote_base_dir, rel_file_path):
    rel_dir = os.path.dirname(rel_file_path)
    filename = os.path.basename(rel_file_path)
    target_dir = os.path.join(remote_base_dir, rel_dir).replace('\\', '/').strip('/')
    
    if target_dir not in ensured_dirs:
        ftp_ensure_dir(ftp, target_dir)
        ensured_dirs.add(target_dir)
    else:
        ftp.cwd("/" + target_dir)
        
    with open(rel_file_path, "rb") as f:
        ftp.storbinary(f"STOR {filename}", f)

def upload_file(ftp, local_file, remote_file):
    print(f"[*] Uploading {local_file} to {remote_file}...")
    size = os.path.getsize(local_file)
    uploaded = 0
    
    def progress_callback(chunk):
        nonlocal uploaded
        uploaded += len(chunk)
        percent = (uploaded / size) * 100
        print(f"\r    Uploading: {percent:.1f}% ({uploaded}/{size} bytes)", end="", flush=True)

    with open(local_file, "rb") as f:
        ftp.storbinary(f"STOR {remote_file}", f, callback=progress_callback)
    print("\n[+] Upload complete.")

def auto_detect_domain(ip_host):
    url = f"http://{ip_host}/"
    print(f"[*] Resolving HTTP domain for {ip_host}...")
    try:
        ssl_context = ssl._create_unverified_context()
        with urllib.request.urlopen(url, timeout=5, context=ssl_context) as resp:
            resolved_url = resp.geturl()
            if resolved_url != url:
                print(f"[+] Auto-detected domain URL: {resolved_url}")
                return resolved_url.rstrip('/')
    except Exception as e:
        print(f"[!] Warning: Could not resolve domain automatically.")
    return f"http://{ip_host}"

def generate_extractor_php(run_migrate=False, seed_type='none', keep_zip=False):
    migrate_val = "true" if run_migrate else "false"

    # Keeping the package lets the next run skip re-uploading tens of megabytes with
    # --reuse-zip; deleting it keeps a full copy of the source off the server.
    zip_disposal = (
        'echo "Giu lai deploy.zip de lan sau dung --reuse-zip.\\n";'
        if keep_zip else
        '@unlink($zipFile);\n        echo "Da xoa deploy.zip.\\n";'
    )
    
    # Only DatabaseSeeder -> FoundationSeeder is offered here. Every other seeder
    # in this project truncates its tables (OasisProductSeeder wipes products,
    # categories and brands), so none of them belongs in a deploy path.
    seed_block = ""
    if seed_type == 'full':
        seed_block = """
echo "Seeding foundation data...\\n";
$output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan db:seed --force 2>&1");
echo $output . "\\n";
"""

    php_code = f"""<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '512M');
echo "{DEPLOY_MARKER}\\n";
// The panel decides which PHP serves the site; this script can only report it.
// Bail before touching anything so a wrong version never leaves a half-deploy.
echo "Web SAPI PHP: " . PHP_VERSION . " (" . PHP_SAPI . ")\\n";
if (version_compare(PHP_VERSION, '{PHP_MINIMUM}', '<')) {{
    echo "ERROR: site dang chay PHP " . PHP_VERSION . " nhung project yeu cau ^{PHP_MINIMUM}.\\n";
    echo "Sua trong Plesk: Websites & Domains -> PHP Settings -> PHP version, roi chay lai deploy.\\n";
    exit(1);
}}


$zipFile = '../deploy.zip';
if (!file_exists($zipFile)) {{
    echo "ERROR: Zip file $zipFile not found.\\n";
    exit(1);
}}

if (!class_exists('ZipArchive')) {{
    echo "ERROR: ZipArchive extension is not enabled on this PHP server.\\n";
    exit(1);
}}

echo "Extracting package...\\n";
$zip = new ZipArchive;
$res = $zip->open($zipFile);
if ($res === TRUE) {{
    $baseDir = dirname(__DIR__);
    if ($zip->extractTo($baseDir)) {{
        $zip->close();
        echo "SUCCESS: Extracted all files successfully.\\n";
        {zip_disposal}
    }} else {{
        $zip->close();
        echo "ERROR: Failed to extract files. Check write permissions of " . $baseDir . "\\n";
        exit(1);
    }}
}} else {{
    echo "ERROR: Could not open zip file. Code: " . $res . "\\n";
    exit(1);
}}

// Setup storage link so /storage/<file> reaches the public disk.
echo "Configuring storage link...\\n";
$baseDir = dirname(__DIR__);
$linkTarget = $baseDir . '/storage/app/public';
$publicStorage = $baseDir . '/public/storage';

if (!is_dir($linkTarget)) {{
    @mkdir($linkTarget, 0755, true);
}}

if (is_link($publicStorage)) {{
    // A link from an earlier deploy can point at a path that no longer
    // exists; replace it rather than trusting it.
    @unlink($publicStorage);
}}

if (is_dir($publicStorage)) {{
    // A real directory here holds uploaded files. Never delete it.
    echo "Storage directory already present; left untouched.\\n";
}} elseif (@symlink($linkTarget, $publicStorage) && is_dir($publicStorage)) {{
    echo "Storage link created.\\n";
}} else {{
    // Not fatal: the public disk has "serve" enabled, so the application
    // streams media itself when it cannot be exposed as a symlink.
    echo "WARNING: could not link public/storage (symlink() may be disabled).\\n";
    echo "         Images will be served by PHP instead. This is slower but works.\\n";
}}

/**
 * Pick the newest CLI interpreter that satisfies the requirement.
 *
 * Returning the first binary that answers is how `artisan migrate` ends up running
 * under the system default (often still 7.x) while a compatible interpreter sits
 * two lines further down the list.
 */
function getPHPExecutable() {{
    $candidates = glob('/opt/plesk/php/*/bin/php') ?: [];
    foreach (['php', '/usr/bin/php', '/usr/local/bin/php'] as $fallback) {{
        $candidates[] = $fallback;
    }}

    $usable = [];
    foreach (array_unique($candidates) as $cmd) {{
        $out = @shell_exec($cmd . ' -r "echo PHP_VERSION;" 2>&1');
        if ($out && preg_match('/^[0-9]+\\.[0-9]+\\.[0-9]+/', trim($out), $m)) {{
            $usable[$cmd] = $m[0];
        }}
    }}

    echo "PHP CLI co san:\\n";
    foreach ($usable as $cmd => $version) {{
        echo "  $version  $cmd\\n";
    }}

    $best = null;
    foreach ($usable as $cmd => $version) {{
        if (version_compare($version, '{PHP_MINIMUM}', '<')) {{
            continue;
        }}
        if ($best === null || version_compare($version, $usable[$best], '>')) {{
            $best = $cmd;
        }}
    }}
    return $best;
}}

$phpBin = getPHPExecutable();
if ($phpBin === null) {{
    echo "ERROR: khong tim thay PHP CLI >= {PHP_MINIMUM}; bo qua cac lenh artisan.\\n";
    echo "Chon PHP >= {PHP_MINIMUM} trong Plesk roi chay lai deploy.\\n";
    exit(1);
}}
echo "Dung PHP CLI: $phpBin\\n";

$baseDir = dirname(__DIR__);

// Cached config and routes survive an upload. Until they are dropped, the
// freshly deployed config/filesystems.php and the routes built from it do not
// take effect, and media keeps 403-ing on the old cached configuration.
echo "Clearing cached configuration, routes and views...\\n";
$output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan optimize:clear 2>&1");
echo $output . "\\n";

if ({migrate_val}) {{
    echo "Running migrations...\\n";
    $output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan migrate --force 2>&1");
    echo $output . "\\n";
}}

{seed_block}

echo "Deployment complete!\\n";
// Self-destruct
@unlink(__FILE__);
"""
    return php_code

def generate_artisan_trigger_php(run_migrate=False, seed_type='none'):
    migrate_val = "true" if run_migrate else "false"
    
    # Only DatabaseSeeder -> FoundationSeeder is offered here. Every other seeder
    # in this project truncates its tables (OasisProductSeeder wipes products,
    # categories and brands), so none of them belongs in a deploy path.
    seed_block = ""
    if seed_type == 'full':
        seed_block = """
echo "Seeding foundation data...\\n";
$output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan db:seed --force 2>&1");
echo $output . "\\n";
"""

    php_code = f"""<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
echo "{DEPLOY_MARKER}\\n";
// The panel decides which PHP serves the site; this script can only report it.
// Bail before touching anything so a wrong version never leaves a half-deploy.
echo "Web SAPI PHP: " . PHP_VERSION . " (" . PHP_SAPI . ")\\n";
if (version_compare(PHP_VERSION, '{PHP_MINIMUM}', '<')) {{
    echo "ERROR: site dang chay PHP " . PHP_VERSION . " nhung project yeu cau ^{PHP_MINIMUM}.\\n";
    echo "Sua trong Plesk: Websites & Domains -> PHP Settings -> PHP version, roi chay lai deploy.\\n";
    exit(1);
}}


/**
 * Pick the newest CLI interpreter that satisfies the requirement.
 *
 * Returning the first binary that answers is how `artisan migrate` ends up running
 * under the system default (often still 7.x) while a compatible interpreter sits
 * two lines further down the list.
 */
function getPHPExecutable() {{
    $candidates = glob('/opt/plesk/php/*/bin/php') ?: [];
    foreach (['php', '/usr/bin/php', '/usr/local/bin/php'] as $fallback) {{
        $candidates[] = $fallback;
    }}

    $usable = [];
    foreach (array_unique($candidates) as $cmd) {{
        $out = @shell_exec($cmd . ' -r "echo PHP_VERSION;" 2>&1');
        if ($out && preg_match('/^[0-9]+\\.[0-9]+\\.[0-9]+/', trim($out), $m)) {{
            $usable[$cmd] = $m[0];
        }}
    }}

    echo "PHP CLI co san:\\n";
    foreach ($usable as $cmd => $version) {{
        echo "  $version  $cmd\\n";
    }}

    $best = null;
    foreach ($usable as $cmd => $version) {{
        if (version_compare($version, '{PHP_MINIMUM}', '<')) {{
            continue;
        }}
        if ($best === null || version_compare($version, $usable[$best], '>')) {{
            $best = $cmd;
        }}
    }}
    return $best;
}}

$phpBin = getPHPExecutable();
if ($phpBin === null) {{
    echo "ERROR: khong tim thay PHP CLI >= {PHP_MINIMUM}; bo qua cac lenh artisan.\n";
    echo "Chon PHP >= {PHP_MINIMUM} trong Plesk roi chay lai deploy.\n";
    exit(1);
}}
echo "Dung PHP CLI: $phpBin\n";

$baseDir = dirname(__DIR__);

// Same storage wiring as the full deploy: an incremental upload can land a
// changed config/filesystems.php, and a missing public/storage link is the
// usual reason media 403s after a deploy.
echo "Configuring storage link...\n";
$linkTarget = $baseDir . '/storage/app/public';
$publicStorage = $baseDir . '/public/storage';

if (!is_dir($linkTarget)) {{
    @mkdir($linkTarget, 0755, true);
}}

if (is_link($publicStorage)) {{
    @unlink($publicStorage);
}}

if (is_dir($publicStorage)) {{
    echo "Storage directory already present; left untouched.\n";
}} elseif (@symlink($linkTarget, $publicStorage) && is_dir($publicStorage)) {{
    echo "Storage link created.\n";
}} else {{
    echo "WARNING: could not link public/storage (symlink() may be disabled).\n";
    echo "         Images will be served by PHP instead. This is slower but works.\n";
}}

// Clears config, routes, views and compiled classes. Routes matter here: the
// media-serving route is built from the filesystem config at boot and is
// skipped entirely while a stale route cache is present.
echo "Clearing cached configuration, routes and views...\n";
$outputClear = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan optimize:clear 2>&1");
echo $outputClear . "\n";

if ({migrate_val}) {{
    echo "Running migrations...\n";
    $output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan migrate --force 2>&1");
    echo $output . "\n";
}}

{seed_block}

echo "Artisan tasks completed.\\n";
// Self-destruct
@unlink(__FILE__);
"""
    return php_code

def trigger_candidate_urls(url, remote_dir, script_name):
    """
    The script is uploaded to <remote_dir>/public/<script_name>. Which URL that
    answers to depends entirely on where the panel's document root points, and the
    script cannot read that setting over FTP — so every sane layout is tried:

        docroot = <remote_dir>/public   ->  /<script>
        docroot = <remote_dir>          ->  /public/<script>
        docroot = <first segment>       ->  /<rest>/public/<script>

    Assuming only the last one is how an otherwise complete deploy silently leaves
    the package sitting on the server, unextracted.
    """
    parts = [p for p in remote_dir.strip('/').replace('\\', '/').split('/') if p]
    suffix = '/'.join(parts[1:])

    paths = [f"/{script_name}", f"/public/{script_name}"]
    if suffix:
        paths.append(f"/{suffix}/public/{script_name}")
        paths.append(f"/{suffix}/{script_name}")

    seen = set()
    candidates = []
    for path in paths:
        full = url.rstrip('/') + re.sub(r'/+', '/', path)
        if full not in seen:
            seen.add(full)
            candidates.append(full)
    return candidates


def trigger_http_url(url, remote_dir, script_name):
    candidates = trigger_candidate_urls(url, remote_dir, script_name)
    ssl_context = ssl._create_unverified_context()

    for trigger_url in candidates:
        print(f"[*] Triggering script via: {trigger_url}")
        try:
            req = urllib.request.Request(trigger_url, headers={'User-Agent': 'Mozilla/5.0'})
            with urllib.request.urlopen(req, timeout=300, context=ssl_context) as resp:
                content = resp.read().decode('utf-8', errors='replace')
                if DEPLOY_MARKER not in content:
                    # A 200 without the marker is the panel placeholder or a
                    # catch-all route, not our script.
                    print("    (200 nhưng không phải script deploy; thử URL khác)")
                    continue
                print("=" * 60)
                print("                  REMOTE EXECUTION OUTPUT")
                print("=" * 60)
                print(content)
                print("=" * 60)
                return "SUCCESS:" in content or "completed" in content.lower()
        except urllib.error.HTTPError as e:
            if e.code == 404:
                continue
            print(f"[-] HTTP Error: {e.code} - {e.reason}")
        except Exception as e:
            print(f"[-] HTTP Request to {script_name} failed: {e}")

    print(f"[!] Could not run {script_name} automatically via any endpoint.")
    print("[!] The file is on the server at "
          f"/{remote_dir.strip('/')}/public/{script_name} — open whichever of these")
    print("[!] URLs matches your document root, in a browser:")
    for candidate in candidates:
        print(f"      {candidate}")
    return False

def resolve_domain(env, ftp_host, args):
    """
    Order: explicit flag, the value cached from the last run, APP_URL, then a
    redirect probe. Only a completely fresh checkout has to be asked.
    """
    cached_file = ".deploy_url"
    for candidate in (args.domain,
                      open(cached_file).read() if os.path.exists(cached_file) else "",
                      env.get("APP_URL", "")):
        domain = normalize_domain(candidate)
        if domain and "localhost" not in domain and "127.0.0.1" not in domain:
            break
    else:
        domain = ""

    if not domain:
        domain = normalize_domain(auto_detect_domain(ftp_host))
        if not domain or ftp_host in domain:
            if args.yes:
                print("[-] Không xác định được domain. Dùng --domain https://... rồi chạy lại.")
                sys.exit(1)
            domain = normalize_domain(input("Nhập domain website remote: "))

    with open(cached_file, "w") as f:
        f.write(domain)
    return domain


def upload_and_trigger(ftp, remote_dir, script_name, code, domain):
    public_dir = os.path.join(remote_dir, "public").replace("\\", "/").strip("/")
    ftp_ensure_dir(ftp, public_dir)
    ftp.storbinary(f"STOR {script_name}", io.BytesIO(code.encode("utf-8")))
    print(f"[+] Uploaded {script_name}.")
    ftp.quit()
    return trigger_http_url(domain, remote_dir, script_name)


def main():
    args = parse_args()
    print_banner()
    env = load_env()

    # 1. Connection. Asked once, then remembered in .env.
    resolve_ftp_credentials(env, args)
    ftp_host = env['FTP_HOST']
    ftp_user = env['FTP_USER']
    ftp_pass = env['FTP_PASS']
    remote_dir = env.get('FTP_REMOTE_DIR', 'httpdocs/backend')

    domain = resolve_domain(env, ftp_host, args)
    print(f"[+] FTP {ftp_user}@{ftp_host} -> /{remote_dir.strip('/')}")
    print(f'[+] Web  {domain}')
    print('-' * 60)

    ftp = ftp_connect(ftp_host, ftp_user, ftp_pass)

    # 2. Packaging mode. The server decides whether this is an install or an update.
    first_deploy = not server_has_application(ftp, remote_dir)
    if args.incremental:
        incremental = True
    elif args.full:
        incremental = False
    elif first_deploy:
        # Nothing to diff against, so there is no question worth asking.
        print('[+] Server chưa có mã nguồn -> lần đầu: nén ZIP và đẩy toàn bộ.')
        incremental = False
    else:
        incremental = prompt_choice(
            'Server đã có mã nguồn. Đẩy kiểu nào?',
            [('Chỉ các file đã thay đổi (nhanh)', True),
             ('Nén ZIP và đẩy lại toàn bộ', False)],
            0, args,
        )

    # 3. Database. Defaults follow install vs update rather than being fixed.
    if args.migrate:
        migrate = True
    elif args.no_migrate:
        migrate = False
    else:
        migrate = prompt_yes_no('Chạy migration trên server?', True, args)

    seed_type = 'none'
    if args.no_seed:
        want_seed = False
    elif args.seed:
        want_seed = True
    elif not migrate:
        want_seed = False
    elif first_deploy:
        want_seed = prompt_yes_no(
            'Seed dữ liệu nền (tạo superadmin, quyền, feature)?', True, args)
    else:
        print('[!] Site đã cài rồi: seed sẽ BẬT LẠI toàn bộ feature flag.')
        print('    (Superadmin đã tồn tại thì seed không đụng tới mật khẩu.)')
        want_seed = prompt_yes_no('Vẫn seed?', False, args)

    if want_seed:
        seed_type = 'full'

    # 5. What happens to the package once it is unpacked. Only meaningful for a full
    # deploy, and the answer has to be decided now because the extractor script on the
    # server is what does the deleting.
    keep_zip = args.keep_zip
    if not incremental and not args.keep_zip:
        keep_zip = not prompt_yes_no(
            'Xoá deploy.zip trên server sau khi giải nén?', True, args)

    # 6. Last look before anything is written. Everything up to here was read-only.
    print('-' * 60)
    print('[=] Sắp thực hiện:')
    print(f"      Đích      /{remote_dir.strip('/')} trên {ftp_host}")
    print(f'      Website   {domain}')
    print(f"      Kiểu đẩy  {'chỉ file thay đổi' if incremental else 'nén ZIP toàn bộ'}"
          f"{' (dùng lại zip có sẵn)' if args.reuse_zip and not incremental else ''}")
    print(f"      Migration {'có' if migrate else 'không'}")
    print(f"      Seed      {'có' if want_seed else 'không'}")
    if not incremental:
        print(f"      deploy.zip{'giữ lại trên server' if keep_zip else ' xoá sau khi giải nén'}")
    print(f"      .env      ghi đè cấu hình app, giữ nguyên DB và APP_KEY của server")
    if not args.keep_default_index:
        print('      index.html xoá trang mặc định của panel nếu còn (có backup)')
    print('-' * 60)

    if not prompt_yes_no('Tiến hành?', True, args):
        print('[-] Đã huỷ. Chưa có gì được ghi lên server.')
        ftp.quit()
        sys.exit(0)

    # 4. The .env must be right before the trigger runs migrate/seed, and it is kept
    # out of the package so it can never clobber the server's own credentials.
    sync_remote_env(ftp, remote_dir, env, domain, args)
    remove_default_index(ftp, remote_dir, args)

    timestamp_file = '.deploy_timestamp'
    success = False

    if not incremental:
        zip_name = None
        if args.reuse_zip:
            try:
                ftp.cwd('/' + remote_dir.strip('/'))
                size = ftp.size('deploy.zip')
                print(f'[+] Dùng lại deploy.zip có sẵn trên server ({size / (1024 * 1024):.2f} MB).')
            except Exception:
                print('[!] --reuse-zip nhưng server không có deploy.zip; sẽ nén và upload lại.')
                args.reuse_zip = False

        ftp_ensure_dir(ftp, remote_dir)
        if not args.reuse_zip:
            zip_name = build_zip_package()
            upload_file(ftp, zip_name, 'deploy.zip')

        print('[*] Generating extractor script...')
        success = upload_and_trigger(
            ftp, remote_dir, 'unzip.php',
            generate_extractor_php(migrate, seed_type, keep_zip=keep_zip), domain,
        )

        if zip_name and os.path.exists(zip_name):
            os.remove(zip_name)

    else:
        last_deploy_time = 0.0
        if os.path.exists(timestamp_file):
            try:
                with open(timestamp_file) as f:
                    last_deploy_time = float(f.read().strip())
            except ValueError:
                pass

        if last_deploy_time == 0.0:
            print('[-] Máy này chưa có lịch sử deploy nên không biết file nào đã đổi.')
            print('[-] Chạy full một lần trước: python3 deploy.py --full')
            ftp.quit()
            sys.exit(1)

        changed_files = get_changed_files(last_deploy_time)
        if changed_files:
            print(f'[+] {len(changed_files)} file thay đổi:')
            for path in changed_files[:20]:
                print(f'  - {path}')
            if len(changed_files) > 20:
                print(f'  ... và {len(changed_files) - 20} file khác.')

            for index, path in enumerate(changed_files, 1):
                print(f'[{index}/{len(changed_files)}] Đang đẩy: {path}')
                try:
                    ftp_upload_file_path(ftp, remote_dir, path)
                except Exception as e:
                    print(f'[-] Lỗi khi đẩy file {path}: {e}')
        else:
            print('[+] Không có file nào thay đổi.')

        # The trigger runs even with nothing to upload: it refreshes the storage link
        # and drops the cached config and routes, without which an uploaded config
        # change stays inert.
        print('[*] Generating Artisan trigger script...')
        success = upload_and_trigger(
            ftp, remote_dir, 'artisan_trigger.php',
            generate_artisan_trigger_php(migrate, seed_type), domain,
        )

    if success:
        with open(timestamp_file, 'w') as f:
            f.write(str(time.time()))
        print('-' * 60)
        probe_secret_exposure(domain, remote_dir)
        print('[+++] DEPLOYMENT COMPLETED SUCCESSFULLY!')
        print(f'You can now visit your site at: {domain}')
    else:
        print('[-] Deployment had warnings or errors. Check remote logs or run manually.')


if __name__ == '__main__':
    main()

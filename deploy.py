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
import json

EXCLUDE_FILES = {
    'deploy.py',
    'new-project.py',
    'team.py',
    'devenv.py',
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

# Before site profiles there was one .env.production per checkout; still read once to migrate it.
LEGACY_PROD_ENV_FILE = '.env.production'
BACKUP_DIR = '.deploy-backups'

# One profile per website: FTP, database, admin password, APP_KEY and the last deploy time of
# that site, so one core checkout deploys any number of customer sites without editing .env.
# Never packaged or uploaded (should_exclude), never committed (.gitignore), chmod 600.
SITES_DIR = '.deploy-sites'

# The server's .env is built from this template — the core's documented settings — plus the
# site's profile, never from the developer's own .env.
ENV_TEMPLATE = '.env.example'

# The gateway's internal key lets deploy.py register a site and fetch its token by itself. It can
# create and close any project, so it lives in the user's home — never in a checkout, never
# uploaded — and is asked only once. GATEWAY_INTERNAL_KEY in the environment works too.
DEPLOYER_SECRETS = os.path.join(os.path.expanduser('~'), '.matbaows', 'deploy.env')

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
ENV_KEYS_NOT_ON_REMOTE = {'FTP_HOST', 'FTP_USER', 'FTP_PASS', 'FTP_REMOTE_DIR', 'DEPLOY_LAST_TS', 'HOST_CRON'}

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
                   help="Domain website cần deploy (bỏ qua câu hỏi chọn site).")
    p.add_argument('--gateway-token', action='store_true',
                   help="Lấy lại token gateway cho site này (token cũ ngừng hoạt động).")
    p.add_argument('--reconfigure', action='store_true',
                   help="Nhập lại FTP / database / admin của site này (APP_KEY được giữ nguyên).")
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


def host_of(domain):
    return re.sub(r'^https?://', '', domain or '', flags=re.IGNORECASE).split('/')[0].lower()


def site_profile_path(domain):
    return os.path.join(SITES_DIR, host_of(domain) + '.env')


def read_env_file(path):
    if not os.path.exists(path):
        return {}
    with open(path, encoding='utf-8') as f:
        return parse_env_text(f.read())


def save_site_profile(domain, site):
    os.makedirs(SITES_DIR, exist_ok=True)
    path = site_profile_path(domain)
    header = [
        f'# deploy.py profile of {host_of(domain)}. Holds passwords: never commit, never share.',
        '# APP_KEY is pinned on purpose: rotating it blanks the encrypted payment settings.',
        '',
    ]
    with open(path, 'w', encoding='utf-8') as f:
        f.write('\n'.join(header))
        f.write('\n'.join(f'{k}={env_value_literal(v)}' for k, v in site.items() if v not in (None, '')))
        f.write('\n')
    os.chmod(path, 0o600)
    return path


def saved_sites():
    if not os.path.isdir(SITES_DIR):
        return []
    return sorted(name[:-4] for name in os.listdir(SITES_DIR) if name.endswith('.env'))


def ask(label, default='', secret=False, required=True):
    while True:
        # A password's default is never echoed: Enter keeps it.
        hint = (' [Enter = giữ nguyên]' if secret else f' [{default}]') if default else ''
        prompt = f'    {label}{hint}: '
        answer = (getpass.getpass(prompt) if secret else input(prompt)).strip() or default
        if answer or not required:
            return answer
        print('    (bắt buộc)')


def read_pasted_block():
    """
    The hosting panel hands out its database as KEY=VALUE lines (DB_HOST, DB_NAME, DB_USER,
    DB_PASS, CMS_SYSTEM_PASS, CMS_ADMIN_PASS…). Paste them as they are; a blank line ends it.
    """
    lines = []
    while True:
        try:
            line = input()
        except EOFError:
            break
        if not line.strip():
            break
        lines.append(line)
    values = parse_env_text('\n'.join(lines))
    for hosting_key, laravel_key in HOSTING_DB_ALIASES.items():
        if hosting_key in values:
            values.setdefault(laravel_key, values.pop(hosting_key))
    return values


def choose_domain(args):
    if args.domain:
        return normalize_domain(args.domain)

    sites = saved_sites()
    # The site of the old single-site setup is offered too; choosing it migrates its settings.
    if os.path.exists('.deploy_url'):
        legacy_host = host_of(open('.deploy_url').read())
        if legacy_host and legacy_host not in sites:
            sites.append(legacy_host)
    if args.yes:
        if len(sites) == 1:
            return normalize_domain(sites[0])
        print('[-] Chạy với --yes thì phải chỉ rõ site: --domain https://...')
        sys.exit(1)

    print('[?] Deploy lên website nào?')
    for index, host in enumerate(sites, 1):
        print(f'    [{index}] {host}')
    print('    Hoặc gõ domain mới (vd: shop.webwow.vn)')
    while True:
        answer = input('    Chọn: ').strip()
        if answer.isdigit() and 1 <= int(answer) <= len(sites):
            return normalize_domain(sites[int(answer) - 1])
        if '.' in answer:
            return normalize_domain(answer)
        print('    Nhập số trong danh sách hoặc một domain.')


def legacy_profile(domain):
    """
    The single-site setup kept FTP in the checkout's .env, overrides in .env.production and
    the domain in .deploy_url. When those describe this domain, carry them into its profile
    once so the APP_KEY of a live site is not lost.
    """
    url_file = '.deploy_url'
    if not os.path.exists(url_file) or host_of(open(url_file).read()) != host_of(domain):
        return {}
    site = {k: v for k, v in read_env_file('.env').items() if k.startswith('FTP_')}
    site.update(read_env_file(LEGACY_PROD_ENV_FILE))
    if os.path.exists('.deploy_timestamp'):
        site['DEPLOY_LAST_TS'] = open('.deploy_timestamp').read().strip()
    if site:
        print(f'[+] Chuyển cấu hình deploy cũ (.env / {LEGACY_PROD_ENV_FILE}) vào hồ sơ của {host_of(domain)}.')
    return site


def gateway_internal_key(args):
    key = os.environ.get('GATEWAY_INTERNAL_KEY') or read_env_file(DEPLOYER_SECRETS).get('GATEWAY_INTERNAL_KEY')
    if key or args.yes:
        return key
    print('[?] Để tự lấy token, deploy.py cần khoá nội bộ của gateway (chỉ hỏi một lần cho máy này).')
    print("    Lấy trên VPS: grep '^GATEWAY_INTERNAL_KEY=' /opt/matbaows/env/gateway.env | cut -d= -f2-")
    key = ask('GATEWAY_INTERNAL_KEY (Enter = bỏ qua)', secret=True, required=False)
    if key:
        os.makedirs(os.path.dirname(DEPLOYER_SECRETS), exist_ok=True)
        with open(DEPLOYER_SECRETS, 'w', encoding='utf-8') as f:
            f.write('# deploy.py: MatBao gateway internal key. Never commit or share.' + chr(10))
            f.write(f'GATEWAY_INTERNAL_KEY={env_value_literal(key)}' + chr(10))
        os.chmod(DEPLOYER_SECRETS, 0o600)
        print(f'[+] Đã lưu khoá vào {DEPLOYER_SECRETS} (ngoài repo).')
    return key


def gateway_call(url, key, payload=None):
    """POST to the gateway's internal API. Returns (status, json body)."""
    request = urllib.request.Request(
        url, data=json.dumps(payload or {}).encode(), method='POST',
        headers={'Authorization': f'Bearer {key}', 'Content-Type': 'application/json', 'Accept': 'application/json'},
    )
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            return response.status, json.loads(response.read() or b'{}')
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read() or b'{}')
        except ValueError:
            return e.code, {}


def provision_gateway_token(gateway_url, domain, args):
    """
    Register the site on the gateway (or, when it is already there and the user agrees, issue it
    a new token) and return {MATBAO_AI_TOKEN, MATBAO_AI_WEBHOOK_SECRET}, or {} when skipped.
    """
    key = gateway_internal_key(args)
    if not key:
        return {}
    host = host_of(domain)
    base = gateway_url.rstrip('/')
    try:
        status, body = gateway_call(f'{base}/internal/projects', key,
                                    {'tool_domain': host, 'verified_origins': [f'https://{host}']})
        if status == 409 and body.get('reason') == 'already_exists':
            print(f'[!] {host} đã có trên gateway. Cấp token mới thì token đang dùng (nếu có) ngừng hoạt động.')
            if args.yes or not prompt_yes_no('Cấp token mới cho site này?', True, args):
                return {}
            status, body = gateway_call(f'{base}/internal/projects/{host}/token', key)
    except OSError as e:
        print(f'[-] Không gọi được gateway: {e}')
        return {}

    if status == 401:
        print(f'[-] Gateway từ chối GATEWAY_INTERNAL_KEY. Sửa hoặc xoá {DEPLOYER_SECRETS} rồi chạy lại.')
        return {}
    if status not in (200, 201) or not body.get('token'):
        print(f'[-] Gateway trả lỗi {status}: {body.get("reason", "?")}')
        return {}

    print(f'[+] Đã lấy token gateway cho {host}.')
    return {'MATBAO_AI_TOKEN': body['token'], 'MATBAO_AI_WEBHOOK_SECRET': body.get('webhook_secret', '')}


def resolve_site(args):
    """
    Which website, and everything needed to reach and configure it — asked here, once per
    site, and kept in .deploy-sites/<domain>.env. The checkout's own .env is never touched.
    """
    domain = choose_domain(args)
    site = read_env_file(site_profile_path(domain)) or legacy_profile(domain)
    is_new = not site

    if not is_new and not args.reconfigure and not args.yes:
        print(f'[+] Hồ sơ đã lưu của {host_of(domain)}:')
        for key in ('FTP_HOST', 'FTP_USER', 'FTP_REMOTE_DIR', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'ADMIN_EMAIL'):
            if site.get(key):
                print(f'      {key:<15} {site[key]}')
        if not prompt_yes_no('Dùng thông tin này?', True, args):
            args.reconfigure = True

    if args.reconfigure:
        # Everything is asked again except the APP_KEY, which must never change on a live site.
        site = {k: v for k, v in site.items() if k in ('APP_KEY', 'DEPLOY_LAST_TS')}

    needs = lambda *keys: any(not site.get(k) for k in keys)
    if (needs('FTP_HOST', 'FTP_USER', 'FTP_PASS') or needs('DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD')) and args.yes:
        print(f'[-] Site {host_of(domain)} chưa có đủ thông tin và đang chạy --yes. Chạy lại không có --yes.')
        sys.exit(1)

    if needs('FTP_HOST', 'FTP_USER', 'FTP_PASS'):
        print(f'[?] FTP của {host_of(domain)} (Plesk → Websites & Domains → FTP Access):')
        site['FTP_HOST'] = ask('FTP host', site.get('FTP_HOST') or host_of(domain))
        site['FTP_USER'] = ask('FTP user', site.get('FTP_USER', ''))
        site['FTP_PASS'] = ask('FTP password', secret=True)
        site['FTP_REMOTE_DIR'] = ask('Thư mục chứa code trên host', site.get('FTP_REMOTE_DIR') or 'httpdocs/backend')

    if needs('DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'):
        print('[?] Database. Dán nguyên khối thông tin từ panel (DB_HOST=..., DB_NAME=..., DB_USER=...,')
        print('    DB_PASS=..., CMS_ADMIN_PASS=...), xong nhấn Enter ở một dòng trống.')
        print('    Hoặc nhấn Enter ngay để nhập từng mục:')
        site.update({k: v for k, v in read_pasted_block().items() if v})
        for key, label, default, required, secret in DB_PROMPTS:
            if not site.get(key):
                site[key] = ask(label, default, secret=secret, required=required)

    if is_new or args.reconfigure or not site.get('ADMIN_EMAIL'):
        print('[?] Tài khoản superadmin (tạo ở lần cài đầu; site đã có thì giữ nguyên):')
        site['ADMIN_EMAIL'] = ask('Email đăng nhập admin', site.get('ADMIN_EMAIL') or f'admin@{host_of(domain)}')
        default_pass = site.get('ADMIN_PASSWORD') or site.get('CMS_ADMIN_PASS', '')
        site['ADMIN_PASSWORD'] = ask('Mật khẩu admin' + (' (CMS_ADMIN_PASS của panel)' if default_pass else ''),
                                     default_pass, secret=True)

    if 'MATBAO_AI_URL' not in site or args.reconfigure or args.gateway_token:
        # Mail goes through the MatBao relay when the site has a gateway token; without one, and
        # without SMTP in Admin → Cài đặt thông báo, the server's MAIL_MAILER=log only logs it.
        print('[?] Kết nối gateway Mắt Bão: gửi email, AI SEO, cập nhật core.')
        site['MATBAO_AI_URL'] = ask('Gateway URL', site.get('MATBAO_AI_URL') or 'https://aimbw.webwow.vn')
        token = '' if args.gateway_token else ask(
            'MATBAO_AI_TOKEN (Enter = tự lấy từ gateway)', site.get('MATBAO_AI_TOKEN', ''), secret=True, required=False)
        if token:
            site['MATBAO_AI_TOKEN'] = token
        else:
            site.update({k: v for k, v in provision_gateway_token(site['MATBAO_AI_URL'], domain, args).items() if v})

    # Contact form and order notifications fall back to MAIL_SELLER: the admin's address
    # rather than the template's owner@example.com.
    if not site.get('MAIL_SELLER') and site.get('ADMIN_EMAIL'):
        site['MAIL_SELLER'] = site['ADMIN_EMAIL']

    path = save_site_profile(domain, site)
    print(f'[+] Đã lưu hồ sơ {path} (chmod 600). Lần sau chỉ cần chọn site.')
    return domain, site


def resolve_app_key(remote_env, site, args):
    """
    APP_KEY must never change once encrypted rows exist. `payment_methods.settings`
    and `payment_transactions.payload` use the EncryptedJson cast, which swallows a
    DecryptException and returns an empty array — a rotated key does not raise, it
    silently blanks every payment gateway's configuration.

    No prompt: an existing key always wins, and a fresh install always wants a fresh
    key. Reusing the local key is the rare case, so it is an explicit flag.
    """
    for source, env in (("server", remote_env), ("hồ sơ site", site)):
        if env.get("APP_KEY"):
            print(f"[+] APP_KEY: giữ nguyên key sẵn có ({source}).")
            return env["APP_KEY"], False

    local_key = read_env_file('.env').get("APP_KEY")
    if args.reuse_local_app_key and local_key:
        print("[+] APP_KEY: dùng lại key của máy local (--reuse-local-app-key).")
        return local_key, True

    print("[+] APP_KEY: sinh key mới và ghim vào hồ sơ site.")
    return generate_app_key(), True


def build_remote_env(template_env, remote_env, site, domain, app_key):
    """
    Template (.env.example) → what the server already has for the database → what was typed
    for this site → production values that are never negotiable.
    """
    final = dict(template_env)

    for hosting_key, laravel_key in HOSTING_DB_ALIASES.items():
        if remote_env.get(hosting_key):
            final[laravel_key] = remote_env[hosting_key]
    for key, value in remote_env.items():
        if key in HOSTING_DB_ALIASES:
            continue
        if key.startswith("DB_") or key in REMOTE_OWNED_KEYS:
            final[key] = value

    for key, value in site.items():
        if key in HOSTING_DB_ALIASES or key in ENV_KEYS_NOT_ON_REMOTE:
            continue
        if value not in (None, ''):
            final[key] = value

    final["APP_KEY"] = app_key
    final["APP_URL"] = domain
    final.update(ENV_PRODUCTION_FORCED)
    for key in ENV_KEYS_NOT_ON_REMOTE:
        final.pop(key, None)
    return final


def sync_remote_env(ftp, remote_dir, site, domain, args):
    """
    Build the server's .env and upload it only when it would actually change.

    Built from .env.example plus this site's profile — never from the developer's .env —
    with APP_ENV=production, APP_DEBUG=false and APP_URL forced. The previous server file is
    backed up first, and its APP_KEY always wins.
    """
    print("-" * 60)
    print("[*] Đồng bộ .env production ...")
    if not os.path.exists(ENV_TEMPLATE):
        print(f"[-] Thiếu {ENV_TEMPLATE}: không có mẫu để dựng .env cho server.")
        sys.exit(1)
    with open(ENV_TEMPLATE, encoding='utf-8') as f:
        template_text = f.read()

    remote_raw = ftp_read_text(ftp, remote_dir, ".env")
    remote_env = parse_env_text(remote_raw) if remote_raw else {}
    if not remote_raw:
        print("[!] Server chưa có .env; sẽ tạo mới.")

    app_key, key_is_new = resolve_app_key(remote_env, site, args)
    final = build_remote_env(parse_env_text(template_text), remote_env, site, domain, app_key)

    missing = [row[0] for row in DB_PROMPTS if row[3] and not final.get(row[0])]
    if missing:
        print("[-] Thiếu thông tin database: " + ", ".join(missing) + ". Chạy lại với --reconfigure.")
        sys.exit(1)

    if key_is_new or site.get("APP_KEY") != app_key:
        site["APP_KEY"] = app_key
        save_site_profile(domain, site)

    if not final.get("MATBAO_AI_TOKEN") and final.get("MAIL_MAILER", "log") == "log":
        print("[!] Chưa có MATBAO_AI_TOKEN: email sẽ chỉ ghi vào log trên host. Bật SMTP trong")
        print("    Admin → Cài đặt thông báo, hoặc chạy lại với --reconfigure để nhập token gateway.")

    new_text = render_env_file(template_text, final)
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
    ftp_store(ftp, ".env", lambda: io.BytesIO(new_text.encode("utf-8")))
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
    skip_entirely = {'.git', '.github', '.idea', '.vscode', 'node_modules', 'tests', BACKUP_DIR, SITES_DIR}
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

FTP_RETRIES = 3


def ftp_store(ftp, remote_name, open_source, callback=None):
    """
    STOR with retries. On shared hosting the passive data connection times out now and then
    while the control connection stays up, so the same session simply tries again.
    `open_source` returns a fresh readable file object for each attempt.
    """
    for attempt in range(1, FTP_RETRIES + 1):
        try:
            with open_source() as source:
                ftp.storbinary(f"STOR {remote_name}", source, callback=callback)
            return
        except (OSError, ftplib.error_temp) as e:
            if attempt == FTP_RETRIES:
                raise
            print(f"\n[!] Upload {remote_name} lỗi ({e.__class__.__name__}); thử lại lần {attempt + 1}/{FTP_RETRIES}...")
            time.sleep(2 * attempt)


def ftp_upload_file_path(ftp, remote_base_dir, rel_file_path):
    rel_dir = os.path.dirname(rel_file_path)
    filename = os.path.basename(rel_file_path)
    target_dir = os.path.join(remote_base_dir, rel_dir).replace('\\', '/').strip('/')
    
    if target_dir not in ensured_dirs:
        ftp_ensure_dir(ftp, target_dir)
        ensured_dirs.add(target_dir)
    else:
        ftp.cwd("/" + target_dir)
        
    ftp_store(ftp, filename, lambda: open(rel_file_path, "rb"))

def upload_file(ftp, local_file, remote_file):
    print(f"[*] Uploading {local_file} to {remote_file}...")
    size = os.path.getsize(local_file)
    uploaded = 0
    
    def progress_callback(chunk):
        nonlocal uploaded
        uploaded += len(chunk)
        percent = (uploaded / size) * 100
        print(f"\r    Uploading: {percent:.1f}% ({uploaded}/{size} bytes)", end="", flush=True)

    def source():
        nonlocal uploaded
        uploaded = 0  # a retry starts the file over
        return open(local_file, "rb")

    ftp_store(ftp, remote_file, source, callback=progress_callback)
    print("\n[+] Upload complete.")

def generate_extractor_php(run_migrate=False, seed_type='none', keep_zip=False):
    migrate_val = "true" if run_migrate else "false"

    # Keeping the package lets the next run skip re-uploading tens of megabytes with
    # --reuse-zip; deleting it keeps a full copy of the source off the server.
    zip_disposal = (
        'echo "Giu lai deploy.zip de lan sau dung --reuse-zip.\\n";'
        if keep_zip else
        '@unlink($zipFile);\n        echo "Da xoa deploy.zip.\\n";'
    )
    
    # DatabaseSeeder only runs the idempotent FoundationSeeder, so it is the
    # only seed path exposed during deployment.
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

// The core files are now exactly what this deploy shipped: record them, so the updater
// (which refuses a website without a baseline, or with core files edited by hand) can run.
echo "Recording core baseline...\\n";
echo shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan core:status --baseline 2>&1") . "\\n";

echo "Checking MatBao gateway...\\n";
echo shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan gateway:check 2>&1") . "\\n";

echo "Deployment complete!\\n";
// Self-destruct
@unlink(__FILE__);
"""
    return php_code

def generate_artisan_trigger_php(run_migrate=False, seed_type='none'):
    migrate_val = "true" if run_migrate else "false"
    
    # DatabaseSeeder only runs the idempotent FoundationSeeder, so it is the
    # only seed path exposed during deployment.
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

// The core files are now exactly what this deploy shipped: record them, so the updater
// (which refuses a website without a baseline, or with core files edited by hand) can run.
echo "Recording core baseline...\\n";
echo shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan core:status --baseline 2>&1") . "\\n";

echo "Checking MatBao gateway...\\n";
echo shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan gateway:check 2>&1") . "\\n";

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

def upload_and_trigger(ftp, remote_dir, script_name, code, domain):
    public_dir = os.path.join(remote_dir, "public").replace("\\", "/").strip("/")
    ftp_ensure_dir(ftp, public_dir)
    ftp_store(ftp, script_name, lambda: io.BytesIO(code.encode("utf-8")))
    print(f"[+] Uploaded {script_name}.")
    ftp.quit()
    return trigger_http_url(domain, remote_dir, script_name)


def main():
    args = parse_args()
    print_banner()

    # 1. Which site, and its connection — from its profile in .deploy-sites/, asked once.
    domain, site = resolve_site(args)
    ftp_host = site['FTP_HOST']
    ftp_user = site['FTP_USER']
    ftp_pass = site['FTP_PASS']
    remote_dir = site.get('FTP_REMOTE_DIR', 'httpdocs/backend')

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
    print(f"      .env      dựng từ .env.example + hồ sơ site (production), giữ APP_KEY của server")
    if not args.keep_default_index:
        print('      index.html xoá trang mặc định của panel nếu còn (có backup)')
    print('-' * 60)

    if not prompt_yes_no('Tiến hành?', True, args):
        print('[-] Đã huỷ. Chưa có gì được ghi lên server.')
        ftp.quit()
        sys.exit(0)

    # 4. The .env must be right before the trigger runs migrate/seed, and it is kept
    # out of the package so it can never clobber the server's own credentials.
    sync_remote_env(ftp, remote_dir, site, domain, args)
    remove_default_index(ftp, remote_dir, args)

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
        # Per site: what changed since *this* site was last deployed from this machine.
        try:
            last_deploy_time = float(site.get('DEPLOY_LAST_TS') or 0)
        except ValueError:
            last_deploy_time = 0.0

        if last_deploy_time == 0.0:
            print('[-] Máy này chưa có lịch sử deploy nên không biết file nào đã đổi.')
            print(f'[-] Chạy full một lần trước: python3 deploy.py --full --domain {host_of(domain)}')
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
        site['DEPLOY_LAST_TS'] = str(time.time())
        save_site_profile(domain, site)
        print('-' * 60)
        probe_secret_exposure(domain, remote_dir)
        print('[+++] DEPLOYMENT COMPLETED SUCCESSFULLY!')
        print(f'You can now visit your site at: {domain}')
    else:
        print('[-] Deployment had warnings or errors. Check remote logs or run manually.')


if __name__ == '__main__':
    main()

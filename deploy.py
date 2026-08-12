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

EXCLUDE_FILES = {
    'deploy.py',
    'deploy.zip',
    'composer.phar',
    'test_ftp.py',
    'npm-debug.log',
    'yarn-error.log',
    '.phpunit.result.cache',
    '.deploy_timestamp',
    '.deploy_url'
}

def print_banner():
    print("=" * 60)
    print("         Laravel Ecommerce Core - Deploy Script")
    print("=" * 60)

def load_env():
    env = {}
    if not os.path.exists(".env"):
        print("[-] Error: .env file not found. Run setup or create .env first.")
        sys.exit(1)
        
    with open(".env", "r") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if "=" in line:
                key, val = line.split("=", 1)
                val = val.strip().strip('"').strip("'")
                env[key.strip()] = val
    return env

def should_exclude(rel_path):
    path_parts = rel_path.replace('\\', '/').split('/')
    
    # Core directories to skip entirely
    skip_entirely = {'.git', '.github', '.idea', '.vscode', 'node_modules', 'tests'}
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

def generate_extractor_php(run_migrate=False, seed_type='none'):
    migrate_val = "true" if run_migrate else "false"
    
    seed_block = ""
    if seed_type == 'full':
        seed_block = """
echo "Seeding entire database...\\n";
$output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan db:seed --force 2>&1");
echo $output . "\\n";
"""
    elif seed_type == 'products':
        seed_block = """
echo "Seeding products database...\\n";
$output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan db:seed --class=OasisProductSeeder --force 2>&1");
echo $output . "\\n";
"""

    php_code = f"""<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '512M');

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
        @unlink($zipFile);
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

// Helper to get PHP CLI
function getPHPExecutable() {{
    $candidates = [
        'php',
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/plesk/php/8.2/bin/php',
        '/opt/plesk/php/8.3/bin/php',
        '/opt/plesk/php/8.1/bin/php',
    ];
    foreach ($candidates as $cmd) {{
        $out = @shell_exec($cmd . ' -v 2>&1');
        if (strpos($out, 'PHP') !== false) {{
            return $cmd;
        }}
    }}
    return 'php';
}}

$phpBin = getPHPExecutable();
echo "Using PHP CLI binary: $phpBin\\n";

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
    
    seed_block = ""
    if seed_type == 'full':
        seed_block = """
echo "Seeding entire database...\\n";
$output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan db:seed --force 2>&1");
echo $output . "\\n";
"""
    elif seed_type == 'products':
        seed_block = """
echo "Seeding products database...\\n";
$output = shell_exec("cd " . escapeshellarg($baseDir) . " && $phpBin artisan db:seed --class=OasisProductSeeder --force 2>&1");
echo $output . "\\n";
"""

    php_code = f"""<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);

// Helper to get PHP CLI
function getPHPExecutable() {{
    $candidates = [
        'php',
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/plesk/php/8.2/bin/php',
        '/opt/plesk/php/8.3/bin/php',
        '/opt/plesk/php/8.1/bin/php',
    ];
    foreach ($candidates as $cmd) {{
        $out = @shell_exec($cmd . ' -v 2>&1');
        if (strpos($out, 'PHP') !== false) {{
            return $cmd;
        }}
    }}
    return 'php';
}}

$phpBin = getPHPExecutable();
echo "Using PHP CLI binary: $phpBin\n";

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

def trigger_http_url(url, remote_dir, script_name):
    path_parts = remote_dir.strip('/').replace('\\', '/').split('/')
    if path_parts and path_parts[0].lower() in ('httpdocs', 'public_html'):
        web_path = '/' + '/'.join(path_parts[1:])
    else:
        web_path = '/' + '/'.join(path_parts)
    
    web_path = web_path.replace('//', '/')
    if not web_path.endswith('/'):
        web_path += '/'
        
    url_direct = f"{url}{web_path}{script_name}"
    url_fallback = f"{url}{web_path}public/{script_name}"
    
    urls_to_try = [url_direct, url_fallback]
    ssl_context = ssl._create_unverified_context()
    
    for trigger_url in urls_to_try:
        print(f"[*] Triggering script via: {trigger_url}")
        try:
            req = urllib.request.Request(trigger_url, headers={'User-Agent': 'Mozilla/5.0'})
            with urllib.request.urlopen(req, timeout=300, context=ssl_context) as resp:
                content = resp.read().decode('utf-8')
                print("=" * 60)
                print("                  REMOTE EXECUTION OUTPUT")
                print("=" * 60)
                print(content)
                print("=" * 60)
                if "SUCCESS:" in content or "completed" in content.lower():
                    return True
        except urllib.error.HTTPError as e:
            if e.code == 404:
                continue
            print(f"[-] HTTP Error: {e.code} - {e.reason}")
        except Exception as e:
            print(f"[-] HTTP Request to {script_name} failed: {e}")
            
    print(f"[!] Could not run {script_name} automatically via any endpoint.")
    print(f"[!] Please visit manually: {url_direct} or {url_fallback}")
    return False

def main():
    print_banner()
    env = load_env()
    
    ftp_host = env.get("FTP_HOST")
    ftp_user = env.get("FTP_USER")
    ftp_pass = env.get("FTP_PASS")
    remote_dir = env.get("FTP_REMOTE_DIR", "httpdocs/backend")
    
    if not all([ftp_host, ftp_user, ftp_pass]):
        print("[-] Error: FTP credentials missing from .env")
        sys.exit(1)
        
    print(f"[+] FTP Host: {ftp_host}")
    print(f"[+] FTP User: {ftp_user}")
    print(f"[+] FTP Remote Dir: {remote_dir}")
    print("-" * 60)
    
    # 1. Ask for Deployment Mode
    print("Chọn phương thức Deploy:")
    print("  1. Full Deploy (Nén ZIP và đẩy toàn bộ - khuyên dùng)")
    print("  2. Incremental Deploy (Chỉ đẩy các file có sự thay đổi)")
    
    mode = input("Chọn chế độ [1]: ").strip()
    if not mode:
        mode = "1"
        
    migrate = input("Run database migrations on remote server? (y/n) [n]: ").strip().lower() == 'y'
    seed_type = 'none'
    if migrate:
        print("Chọn chế độ Seed CSDL:")
        print("  1. Seed toàn bộ (Chạy DatabaseSeeder - Khuyên dùng để tạo Superadmin)")
        print("  2. Chỉ seed sản phẩm (OasisProductSeeder)")
        print("  3. Không seed")
        seed_choice = input("Chọn chế độ [3]: ").strip()
        if seed_choice == '1':
            seed_type = 'full'
        elif seed_choice == '2':
            seed_type = 'products'
        
    # Auto-detect or retrieve cached domain name
    domain = env.get("APP_URL", f"http://{ftp_host}").rstrip('/')
    if "localhost" in domain or "127.0.0.1" in domain:
        cached_url_file = ".deploy_url"
        cached_url = ""
        if os.path.exists(cached_url_file):
            with open(cached_url_file, "r") as f:
                cached_url = f.read().strip()
                
        prompt_url = cached_url if cached_url else auto_detect_domain(ftp_host)
        user_url = input(f"Nhập domain website remote [{prompt_url}]: ").strip()
        if user_url:
            domain = user_url.rstrip('/')
        else:
            domain = prompt_url.rstrip('/')
            
        with open(cached_url_file, "w") as f:
            f.write(domain)
            
    print(f"[+] Target Web URL: {domain}")
    print("-" * 60)
    
    # Connect to FTP
    ftp = ftp_connect(ftp_host, ftp_user, ftp_pass)
    
    timestamp_file = ".deploy_timestamp"
    success = False
    
    if mode == "1":
        # Mode 1: ZIP Full Deploy
        zip_name = build_zip_package()
        
        # Ensure remote dir
        ftp_ensure_dir(ftp, remote_dir)
        
        # Upload ZIP
        upload_file(ftp, zip_name, "deploy.zip")
        
        # Ensure public dir
        public_dir = os.path.join(remote_dir, "public").replace('\\', '/').strip('/')
        ftp_ensure_dir(ftp, public_dir)
        
        # Generate & Upload unzip.php
        print("[*] Generating extractor script...")
        unzip_code = generate_extractor_php(migrate, seed_type)
        unzip_bio = io.BytesIO(unzip_code.encode('utf-8'))
        ftp.storbinary("STOR unzip.php", unzip_bio)
        print("[+] Extractor script uploaded.")
        ftp.quit()
        
        # Trigger Extractor
        success = trigger_http_url(domain, remote_dir, "unzip.php")
        
        if os.path.exists(zip_name):
            os.remove(zip_name)
            
    else:
        # Mode 2: Incremental Deploy
        last_deploy_time = 0.0
        if os.path.exists(timestamp_file):
            with open(timestamp_file, "r") as f:
                try:
                    last_deploy_time = float(f.read().strip())
                except ValueError:
                    pass
                    
        if last_deploy_time == 0.0:
            print("[!] Cảnh báo: Không tìm thấy lịch sử deploy trước đó.")
            confirm_all = input("Bạn có muốn coi tất cả các file là file thay đổi và upload? (y/n) [n]: ").strip().lower() == 'y'
            if not confirm_all:
                print("[-] Đã hủy deploy.")
                ftp.quit()
                sys.exit(0)
                
        changed_files = get_changed_files(last_deploy_time)
        if not changed_files:
            print("[+] Không có file nào thay đổi kể từ lần deploy trước.")
            run_db_only = input("Bạn có muốn chạy migrations hoặc seed CSDL trên remote không? (y/n) [n]: ").strip().lower() == 'y'
            if run_db_only:
                migrate = input("Run database migrations on remote server? (y/n) [n]: ").strip().lower() == 'y'
                seed_type = 'none'
                if migrate:
                    print("Chọn chế độ Seed CSDL:")
                    print("  1. Seed toàn bộ (Chạy DatabaseSeeder - Khuyên dùng để tạo Superadmin)")
                    print("  2. Chỉ seed sản phẩm (OasisProductSeeder)")
                    print("  3. Không seed")
                    seed_choice = input("Chọn chế độ [3]: ").strip()
                    if seed_choice == '1':
                        seed_type = 'full'
                    elif seed_choice == '2':
                        seed_type = 'products'
                
                if migrate:
                    print("[*] Generating Artisan trigger script...")
                    trigger_code = generate_artisan_trigger_php(migrate, seed_type)
                    trigger_bio = io.BytesIO(trigger_code.encode('utf-8'))
                    
                    # Ensure target public dir and upload
                    public_dir = os.path.join(remote_dir, "public").replace('\\', '/').strip('/')
                    ftp_ensure_dir(ftp, public_dir)
                    ftp.storbinary("STOR artisan_trigger.php", trigger_bio)
                    print("[+] Artisan trigger script uploaded.")
                    ftp.quit()
                    
                    # Trigger execution
                    trigger_http_url(domain, remote_dir, "artisan_trigger.php")
                    success = True
                else:
                    ftp.quit()
            else:
                ftp.quit()
            sys.exit(0)
            
        print(f"[+] Tìm thấy {len(changed_files)} file thay đổi:")
        for idx, fpath in enumerate(changed_files[:20]):
            print(f"  - {fpath}")
        if len(changed_files) > 20:
            print(f"  ... và {len(changed_files) - 20} file khác.")
            
        confirm = input(f"Xác nhận upload {len(changed_files)} file này lên FTP? (y/n) [y]: ").strip().lower() != 'n'
        if not confirm:
            print("[-] Đã hủy deploy.")
            ftp.quit()
            sys.exit(0)
            
        # Upload changed files
        print("[*] Bắt đầu đẩy các file thay đổi...")
        total = len(changed_files)
        for idx, fpath in enumerate(changed_files, 1):
            print(f"[{idx}/{total}] Đang đẩy: {fpath}")
            try:
                ftp_upload_file_path(ftp, remote_dir, fpath)
            except Exception as e:
                print(f"[-] Lỗi khi đẩy file {fpath}: {e}")
                
        # Always run the trigger, not just for migrations: it also refreshes the
        # storage link and drops the cached config/routes. Skipping it leaves an
        # uploaded config change inert, which is how media ends up 403-ing.
        print("[*] Generating Artisan trigger script...")
        trigger_code = generate_artisan_trigger_php(migrate, seed_type)
        trigger_bio = io.BytesIO(trigger_code.encode('utf-8'))

        # Ensure target public dir and upload
        public_dir = os.path.join(remote_dir, "public").replace('\\', '/').strip('/')
        ftp_ensure_dir(ftp, public_dir)
        ftp.storbinary("STOR artisan_trigger.php", trigger_bio)
        print("[+] Artisan trigger script uploaded.")
        ftp.quit()

        # Trigger execution
        trigger_http_url(domain, remote_dir, "artisan_trigger.php")

        success = True
        
    if success:
        # Save timestamp of successful deployment
        with open(timestamp_file, "w") as f:
            f.write(str(time.time()))
        print("[+++] DEPLOYMENT COMPLETED SUCCESSFULLY!")
        print(f"You can now visit your site at: {domain}")
    else:
        print("[-] Deployment had warnings or errors. Check remote logs or run manually.")

if __name__ == "__main__":
    main()

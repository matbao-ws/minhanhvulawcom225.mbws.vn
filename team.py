#!/usr/bin/env python3
"""
The one script for the team building customer websites (docs/TEAM_WORKFLOW.md). Needs only git
and Python; it finds or installs php and composer itself.

    python team.py                       # menu
    python team.py new [name] [options]  # create a customer project (python team.py new -h)
    python team.py doctor                # check the machine's tools

In the core repository: set up the machine, create a customer project. In a customer project:
start a task, save and push it with a PR, take a new core release, deploy (through deploy.py).

Which files are core comes from app/Support/CoreManifest.php — the same list the updater uses —
so a commit that touches core files is stopped before it reaches a website.
"""
import argparse
import glob
import hashlib
import os
import platform
import re
import shutil
import socket
import subprocess
import sys
import urllib.request

HERE = os.getcwd()
WINDOWS = os.name == 'nt'

# Customer projects live in the organization next to the core repository.
DEFAULT_OWNER = 'M-t-Bao-WS'
NAME_PATTERN = r'[a-z0-9][a-z0-9-]{1,48}'


# ================================================================ helpers

class Stop(Exception):
    pass


def say(message=''):
    print(message, flush=True)


def ask(label, default='', required=False):
    hint = f' [{default}]' if default else ''
    while True:
        try:
            answer = input(f'  {label}{hint}: ').strip() or default
        except EOFError:
            answer = default
            required = False
        if answer or not required:
            return answer
        say('  (bắt buộc)')


def yes(label, default=True):
    answer = ask(label + (' [C/k]' if default else ' [c/K]')).lower()
    return default if answer == '' else answer in ('c', 'co', 'có', 'y', 'yes')


def run(cmd, check=True, cwd=None):
    say('  $ ' + ' '.join(cmd))
    code = subprocess.run(cmd, cwd=cwd or HERE).returncode
    if check and code != 0:
        raise Stop(f'Lệnh thất bại (mã {code}).')
    return code


def out(cmd, cwd=None):
    try:
        r = subprocess.run(cmd, cwd=cwd or HERE, capture_output=True, text=True, encoding='utf-8',
                           errors='replace', timeout=60)
    except (OSError, subprocess.TimeoutExpired):
        return ''
    return r.stdout.strip() if r.returncode == 0 else ''


def has(tool):
    return shutil.which(tool) is not None


def git(*args, check=True, cwd=None):
    return run(['git', *args], check=check, cwd=cwd)


def version_tuple(text):
    m = re.match(r'v?(\d+)\.(\d+)\.(\d+)', text or '')
    return tuple(int(x) for x in m.groups()) if m else None


def read_version(folder=None):
    path = os.path.join(folder or HERE, 'VERSION')
    return open(path, encoding='utf-8').read().strip() if os.path.exists(path) else ''


# ================================================================ machine: php, composer, gh

TOOLS_DIR = os.path.join(os.path.expanduser('~'), '.matbaows')
SHIM_DIR = os.path.join(TOOLS_DIR, 'bin')
COMPOSER_PHAR = os.path.join(TOOLS_DIR, 'composer.phar')
COMPOSER_URL = 'https://getcomposer.org/download/latest-stable/composer.phar'

PHP_MIN = (8, 2)
# composer.lock (ext-*), plus what Laravel, composer and the database need at run time.
REQUIRED_EXT = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'iconv', 'json', 'libxml', 'mbstring',
                'openssl', 'pcre', 'session', 'tokenizer', 'xml', 'zip', 'pdo_mysql', 'pdo_sqlite']
# Features use them (images, money, dates); the app starts without them.
RECOMMENDED_EXT = ['gd', 'intl', 'bcmath', 'exif']


def php_version(php):
    text = out([php, '-r', 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;'])
    return version_tuple(text)


def php_candidates():
    found = [shutil.which('php')] if has('php') else []
    if WINDOWS:
        for drive in [f'{d}:\\' for d in 'CDEFGHIJ' if os.path.exists(f'{d}:\\')]:
            # Laragon keeps every PHP it ever downloaded (not necessarily in PATH): newest first.
            found += sorted(glob.glob(os.path.join(drive, 'laragon', 'bin', 'php', '*', 'php.exe')), reverse=True)
            found.append(os.path.join(drive, 'xampp', 'php', 'php.exe'))
    else:
        found += ['/opt/homebrew/bin/php', '/usr/local/bin/php', '/usr/bin/php', '/opt/lampp/bin/php']
        found += sorted(glob.glob('/usr/bin/php8.*'), reverse=True)
    seen, unique = set(), []
    for path in found:
        key = os.path.normcase(os.path.abspath(path))
        if os.path.isfile(path) and key not in seen:
            seen.add(key)
            unique.append(path)
    return unique


def find_php():
    """(path, version) of the first php 8.2+; else the newest php found, to report it; else (None, None)."""
    best = (None, None)
    for php in php_candidates():
        version = php_version(php)
        if not version:
            continue
        if version[:2] >= PHP_MIN:
            return php, version
        if best[1] is None or version > best[1]:
            best = (php, version)
    return best


def write_composer_shim(php):
    os.makedirs(SHIM_DIR, exist_ok=True)
    if WINDOWS:
        with open(os.path.join(SHIM_DIR, 'composer.bat'), 'w', encoding='ascii', newline='\r\n') as f:
            f.write(f'@"{php}" "{COMPOSER_PHAR}" %*\n')
    else:
        path = os.path.join(SHIM_DIR, 'composer')
        with open(path, 'w', encoding='utf-8', newline='\n') as f:
            f.write(f'#!/bin/sh\nexec "{php}" "{COMPOSER_PHAR}" "$@"\n')
        os.chmod(path, 0o755)


def download_composer():
    """composer.phar from getcomposer.org, checked against its published sha256."""
    os.makedirs(TOOLS_DIR, exist_ok=True)
    say('  Tải composer.phar ...')
    with urllib.request.urlopen(COMPOSER_URL, timeout=60) as r:
        data = r.read()
    with urllib.request.urlopen(COMPOSER_URL + '.sha256', timeout=30) as r:
        expected = r.read().decode().split()[0].strip()
    if hashlib.sha256(data).hexdigest() != expected:
        raise Stop('composer.phar tải về không khớp checksum; thử lại sau.')
    with open(COMPOSER_PHAR, 'wb') as f:
        f.write(data)


def activate():
    """
    Put a working php (8.2+) and composer first in PATH, for this process and everything it starts.
    Installs nothing. Returns {'php', 'php_version', 'composer'}.
    """
    php, version = find_php()
    path = os.environ.get('PATH', '')
    if php and version[:2] >= PHP_MIN:
        path = os.path.dirname(php) + os.pathsep + path
    composer = shutil.which('composer', path=path) is not None
    if not composer and php and os.path.isfile(COMPOSER_PHAR):
        write_composer_shim(php)  # rewritten every time: it follows the php found today
        path, composer = SHIM_DIR + os.pathsep + path, True
    os.environ['PATH'] = path
    return {'php': php, 'php_version': version, 'composer': composer}


def install(cmd):
    say('  $ ' + ' '.join(cmd))
    return subprocess.run(cmd).returncode == 0


def install_php():
    """One command per platform. Windows gets Laragon: PHP with the extensions on, MySQL, composer."""
    if WINDOWS:
        if not has('winget'):
            say('  Không có winget. Tải Laragon tại https://laragon.org/download rồi chạy lại.')
            return False
        return install(['winget', 'install', '-e', '--id', 'LeNgocKhoa.Laragon',
                        '--accept-package-agreements', '--accept-source-agreements'])
    if platform.system() == 'Darwin':
        return has('brew') and install(['brew', 'install', 'php'])
    if has('apt-get'):
        return install(['sudo', 'apt-get', 'update']) and install(
            ['sudo', 'apt-get', 'install', '-y', 'php-cli', 'php-mbstring', 'php-xml', 'php-curl', 'php-zip',
             'php-sqlite3', 'php-mysql', 'php-intl', 'php-gd', 'php-bcmath', 'unzip'])
    if has('dnf'):
        return install(['sudo', 'dnf', 'install', '-y', 'php-cli', 'php-mbstring', 'php-xml', 'php-pdo',
                        'php-mysqlnd', 'php-intl', 'php-gd', 'php-bcmath', 'php-pecl-zip', 'unzip'])
    say('  Không nhận ra trình quản lý gói; cài PHP 8.2+ bằng tay.')
    return False


def install_gh():
    if WINDOWS and has('winget'):
        return install(['winget', 'install', '-e', '--id', 'GitHub.cli',
                        '--accept-package-agreements', '--accept-source-agreements'])
    if platform.system() == 'Darwin' and has('brew'):
        return install(['brew', 'install', 'gh'])
    if has('apt-get'):
        return install(['sudo', 'apt-get', 'install', '-y', 'gh'])
    if has('dnf'):
        return install(['sudo', 'dnf', 'install', '-y', 'gh'])
    say('  Cài GitHub CLI: https://cli.github.com')
    return False


def doctor(offer_install=False):
    """Report every tool; offer_install asks to install what is missing. True when the required ones work."""
    ok = True
    say('Công cụ:')
    if has('git'):
        say('  ✓ git')
    else:
        ok = False
        say('  ✗ git  → cài: https://git-scm.com (Linux: sudo apt-get install -y git), rồi mở terminal mới')

    env = activate()
    php, version = env['php'], env['php_version']
    if not php or version[:2] < PHP_MIN:
        if php:
            say(f'  ✗ php {".".join(map(str, version))} ở {php}: cần {PHP_MIN[0]}.{PHP_MIN[1]} trở lên')
            if 'xampp' in php.lower():
                say('    XAMPP này cũ: cài XAMPP bản PHP 8.2+, hoặc dùng Laragon.')
            elif not WINDOWS:
                say('    Ubuntu 22.04 chỉ có PHP 8.1: sudo add-apt-repository ppa:ondrej/php, rồi cài lại.')
        else:
            say('  ✗ php: không tìm thấy (đã tìm PATH, Laragon, XAMPP)')
        if offer_install and yes('Cài PHP ngay (Windows: Laragon; Linux/macOS: gói php)?') and install_php():
            say('  Cài xong. Mở terminal MỚI rồi chạy lại python team.py.')
        return False

    say(f'  ✓ php {".".join(map(str, version))}  ({php})')
    loaded = {line.strip().lower() for line in out([php, '-m']).splitlines() if line.strip() and not line.startswith('[')}
    missing = [e for e in REQUIRED_EXT if e not in loaded]
    weak = [e for e in RECOMMENDED_EXT if e not in loaded]
    if missing:
        ok = False
        say(f'  ✗ thiếu extension: {", ".join(missing)}')
    if weak:
        say(f'  ! nên bật thêm: {", ".join(weak)}')
    if missing or weak:
        m = re.search(r'Loaded Configuration File:\s*(.+)', out([php, '--ini']))
        ini = m.group(1).strip() if m and not m.group(1).strip().startswith('(none)') else ''
        if ini:
            say(f'    Mở {ini}, bỏ dấu ; trước các dòng extension=<tên> tương ứng, lưu lại.')
        elif WINDOWS:
            say(f'    PHP này chưa có php.ini: chép php.ini-development thành php.ini trong {os.path.dirname(php)},')
            say('    bỏ dấu ; trước extension_dir = "ext" và các dòng extension=<tên>.')
        else:
            pkgs = ' '.join('php-' + e for e in missing + weak if not e.startswith('pdo_'))
            say(f'    Linux: sudo apt-get install -y {pkgs}')

    if env['composer']:
        say('  ✓ composer')
    elif offer_install and yes('Chưa có composer. Tải composer.phar về ~/.matbaows (không cần cài)?'):
        try:
            download_composer()
            activate()
            say('  ✓ composer (composer.phar)')
        except (OSError, Stop) as e:
            ok = False
            say(f'  ✗ composer: {e}')
    else:
        ok = False
        say('  ✗ composer')

    if has('gh'):
        say('  ✓ gh (GitHub CLI)')
    else:
        say('  ! gh (GitHub CLI) chưa có: không tự tạo repo / PR được')
        if offer_install and yes('Cài GitHub CLI?') and install_gh():
            say('  Cài xong. Mở terminal MỚI rồi chạy lại python team.py.')
    return ok


def setup_machine():
    ready = doctor(offer_install=True)

    if has('gh'):
        if subprocess.run(['gh', 'auth', 'status'], capture_output=True).returncode == 0:
            say('  ✓ đã đăng nhập GitHub')
        elif yes('Chưa đăng nhập GitHub. Đăng nhập ngay (mở trình duyệt)?'):
            run(['gh', 'auth', 'login', '--hostname', 'github.com', '--git-protocol', 'https', '--web'], check=False)
            run(['gh', 'auth', 'setup-git'], check=False)

    if has('git'):
        name = out(['git', 'config', '--global', 'user.name'])
        email = out(['git', 'config', '--global', 'user.email'])
        if name and email:
            say(f'  ✓ git: {name} <{email}>')
        else:
            say('Git chưa biết bạn là ai (tên/email hiện trên commit):')
            if not name:
                run(['git', 'config', '--global', 'user.name', ask('Tên của bạn', required=True)])
            if not email:
                run(['git', 'config', '--global', 'user.email', ask('Email tài khoản GitHub', required=True)])
        run(['git', 'config', '--global', 'fetch.prune', 'true'])

    say('[+] Máy đã sẵn sàng.' if ready else
        '[!] Còn thiếu công cụ (✗ ở trên). Cài xong, mở terminal mới rồi chạy lại.')
    return ready


# ================================================================ new customer project

PROJECT_CI = """name: Tests

# Project repository: tests only. Core releases and the staging deploy belong to the core repo.
on:
  pull_request:
  push:
    branches: [main, dev]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, openssl, curl, intl, gd, zip, bcmath, exif, pdo_sqlite, sqlite3
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Prepare environment
        run: cp .env.example .env && php artisan key:generate

      - name: Run tests
        run: php artisan test --compact
"""

# Files of the core repository that make no sense in a customer project.
CORE_ONLY_FILES = ['.github/workflows/release.yml', '.deploy_url', '.deploy_timestamp']


def new_project_parser():
    p = argparse.ArgumentParser(prog='team.py new', description='Tạo dự án khách mới từ core.')
    p.add_argument('name', nargs='?', help='Tên dự án = tên thư mục, vd: web-khachabc. Bỏ trống thì hỏi.')
    p.add_argument('--parent', default=None, help='Thư mục chứa dự án mới (mặc định: cạnh repo core).')
    p.add_argument('--from', dest='source', default=None, help='Repo core để clone (mặc định: origin của repo core).')
    p.add_argument('--ref', default='main', help='Nhánh hoặc tag core để bắt đầu (mặc định: main).')
    p.add_argument('--origin', default=None, help='URL repo GitHub trống có sẵn; bỏ trống thì tự tạo repo bằng gh.')
    p.add_argument('--owner', default=None, help=f'User/organization chứa repo mới (mặc định: {DEFAULT_OWNER}).')
    p.add_argument('--public', action='store_true', help='Tạo repo public (mặc định private).')
    p.add_argument('--no-github', action='store_true', help='Không tạo repo GitHub, chỉ git local.')
    p.add_argument('--db', choices=['mysql', 'sqlite'], default=None,
                   help='Database local (mặc định: mysql nếu đang chạy, không thì sqlite).')
    p.add_argument('--db-host', default='127.0.0.1')
    p.add_argument('--db-user', default='root')
    p.add_argument('--db-pass', default='')
    p.add_argument('--skip-install', action='store_true', help='Chỉ tạo repo và .env; không composer install / core:install.')
    p.add_argument('--yes', action='store_true', help='Không hỏi lại trước khi tạo.')
    return p


def mysql_running(host):
    try:
        with socket.create_connection((host, 3306), timeout=1):
            return True
    except OSError:
        return False


def name_problem(name, parent):
    if not re.fullmatch(NAME_PATTERN, name or ''):
        return 'Tên chỉ gồm chữ thường, số và gạch ngang (2–49 ký tự), vd: web-khachabc'
    if os.path.exists(os.path.join(parent, name)):
        return f'{os.path.join(parent, name)} đã tồn tại.'
    return None


def set_env(text, values):
    """Set KEY=value lines, uncommenting a commented template line when there is one."""
    for key, value in values.items():
        line = f'{key}={value}'
        text, count = re.compile(rf'^#?\s*{re.escape(key)}=.*$', re.MULTILINE).subn(line, text, count=1)
        if count == 0:
            text = text.rstrip('\n') + '\n' + line + '\n'
    return text


def create_github_repo(target, name, owner, public):
    """Create <owner>/<name> with the GitHub CLI and push main. Returns the repo URL or None."""
    if not has('gh'):
        say('[!] Chưa có GitHub CLI nên chưa tạo repo: python team.py → "Thiết lập máy".')
        return None
    if subprocess.run(['gh', 'auth', 'status'], capture_output=True).returncode != 0:
        say('[!] gh chưa đăng nhập nên chưa tạo repo: python team.py → "Thiết lập máy".')
        return None
    code = run(['gh', 'repo', 'create', f'{owner}/{name}', '--public' if public else '--private',
                '--source', target, '--remote', 'origin', '--push'], cwd=target, check=False)
    if code != 0:
        say('[!] Tạo repo GitHub không được (tên đã có, hoặc bạn không có quyền tạo repo ở owner).')
        return None
    return out(['git', 'remote', 'get-url', 'origin'], cwd=target)


def new_project(argv=None):
    args = new_project_parser().parse_args(argv or [])
    parent = os.path.abspath(args.parent or os.path.dirname(HERE))
    if args.db is None:
        args.db = 'mysql' if mysql_running(args.db_host) else 'sqlite'
    if not args.skip_install and not activate()['composer']:
        raise Stop('Chưa có php/composer: chọn "Thiết lập máy" trước.')

    if not args.name:
        say('Tạo dự án khách mới từ core (Enter = giá trị trong [ ]).')
        while True:
            args.name = ask('Tên dự án (vd: web-khachabc)', required=True).lower()
            problem = name_problem(args.name, parent)
            if not problem:
                break
            say('  ' + problem)
        if not args.origin and not args.no_github:
            answer = ask('Tạo repo GitHub private ở (owner, "-" = không tạo)', args.owner or DEFAULT_OWNER)
            args.no_github, args.owner = answer == '-', (None if answer == '-' else answer)
        while True:
            args.db = ask('Database local (mysql/sqlite)', args.db).lower()
            if args.db in ('mysql', 'sqlite'):
                break
    name = args.name.strip().lower()
    problem = name_problem(name, parent)
    if problem:
        raise Stop(problem)
    owner = args.owner or DEFAULT_OWNER
    target = os.path.join(parent, name)
    db_name = name.replace('-', '_')

    source = args.source or out(['git', 'remote', 'get-url', 'origin'])
    if not source:
        raise Stop('Không xác định được repo core; dùng: python team.py new --from <url>.')

    say('')
    say(f'  Thư mục:  {target}')
    say(f'  Core:     {args.ref}')
    say(f'  GitHub:   {"không" if args.no_github else (args.origin or f"{owner}/{name} (private)")}')
    say(f'  Database: {args.db}' + (f' ({db_name})' if args.db == 'mysql' else ''))
    if not args.yes and not yes('Tạo luôn?'):
        raise Stop('Đã huỷ.')
    say('')

    say(f'[1/5] Clone core ({args.ref})')
    git('clone', '--branch', args.ref, source, target)
    if out(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], cwd=target) != 'main':
        git('switch', '-q', '-C', 'main', cwd=target)  # a tag or another branch: start "main" there
    # "core" = where later core releases come from: git fetch core --tags; git merge vX.Y.Z
    git('remote', 'rename', 'origin', 'core', cwd=target)
    core_version = read_version(target) or args.ref

    say('[2/5] Bỏ phần chỉ dành cho repo core')
    tracked = set(out(['git', 'ls-files'], cwd=target).splitlines())
    for path in CORE_ONLY_FILES + [f for f in tracked if f.startswith('__pycache__/')]:
        if path in tracked:
            git('rm', '-q', path, cwd=target)
    with open(os.path.join(target, '.github', 'workflows', 'ci.yml'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(PROJECT_CI)

    say('[3/5] Tạo .env local')
    with open(os.path.join(target, '.env.example'), encoding='utf-8') as f:
        env = f.read()
    values = {
        'APP_NAME': f'"{name}"',
        'APP_URL': f'http://{name}.test',
        # A checkout is never updated by core:update — that would overwrite the working tree.
        'CORE_UPDATE_ENABLED': 'false',
        'QUEUE_CONNECTION': 'sync',
    }
    if args.db == 'mysql':
        values.update({'DB_CONNECTION': 'mysql', 'DB_HOST': args.db_host, 'DB_PORT': '3306',
                       'DB_DATABASE': db_name, 'DB_USERNAME': args.db_user, 'DB_PASSWORD': args.db_pass})
    else:
        values['DB_CONNECTION'] = 'sqlite'
    with open(os.path.join(target, '.env'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(set_env(env, values))

    if args.skip_install:
        say('[4/5] Bỏ qua cài đặt (--skip-install)')
    else:
        say('[4/5] Cài đặt')
        if args.db == 'mysql':
            create = ("$pdo = new PDO('mysql:host=' . $argv[1], $argv[2], $argv[3]);"
                      "$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $argv[4] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
                      "echo 'Database ' . $argv[4] . ' OK' . PHP_EOL;")
            run(['php', '-r', create, args.db_host, args.db_user, args.db_pass, db_name], cwd=target)
        else:
            open(os.path.join(target, 'database', 'database.sqlite'), 'a').close()
        run([shutil.which('composer'), 'install', '--no-interaction', '--no-progress'], cwd=target)
        run(['php', 'artisan', 'key:generate'], cwd=target)
        run(['php', 'artisan', 'core:install'], cwd=target)  # asks for the admin account
        run(['php', 'artisan', 'storage:link'], cwd=target, check=False)

    say('[5/5] Commit điểm bắt đầu')
    git('add', '-A', cwd=target)
    git('commit', '-q', '-m', f'chore: start project {name} from core {core_version}', cwd=target)
    repo_url = None
    if args.origin:
        git('remote', 'add', 'origin', args.origin, cwd=target)
        git('push', '-u', 'origin', 'main', cwd=target)
        repo_url = args.origin
    elif not args.no_github:
        repo_url = create_github_repo(target, name, owner, args.public)

    say('')
    say(f'[+] Xong: {target} (core {core_version})')
    say(f'    Web local:  http://{name}.test  (Laragon: Menu → Reload để nhận thư mục mới)')
    say(f'    Admin:      http://{name}.test/vi/admin')
    say('    Theme:      resources/views/client, public/client-assets (trừ vendor/), routes/theme.php')
    say(f'    GitHub:     {repo_url}' if repo_url else
        f'    GitHub:     chưa có. Tạo repo trống rồi: git -C "{target}" remote add origin <url>; git -C "{target}" push -u origin main')
    say(f'    Làm việc:   cd "{target}"; python team.py')


# ================================================================ customer project: git

def pull_main():
    """Bring main up to date with GitHub; a project not on GitHub yet has nothing to pull."""
    git('switch', 'main')
    if out(['git', 'rev-parse', '--abbrev-ref', 'main@{upstream}']):
        git('pull', '--ff-only')
    else:
        say('  [!] main chưa nối với repo GitHub nào (chưa có origin): bỏ qua git pull.')


def branch():
    return out(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])


def dirty_files():
    """Changed, added and deleted paths (untracked included)."""
    # Not out(): stripping the output would eat the status column of the first line.
    lines = subprocess.run(['git', 'status', '--porcelain', '--untracked-files=all'], cwd=HERE,
                           capture_output=True, text=True, encoding='utf-8').stdout.splitlines()
    return [line[3:].split(' -> ')[-1].strip('"') for line in lines if line.strip()]


def core_patterns():
    path = os.path.join(HERE, 'app', 'Support', 'CoreManifest.php')
    if not os.path.exists(path):
        return []
    block = re.search(r'const CORE = \[(.*?)\];', open(path, encoding='utf-8').read(), re.S)
    return re.findall(r"'([^']+)'", block.group(1)) if block else []


def is_core(path, patterns):
    path = path.replace('\\', '/')
    return any(path.startswith(p[:-2]) if p.endswith('/**') else path == p for p in patterns)


def start_task():
    if dirty_files():
        raise Stop('Đang có thay đổi chưa lưu. Chọn "Lưu & đẩy" trước, hoặc bỏ chúng đi.')
    slug = re.sub(r'[^a-z0-9]+', '-', ask('Tên việc (vd: trang-chu, sua-menu-mobile)').lower()).strip('-')
    if not slug:
        raise Stop('Tên việc trống.')
    prefix = 'fix' if yes('Đây là sửa lỗi?', default=False) else 'feat'
    pull_main()
    git('switch', '-c', f'{prefix}/{slug}')
    say(f'[+] Đang ở nhánh {prefix}/{slug}. Code xong chọn "Lưu & đẩy".')


def save_and_push():
    current = branch()
    if current in ('main', 'HEAD', ''):
        raise Stop('Đang ở main. Chọn "Bắt đầu việc mới" để tạo nhánh trước khi lưu.')
    files = dirty_files()
    if not files:
        say('Không có thay đổi mới.')
    else:
        patterns = core_patterns()
        core = [f for f in files if is_core(f, patterns)]
        say(f'{len(files)} file thay đổi.')
        if core:
            say('[!] Các file sau là file CORE (bản cập nhật core sẽ đè, hoặc bị chặn vì chúng):')
            for f in core[:20]:
                say('      ' + f)
            if len(core) > 20:
                say(f'      ... và {len(core) - 20} file nữa')
            say('    Sửa core không làm trong dự án khách: báo trưởng nhóm hoặc mở Issue trên repo core.')
            if not yes('Vẫn lưu các file này? (chỉ khi trưởng nhóm đồng ý)', default=False):
                raise Stop('Đã dừng. Bỏ thay đổi một file core: git restore <file>')
        message = ask('Mô tả ngắn việc đã làm (vd: trang chủ theo thiết kế)')
        if not message:
            raise Stop('Cần mô tả.')
        if not re.match(r'^(feat|fix|chore|docs|style|refactor)(\(.+\))?:', message):
            message = ('fix: ' if current.startswith('fix/') else 'feat: ') + message
        git('add', '-A')
        git('commit', '-m', message)

    git('push', '-u', 'origin', current)
    if has('gh') and yes('Mở Pull Request vào main?'):
        existing = out(['gh', 'pr', 'view', current, '--json', 'url', '-q', '.url'])
        if existing:
            say(f'[+] PR đã có: {existing}')
        else:
            run(['gh', 'pr', 'create', '--base', 'main', '--head', current, '--fill'], check=False)
    say('[+] Đã đẩy. Chờ CI ✅ trên PR rồi merge; merge xong chọn "Dọn nhánh đã merge".')


def clean_merged():
    if dirty_files():
        raise Stop('Đang có thay đổi chưa lưu.')
    pull_main()
    for line in out(['git', 'branch', '--merged', 'main']).splitlines():
        name = line.lstrip('* ').strip()
        if name and name != 'main':
            git('branch', '-d', name, check=False)
    say('[+] Đã về main mới nhất.')


def update_core(target=None):
    if dirty_files():
        raise Stop('Đang có thay đổi chưa lưu. Lưu & đẩy trước.')
    mine = read_version()
    git('fetch', 'core', '--tags', '--quiet')
    tags = sorted((t for t in out(['git', 'tag', '-l', 'v*']).split() if version_tuple(t)), key=version_tuple)
    newer = [t for t in tags if version_tuple(t) > (version_tuple(mine) or (0, 0, 0))]
    say(f'Core trong dự án: {mine or "?"}')
    if not newer:
        say('[+] Đã là bản core mới nhất.')
        return
    say('Bản mới hơn: ' + ', '.join(newer))
    tag = target or ask('Lấy bản nào', newer[-1])
    if tag not in tags:
        raise Stop(f'Không có tag {tag}.')

    pull_main()
    work = f'chore/core-{tag.lstrip("v")}'
    git('switch', '-c', work)
    if git('merge', '--no-edit', tag, check=False) != 0:
        conflicted = out(['git', 'diff', '--name-only', '--diff-filter=U']).splitlines()
        patterns = core_patterns()
        say('[!] Conflict ở:')
        for f in conflicted:
            say('      ' + f + ('   (file core)' if is_core(f, patterns) else '   (file theme)'))
        core = [f for f in conflicted if is_core(f, patterns)]
        if core and len(core) == len(conflicted) and yes('Tất cả là file core: lấy bản của core cho chúng?'):
            git('checkout', '--theirs', '--', *core)
            git('add', '--', *core)
            git('commit', '--no-edit')
        else:
            raise Stop('Xử lý conflict trong VS Code, rồi: git add -A; git commit --no-edit. '
                       'Hủy: git merge --abort. Không chắc thì hỏi trưởng nhóm.')
    if has('composer'):
        run(['composer', 'install', '--no-interaction'], check=False)
    run(['php', 'artisan', 'migrate', '--force'], check=False)
    run(['php', 'artisan', 'optimize:clear'], check=False)
    say(f'[+] Đã lấy core {tag} trên nhánh {work}. Mở web local kiểm tra giao diện,')
    say('    rồi chọn "Lưu & đẩy" để mở PR vào main.')


def deploy():
    if not os.path.exists(os.path.join(HERE, 'deploy.py')):
        raise Stop('Không thấy deploy.py trong thư mục này.')
    if dirty_files():
        raise Stop('Đang có thay đổi chưa lưu. Lưu & đẩy, merge vào main rồi mới deploy.')
    pull_main()

    mine = read_version()
    say(f'Core trong dự án: {mine}')
    say('Xem phiên bản đang chạy trên website: admin website → menu "Cập nhật phiên bản".')
    live = ask('Phiên bản trên website (Enter nếu là website mới)')
    if live and version_tuple(live) is None:
        raise Stop(f'"{live}" không phải số phiên bản (vd 1.2.0).')
    if live and version_tuple(live) > (version_tuple(mine) or (0, 0, 0)):
        say(f'[!] Website đang chạy core {live}, dự án mới có {mine}. Deploy bây giờ sẽ đè core cũ lên website.')
        if yes(f'Lấy core v{live.lstrip("v")} về dự án trước?'):
            update_core('v' + live.lstrip('v'))
            say('Lấy core xong: Lưu & đẩy, merge PR vào main, rồi chọn Deploy lại.')
        return
    run([sys.executable, 'deploy.py'], check=False)


def status():
    say(f'Thư mục:  {HERE}')
    say(f'Nhánh:    {branch()}')
    say(f'Core:     {read_version() or "?"}')
    files = dirty_files()
    core = sum(is_core(f, core_patterns()) for f in files)
    say(f'Thay đổi chưa lưu: {len(files)} file' + (f' (trong đó {core} file core)' if files else ''))
    origin = out(['git', 'remote', 'get-url', 'origin'])
    if origin:
        say(f'GitHub:   {origin}')


# ================================================================ menu

def kind():
    """'project' (a customer project), 'core' (the core repository) or None."""
    if not out(['git', 'rev-parse', '--is-inside-work-tree']):
        return None
    if 'core' in out(['git', 'remote']).split():
        return 'project'
    if os.path.exists(os.path.join(HERE, 'app', 'Support', 'CoreManifest.php')):
        return 'core'
    return None


MENUS = {
    'core': [
        ('Tạo dự án khách mới', new_project),
        ('Thiết lập máy (công cụ, đăng nhập GitHub)', setup_machine),
    ],
    'project': [
        ('Bắt đầu việc mới (tạo nhánh từ main)', start_task),
        ('Lưu & đẩy (commit, push, mở PR)', save_and_push),
        ('Dọn nhánh đã merge, về main mới nhất', clean_merged),
        ('Lấy bản core mới', update_core),
        ('Deploy lên hosting khách', deploy),
        ('Xem trạng thái', status),
        ('Thiết lập máy', setup_machine),
    ],
}


def menu():
    where = kind()
    if where is None:
        raise Stop('Chạy trong thư mục repo core (matbaows-core-admin) hoặc thư mục một dự án khách.')

    env = activate()
    if not env['php'] or not env['composer'] or not has('git'):
        say('Máy này chưa đủ công cụ: thiết lập trước.')
        say('')
        if not setup_machine():
            return

    items = MENUS[where]
    title = 'REPO CORE' if where == 'core' else f'DỰ ÁN {os.path.basename(HERE)}'
    while True:
        say('')
        say(f'=== {title} — nhánh {branch()} ===')
        for i, (label, _) in enumerate(items, 1):
            say(f'  {i}. {label}')
        say('  0. Thoát')
        choice = ask('Chọn')
        if choice in ('0', 'q', ''):
            return
        if choice.isdigit() and 1 <= int(choice) <= len(items):
            say('')
            try:
                items[int(choice) - 1][1]()
            except Stop as e:
                say(f'[-] {e}')
            except KeyboardInterrupt:
                say('\n[-] Đã huỷ.')


def main():
    for stream in (sys.stdout, sys.stderr):
        stream.reconfigure(encoding='utf-8')  # Vietnamese on a cp1252 Windows console
    command, rest = (sys.argv[1], sys.argv[2:]) if len(sys.argv) > 1 else ('', [])
    try:
        if command == 'new':
            activate()
            new_project(rest)
        elif command == 'doctor':
            sys.exit(0 if doctor(offer_install='--install' in rest) else 1)
        elif command in ('', 'menu'):
            menu()
        else:
            say(__doc__)
    except Stop as e:
        say(f'[-] {e}')
        sys.exit(1)
    except KeyboardInterrupt:
        say('\n[-] Đã huỷ.')
        sys.exit(130)


if __name__ == '__main__':
    main()

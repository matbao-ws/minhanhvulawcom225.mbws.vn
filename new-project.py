#!/usr/bin/env python3
"""
Start a customer project from the core: one command instead of a page of steps.

    python new-project.py                                 # asks for everything
    python new-project.py khach-abc
    python new-project.py khach-abc --owner DucToanDev    # repo under another account
    python new-project.py khach-abc --origin https://github.com/m-t-bao-ws/khach-abc.git
    python new-project.py khach-abc --no-github           # local only
    python new-project.py khach-abc --ref v1.1.0 --db sqlite

What it does, in a new folder next to this repository:
  1. clones the core (remote renamed "core", so later core releases merge in with
     `git fetch core --tags && git merge vX.Y.Z`);
  2. drops what only the core repository needs (release workflow, staging deploy, the
     old single-site deploy markers) and keeps a test-only CI;
  3. writes a local .env (APP_URL http://<name>.test for Laragon, its own database, core
     updates off — the code is a checkout, not an installed site);
  4. creates the database, installs dependencies, generates APP_KEY, runs core:install
     (asks for the admin account) and storage:link;
  5. commits the starting point and pushes it to the project's own repo: with --origin to that
     URL, otherwise it creates a private GitHub repo <owner>/<name> with the GitHub CLI (gh),
     <owner> being the organization by default.

The theme then goes into resources/views/client, public/client-assets and routes/theme.php —
never into core files (AI_BUILD_PROMPT.md, "Cập nhật phiên bản core: core và theme").
"""
import argparse
import os
import re
import shutil
import socket
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import devenv  # noqa: E402  (php and composer from Laragon, XAMPP, ~/.matbaows even outside PATH)

# Customer projects live in the organization next to the core repository.
DEFAULT_OWNER = 'M-t-Bao-WS'

NAME_PATTERN = r'[a-z0-9][a-z0-9-]{1,48}'

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
CORE_ONLY_FILES = [
    '.github/workflows/release.yml',
    '.deploy_url',
    '.deploy_timestamp',
]


def say(message):
    print(message, flush=True)


def run(cmd, cwd=None, check=True):
    say('    $ ' + ' '.join(cmd))
    result = subprocess.run(cmd, cwd=cwd)
    if check and result.returncode != 0:
        say(f'[-] Lệnh thất bại (mã {result.returncode}). Dừng lại; thư mục dự án giữ nguyên để xem.')
        sys.exit(result.returncode)
    return result.returncode


def output(cmd, cwd=None):
    return subprocess.run(cmd, cwd=cwd, capture_output=True, text=True).stdout.strip()


def tool(name):
    path = shutil.which(name)
    if not path:
        say(f'[-] Không tìm thấy "{name}". Chạy: python team.py → "Thiết lập máy" (tự tìm hoặc cài).')
        sys.exit(1)
    return path


def mysql_running(host='127.0.0.1', port=3306):
    try:
        with socket.create_connection((host, port), timeout=1):
            return True
    except OSError:
        return False


def ask(label, default=''):
    hint = f' [{default}]' if default else ''
    while True:
        answer = input(f'    {label}{hint}: ').strip() or default
        if answer:
            return answer
        say('    (bắt buộc)')


def name_problem(name, parent):
    if not re.fullmatch(NAME_PATTERN, name):
        return 'Tên chỉ gồm chữ thường, số và gạch ngang (2–49 ký tự), vd: web-khachabc'
    if os.path.exists(os.path.join(parent, name)):
        return f'{os.path.join(parent, name)} đã tồn tại.'
    return None


def ask_missing(args):
    """No name on the command line: ask for what the flags would have said."""
    parent = os.path.abspath(args.parent)
    say('Tạo dự án khách mới từ core (Enter = giá trị trong [ ]).')
    while True:
        args.name = ask('Tên dự án (vd: web-khachabc)').lower()
        problem = name_problem(args.name, parent)
        if not problem:
            break
        say('    ' + problem)

    if not args.origin and not args.no_github:
        answer = ask('Tạo repo GitHub private ở (owner, "-" = không tạo)', args.owner or DEFAULT_OWNER)
        if answer == '-':
            args.no_github = True
        else:
            args.owner = answer

    args.db = ask('Database local (mysql/sqlite)', args.db).lower()
    while args.db not in ('mysql', 'sqlite'):
        args.db = ask('Database local (mysql/sqlite)', 'mysql').lower()

    repo = 'không' if args.no_github else (args.origin or f'{args.owner}/{args.name} (private)')
    say('')
    say(f'    Thư mục:  {os.path.join(parent, args.name)}')
    say(f'    Core:     {args.ref}')
    say(f'    GitHub:   {repo}')
    say(f'    Database: {args.db}' + (f' ({args.name.replace("-", "_")})' if args.db == 'mysql' else ''))
    if input('    Tạo luôn? [C/k]: ').strip().lower() not in ('', 'c', 'co', 'y', 'yes'):
        say('[-] Đã huỷ.')
        sys.exit(0)
    say('')


def set_env(text, values):
    """Set KEY=value lines, uncommenting a commented template line when there is one."""
    for key, value in values.items():
        line = f'{key}={value}'
        pattern = re.compile(rf'^#?\s*{re.escape(key)}=.*$', re.MULTILINE)
        text, count = pattern.subn(line, text, count=1)
        if count == 0:
            text = text.rstrip('\n') + '\n' + line + '\n'
    return text


def create_github_repo(target, name, owner, public):
    """Create <owner>/<name> on GitHub with the GitHub CLI and push main. Returns the repo URL or None."""
    gh = shutil.which('gh')
    if not gh:
        say('[!] Chưa có GitHub CLI nên chưa tạo repo. Cài một lần: winget install GitHub.cli, rồi gh auth login')
        return None
    if subprocess.run([gh, 'auth', 'status'], capture_output=True).returncode != 0:
        say('[!] gh chưa đăng nhập nên chưa tạo repo. Chạy một lần: gh auth login')
        return None
    repo = f'{owner}/{name}' if owner else name
    code = run([gh, 'repo', 'create', repo, '--public' if public else '--private',
                '--source', target, '--remote', 'origin', '--push'], cwd=target, check=False)
    if code != 0:
        say('[!] Tạo repo GitHub không được (tên đã tồn tại hoặc không có quyền với owner).')
        return None
    return output(['git', 'remote', 'get-url', 'origin'], cwd=target)


def parse_args():
    p = argparse.ArgumentParser(description='Tạo dự án khách mới từ core.')
    p.add_argument('name', nargs='?', help='Tên dự án = tên thư mục (chữ thường, số, gạch ngang), vd: web-khachabc. Bỏ trống thì script hỏi.')
    p.add_argument('--parent', default=os.path.dirname(HERE),
                   help='Thư mục chứa dự án mới (mặc định: cạnh repo core).')
    p.add_argument('--from', dest='source', default=None,
                   help='Repo core để clone (mặc định: origin của repo core này).')
    p.add_argument('--ref', default='main', help='Nhánh hoặc tag core để bắt đầu (mặc định: main).')
    p.add_argument('--origin', default=None,
                   help='URL repo GitHub (trống) có sẵn của dự án; bỏ trống thì tự tạo repo bằng gh.')
    p.add_argument('--owner', default=None,
                   help=f'User/organization GitHub chứa repo mới (mặc định: {DEFAULT_OWNER}).')
    p.add_argument('--public', action='store_true', help='Tạo repo public (mặc định private).')
    p.add_argument('--no-github', action='store_true', help='Không tạo repo GitHub, chỉ git local.')
    p.add_argument('--db', choices=['mysql', 'sqlite'], default=None,
                   help='Database local (mặc định: mysql nếu đang chạy, không thì sqlite).')
    p.add_argument('--db-host', default='127.0.0.1')
    p.add_argument('--db-user', default='root')
    p.add_argument('--db-pass', default='')
    p.add_argument('--skip-install', action='store_true',
                   help='Chỉ tạo repo và .env; không composer install / core:install.')
    return p.parse_args()


def main():
    for stream in (sys.stdout, sys.stderr):
        stream.reconfigure(encoding='utf-8')  # Vietnamese messages on a cp1252 Windows console
    devenv.activate()
    args = parse_args()
    if args.db is None:
        args.db = 'mysql' if mysql_running(args.db_host) else 'sqlite'
    if not args.name:
        ask_missing(args)
    name = args.name.strip().lower()
    problem = name_problem(name, os.path.abspath(args.parent))
    if problem:
        say('[-] ' + problem)
        sys.exit(1)
    target = os.path.join(os.path.abspath(args.parent), name)

    git = tool('git')
    source = args.source or output([git, 'remote', 'get-url', 'origin'], cwd=HERE)
    if not source:
        say('[-] Không xác định được repo core; dùng --from <url>.')
        sys.exit(1)

    say(f'[1/5] Clone core ({args.ref}) → {target}')
    run([git, 'clone', '--branch', args.ref, source, target])
    if output([git, 'rev-parse', '--abbrev-ref', 'HEAD'], cwd=target) != 'main':
        run([git, 'switch', '-q', '-C', 'main'], cwd=target)  # a tag or another branch: start "main" there
    run([git, 'remote', 'rename', 'origin', 'core'], cwd=target)
    core_version = open(os.path.join(target, 'VERSION'), encoding='utf-8').read().strip() \
        if os.path.exists(os.path.join(target, 'VERSION')) else args.ref

    say('[2/5] Bỏ phần chỉ dành cho repo core')
    tracked = set(output([git, 'ls-files'], cwd=target).splitlines())
    for path in CORE_ONLY_FILES + [f for f in tracked if f.startswith('__pycache__/')]:
        if path in tracked:
            run([git, 'rm', '-q', path], cwd=target)
    with open(os.path.join(target, '.github', 'workflows', 'ci.yml'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(PROJECT_CI)

    say('[3/5] Tạo .env local')
    with open(os.path.join(target, '.env.example'), encoding='utf-8') as f:
        env = f.read()
    db_name = name.replace('-', '_')
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
        php = tool('php')
        say('[4/5] Cài đặt')
        if args.db == 'mysql':
            create = (
                "$pdo = new PDO('mysql:host=' . $argv[1], $argv[2], $argv[3]);"
                "$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $argv[4] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
                "echo 'Database ' . $argv[4] . ' OK' . PHP_EOL;"
            )
            run([php, '-r', create, args.db_host, args.db_user, args.db_pass, db_name])
        else:
            open(os.path.join(target, 'database', 'database.sqlite'), 'a').close()
        run([tool('composer'), 'install', '--no-interaction', '--no-progress'], cwd=target)
        run([php, 'artisan', 'key:generate'], cwd=target)
        run([php, 'artisan', 'core:install'], cwd=target)  # asks for the admin account
        run([php, 'artisan', 'storage:link'], cwd=target, check=False)

    say('[5/5] Commit điểm bắt đầu')
    run([git, 'add', '-A'], cwd=target)
    run([git, 'commit', '-q', '-m', f'chore: start project {name} from core {core_version}'], cwd=target)
    repo_url = None
    if args.origin:
        run([git, 'remote', 'add', 'origin', args.origin], cwd=target)
        run([git, 'push', '-u', 'origin', 'main'], cwd=target)
        repo_url = args.origin
    elif not args.no_github:
        say('[+] Tạo repo GitHub ' + ('public' if args.public else 'private'))
        repo_url = create_github_repo(target, name, args.owner or DEFAULT_OWNER, args.public)

    say('')
    say(f'[+] Xong: {target} (core {core_version})')
    say(f'    Web local:  http://{name}.test  (Laragon: Menu → Reload để nhận thư mục mới)')
    say(f'    Admin:      http://{name}.test/vi/admin')
    say('    Theme:      resources/views/client, public/client-assets (trừ vendor/), routes/theme.php — không sửa file core.')
    say(f'    Làm việc:   cd "{target}"; python team.py')
    if repo_url:
        say(f'    GitHub:     {repo_url}')
    else:
        say('    Repo riêng: tạo repo trống trên GitHub rồi:')
        say(f'                git -C "{target}" remote add origin <url> && git -C "{target}" push -u origin main')
    say('    Deploy:     python deploy.py   (chạy trong thư mục dự án)')
    say('    Lấy core mới: git fetch core --tags && git merge vX.Y.Z')


if __name__ == '__main__':
    main()

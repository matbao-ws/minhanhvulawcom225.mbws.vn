#!/usr/bin/env python3
"""
Start a customer project from the core: one command instead of a page of steps.

    python new-project.py khach-abc
    python new-project.py khach-abc --origin https://github.com/DucToanDev/khach-abc.git
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
  5. commits the starting point and, with --origin, pushes it to the project's own repo.

The theme then goes into resources/views/client, public/client-assets and routes/theme.php —
never into core files (AI_BUILD_PROMPT.md, "Cập nhật phiên bản core: core và theme").
"""
import argparse
import os
import re
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))

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
        say(f'[-] Không tìm thấy "{name}" trong PATH.')
        sys.exit(1)
    return path


def set_env(text, values):
    """Set KEY=value lines, uncommenting a commented template line when there is one."""
    for key, value in values.items():
        line = f'{key}={value}'
        pattern = re.compile(rf'^#?\s*{re.escape(key)}=.*$', re.MULTILINE)
        text, count = pattern.subn(line, text, count=1)
        if count == 0:
            text = text.rstrip('\n') + '\n' + line + '\n'
    return text


def parse_args():
    p = argparse.ArgumentParser(description='Tạo dự án khách mới từ core.')
    p.add_argument('name', help='Tên dự án = tên thư mục (chữ thường, số, gạch ngang), vd: khach-abc')
    p.add_argument('--parent', default=os.path.dirname(HERE),
                   help='Thư mục chứa dự án mới (mặc định: cạnh repo core).')
    p.add_argument('--from', dest='source', default=None,
                   help='Repo core để clone (mặc định: origin của repo core này).')
    p.add_argument('--ref', default='main', help='Nhánh hoặc tag core để bắt đầu (mặc định: main).')
    p.add_argument('--origin', default=None,
                   help='URL repo GitHub riêng (trống) của dự án; có thì đẩy code lên.')
    p.add_argument('--db', choices=['mysql', 'sqlite'], default='mysql', help='Database local (mặc định: mysql của Laragon).')
    p.add_argument('--db-host', default='127.0.0.1')
    p.add_argument('--db-user', default='root')
    p.add_argument('--db-pass', default='')
    p.add_argument('--skip-install', action='store_true',
                   help='Chỉ tạo repo và .env; không composer install / core:install.')
    return p.parse_args()


def main():
    args = parse_args()
    name = args.name.strip().lower()
    if not re.fullmatch(r'[a-z0-9][a-z0-9-]{1,48}', name):
        say('[-] Tên dự án chỉ gồm chữ thường, số và gạch ngang, vd: khach-abc')
        sys.exit(1)

    target = os.path.join(os.path.abspath(args.parent), name)
    if os.path.exists(target):
        say(f'[-] {target} đã tồn tại.')
        sys.exit(1)

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
    if args.origin:
        run([git, 'remote', 'add', 'origin', args.origin], cwd=target)
        answer = input(f'Đẩy lên {args.origin} ngay? [C/k]: ').strip().lower()
        if answer in ('', 'c', 'co', 'y', 'yes'):
            run([git, 'push', '-u', 'origin', 'main'], cwd=target)

    say('')
    say(f'[+] Xong: {target} (core {core_version})')
    say(f'    Web local:  http://{name}.test  (Laragon: Menu → Reload để nhận thư mục mới)')
    say(f'    Admin:      http://{name}.test/vi/admin')
    say('    Theme:      resources/views/client, public/client-assets, routes/theme.php — không sửa file core.')
    if not args.origin:
        say('    Repo riêng: tạo repo trống trên GitHub rồi:')
        say(f'                git -C "{target}" remote add origin <url> && git -C "{target}" push -u origin main')
    say('    Deploy:     python deploy.py   (chạy trong thư mục dự án)')
    say('    Lấy core mới: git fetch core --tags && git merge vX.Y.Z')


if __name__ == '__main__':
    main()

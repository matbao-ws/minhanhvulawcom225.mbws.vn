"""
Find — or install — what the team scripts need, on any developer machine: Windows with Laragon or
XAMPP (in PATH or not), Linux, macOS. Only git and Python have to be there already.

    import devenv
    devenv.activate()          # PATH gets a working php and composer, for this process and children
    devenv.doctor(install=True)  # report, and offer to install what is missing

- php: PATH first, then Laragon (any drive), XAMPP, Homebrew. It must be 8.2+ with the extensions
  below; a missing one is reported with the php.ini to fix.
- composer: PATH, else composer.phar downloaded once into ~/.matbaows (checksum verified) and
  exposed through a small `composer` shim, so nobody installs Composer by hand.
"""
import glob
import hashlib
import os
import platform
import re
import shutil
import subprocess
import sys
import urllib.request

HOME_DIR = os.path.join(os.path.expanduser('~'), '.matbaows')
SHIM_DIR = os.path.join(HOME_DIR, 'bin')
COMPOSER_PHAR = os.path.join(HOME_DIR, 'composer.phar')
COMPOSER_URL = 'https://getcomposer.org/download/latest-stable/composer.phar'

PHP_MIN = (8, 2)
# composer.lock (ext-*), plus what Laravel, composer and the database need at run time.
REQUIRED_EXT = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'iconv', 'json', 'libxml', 'mbstring',
                'openssl', 'pcre', 'session', 'tokenizer', 'xml', 'zip', 'pdo_mysql', 'pdo_sqlite']
# Features use them (images, money, dates); the app starts without them.
RECOMMENDED_EXT = ['gd', 'intl', 'bcmath', 'exif']

WINDOWS = os.name == 'nt'


def say(message=''):
    print(message, flush=True)


def _run(cmd):
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=30)
        return r.returncode, r.stdout.strip()
    except (OSError, subprocess.TimeoutExpired):
        return 1, ''


def _version(php):
    code, text = _run([php, '-r', 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;'])
    m = re.match(r'(\d+)\.(\d+)\.(\d+)', text) if code == 0 else None
    return tuple(int(x) for x in m.groups()) if m else None


def _php_candidates():
    found = []
    if shutil.which('php'):
        found.append(shutil.which('php'))
    if WINDOWS:
        drives = [f'{d}:\\' for d in 'CDEFGHIJ' if os.path.exists(f'{d}:\\')]
        for drive in drives:
            # Laragon keeps every PHP it ever downloaded: newest first.
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
    """The first php 8.2+; else the newest one found (to report it); else None. Returns (path, version)."""
    best = None
    for php in _php_candidates():
        version = _version(php)
        if not version:
            continue
        if version[:2] >= PHP_MIN:
            return php, version
        if best is None or version > best[1]:
            best = (php, version)
    return best or (None, None)


def php_extensions(php):
    code, text = _run([php, '-m'])
    return {line.strip().lower() for line in text.splitlines() if line.strip() and not line.startswith('[')} if code == 0 else set()


def php_ini(php):
    code, text = _run([php, '--ini'])
    m = re.search(r'Loaded Configuration File:\s*(.+)', text)
    loaded = m.group(1).strip() if m else ''
    return '' if loaded.startswith('(none)') else loaded


def _write_shim(php):
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
    os.makedirs(HOME_DIR, exist_ok=True)
    say('  Tải composer.phar ...')
    with urllib.request.urlopen(COMPOSER_URL, timeout=60) as r:
        data = r.read()
    with urllib.request.urlopen(COMPOSER_URL + '.sha256', timeout=30) as r:
        expected = r.read().decode().split()[0].strip()
    if hashlib.sha256(data).hexdigest() != expected:
        raise RuntimeError('composer.phar tải về không khớp checksum; thử lại sau.')
    with open(COMPOSER_PHAR, 'wb') as f:
        f.write(data)


def activate():
    """
    Put a working php (8.2+) and composer first in PATH for this process and everything it starts.
    Returns {'php': path|None, 'php_version': tuple|None, 'composer': bool}. Never installs anything.
    """
    php, version = find_php()
    path = os.environ.get('PATH', '')
    if php and version and version[:2] >= PHP_MIN:
        path = os.path.dirname(php) + os.pathsep + path
    shim_path = SHIM_DIR + os.pathsep + path
    composer = shutil.which('composer', path=path) is not None
    if not composer and php and os.path.isfile(COMPOSER_PHAR):
        _write_shim(php)  # rewritten every time: it follows the php found today
        path, composer = shim_path, True
    os.environ['PATH'] = path
    return {'php': php, 'php_version': version, 'composer': composer}


def _ask_yes(label):
    try:
        return input(f'  {label} [C/k]: ').strip().lower() in ('', 'c', 'co', 'có', 'y', 'yes')
    except EOFError:
        return False


def _install(cmd):
    say('  $ ' + ' '.join(cmd))
    return subprocess.run(cmd).returncode == 0


def _linux_php_packages():
    return ['php-cli', 'php-mbstring', 'php-xml', 'php-curl', 'php-zip', 'php-sqlite3', 'php-mysql',
            'php-intl', 'php-gd', 'php-bcmath', 'unzip']


def install_php():
    """One command per platform. Windows gets Laragon (PHP + MySQL + everything enabled)."""
    if WINDOWS:
        if not shutil.which('winget'):
            say('  Không có winget. Tải Laragon tại https://laragon.org/download rồi chạy lại.')
            return False
        return _install(['winget', 'install', '-e', '--id', 'LeNgocKhoa.Laragon',
                         '--accept-package-agreements', '--accept-source-agreements'])
    if platform.system() == 'Darwin':
        return shutil.which('brew') is not None and _install(['brew', 'install', 'php'])
    if shutil.which('apt-get'):
        return _install(['sudo', 'apt-get', 'update']) and _install(['sudo', 'apt-get', 'install', '-y', *_linux_php_packages()])
    if shutil.which('dnf'):
        return _install(['sudo', 'dnf', 'install', '-y', 'php-cli', 'php-mbstring', 'php-xml', 'php-pdo', 'php-mysqlnd',
                         'php-intl', 'php-gd', 'php-bcmath', 'php-pecl-zip', 'unzip'])
    say('  Không nhận ra trình quản lý gói; cài PHP 8.2+ bằng tay.')
    return False


def install_gh():
    if WINDOWS and shutil.which('winget'):
        return _install(['winget', 'install', '-e', '--id', 'GitHub.cli', '--accept-package-agreements', '--accept-source-agreements'])
    if platform.system() == 'Darwin' and shutil.which('brew'):
        return _install(['brew', 'install', 'gh'])
    if shutil.which('apt-get'):
        return _install(['sudo', 'apt-get', 'install', '-y', 'gh'])
    if shutil.which('dnf'):
        return _install(['sudo', 'dnf', 'install', '-y', 'gh'])
    say('  Cài GitHub CLI: https://cli.github.com')
    return False


def doctor(install=False):
    """Report every tool; with install=True offer to install what is missing. True when all required are OK."""
    ok = True

    say('Công cụ:')
    if shutil.which('git'):
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
        if install and _ask_yes('Cài PHP ngay (Windows: Laragon; Linux/macOS: gói php)?') and install_php():
            say('  Cài xong. Mở terminal MỚI rồi chạy lại để nhận PHP.')
        return False

    say(f'  ✓ php {".".join(map(str, version))}  ({php})')
    have = php_extensions(php)
    missing = [e for e in REQUIRED_EXT if e not in have]
    weak = [e for e in RECOMMENDED_EXT if e not in have]
    if missing:
        ok = False
        say(f'  ✗ thiếu extension: {", ".join(missing)}')
    if weak:
        say(f'  ! nên bật thêm: {", ".join(weak)}')
    if missing or weak:
        ini = php_ini(php)
        if ini:
            say(f'    Mở {ini}, bỏ dấu ; trước các dòng extension=<tên> tương ứng, lưu lại.')
        elif WINDOWS:
            say(f'    PHP này chưa có php.ini: chép php.ini-development thành php.ini trong {os.path.dirname(php)},')
            say('    bỏ dấu ; trước extension_dir = "ext" và các dòng extension=<tên>.')
        else:
            say(f'    Linux: sudo apt-get install -y {" ".join("php-" + e for e in missing + weak if e not in ("pdo_mysql", "pdo_sqlite"))}')

    if env['composer']:
        say('  ✓ composer')
    else:
        if install and _ask_yes('Chưa có composer. Tải composer.phar về ~/.matbaows (không cần cài)?'):
            try:
                download_composer()
                _write_shim(php)
                activate()
                say('  ✓ composer (composer.phar)')
            except Exception as e:  # network, checksum
                ok = False
                say(f'  ✗ composer: {e}')
        else:
            ok = False
            say('  ✗ composer')

    if shutil.which('gh'):
        say('  ✓ gh (GitHub CLI)')
    else:
        say('  ! gh (GitHub CLI) chưa có: không tự tạo repo / PR được')
        if install and _ask_yes('Cài GitHub CLI?') and install_gh():
            say('  Cài xong. Mở terminal MỚI rồi chạy lại.')

    return ok


if __name__ == '__main__':
    for stream in (sys.stdout, sys.stderr):
        stream.reconfigure(encoding='utf-8')
    sys.exit(0 if doctor(install='--install' in sys.argv) else 1)

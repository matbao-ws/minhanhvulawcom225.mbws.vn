#!/usr/bin/env python3
"""
One menu for the team building customer websites (docs/TEAM_WORKFLOW.md):

    python team.py

Run it in the core repository to set up a machine or start a customer project, or in a customer
project to start a task, save and push it, take a new core release and deploy. It only wraps git,
gh, composer, artisan, new-project.py and deploy.py, and says what it runs.

Which files are core comes from app/Support/CoreManifest.php — the same list the updater uses —
so a commit that touches core files is caught before it reaches the website.
"""
import os
import re
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import devenv  # noqa: E402  (next to this script)

HERE = os.getcwd()


# ---------------------------------------------------------------- helpers

def say(message=''):
    print(message, flush=True)


def ask(label, default=''):
    hint = f' [{default}]' if default else ''
    try:
        return input(f'  {label}{hint}: ').strip() or default
    except EOFError:
        return default


def yes(label, default=True):
    answer = ask(label + (' [C/k]' if default else ' [c/K]')).lower()
    return default if answer == '' else answer in ('c', 'co', 'có', 'y', 'yes')


def run(cmd, check=True):
    say('  $ ' + ' '.join(cmd))
    code = subprocess.run(cmd, cwd=HERE).returncode
    if check and code != 0:
        raise Stop(f'Lệnh thất bại (mã {code}).')
    return code


def out(cmd):
    result = subprocess.run(cmd, cwd=HERE, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return result.stdout.strip() if result.returncode == 0 else ''


def has(tool):
    return shutil.which(tool) is not None


class Stop(Exception):
    pass


def git(*args, check=True):
    return run(['git', *args], check=check)


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


def version_tuple(text):
    m = re.match(r'v?(\d+)\.(\d+)\.(\d+)', text or '')
    return tuple(int(x) for x in m.groups()) if m else None


def local_version():
    path = os.path.join(HERE, 'VERSION')
    return open(path, encoding='utf-8').read().strip() if os.path.exists(path) else ''


# ---------------------------------------------------------------- core files

def core_patterns():
    path = os.path.join(HERE, 'app', 'Support', 'CoreManifest.php')
    if not os.path.exists(path):
        return []
    source = open(path, encoding='utf-8').read()
    block = re.search(r'const CORE = \[(.*?)\];', source, re.S)
    return re.findall(r"'([^']+)'", block.group(1)) if block else []


def is_core(path, patterns):
    path = path.replace('\\', '/')
    for pattern in patterns:
        if pattern.endswith('/**'):
            if path.startswith(pattern[:-2]):
                return True
        elif path == pattern:
            return True
    return False


# ---------------------------------------------------------------- where are we

def kind():
    """'core' (the core repository), 'project' (a customer project) or None."""
    if not out(['git', 'rev-parse', '--is-inside-work-tree']):
        return None
    remotes = out(['git', 'remote']).split()
    if 'core' in remotes:
        return 'project'
    if os.path.exists(os.path.join(HERE, 'new-project.py')):
        return 'core'
    return None


# ---------------------------------------------------------------- actions

def setup_machine():
    ready = devenv.doctor(install=True)

    if has('gh'):
        if subprocess.run(['gh', 'auth', 'status'], capture_output=True).returncode == 0:
            say('  ✓ đã đăng nhập GitHub')
        elif yes('Chưa đăng nhập GitHub. Đăng nhập ngay (mở trình duyệt)?'):
            run(['gh', 'auth', 'login', '--hostname', 'github.com', '--git-protocol', 'https', '--web'], check=False)
            run(['gh', 'auth', 'setup-git'], check=False)

    name = out(['git', 'config', '--global', 'user.name'])
    email = out(['git', 'config', '--global', 'user.email'])
    if not name or not email:
        say('Git chưa biết bạn là ai (tên/email hiện trên commit):')
        if not name:
            run(['git', 'config', '--global', 'user.name', ask('Tên của bạn')])
        if not email:
            run(['git', 'config', '--global', 'user.email', ask('Email tài khoản GitHub')])
    else:
        say(f'  ✓ git: {name} <{email}>')
    run(['git', 'config', '--global', 'fetch.prune', 'true'])

    if ready:
        say('[+] Máy đã sẵn sàng.')
    else:
        say('[!] Còn thiếu công cụ (✗ ở trên). Cài xong, mở terminal mới rồi chạy lại mục này.')


def new_project():
    git('pull', '--ff-only', check=False)
    run([sys.executable, 'new-project.py'], check=False)


def start_task():
    files = dirty_files()
    if files:
        raise Stop('Đang có thay đổi chưa lưu. Chọn "Lưu & đẩy" trước, hoặc bỏ chúng đi.')
    task = ask('Tên việc (vd: trang-chu, sua-menu-mobile)')
    slug = re.sub(r'[^a-z0-9]+', '-', task.lower()).strip('-')
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
            say('[!] Các file sau là file CORE (bản cập nhật core sẽ đè hoặc bị chặn vì chúng):')
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
    merged = [b.strip() for b in out(['git', 'branch', '--merged', 'main']).splitlines()]
    for b in merged:
        name = b.lstrip('* ').strip()
        if name and name != 'main':
            git('branch', '-d', name, check=False)
    say('[+] Đã về main mới nhất.')


def core_tags():
    git('fetch', 'core', '--tags', '--quiet')
    tags = [t for t in out(['git', 'tag', '-l', 'v*']).split() if version_tuple(t)]
    return sorted(tags, key=version_tuple)


def update_core(target=None):
    if dirty_files():
        raise Stop('Đang có thay đổi chưa lưu. Lưu & đẩy trước.')
    mine = local_version()
    tags = core_tags()
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

    mine = local_version()
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
    say(f'Core:     {local_version() or "?"}')
    files = dirty_files()
    patterns = core_patterns()
    say(f'Thay đổi chưa lưu: {len(files)} file' + (f' (trong đó {sum(is_core(f, patterns) for f in files)} file core)' if files else ''))
    origin = out(['git', 'remote', 'get-url', 'origin'])
    if origin:
        say(f'GitHub:   {origin}')


# ---------------------------------------------------------------- menu

MENUS = {
    'core': [
        ('Thiết lập máy (công cụ, đăng nhập GitHub)', setup_machine),
        ('Tạo dự án khách mới', new_project),
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


def main():
    for stream in (sys.stdout, sys.stderr):
        stream.reconfigure(encoding='utf-8')
    env = devenv.activate()  # php/composer from Laragon, XAMPP... even when not in PATH
    if not env['php'] or not env['composer']:
        say('[!] Chưa đủ php/composer trên máy này: chọn "Thiết lập máy" trước.')
    where = kind()
    if where is None:
        say('[-] Chạy trong thư mục repo core (matbaows-core-admin) hoặc thư mục một dự án khách.')
        sys.exit(1)

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
        if not choice.isdigit() or not 1 <= int(choice) <= len(items):
            continue
        say('')
        try:
            items[int(choice) - 1][1]()
        except Stop as e:
            say(f'[-] {e}')
        except KeyboardInterrupt:
            say('\n[-] Đã huỷ.')


if __name__ == '__main__':
    main()

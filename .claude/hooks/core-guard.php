<?php

/*
 * Core files are not edited in a customer project: a core update replaces them, or stops the
 * website's updates until they are put back. This guard enforces it where people and AI agents
 * actually write code, so nobody has to remember the rule.
 *
 *   php .claude/hooks/core-guard.php claude      Claude Code PreToolUse hook (.claude/settings.json):
 *                                                reads the tool call on stdin, exit 2 blocks it
 *   php .claude/hooks/core-guard.php pre-commit  git hook (.githooks/pre-commit): refuses a commit
 *                                                with core files, except a core release being merged
 *   php .claude/hooks/core-guard.php install     points git at .githooks (composer runs it)
 *
 * It only acts in a customer project, i.e. a clone with the `core` remote that `team.py new`
 * creates; in the core repository itself everything is allowed. Which files are core comes from
 * App\Support\CoreManifest, the same list core updates and `core:status` use.
 */

$root = dirname(__DIR__, 2);
require_once $root.'/app/Support/CoreManifest.php';

use App\Support\CoreManifest;

const CORE_GUARD_ADVICE = <<<'TXT'
Đây là file CORE (Mắt Bão phát hành cho mọi website). Dự án khách không sửa core: bản cập nhật core
sẽ đè lên, hoặc website bị chặn cập nhật cho tới khi file được khôi phục.
Làm thay đổi ở phần của website:
  - giao diện: resources/views/client/**, public/client-assets/css/**, routes/theme.php
  - tính năng riêng: extensions/<tên>/ (php artisan extension:make)
Nếu thật sự cần đổi core: báo trưởng nhóm hoặc mở Issue trên repo core.
TXT;

exit(match ($argv[1] ?? '') {
    'claude' => guardToolCall($root),
    'pre-commit' => guardCommit($root),
    'install' => installGitHook($root),
    default => usage(),
});

function usage(): int
{
    fwrite(STDERR, "usage: php .claude/hooks/core-guard.php claude|pre-commit|install\n");

    return 1;
}

/** A customer project: `team.py new` renames the core clone's remote to `core`. */
function isCustomerProject(string $root): bool
{
    $remotes = @shell_exec('git -C '.escapeshellarg($root).' remote 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));

    return in_array('core', preg_split('/\R/', trim((string) $remotes)) ?: [], true);
}

/** The path relative to the project root, with forward slashes; null when it is outside. */
function relativePath(string $root, string $path): ?string
{
    $normalise = fn (string $p) => rtrim(str_replace('\\', '/', $p), '/');
    $path = $normalise($path);
    if (! preg_match('#^([a-zA-Z]:|/)#', $path)) {
        $path = $normalise((string) getcwd()).'/'.$path;
    }
    $base = $normalise((string) (realpath($root) ?: $root));

    // Git Bash style /f/laragon/... for F:/laragon/...
    $path = preg_replace('#^/([a-zA-Z])/#', '$1:/', $path);
    $base = preg_replace('#^/([a-zA-Z])/#', '$1:/', $base);

    $insensitive = PHP_OS_FAMILY === 'Windows';
    $prefix = $base.'/';
    if (! ($insensitive ? stripos($path, $prefix) === 0 : str_starts_with($path, $prefix))) {
        return null;
    }

    $relative = substr($path, strlen($prefix));
    // Resolve ./ and ../ without touching the disk (the file may not exist yet).
    $parts = [];
    foreach (explode('/', $relative) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            if ($parts === []) {
                return null;
            }
            array_pop($parts);

            continue;
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

function isCore(string $root, string $relative): bool
{
    return (new CoreManifest($root))->owner($relative) === 'core';
}

/** Claude Code PreToolUse: Edit, MultiEdit, Write and NotebookEdit carry the file they write. */
function guardToolCall(string $root): int
{
    $call = json_decode((string) stream_get_contents(STDIN), true);
    $input = is_array($call) ? (array) ($call['tool_input'] ?? []) : [];
    $path = $input['file_path'] ?? $input['notebook_path'] ?? null;

    if (! is_string($path) || $path === '' || ! isCustomerProject($root)) {
        return 0;
    }

    $relative = relativePath($root, $path);
    if ($relative === null || ! isCore($root, $relative)) {
        return 0;
    }

    fwrite(STDERR, "Chặn: {$relative}\n".CORE_GUARD_ADVICE."\n");

    return 2;
}

/**
 * Staged core files are refused, unless a core release is being merged and the file is exactly
 * what that release has (the conflict a core update leaves, resolved to the core's side).
 */
function guardCommit(string $root): int
{
    if (! isCustomerProject($root)) {
        return 0;
    }

    $git = 'git -C '.escapeshellarg($root);
    $staged = array_filter(explode("\0", (string) shell_exec($git.' diff --cached --name-only --no-renames -z')));
    $merging = trim((string) shell_exec($git.' rev-parse -q --verify MERGE_HEAD')) !== '';

    $blocked = [];
    foreach ($staged as $path) {
        if (! isCore($root, $path)) {
            continue;
        }
        if ($merging) {
            $ours = trim((string) shell_exec($git.' rev-parse -q --verify '.escapeshellarg(':'.$path)));
            $theirs = trim((string) shell_exec($git.' rev-parse -q --verify '.escapeshellarg('MERGE_HEAD:'.$path)));
            if ($ours === $theirs) {
                continue;
            }
        }
        $blocked[] = $path;
    }

    if ($blocked === []) {
        return 0;
    }

    fwrite(STDERR, "\nCommit bị chặn: ".count($blocked)." file core đã sửa\n");
    foreach (array_slice($blocked, 0, 20) as $path) {
        fwrite(STDERR, "  - {$path}\n");
    }
    if (count($blocked) > 20) {
        fwrite(STDERR, '  ... và '.(count($blocked) - 20)." file nữa\n");
    }
    fwrite(STDERR, "\n".CORE_GUARD_ADVICE."\nBỏ thay đổi một file core: git restore --staged --worktree <file>\n\n");

    return 1;
}

/** Points git at the versioned hooks once; never fails (composer runs it on servers without git). */
function installGitHook(string $root): int
{
    if (! is_dir($root.'/.git') && ! is_file($root.'/.git')) {
        return 0;
    }
    if (! isCustomerProject($root)) {
        return 0;
    }

    $git = 'git -C '.escapeshellarg($root);
    $current = trim((string) shell_exec($git.' config --local --get core.hooksPath'));
    if ($current === '.githooks') {
        return 0;
    }
    if ($current !== '') {
        fwrite(STDERR, "core-guard: core.hooksPath đang là {$current}; tự thêm .githooks/pre-commit vào đó để chặn sửa core.\n");

        return 0;
    }

    shell_exec($git.' config --local core.hooksPath .githooks');
    fwrite(STDOUT, "core-guard: đã bật git hook chặn commit file core (.githooks/pre-commit).\n");

    return 0;
}

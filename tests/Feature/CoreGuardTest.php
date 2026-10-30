<?php

namespace Tests\Feature;

use App\Support\CoreManifest;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * .claude/hooks/core-guard.php on a throwaway git repository shaped like a customer project
 * (a clone with the `core` remote): Claude Code edits and git commits of core files are refused,
 * the website's own files are not, and the core repository itself is never blocked.
 */
class CoreGuardTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = sys_get_temp_dir().'/core-guard-'.uniqid();
        File::ensureDirectoryExists($this->project.'/app/Support');
        File::ensureDirectoryExists($this->project.'/.claude/hooks');
        File::copy(base_path('app/Support/CoreManifest.php'), $this->project.'/app/Support/CoreManifest.php');
        File::copy(base_path('.claude/hooks/core-guard.php'), $this->project.'/.claude/hooks/core-guard.php');

        $this->git('init', '-q', '-b', 'main');
        $this->git('config', 'user.email', 'dev@example.test');
        $this->git('config', 'user.name', 'Dev');
        $this->git('config', 'commit.gpgsign', 'false');
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', 'core');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->project);

        parent::tearDown();
    }

    private function git(string ...$args): Process
    {
        $process = new Process(['git', ...$args], $this->project);
        $process->run();

        return $process;
    }

    private function guard(string $mode, ?string $stdin = null): Process
    {
        $process = new Process([PHP_BINARY, '.claude/hooks/core-guard.php', $mode], $this->project, null, $stdin);
        $process->run();

        return $process;
    }

    private function edit(string $path): Process
    {
        return $this->guard('claude', json_encode(['tool_name' => 'Edit', 'tool_input' => ['file_path' => $this->project.'/'.$path]]));
    }

    private function write(string $path, string $content): void
    {
        File::ensureDirectoryExists(dirname($this->project.'/'.$path));
        File::put($this->project.'/'.$path, $content);
    }

    public function test_the_core_repository_itself_is_never_blocked(): void
    {
        $this->assertSame(0, $this->edit('app/Models/Product.php')->getExitCode());

        $this->write('app/Models/Product.php', '<?php // core work');
        $this->git('add', '-A');
        $this->assertSame(0, $this->guard('pre-commit')->getExitCode());
    }

    public function test_claude_code_cannot_edit_core_files_in_a_customer_project(): void
    {
        $this->git('remote', 'add', 'core', 'https://github.com/M-t-Bao-WS/matbaows-core-admin.git');

        $blocked = $this->edit('app/Models/Product.php');
        $this->assertSame(2, $blocked->getExitCode());
        $this->assertStringContainsString('Chặn: app/Models/Product.php', $blocked->getErrorOutput());
        $this->assertStringContainsString('resources/views/client', $blocked->getErrorOutput());

        // The guard cannot be switched off by editing it, or its settings.
        $this->assertSame(2, $this->edit('.claude/settings.json')->getExitCode());
        $this->assertSame(2, $this->edit('.claude/hooks/core-guard.php')->getExitCode());
        $this->assertSame(2, $this->guard('claude', json_encode(['tool_input' => ['file_path' => $this->project.'/app/../config/app.php']]))->getExitCode());

        foreach (['resources/views/client/home.blade.php', 'public/client-assets/css/site.css', 'routes/theme.php', 'extensions/shop/src/Plugin.php', '.claude/settings.local.json', 'notes.md'] as $own) {
            $this->assertSame(0, $this->edit($own)->getExitCode(), $own);
        }
        $this->assertSame(0, $this->guard('claude', json_encode(['tool_input' => ['file_path' => sys_get_temp_dir().'/elsewhere/app/x.php']]))->getExitCode());
        $this->assertSame(0, $this->guard('claude', json_encode(['tool_name' => 'Bash', 'tool_input' => ['command' => 'ls']]))->getExitCode());
    }

    public function test_a_commit_with_core_files_is_refused_in_a_customer_project(): void
    {
        $this->git('remote', 'add', 'core', 'https://github.com/M-t-Bao-WS/matbaows-core-admin.git');

        $this->write('resources/views/client/home.blade.php', '<h1>Shop</h1>');
        $this->git('add', '-A');
        $this->assertSame(0, $this->guard('pre-commit')->getExitCode());
        $this->git('commit', '-q', '-m', 'theme');

        $this->write('app/Models/Product.php', '<?php // hand edit');
        $this->git('add', '-A');
        $refused = $this->guard('pre-commit');
        $this->assertSame(1, $refused->getExitCode());
        $this->assertStringContainsString('app/Models/Product.php', $refused->getErrorOutput());

        // Deleting a core file is an edit too.
        $this->git('commit', '-q', '--no-verify', '-m', 'as if it came with core');
        $this->git('rm', '-q', 'app/Models/Product.php');
        $this->assertSame(1, $this->guard('pre-commit')->getExitCode());
    }

    public function test_merging_a_core_release_passes_only_with_the_releases_own_files(): void
    {
        $this->git('remote', 'add', 'core', 'https://github.com/M-t-Bao-WS/matbaows-core-admin.git');

        $this->git('checkout', '-q', '-b', 'release');
        $this->write('app/Models/Product.php', '<?php // 1.7.0');
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', 'release 1.7.0');

        $this->git('checkout', '-q', 'main');
        $this->write('app/Models/Product.php', '<?php // edited on the site');
        $this->git('add', '-A');
        $this->git('commit', '-q', '--no-verify', '-m', 'hand edit');

        $this->assertNotSame(0, $this->git('merge', '--no-edit', 'release')->getExitCode(), 'a conflict to resolve');

        // Resolved to a new edit, neither the site's nor the release's: refused.
        $this->write('app/Models/Product.php', '<?php // a bit of both');
        $this->git('add', 'app/Models/Product.php');
        $this->assertSame(1, $this->guard('pre-commit')->getExitCode());

        // Resolved to the release: allowed.
        $this->git('checkout', 'MERGE_HEAD', '--', 'app/Models/Product.php');
        $this->assertSame(0, $this->guard('pre-commit')->getExitCode());
    }

    public function test_install_points_git_at_the_versioned_hooks_in_a_customer_project_only(): void
    {
        $this->assertSame(0, $this->guard('install')->getExitCode());
        $this->assertSame('', trim($this->git('config', '--local', '--get', 'core.hooksPath')->getOutput()));

        $this->git('remote', 'add', 'core', 'https://github.com/M-t-Bao-WS/matbaows-core-admin.git');
        $this->assertSame(0, $this->guard('install')->getExitCode());
        $this->assertSame('.githooks', trim($this->git('config', '--local', '--get', 'core.hooksPath')->getOutput()));

        // A hooks path the project chose itself is left alone.
        $this->git('config', '--local', 'core.hooksPath', '.husky');
        $this->assertSame(0, $this->guard('install')->getExitCode());
        $this->assertSame('.husky', trim($this->git('config', '--local', '--get', 'core.hooksPath')->getOutput()));
    }

    public function test_the_guard_and_its_hooks_ship_with_core(): void
    {
        $settings = json_decode(File::get(base_path('.claude/settings.json')), true);
        $hook = $settings['hooks']['PreToolUse'][0];
        $this->assertSame('Edit|MultiEdit|Write|NotebookEdit', $hook['matcher']);
        $this->assertStringContainsString('core-guard.php" claude', $hook['hooks'][0]['command']);

        $this->assertStringContainsString('core-guard.php" pre-commit', File::get(base_path('.githooks/pre-commit')));
        $script = collect(json_decode(File::get(base_path('composer.json')), true)['scripts']['post-autoload-dump'])
            ->first(fn (string $line) => str_contains($line, 'core-guard.php install'));
        $this->assertNotNull($script, 'composer turns the git hook on');

        // What composer runs. Where the guard is absent (a Docker image built without .claude/),
        // it must not fail the build; in a customer project it turns the hook on.
        $run = fn (string $dir) => tap(Process::fromShellCommandline(PHP_BINARY.' '.substr($script, strlen('@php ')), $dir))->run();
        $bare = sys_get_temp_dir().'/core-guard-bare-'.uniqid();
        File::ensureDirectoryExists($bare);
        $this->assertSame(0, $run($bare)->getExitCode());
        File::deleteDirectory($bare);

        $this->git('remote', 'add', 'core', 'https://github.com/M-t-Bao-WS/matbaows-core-admin.git');
        $this->assertSame(0, $run($this->project)->getExitCode());
        $this->assertSame('.githooks', trim($this->git('config', '--local', '--get', 'core.hooksPath')->getOutput()));

        $manifest = new CoreManifest(base_path());
        foreach (['.claude/hooks/core-guard.php', '.claude/settings.json', '.githooks/pre-commit'] as $file) {
            $this->assertSame('core', $manifest->owner($file), $file);
        }
    }
}

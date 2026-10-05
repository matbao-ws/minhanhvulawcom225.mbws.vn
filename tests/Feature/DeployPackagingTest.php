<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy.py never ships storage/app/core — the website's installed.json baseline, update state and
 * history, backups and extension on/off state. Runs deploy.py's own packaging functions on a
 * throwaway project: the full package (first deploy or redeploy) and the incremental file list.
 */
class DeployPackagingTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = sys_get_temp_dir().'/deploy-pkg-'.uniqid();
        foreach ([
            'app/Models/Product.php' => '<?php',
            'extensions/dealers/extension.json' => '{}',
            'storage/app/public/logo.png' => 'png',
            'storage/app/core/installed.json' => '{"version":"1.0.0"}',
            'storage/app/core/extensions.json' => '{"disabled":{}}',
            'storage/app/core/state.json' => '{}',
            'storage/app/core/backups/20260101-1.0.0-to-1.1.0/app/Models/Product.php' => 'old',
            'storage/app/corex/kept.txt' => 'not the core folder',
        ] as $path => $contents) {
            File::ensureDirectoryExists(dirname($this->project.'/'.$path));
            file_put_contents($this->project.'/'.$path, $contents);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->project);

        parent::tearDown();
    }

    public function test_full_and_incremental_deploys_leave_storage_app_core_on_the_website(): void
    {
        $python = $this->python();
        $script = <<<'PY'
            import importlib.util, json, os, sys, zipfile
            spec = importlib.util.spec_from_file_location('deploy', sys.argv[1])
            deploy = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(deploy)
            os.chdir(sys.argv[2])
            zip_name = deploy.build_zip_package(os.path.join(sys.argv[3], 'deploy.zip'))
            with zipfile.ZipFile(zip_name) as z:
                packaged = [n.replace('\\', '/') for n in z.namelist()]
            changed = [p.replace('\\', '/') for p in deploy.get_changed_files(0)]
            print('RESULT=' + json.dumps({'packaged': packaged, 'changed': changed}))
            PY;
        $out = sys_get_temp_dir().'/deploy-pkg-out-'.uniqid();
        File::ensureDirectoryExists($out);
        file_put_contents($out.'/check.py', $script);

        try {
            // No __pycache__ left in the repository by importing deploy.py.
            $process = new Process([$python, $out.'/check.py', base_path('deploy.py'), $this->project, $out], null, ['PYTHONDONTWRITEBYTECODE' => '1']);
            $process->setTimeout(60)->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            preg_match('/RESULT=(.*)$/m', $process->getOutput(), $m);
            $result = json_decode($m[1] ?? 'null', true);
        } finally {
            File::deleteDirectory($out);
        }

        foreach (['packaged' => 'full deploy', 'changed' => 'incremental deploy'] as $key => $mode) {
            $files = $result[$key];
            $this->assertContains('app/Models/Product.php', $files, $mode);
            $this->assertContains('extensions/dealers/extension.json', $files, $mode);
            $this->assertContains('storage/app/corex/kept.txt', $files, "{$mode}: only the core folder itself is left out");
            $this->assertSame([], array_values(array_filter($files, fn ($f) => str_starts_with(rtrim($f, '/').'/', 'storage/app/core/'))), "{$mode} ships storage/app/core");
        }
    }

    private function python(): string
    {
        foreach (['python3', 'python'] as $candidate) {
            $probe = new Process([$candidate, '-c', 'import sys; print(sys.version_info[0])']);
            $probe->run();
            if ($probe->isSuccessful() && trim($probe->getOutput()) === '3') {
                return $candidate;
            }
        }
        $this->markTestSkipped('Python 3 is not installed.');
    }
}

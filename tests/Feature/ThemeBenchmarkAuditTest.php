<?php

namespace Tests\Feature;

use App\Support\EditableRegionScanner;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The audit against a theme as it is delivered (tests/Fixtures/theme-benchmark/raw): one of
 * every pattern a cut meets, in a layout, a page and the header and footer partials.
 * expected.json is the answer key. A pattern the scanner stops seeing fails here, and so
 * does anything it starts reporting that is not in the key: fonts, masks, gradients, icons,
 * decorative art, media and class-name data-bg must never become findings.
 */
class ThemeBenchmarkAuditTest extends TestCase
{
    private const ROOT = 'tests/Fixtures/theme-benchmark';

    public function test_every_case_is_reported_as_expected_and_nothing_else_is(): void
    {
        $expected = $this->expected();
        $found = [];
        $styleBlocks = [];
        $totals = ['ignored' => 0, 'invalidIgnores' => 0, 'image' => [], 'text' => []];

        foreach ($this->audits() as $file => $audit) {
            foreach ($audit['findings'] as $finding) {
                if ($finding['reason'] === 'style-block') {
                    $styleBlocks[] = ['file' => $file, 'preview' => $finding['preview'], 'reason' => $finding['reason']];

                    continue;
                }
                $this->assertMatchesRegularExpression('/data-case="([^"]+)"/', $finding['attrs'],
                    "False positive in {$file}:{$finding['line']} ({$finding['kind']}/{$finding['reason']}): {$finding['preview']}");
                preg_match('/data-case="([^"]+)"/', $finding['attrs'], $m);
                $this->assertArrayNotHasKey($m[1], $found, "case {$m[1]} reported twice");
                $found[$m[1]] = ['file' => $file, 'kind' => $finding['kind'], 'status' => $finding['status'], 'reason' => $finding['reason']];
            }
            $totals['ignored'] += count($audit['ignored']);
            $totals['invalidIgnores'] += count($audit['invalidIgnores']);
            foreach ($audit['counts'] as $kind => $counts) {
                foreach ($counts as $status => $count) {
                    $totals[$kind][$status] = ($totals[$kind][$status] ?? 0) + $count;
                }
            }
        }

        foreach ($expected['findings'] as $case => $want) {
            $this->assertArrayHasKey($case, $found, "case {$case} ({$want['kind']}/{$want['reason']}) is no longer reported");
            $this->assertSame($want, $found[$case], "case {$case}");
        }
        $this->assertSame([], array_values(array_diff(array_keys($found), array_keys($expected['findings']))), 'reported cases missing from expected.json');

        $this->assertCount(count($expected['styleBlocks']), $styleBlocks);
        foreach ($expected['styleBlocks'] as $i => $want) {
            $this->assertSame($want['file'], $styleBlocks[$i]['file']);
            $this->assertStringContainsString($want['contains'], $styleBlocks[$i]['preview']);
            $this->assertSame($want['reason'], $styleBlocks[$i]['reason']);
        }

        foreach ($expected['setAside'] as $bucket => $count) {
            $this->assertSame($count, $totals['image'][$bucket], "set aside as {$bucket}");
        }
        $this->assertSame($expected['dynamic']['text'], $totals['text']['dynamic'], 'dynamic text (database values, defined translations)');
        $this->assertSame($expected['dynamic']['image'], $totals['image']['dynamic'], 'dynamic images');
        $this->assertSame($expected['ignored'], $totals['ignored']);
        $this->assertSame($expected['invalidIgnores'], $totals['invalidIgnores']);
    }

    public function test_the_command_audits_another_folder_and_fails_it(): void
    {
        $status = Artisan::call('client:editable-audit', ['--path' => self::ROOT.'/raw']);
        $output = Artisan::output();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('RESULT: FAIL', $output);
        $this->assertStringContainsString('Files scanned ................     4', $output);
        $this->assertStringContainsString('Section/menu wiring errors ...     0', $output, 'the app wiring checks are skipped for another folder');
        $this->assertStringContainsString('icon / inline svg ..........     2', $output);
    }

    public function test_a_missing_folder_is_an_error_not_a_pass(): void
    {
        $this->assertSame(1, Artisan::call('client:editable-audit', ['--path' => self::ROOT.'/does-not-exist']));
    }

    /** @return array<string, mixed> */
    private function expected(): array
    {
        return json_decode((string) file_get_contents(base_path(self::ROOT.'/expected.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array<string, mixed>> relative file => audit */
    private function audits(): array
    {
        $scanner = app(EditableRegionScanner::class)->withAppTranslations();
        $audits = [];

        foreach (Finder::create()->files()->in(base_path(self::ROOT.'/raw'))->name('*.blade.php')->sortByName() as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $audits[$relative] = $scanner->audit($file->getContents(), str_replace('/', '.', substr($relative, 0, -10)));
        }

        return $audits;
    }
}

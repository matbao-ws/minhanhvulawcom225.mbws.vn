<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * What client:editable-audit does to files and what it tells the reader.
 *
 * The scanner's verdicts are unit tested; this covers the two promises the
 * command makes on top of them: `--fix` rewrites only what is mechanical, and
 * every opt-out stays visible in the output with its reason.
 */
class EditableAuditCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = resource_path('views/client/__editable_audit_test__');
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function audit(array $options = []): array
    {
        $output = new BufferedOutput;
        $status = Artisan::call('client:editable-audit', $options, $output);

        return [$status, $output->fetch()];
    }

    public function test_fix_wraps_only_the_mechanical_cases_and_reports_the_rest(): void
    {
        $path = $this->directory.'/home.blade.php';

        File::put($path, <<<'BLADE'
        <section class="hero">
            <h2>Dịch vụ nổi bật</h2>
            <img src="/client-assets/img/hero.jpg" alt="Hero">
            <a class="btn" href="/lien-he">Liên hệ ngay</a>
            <div class="banner" style="background-image:url(/client-assets/img/bg.jpg)"></div>
        </section>
        BLADE);

        [$status, $output] = $this->audit(['--fix' => true]);
        $source = File::get($path);

        $this->assertStringContainsString('<x-client::editable key="editable-audit-test.home.hero.h2-1" tag="h2">Dịch vụ nổi bật</x-client::editable>', $source);
        $this->assertStringContainsString('<x-client::editable-image key="editable-audit-test.home.hero.img-1" src="/client-assets/img/hero.jpg" alt="Hero" />', $source);

        // Untouched: a CTA and a background are a human's call.
        $this->assertStringContainsString('<a class="btn" href="/lien-he">Liên hệ ngay</a>', $source);
        $this->assertStringContainsString('style="background-image:url(/client-assets/img/bg.jpg)"', $source);

        // The report is taken after the fix, so it shows what is left.
        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('Liên hệ ngay', $output);
        $this->assertStringContainsString('[chưa hỗ trợ]', $output);
        $this->assertStringNotContainsString('Dịch vụ nổi bật', $output);
        $this->assertStringContainsString('RESULT: FAIL', $output);
    }

    public function test_every_honoured_ignore_is_listed_with_its_reason_and_a_bare_one_fails(): void
    {
        File::put($this->directory.'/page.blade.php', <<<'BLADE'
        <div class="banner" data-editable-ignore="ảnh nền trang trí" style="background:url(/bg.jpg)"></div>
        <p data-editable-ignore>Hotline 24/7</p>
        BLADE);

        [$status, $output] = $this->audit();

        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('"ảnh nền trang trí"', $output);
        $this->assertStringContainsString('thiếu lý do', $output);
        $this->assertMatchesRegularExpression('/Ignored without reason \.+\s+1\s+FAIL/', $output);
    }

    public function test_a_fully_classified_storefront_passes(): void
    {
        File::put($this->directory.'/page.blade.php', <<<'BLADE'
        <section class="intro">
            <x-client::editable key="page.intro.title" tag="h2">Giới thiệu</x-client::editable>
            <p>{{ $settings['address'] }}</p>
            <div data-editable-ignore="ảnh nền trang trí" style="background:url(/bg.jpg)"></div>
        </section>
        BLADE);

        [$status, $output] = $this->audit();

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('RESULT: PASS', $output);
    }
}

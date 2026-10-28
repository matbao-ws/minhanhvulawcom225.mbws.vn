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

        // A single static background moves, byte for byte, into @clientBackground on the same tag.
        $this->assertStringContainsString("<div class=\"banner\" @clientBackground('editable-audit-test.home.hero.div-1', 'background-image:url(/client-assets/img/bg.jpg)')></div>", $source);

        // Untouched: a CTA is a human's call.
        $this->assertStringContainsString('<a class="btn" href="/lien-he">Liên hệ ngay</a>', $source);

        // The report is taken after the fix, so it shows what is left.
        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('Liên hệ ngay', $output);
        $this->assertStringNotContainsString('Dịch vụ nổi bật', $output);
        $this->assertStringNotContainsString('bg.jpg', $output);
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

    public function test_a_layout_that_does_not_load_the_editor_fails(): void
    {
        File::put($this->directory.'/shell.blade.php', <<<'BLADE'
        <html><head><title>{{ $title }}</title></head>
        <body>@yield('content')</body></html>
        BLADE);

        [$status, $output] = $this->audit();

        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString("@include('core.head')", $output);
        $this->assertStringContainsString("@include('core.editor')", $output);
        $this->assertMatchesRegularExpression('/layout wiring errors \.+\s+2\s+FAIL/', $output);
    }

    /**
     * A loop that repeats authored copy is a fixed run until each unit is a list item: no
     * "Nhân bản", no move, no delete — and nothing on the page shows it is missing.
     */
    public function test_a_repeated_run_must_be_a_list_of_items_at_its_outermost_element(): void
    {
        File::put($this->directory.'/cards.blade.php', <<<'BLADE'
        <div class="grid">
            @foreach(['a' => 'Một', 'b' => 'Hai'] as $id => $title)
                <div class="card">
                    <x-client::editable :key="'page.cards.'.$id.'.title'" tag="h3">{{ $title }}</x-client::editable>
                </div>
            @endforeach
        </div>
        <ul>
            @foreach($lists->items('page.steps', ['a', 'b']) as $id)
                <div class="col">
                    <x-client::list-item list="page.steps" :item="$id">
                        <x-client::editable :key="'page.steps.item_'.$id.'.title'" tag="span">Bước</x-client::editable>
                    </x-client::list-item>
                </div>
            @endforeach
        </ul>
        @foreach($products as $product)
            <p>{{ $product->name }}</p>
        @endforeach
        BLADE);

        [$status, $output] = $this->audit();

        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('cards.blade.php:2', $output);
        $this->assertStringContainsString('chưa bọc từng mục', $output);
        // Wrapped, but inside a column: a copy would land in that column, not in the grid.
        $this->assertStringContainsString('cards.blade.php:9', $output);
        $this->assertStringContainsString('phần tử ngoài cùng', $output);
        // Database rows repeat nothing an editor authored.
        $this->assertMatchesRegularExpression('/repeated runs \.+\s+2\b/', $output);
        $this->assertMatchesRegularExpression('/not a list-item \.+\s+2\s+FAIL/', $output);
    }

    public function test_a_run_fixed_on_purpose_is_listed_with_its_reason_and_a_bare_marker_fails(): void
    {
        File::put($this->directory.'/fixed.blade.php', <<<'BLADE'
        {{-- editor:fixed-run băng logo lặp lại cho hiệu ứng chạy --}}
        @foreach(['a', 'b'] as $logo)
            <x-client::editable-image :key="'home.logos.'.$logo" src="/logo.png" alt="" />
        @endforeach
        {{-- editor:fixed-run --}}
        @foreach(['a', 'b'] as $step)
            <x-client::editable :key="'home.steps.'.$step" tag="p">Bước</x-client::editable>
        @endforeach
        BLADE);

        [$status, $output] = $this->audit();

        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('"băng logo lặp lại cho hiệu ứng chạy"', $output);
        $this->assertMatchesRegularExpression('/fixed on purpose \.+\s+1\b/', $output);
        $this->assertMatchesRegularExpression('/fixed without reason \.+\s+1\s+FAIL/', $output);
    }

    /**
     * An inline edit of a product name is stored in site_blocks and shadows the record: the admin
     * screen and the API keep the old name, the page shows the new one.
     */
    public function test_database_content_may_not_be_an_inline_region(): void
    {
        File::put($this->directory.'/products.blade.php', <<<'BLADE'
        @foreach($products as $product)
            <x-client::editable :key="'catalog.product.'.$product->id.'.name'" tag="h3">{{ $product->name }}</x-client::editable>
            <x-client::editable-image key="catalog.product.photo" :src="$product->image_url" alt="" />
        @endforeach
        @foreach($partners as $partner)
            <x-client::editable key="home.partner.name" tag="span">{{ $partner?->name }}</x-client::editable>
        @endforeach
        <x-client::editable key="home.intro.title" tag="h2">{{ $shopName }} — {{ $loop->iteration ?? '' }}</x-client::editable>
        BLADE);

        [$status, $output] = $this->audit();

        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('dữ liệu từ database', $output);
        $this->assertMatchesRegularExpression('/editable database data \.+\s+3\s+FAIL/', $output);
    }

    /**
     * A theme cut before the structure rules must not fail CI the day it takes the core update:
     * what it has is recorded once, and only new findings fail.
     */
    public function test_a_baseline_keeps_an_existing_theme_green_and_still_fails_new_findings(): void
    {
        $cards = <<<'BLADE'
        @foreach(['a' => 'Một', 'b' => 'Hai'] as $id => $title)
            <div class="card"><x-client::editable :key="'page.cards.'.$id.'.title'" tag="h3">{{ $title }}</x-client::editable></div>
        @endforeach
        BLADE;
        File::put($this->directory.'/cards.blade.php', $cards);
        $baseline = resource_path('views/client/.editor-structure-baseline.json');

        try {
            [$status] = $this->audit();
            $this->assertSame(1, $status);

            [$status, $output] = $this->audit(['--baseline' => true]);
            $this->assertSame(0, $status, $output);
            $this->assertFileExists($baseline);
            $this->assertMatchesRegularExpression('/known \(baseline\) \.+\s+1\b/', $output);

            // Lines moving above the run do not revive it.
            File::put($this->directory.'/cards.blade.php', "<h2>{{ \$heading }}</h2>\n\n".$cards);
            [$status, $output] = $this->audit();
            $this->assertSame(0, $status, $output);

            // A new unwrapped run fails as before.
            File::put($this->directory.'/steps.blade.php', str_replace(['page.cards.', '$title'], ['page.steps.', '$step'], $cards));
            [$status, $output] = $this->audit();
            $this->assertSame(1, $status, $output);
            $this->assertStringContainsString('steps.blade.php', $output);
        } finally {
            File::delete($baseline);
        }
    }

    public function test_a_list_of_items_passes_the_structure_check(): void
    {
        File::put($this->directory.'/list.blade.php', <<<'BLADE'
        <ul class="benefits">
            @foreach($lists->items('page.benefits', ['quality', 'service']) as $id)
                {{-- A copied item has no template copy. --}}
                @php($label = ['quality' => 'Chất lượng', 'service' => 'Dịch vụ'][$id] ?? '')
                <x-client::list-item list="page.benefits" :item="$id" tag="li" class="flex">
                    <x-client::editable :key="'page.benefits.item_'.$id.'.label'" tag="span">{{ $label }}</x-client::editable>
                </x-client::list-item>
            @endforeach
        </ul>
        BLADE);

        [$status, $output] = $this->audit();

        $this->assertSame(0, $status, $output);
        $this->assertMatchesRegularExpression('/repeated runs \.+\s+1\b/', $output);
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

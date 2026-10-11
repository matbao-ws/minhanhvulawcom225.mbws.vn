<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\Page;
use App\Services\LocalizedSlugService;
use App\Support\EditableRegionScanner;
use App\Support\SectionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Reports — and, where it is mechanical, wraps — storefront content nobody owns.
 *
 * Cutting a theme into Blade means deciding, element by element, which content
 * an admin may rewrite later. Nothing enforces that decision, so the regions
 * someone forgot are invisible until an editor goes looking for them on a live
 * site. This command is the pass that finds them: every visible text run and
 * every image source in `views/client` must end up editable, dynamic, or
 * ignored with a stated reason. Anything else fails the run.
 *
 * `--fix` wraps only what EditableRegionScanner marks `fixable` — text-level
 * tags holding a plain sentence, and plain `<img>` tags. The rest is reported
 * with the reason it cannot be wrapped mechanically, because a fixer that
 * guesses at a CTA, a background or a lazy image produces a new bug instead of
 * removing one.
 *
 * It also checks the section wiring, because the failure there is quieter
 * still: `section-list` includes its partials with `@includeIf`, so a section
 * whose Blade file is missing renders nothing at all and reports nothing.
 */
class AuditEditableRegions extends Command
{
    protected $signature = 'client:editable-audit
        {--fix : Wrap what can be wrapped safely and rewrite the files}';

    protected $description = 'Classify every piece of storefront content as editable, dynamic or ignored, and fail on anything left over';

    /** What a non-fixable finding needs from a human, by scanner reason. */
    private const ADVICE = [
        'manual' => 'bọc tay bằng <x-client::editable key="%key%" tag="%tag%">…</x-client::editable>, giữ nguyên class/href',
        'conditional' => 'chữ tĩnh nằm trong nhánh @if/@foreach — bọc riêng chữ của từng nhánh',
        'slot' => 'chữ truyền vào slot của component — đánh dấu bên trong component hoặc truyền vùng editable vào slot',
        'mixed' => 'chữ nằm lẫn với khối con của <%container%> — tách đoạn chữ vào một thẻ riêng rồi đánh dấu thẻ đó',
        'lazy' => 'ảnh lazy-load (data-src): editor chỉ thay src, script lazy sẽ ghi đè lại — editor chưa hỗ trợ',
        'srcset' => 'trình duyệt chọn ảnh theo srcset, thay src không có tác dụng — editor chưa hỗ trợ',
        'picture' => 'ảnh trong <picture>: <source> thắng src — editor chưa hỗ trợ',
        'background' => 'ảnh nền CSS inline — editor chưa hỗ trợ thay ảnh nền',
        'data-background' => 'ảnh nền qua data-bg — editor chưa hỗ trợ thay ảnh nền',
        'poster' => 'poster của <video> — editor chưa hỗ trợ',
        'image-manual' => 'bọc tay bằng editable-image (alt đang lấy từ biến, hoặc src không đọc được)',
    ];

    public function handle(EditableRegionScanner $scanner, SectionRegistry $registry): int
    {
        $root = resource_path('views/client');

        if (! is_dir($root)) {
            $this->components->warn('No storefront views at resources/views/client — nothing to audit.');

            return self::SUCCESS;
        }

        $audit = $this->auditViews($scanner, $root);
        $sectionErrors = array_merge($this->auditSections($registry), $this->auditMenuTargets());

        if ($this->option('fix') && $this->applyFixes($audit['findings'])) {
            // Report what is on disk now, not what was there before the fix:
            // the summary is the thing an agent reads to decide it is done.
            $audit = $this->auditViews($scanner, $root);
        }

        $this->report($audit, $sectionErrors);

        return $this->passes($audit, $sectionErrors) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Every finding, ignore and count across the storefront, grouped by file.
     *
     * @return array{
     *     files: int,
     *     findings: array<string, array<int, array<string, mixed>>>,
     *     ignored: array<string, array<int, array<string, mixed>>>,
     *     invalidIgnores: array<string, array<int, array<string, mixed>>>,
     *     counts: array<string, array<string, int>>,
     * }
     */
    private function auditViews(EditableRegionScanner $scanner, string $root): array
    {
        $result = ['files' => 0, 'findings' => [], 'ignored' => [], 'invalidIgnores' => [], 'counts' => []];

        foreach ($this->bladeFiles($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $audit = $scanner->audit((string) file_get_contents($file->getPathname()), $this->viewKey($relative));
            $result['files']++;

            foreach (['findings', 'ignored', 'invalidIgnores'] as $bucket) {
                if ($audit[$bucket] !== []) {
                    $result[$bucket][$relative] = $audit[$bucket];
                }
            }

            foreach ($audit['counts'] as $kind => $counts) {
                foreach ($counts as $status => $count) {
                    $result['counts'][$kind][$status] = ($result['counts'][$kind][$status] ?? 0) + $count;
                }
            }
        }

        return $result;
    }

    /**
     * Every Blade file the site owns under `views/client`.
     *
     * Nothing is skipped by directory. `partials/` used to be, back when the
     * editor lived there; it has since moved to `views/core`, and what a theme
     * cut puts in `partials/` today is its header, footer and shared sections —
     * the copy most likely to be forgotten. A partial that only renders
     * database rows simply classifies as dynamic.
     *
     * @return iterable<SplFileInfo>
     */
    private function bladeFiles(string $root): iterable
    {
        return Finder::create()
            ->files()
            ->in($root)
            ->name('*.blade.php')
            ->sortByName();
    }

    /**
     * The first segment of every key generated for this file.
     *
     * `catalog/category.blade.php` becomes `catalog.category`, which is the
     * page half of the `<page>.<area>.<box>` convention.
     */
    private function viewKey(string $relative): string
    {
        return str_replace('/', '.', substr($relative, 0, -strlen('.blade.php')));
    }

    /**
     * Section declarations and section partials that disagree.
     *
     * @return array<int, string>
     */
    private function auditSections(SectionRegistry $registry): array
    {
        $errors = [];
        $directory = resource_path('views/client/sections');

        foreach (array_keys($registry->sections()) as $section) {
            if (! is_file($directory.'/'.$section.'.blade.php')) {
                $errors[] = "SectionRegistry khai báo section `{$section}` nhưng không có views/client/sections/{$section}.blade.php";
            }
        }

        foreach ($this->sectionsNamedInTemplates() as $section => $where) {
            if (! is_file($directory.'/'.$section.'.blade.php')) {
                $errors[] = "{$where} gọi section `{$section}` nhưng không có Blade tương ứng — @includeIf sẽ render rỗng, không báo lỗi";
            }
        }

        return $errors;
    }

    /**
     * Menu links typed by hand at a CMS page that does not exist.
     *
     * A `type=page` item cannot point at nothing: the column is a foreign key
     * and the request validates `exists:pages,id`, and a page deleted later
     * renders as plain text rather than a broken link. A `type=url` item has
     * neither protection. Cutting a theme and wiring the nav with hand-typed
     * paths — `/vi/chinh-sach/gioi-thieu` — before the pages exist produces a menu
     * that looks finished and 404s on click, and nothing anywhere reports it.
     *
     * @return array<int, string>
     */
    private function auditMenuTargets(): array
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasTable('pages')) {
            return [];
        }

        $slugs = app(LocalizedSlugService::class);
        $errors = [];

        $items = MenuItem::query()
            ->where('type', MenuItem::TYPE_URL)
            ->whereNotNull('url')
            ->get(['id', 'menu_id', 'label', 'url']);

        foreach ($items as $item) {
            $path = parse_url((string) $item->url, PHP_URL_PATH);

            if (! is_string($path)) {
                continue;
            }

            if (! preg_match('~^/(?:([a-z]{2}(?:-[a-zA-Z]{2,4})?)/)?(?:chinh-sach|pages)/([^/]+)/?$~', $path, $match)) {
                continue;
            }

            $locale = $match[1] !== '' ? $match[1] : app()->getLocale();

            // Resolved the same way the storefront resolves it, localized slugs
            // included — reimplementing the lookup here is how an audit starts
            // disagreeing with the router it is supposed to be checking.
            if ($slugs->find(Page::class, $match[2], $locale) !== null) {
                continue;
            }

            $errors[] = sprintf(
                'menu item #%d (%s) trỏ tới %s nhưng không có trang chính sách nào mang slug `%s` — link này 404',
                $item->id,
                $this->menuItemLabel($item),
                $item->url,
                $match[2],
            );
        }

        return $errors;
    }

    private function menuItemLabel(MenuItem $item): string
    {
        $label = $item->getTranslation('label', app()->getLocale(), false)
            ?: (string) collect($item->getTranslations('label'))->first();

        return $label !== '' ? $label : 'không nhãn';
    }

    /**
     * Section names written literally into a `section-list` call.
     *
     * Only literal arrays are read. A `:sections="$fromController"` is a
     * runtime value and this command has no business guessing at it — the
     * component drops unknown names anyway, so the visible symptom is the same
     * empty area either way.
     *
     * @return array<string, string>
     */
    private function sectionsNamedInTemplates(): array
    {
        $root = resource_path('views/client');
        $named = [];

        if (! is_dir($root)) {
            return $named;
        }

        foreach ($this->bladeFiles($root) as $file) {
            $source = (string) file_get_contents($file->getPathname());

            if (! preg_match_all('/:sections\s*=\s*"\[(.*?)\]"/s', $source, $matches)) {
                continue;
            }

            foreach ($matches[1] as $list) {
                preg_match_all('/[\'"]([a-z0-9][a-z0-9-]*)[\'"]/i', $list, $names);

                foreach ($names[1] as $name) {
                    $named[strtolower($name)] = str_replace('\\', '/', $file->getRelativePathname());
                }
            }
        }

        return $named;
    }

    /**
     * Rewrite each file, wrapping its fixable findings.
     *
     * Splices run back to front so that an earlier finding's offsets are still
     * valid when its turn comes — the scanner reports positions in the file as
     * it was read, and every edit before them would shift the rest.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return bool whether any file was written
     */
    private function applyFixes(array $grouped): bool
    {
        $written = false;

        foreach ($grouped as $relative => $findings) {
            $path = resource_path('views/client/'.$relative);
            $source = (string) file_get_contents($path);
            $wrapped = 0;

            foreach (array_reverse($findings) as $finding) {
                $replacement = $finding['fixable'] ? $this->wrap($finding) : null;

                if ($replacement === null) {
                    continue;
                }

                $source = substr($source, 0, $finding['start'])
                    .$replacement
                    .substr($source, $finding['end']);
                $wrapped++;
            }

            if ($wrapped === 0) {
                continue;
            }

            file_put_contents($path, $source);
            $written = true;
            $this->components->twoColumnDetail($relative, $wrapped.' vùng đã bọc');
        }

        if ($written) {
            $this->components->info('Đã ghi. Xem lại bằng `git diff` trước khi commit.');
        }

        return $written;
    }

    /**
     * The component call that replaces one finding, or null to leave it alone.
     *
     * @param  array<string, mixed>  $finding
     */
    private function wrap(array $finding): ?string
    {
        return $finding['kind'] === 'image'
            ? $this->wrapImage($finding)
            : $this->wrapText($finding);
    }

    /** @param  array<string, mixed>  $finding */
    private function wrapText(array $finding): string
    {
        return sprintf(
            '<x-client::editable key="%s" tag="%s"%s>%s</x-client::editable>',
            $finding['key'],
            $finding['tag'],
            $finding['attrs'],
            $finding['inner'],
        );
    }

    /**
     * `<img>` becomes `<x-client::editable-image>`, which owns src and alt.
     *
     * Both are props on the component rather than passthrough attributes, so
     * they are lifted out of the attribute string and everything else rides
     * along untouched. The scanner only marks an image fixable when this can
     * be done without guessing; the checks here are the last line, not the
     * decision.
     *
     * @param  array<string, mixed>  $finding
     */
    private function wrapImage(array $finding): ?string
    {
        $attrs = $finding['attrs'];

        if (! preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/is', $attrs, $src)) {
            return null;
        }

        $alt = '';

        if (preg_match('/\salt\s*=\s*(["\'])(.*?)\1/is', $attrs, $altMatch)) {
            if (str_contains($altMatch[2], '{{') || str_contains($altMatch[2], '{!!')) {
                return null;
            }

            $alt = $altMatch[2];
            $attrs = str_replace($altMatch[0], '', $attrs);
        }

        $attrs = rtrim(str_replace($src[0], '', $attrs));

        return sprintf(
            '<x-client::editable-image key="%s" src="%s" alt="%s"%s />',
            $finding['key'],
            $src[2],
            $alt,
            $attrs,
        );
    }

    /**
     * @param  array<string, mixed>  $audit
     * @param  array<int, string>  $sectionErrors
     */
    private function passes(array $audit, array $sectionErrors): bool
    {
        return $audit['findings'] === [] && $audit['invalidIgnores'] === [] && $sectionErrors === [];
    }

    /**
     * Details first, then the summary — the summary is what has to be on screen
     * when the output is cut off.
     *
     * @param  array<string, mixed>  $audit
     * @param  array<int, string>  $sectionErrors
     */
    private function report(array $audit, array $sectionErrors): void
    {
        foreach ($sectionErrors as $error) {
            $this->components->error($error);
        }

        $this->reportFindings($audit['findings']);
        $this->reportInvalidIgnores($audit['invalidIgnores']);
        $this->reportIgnored($audit['ignored']);
        $this->reportSummary($audit, $sectionErrors);
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $grouped */
    private function reportFindings(array $grouped): void
    {
        $fixable = 0;

        foreach ($grouped as $relative => $findings) {
            $this->newLine();
            $this->line("  <fg=yellow>{$relative}</>");

            foreach ($findings as $finding) {
                $fixable += $finding['fixable'] ? 1 : 0;

                $this->line(sprintf(
                    '    <fg=gray>%s:%d</> %s <fg=cyan>%s</>%s<fg=gray>%s</>',
                    $finding['tag'],
                    $finding['line'],
                    $this->badge($finding),
                    $finding['key'],
                    str_repeat(' ', max(1, 34 - strlen($finding['key']))),
                    $finding['preview'],
                ));

                if (! $finding['fixable']) {
                    $this->line('      <fg=gray>→ '.$this->advice($finding).'</>');
                }
            }
        }

        if ($grouped !== []) {
            $this->newLine();

            if ($fixable > 0) {
                $this->line("  Bọc tự động {$fixable} vùng [--fix]: <fg=cyan>php artisan client:editable-audit --fix</>");
            }

            $this->line('  Cố ý để tĩnh: <fg=cyan>'.EditableRegionScanner::IGNORE_ATTRIBUTE.'="lý do"</> trên phần tử — bắt buộc có lý do.');
        }
    }

    /** @param  array<string, mixed>  $finding */
    private function badge(array $finding): string
    {
        return match (true) {
            $finding['status'] === EditableRegionScanner::STATUS_UNSUPPORTED => '<fg=magenta>[chưa hỗ trợ]</>',
            $finding['fixable'] => '<fg=green>[--fix]</>',
            default => '<fg=red>[sửa tay]</>',
        };
    }

    /** @param  array<string, mixed>  $finding */
    private function advice(array $finding): string
    {
        $reason = $finding['kind'] === 'image' && $finding['reason'] === 'manual' ? 'image-manual' : $finding['reason'];

        return strtr(self::ADVICE[$reason] ?? 'sửa tay', [
            '%key%' => $finding['key'],
            '%tag%' => $finding['tag'],
            '%container%' => $finding['container'] ?? 'tài liệu',
        ]);
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $grouped */
    private function reportInvalidIgnores(array $grouped): void
    {
        if ($grouped === []) {
            return;
        }

        $this->newLine();
        $this->line('  <fg=red>'.EditableRegionScanner::IGNORE_ATTRIBUTE.' thiếu lý do</> — nội dung bên trong vẫn được kiểm như chưa đánh dấu:');

        foreach ($grouped as $relative => $entries) {
            foreach ($entries as $entry) {
                $this->line(sprintf('    <fg=gray>%s:%d</> <%s> %s', $relative, $entry['line'], $entry['tag'], $entry['preview']));
            }
        }
    }

    /**
     * Every honoured opt-out, with its reason.
     *
     * Listed on every run, pass or fail. An ignore is a decision, and the only
     * thing that stops it from becoming the easy way to turn this audit green
     * is that each one stays in front of whoever reads the output.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     */
    private function reportIgnored(array $grouped): void
    {
        if ($grouped === []) {
            return;
        }

        $this->newLine();
        $this->line('  Vùng cố ý để tĩnh:');

        foreach ($grouped as $relative => $entries) {
            foreach ($entries as $entry) {
                $this->line(sprintf(
                    '    <fg=gray>%s:%d</> <%s> <fg=cyan>"%s"</>%s',
                    $relative,
                    $entry['line'],
                    $entry['tag'],
                    $entry['reason'],
                    $entry['preview'] !== '' ? ' <fg=gray>'.$entry['preview'].'</>' : '',
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $audit
     * @param  array<int, string>  $sectionErrors
     */
    private function reportSummary(array $audit, array $sectionErrors): void
    {
        $counts = $audit['counts'];
        $invalid = array_sum(array_map('count', $audit['invalidIgnores']));

        $this->newLine();
        $this->line('  <options=bold>EDITABLE AUDIT</>');
        $this->newLine();
        $this->summaryRow('Files scanned', $audit['files'], false, 0);

        $this->line('  Text');
        $this->summaryRow('editable', $counts['text']['editable'] ?? 0);
        $this->summaryRow('dynamic', $counts['text']['dynamic'] ?? 0);
        $this->summaryRow('ignored', $counts['text']['ignored'] ?? 0);
        $this->summaryRow('unknown', $counts['text']['unknown'] ?? 0, true);

        $this->line('  Images');
        $this->summaryRow('editable', $counts['image']['editable'] ?? 0);
        $this->summaryRow('dynamic', $counts['image']['dynamic'] ?? 0);
        $this->summaryRow('ignored', $counts['image']['ignored'] ?? 0);
        $this->summaryRow('unsupported', $counts['image']['unsupported'] ?? 0, true);
        $this->summaryRow('unknown', $counts['image']['unknown'] ?? 0, true);

        $this->summaryRow('Ignored without reason', $invalid, true, 0);
        $this->summaryRow('Section/menu wiring errors', count($sectionErrors), true, 0);

        $this->newLine();
        $this->line($this->passes($audit, $sectionErrors)
            ? '  <fg=green;options=bold>RESULT: PASS</>'
            : '  <fg=red;options=bold>RESULT: FAIL</>');
        $this->newLine();
    }

    private function summaryRow(string $label, int $count, bool $gate = false, int $indent = 2): void
    {
        $verdict = ! $gate ? '' : ($count === 0 ? '  <fg=green>PASS</>' : '  <fg=red>FAIL</>');
        $label = str_repeat(' ', $indent).$label.' ';

        $this->line(sprintf('  %s%s %5d%s', $label, str_repeat('.', max(2, 30 - mb_strlen($label))), $count, $verdict));
    }
}

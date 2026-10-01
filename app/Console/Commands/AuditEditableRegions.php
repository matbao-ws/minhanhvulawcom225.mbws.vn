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
 * Reports — and optionally wraps — storefront copy nobody marked editable.
 *
 * Cutting a theme into Blade means deciding, element by element, which copy an
 * admin may rewrite later. Nothing enforces that decision, so the regions
 * someone forgot are invisible until an editor goes looking for them on a live
 * site. This command is the pass that finds them, and `--fix` is the pass that
 * wraps them.
 *
 * It also checks the section wiring, because the failure there is quieter
 * still: `section-list` includes its partials with `@includeIf`, so a section
 * whose Blade file is missing renders nothing at all and reports nothing.
 */
class AuditEditableRegions extends Command
{
    protected $signature = 'client:editable-audit
        {--fix : Wrap what can be wrapped safely and rewrite the files}';

    protected $description = 'Find storefront copy that is not marked editable, and section wiring that points nowhere';

    /**
     * Directories under `views/client` that are never scanned.
     *
     * `partials` is the editor itself plus the blocks that render database
     * rows; `components` is the marking mechanism. Marking either would be
     * marking the tool with the tool.
     */
    private const SKIPPED_DIRECTORIES = ['partials', 'components'];

    public function handle(EditableRegionScanner $scanner, SectionRegistry $registry): int
    {
        $root = resource_path('views/client');

        if (! is_dir($root)) {
            $this->components->warn('No storefront views at resources/views/client — nothing to audit.');

            return self::SUCCESS;
        }

        $unmarked = $this->auditRegions($scanner, $root);
        $sectionErrors = array_merge($this->auditSections($registry), $this->auditMenuTargets());

        if ($this->option('fix') && $unmarked !== []) {
            $unmarked = $this->applyFixes($unmarked);
        }

        $this->report($unmarked, $sectionErrors);

        return ($unmarked === [] && $sectionErrors === []) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Every unmarked region across the storefront, grouped by file.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function auditRegions(EditableRegionScanner $scanner, string $root): array
    {
        $grouped = [];

        foreach ($this->bladeFiles($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $source = (string) file_get_contents($file->getPathname());
            $findings = $scanner->scan($source, $this->viewKey($relative));

            if ($findings !== []) {
                $grouped[$relative] = $findings;
            }
        }

        ksort($grouped);

        return $grouped;
    }

    /** @return iterable<SplFileInfo> */
    private function bladeFiles(string $root): iterable
    {
        return Finder::create()
            ->files()
            ->in($root)
            ->name('*.blade.php')
            ->exclude(self::SKIPPED_DIRECTORIES)
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
            if (! is_file($directory . '/' . $section . '.blade.php')) {
                $errors[] = "SectionRegistry khai báo section `{$section}` nhưng không có views/client/sections/{$section}.blade.php";
            }
        }

        foreach ($this->sectionsNamedInTemplates() as $section => $where) {
            if (! is_file($directory . '/' . $section . '.blade.php')) {
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
     * Rewrite each file, wrapping its findings.
     *
     * Splices run back to front so that an earlier finding's offsets are still
     * valid when its turn comes — the scanner reports positions in the file as
     * it was read, and every edit before them would shift the rest.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function applyFixes(array $grouped): array
    {
        $remaining = [];

        foreach ($grouped as $relative => $findings) {
            $path = resource_path('views/client/' . $relative);
            $source = (string) file_get_contents($path);
            $skipped = [];

            foreach (array_reverse($findings) as $finding) {
                $replacement = $this->wrap($finding);

                if ($replacement === null) {
                    $skipped[] = $finding;

                    continue;
                }

                $source = substr($source, 0, $finding['start'])
                    . $replacement
                    . substr($source, $finding['end']);
            }

            file_put_contents($path, $source);
            $this->components->twoColumnDetail(
                $relative,
                (count($findings) - count($skipped)) . ' vùng đã bọc',
            );

            if ($skipped !== []) {
                $remaining[$relative] = array_reverse($skipped);
            }
        }

        if ($remaining === []) {
            $this->newLine();
            $this->components->info('Đã ghi. Xem lại bằng `git diff` trước khi commit.');
        }

        return $remaining;
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
     * along untouched. An `alt` carrying a Blade echo is left for a human: the
     * component renders alt as text, and quietly turning a translated string
     * into a literal is the kind of change that never gets noticed.
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
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @param  array<int, string>  $sectionErrors
     */
    private function report(array $grouped, array $sectionErrors): void
    {
        foreach ($sectionErrors as $error) {
            $this->components->error($error);
        }

        if ($grouped === []) {
            $this->newLine();
            $this->components->info('Không còn vùng nội dung tĩnh nào chưa đánh dấu.');

            return;
        }

        $total = 0;

        foreach ($grouped as $relative => $findings) {
            $this->newLine();
            $this->line("  <fg=yellow>{$relative}</>");

            foreach ($findings as $finding) {
                $total++;
                $this->line(sprintf(
                    '    <fg=gray>%s:%d</> <fg=cyan>%s</> %s  <fg=gray>%s</>',
                    $finding['kind'] === 'image' ? 'img' : $finding['tag'],
                    $finding['line'],
                    $finding['key'],
                    str_repeat(' ', max(1, 34 - strlen($finding['key']))),
                    $finding['preview'],
                ));
            }
        }

        $this->newLine();
        $this->components->warn($total . ' vùng chưa đánh dấu.');
        $this->line('  Bọc tự động: <fg=cyan>php artisan client:editable-audit --fix</>');
        $this->line('  Cố ý để tĩnh: thêm <fg=cyan>' . EditableRegionScanner::IGNORE_ATTRIBUTE . '</> lên phần tử.');
        $this->newLine();
    }
}
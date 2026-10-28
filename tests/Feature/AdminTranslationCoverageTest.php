<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The admin is offered in every language of config('multilingual.admin_locales'). Text written
 * straight into an admin view or controller shows in Vietnamese to an English admin, and a key
 * missing from one lang/ file prints as "admin.orders.print". Both are checked on the source.
 */
class AdminTranslationCoverageTest extends TestCase
{
    /** Vietnamese letters that no other admin language uses. */
    private const VIETNAMESE = '/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu';

    /** "12.500.000 đ": the currency sign stays, whatever the admin language. */
    private const CURRENCY_SIGN = '/(?<!\pL)đ(?!\pL)/u';

    private const VIEW_DIRS = ['resources/views/admin', 'resources/views/components/admin'];

    private const PHP_DIRS = ['app/Http/Controllers/Admin', 'app/Http/Requests/Admin'];

    public function test_every_admin_lang_file_has_the_same_keys_in_each_admin_locale(): void
    {
        $locales = (array) config('multilingual.admin_locales');
        $groups = collect($locales)
            ->flatMap(fn (string $locale) => glob(lang_path($locale.'/*.php')) ?: [])
            ->map(fn (string $path) => basename($path, '.php'))
            ->unique();

        $problems = [];
        foreach ($groups as $group) {
            $keys = collect($locales)->mapWithKeys(fn (string $locale) => [$locale => $this->keys($locale, $group)]);
            foreach ($keys as $locale => $own) {
                foreach ($keys as $other => $theirs) {
                    foreach (array_diff($theirs, $own) as $missing) {
                        $problems[] = "lang/{$locale}/{$group}.php lacks {$missing} (present in {$other})";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($problems)));
    }

    public function test_every_literal_translation_key_used_by_the_admin_exists(): void
    {
        $locales = (array) config('multilingual.admin_locales');
        $problems = [];

        foreach ($this->files([...self::VIEW_DIRS, ...self::PHP_DIRS]) as $relative => $source) {
            preg_match_all('/(?:__|trans|trans_choice|@lang)\(\s*([\'"])([a-z_]+)\.([A-Za-z0-9_.\-]+)\1/', $source, $matches, PREG_SET_ORDER);
            foreach ($matches as [, , $group, $key]) {
                // 'admin.analytics.range_'.$range: the rest of the key is built at runtime.
                if (! is_file(lang_path($locales[0].'/'.$group.'.php')) || str_ends_with($key, '_') || str_ends_with($key, '.')) {
                    continue;
                }
                foreach ($locales as $locale) {
                    if (! $this->hasKey($locale, $group, $key)) {
                        $problems[] = "{$relative}: {$group}.{$key} is missing in lang/{$locale}";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($problems)));
    }

    public function test_admin_views_and_controllers_do_not_hardcode_vietnamese_text(): void
    {
        $problems = [];

        foreach ($this->files([...self::VIEW_DIRS, ...self::PHP_DIRS]) as $relative => $source) {
            $code = str_ends_with($relative, '.blade.php') ? $this->withoutBladeComments($source) : $this->withoutPhpComments($source);

            foreach (explode("\n", $code) as $index => $line) {
                if (preg_match(self::VIETNAMESE, (string) preg_replace(self::CURRENCY_SIGN, '', $line))) {
                    $problems[] = $relative.':'.($index + 1).': '.mb_strimwidth(trim($line), 0, 120, '…');
                }
            }
        }

        $this->assertSame([], $problems, 'Move the text into lang/{vi,en} and print it with __().');
    }

    /** @return array<string, string> relative path => source */
    private function files(array $dirs): array
    {
        $dirs = array_values(array_filter(array_map('base_path', $dirs), 'is_dir'));
        $files = [];

        foreach (Finder::create()->files()->in($dirs)->name('*.php')->sortByName() as $file) {
            $files[str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1))] = $file->getContents();
        }

        return $files;
    }

    /** Comments are blanked, not removed, so line numbers still point at the source. */
    private function withoutBladeComments(string $source): string
    {
        $source = (string) preg_replace_callback('/\{\{--.*?--\}\}|<!--.*?-->/s', fn (array $m) => str_repeat("\n", substr_count($m[0], "\n")), $source);

        return (string) preg_replace('#^\s*(//|/?\*).*$#m', '', $source);
    }

    private function withoutPhpComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            $code .= is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
                ? str_repeat("\n", substr_count($token[1], "\n"))
                : (is_array($token) ? $token[1] : $token);
        }

        return $code;
    }

    /** @var array<string, list<string>> */
    private array $keyCache = [];

    /** @return list<string> */
    private function keys(string $locale, string $group): array
    {
        $path = lang_path($locale.'/'.$group.'.php');

        return $this->keyCache[$path] ??= is_file($path) ? array_keys($this->flatten(require $path)) : [];
    }

    private function hasKey(string $locale, string $group, string $key): bool
    {
        foreach ($this->keys($locale, $group) as $existing) {
            // A key naming a whole section (__('admin.ajax') for a JS dictionary) is fine too.
            if ($existing === $key || str_starts_with($existing, $key.'.')) {
                return true;
            }
        }

        return false;
    }

    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $flat += is_array($value) ? $this->flatten($value, $name) : [$name => $value];
        }

        return $flat;
    }
}

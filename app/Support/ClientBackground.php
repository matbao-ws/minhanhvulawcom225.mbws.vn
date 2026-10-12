<?php

namespace App\Support;

use App\Services\SiteContentService;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * An editable CSS background, written on the theme's own element:
 *
 *     <section class="hero" @clientBackground('home.hero.bg', "background: linear-gradient(…), url('".asset('img/a.jpg')."') center/cover")>
 *
 * The directive receives the element's original style, unchanged, and prints it back with only
 * the picture inside its one content `url()` replaced by the image the shop chose. Gradients,
 * position, size, repeat and every other declaration stay byte for byte, so the theme's look and
 * its responsive behaviour do not move. No wrapper element, no new class.
 *
 * Lazy backgrounds keep their loader: `@clientBackground('k', '', ['lazy' => 'data-bg', 'src' => asset('…')])`
 * prints `data-bg="<picture>"` for visitors. An admin in edit mode gets the picture as an inline
 * style instead, so the change is visible at once.
 *
 * Editors also get `data-client-background="<key>"` — deliberately not `data-block-key`. Every
 * editor script finds text and image regions by `data-block-key`; a section carrying it would
 * swallow every click on its children. The background has its own handle instead.
 */
final class ClientBackground
{
    /** Where the region key goes for the editor, and where the current picture is mirrored. */
    public const KEY_ATTRIBUTE = 'data-client-background';

    public const SRC_ATTRIBUTE = 'data-client-background-src';

    public function __construct(private readonly SiteContentService $site) {}

    /**
     * @param  array{lazy?: string, src?: string}  $options
     */
    public function attributes(string $key, string $style, array $options = []): HtmlString
    {
        $lazy = $options['lazy'] ?? null;
        $editing = ClientEditorChrome::editorActive() && auth()->user()?->can('media.view');
        $stored = $this->site->value($key);
        $stored = $stored !== null && self::isSafeUrl($stored) ? $stored : null;

        if ($lazy !== null) {
            $this->assertLazyAttribute($lazy);
            $default = (string) ($options['src'] ?? '');
            $picture = $stored ?? $default;
            $attributes = [];

            if ($editing) {
                // Seen at once in edit mode; the lazy loader would only apply it on scroll.
                $attributes['style'] = trim($style.($style !== '' && ! str_ends_with(trim($style), ';') ? '; ' : '').'background-image: url('.self::cssUrl($picture).')');
            } else {
                $attributes[$lazy] = $picture;
                if ($style !== '') {
                    $attributes['style'] = $style;
                }
            }

            return $this->render($attributes, $editing ? [$key, $picture] : null);
        }

        [$start, $length, $default] = self::locateContentUrl($style);
        $picture = $stored ?? $default;
        $printed = $stored === null ? $style : substr_replace($style, self::cssUrl($stored), $start, $length);

        return $this->render(['style' => $printed], $editing ? [$key, $picture] : null);
    }

    /**
     * Offset and length of the inside of the one content `url( … )` in a style, and the picture it
     * names. Exactly one, on `background` or `background-image`: anything else is a theme the
     * directive cannot edit safely, and failing loudly here is what makes it fixed by hand.
     *
     * @return array{0: int, 1: int, 2: string}
     */
    public static function locateContentUrl(string $style): array
    {
        $found = [];

        if (preg_match_all('/(?<=^|;|\s)(background(?:-image)?)\s*:([^;]*)/i', $style, $declarations, PREG_OFFSET_CAPTURE)) {
            foreach ($declarations[2] as [$value, $valueOffset]) {
                if (preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/is', $value, $urls, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                    foreach ($urls as $url) {
                        $picture = trim($url[2][0]);
                        if ($picture === '' || str_starts_with($picture, '#') || preg_match('/\.(?:woff2?|ttf|otf|eot)(?:[?#].*)?$/i', $picture)) {
                            continue;
                        }
                        // The whole url( … ) argument, quotes included: it is rewritten as one token.
                        $argumentStart = $valueOffset + $url[0][1] + strpos($url[0][0], '(') + 1;
                        $argumentEnd = $valueOffset + $url[0][1] + strrpos($url[0][0], ')');
                        $found[] = [$argumentStart, $argumentEnd - $argumentStart, $picture];
                    }
                }
            }
        }

        if (count($found) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '@clientBackground needs exactly one picture url() in background/background-image, found %d in: %s',
                count($found),
                $style,
            ));
        }

        return $found[0];
    }

    /**
     * Whether a stored picture may go into `url()`: a site path or an http(s) URL, with none of the
     * characters that could close the url() or the declaration. The request validates the same
     * rule; this second check covers rows written before it existed or restored from history.
     */
    public static function isSafeUrl(string $url): bool
    {
        return preg_match('~\A(?:/(?!/)|https?://[A-Za-z0-9.-]+(?::\d+)?/)[^\s\'"()\\\\<>;{}\x00-\x1f\x7f]*\z~', $url) === 1;
    }

    /** A picture written as a CSS url() argument: quoted, with anything risky percent-encoded. */
    private static function cssUrl(string $url): string
    {
        return "'".strtr($url, ["'" => '%27', '"' => '%22', '(' => '%28', ')' => '%29', '\\' => '%5C', ' ' => '%20', "\n" => '', "\r" => ''])."'";
    }

    private function assertLazyAttribute(string $name): void
    {
        if (! preg_match('/^data-[a-z0-9-]+$/', $name)) {
            throw new InvalidArgumentException("@clientBackground lazy attribute must be a data-* name, got: {$name}");
        }
    }

    /**
     * @param  array<string, string>  $attributes
     * @param  array{0: string, 1: string}|null  $editor  the key and current picture, for an admin in edit mode
     */
    private function render(array $attributes, ?array $editor): HtmlString
    {
        if ($editor !== null) {
            $attributes[self::KEY_ATTRIBUTE] = $editor[0];
            $attributes[self::SRC_ATTRIBUTE] = $editor[1];
        }

        $html = [];
        foreach ($attributes as $name => $value) {
            $html[] = $name.'="'.e($value).'"';
        }

        return new HtmlString(implode(' ', $html));
    }
}

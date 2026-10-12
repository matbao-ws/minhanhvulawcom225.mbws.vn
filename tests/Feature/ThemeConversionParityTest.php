<?php

namespace Tests\Feature;

use App\Models\SiteBlock;
use App\Services\SiteContentService;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * A theme converted to editable regions must be, for a visitor, the same document the theme
 * was: same elements, order, classes, attributes and styles. `before` is the theme as cut;
 * `after` is the same page with @clientBackground and the editable components. Allowed
 * differences: none (attribute order is not part of the DOM).
 *
 * Pixels are compared by tests/Support/visual-parity.php, which needs Chrome and so runs outside CI.
 */
class ThemeConversionParityTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURES = __DIR__.'/../Fixtures/theme-conversion';

    /** Every picture key in the converted fixture, with its type. */
    private const PICTURES = [
        'parity.hero.bg' => SiteBlock::TYPE_BACKGROUND,
        'parity.band.bg' => SiteBlock::TYPE_BACKGROUND,
        'parity.grid.card' => SiteBlock::TYPE_IMAGE,
        'parity.grid.b' => SiteBlock::TYPE_IMAGE,
        'parity.grid.c' => SiteBlock::TYPE_IMAGE,
    ];

    private function render(string $name): string
    {
        app(SiteContentService::class)->forget();

        return Blade::render((string) file_get_contents(self::FIXTURES."/{$name}.blade.php"));
    }

    /**
     * The document as a list of lines, one per element or text node, attributes sorted.
     *
     * @return list<string>
     */
    private function tree(string $html, bool $maskPictures = false): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOBLANKS);
        libxml_clear_errors();

        $lines = [];
        $walk = function (DOMNode $node, int $depth) use (&$walk, &$lines, $maskPictures): void {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $attributes = [];
                    foreach ($child->attributes as $attribute) {
                        $value = preg_replace('/\s+/', ' ', trim($attribute->value));
                        if ($maskPictures && in_array($attribute->name, ['srcset', 'data-srcset'], true)) {
                            // A replaced picture is one candidate where the theme listed several.
                            $value = 'CANDIDATES';
                        } elseif ($maskPictures) {
                            $value = preg_replace('~(?:https?://[^/\s\'"]+)?/(?:img|storage)/[^\s\'"),]+~', 'PICTURE', $value);
                        }
                        $attributes[$attribute->name] = $attribute->name.'="'.$value.'"';
                    }
                    ksort($attributes);
                    $lines[] = str_repeat('  ', $depth).'<'.$child->tagName.($attributes ? ' '.implode(' ', $attributes) : '').'>';
                    $walk($child, $depth + 1);
                } elseif ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '') {
                    $lines[] = str_repeat('  ', $depth).'#'.preg_replace('/\s+/', ' ', trim($child->textContent));
                }
            }
        };
        $walk($document, 0);

        return $lines;
    }

    private function replaceEveryPicture(): void
    {
        foreach (self::PICTURES as $key => $type) {
            app(SiteContentService::class)->updateLocale($key, $type, 'vi', '/storage/media/new.png', null);
        }
    }

    public function test_a_visitor_gets_the_same_document_as_the_theme(): void
    {
        $this->assertSame($this->tree($this->render('before')), $this->tree($this->render('after')));
    }

    public function test_after_replacing_every_picture_only_the_pictures_change(): void
    {
        $this->replaceEveryPicture();
        $after = $this->render('after');

        $this->assertSame($this->tree($this->render('before'), true), $this->tree($after, true));

        // No breakpoint, srcset candidate or lazy attribute can bring a theme picture back.
        foreach (['hero.png', 'card.png', 'card-mobile.png', 'b.png', 'b-2x.png', 'c.png', 'd.png'] as $old) {
            $this->assertStringNotContainsString('/img/'.$old, $after, $old);
        }
        // The theme's lazy placeholder stays, so its loader still has something to swap.
        $this->assertStringContainsString('src="/img/blank.gif"', $after);
    }

    public function test_restoring_every_picture_brings_the_theme_document_back(): void
    {
        $this->replaceEveryPicture();
        foreach (array_keys(self::PICTURES) as $key) {
            app(SiteContentService::class)->restoreLocale($key, 'vi', null);
        }

        $this->assertSame($this->tree($this->render('before')), $this->tree($this->render('after')));
    }
}

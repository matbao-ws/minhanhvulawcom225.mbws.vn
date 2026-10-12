<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\User;
use App\Services\SiteContentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `<picture>`, srcset and lazy images through `x-client::editable-image`: the theme's elements,
 * classes and order are printed as written; a replaced picture takes over every source the
 * browser could choose; a restore brings the theme's markup back exactly.
 */
class EditableResponsiveImageTest extends TestCase
{
    use RefreshDatabase;

    private const PICTURE = <<<'BLADE'
    <x-client::editable-image key="home.hero.photo" src="/img/hero.jpg" alt="Hero" class="hero__img"
        srcset="/img/hero-800.jpg 800w, /img/hero-1600.jpg 1600w" sizes="(min-width: 992px) 50vw, 100vw"
        picture-class="hero__picture"
        :sources="[['media' => '(min-width: 992px)', 'type' => 'image/webp', 'srcset' => '/img/hero.webp'], ['media' => '(max-width: 991px)', 'srcset' => '/img/hero-m.jpg']]" />
    BLADE;

    private const THEME_PICTURE = '<picture class="hero__picture">'
        .'<source media="(min-width: 992px)" type="image/webp" srcset="/img/hero.webp">'
        .'<source media="(max-width: 991px)" srcset="/img/hero-m.jpg">'
        .'<img src="/img/hero.jpg" srcset="/img/hero-800.jpg 800w, /img/hero-1600.jpg 1600w" sizes="(min-width: 992px) 50vw, 100vw" alt="Hero" class="hero__img">'
        .'</picture>';

    private const LAZY = '<x-client::editable-image key="home.hero.photo" src="/img/real.jpg" lazy="data-src" placeholder="/img/blank.gif" srcset="/img/real-2x.jpg 2x" alt="" class="lazyload" />';

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();
    }

    private function render(string $blade): string
    {
        app(SiteContentService::class)->forget();

        return $this->normalize(Blade::render($blade));
    }

    /** Blade's line breaks between attributes and tags, collapsed — the DOM is what is compared. */
    private function normalize(string $html): string
    {
        return trim(preg_replace(['/\s+/', '/>\s+</', '/\s+>/'], [' ', '><', '>'], $html));
    }

    private function replace(string $value = '/storage/media/new.jpg'): void
    {
        app(SiteContentService::class)->updateLocale('home.hero.photo', SiteBlock::TYPE_IMAGE, 'vi', $value, null);
    }

    private function actingAsEditor(): void
    {
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['pages.update', 'media.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    public function test_an_untouched_picture_is_the_theme_markup(): void
    {
        $this->assertSame(self::THEME_PICTURE, $this->render(self::PICTURE));
    }

    public function test_a_replaced_picture_takes_every_source_and_keeps_every_element(): void
    {
        $this->replace();
        $new = url('/storage/media/new.jpg');

        $html = $this->render(self::PICTURE);

        // Same elements, same classes, same order, same media/type/sizes; every candidate is the new picture.
        $this->assertSame('<picture class="hero__picture">'
            .'<source media="(min-width: 992px)" type="image/webp" srcset="'.$new.'">'
            .'<source media="(max-width: 991px)" srcset="'.$new.'">'
            .'<img src="'.$new.'" srcset="'.$new.'" sizes="(min-width: 992px) 50vw, 100vw" alt="Hero" class="hero__img">'
            .'</picture>', $html);
        $this->assertStringNotContainsString('/img/hero', $html);
    }

    public function test_restoring_brings_back_the_theme_markup_exactly(): void
    {
        $this->replace();
        app(SiteContentService::class)->restoreLocale('home.hero.photo', 'vi', null);

        $this->assertSame(self::THEME_PICTURE, $this->render(self::PICTURE));
    }

    public function test_a_lazy_image_keeps_its_loader_for_visitors(): void
    {
        $this->assertSame('<img src="/img/blank.gif" data-src="/img/real.jpg" data-srcset="/img/real-2x.jpg 2x" alt="" class="lazyload">', $this->render(self::LAZY));

        $this->replace();
        $new = url('/storage/media/new.jpg');
        $this->assertSame('<img src="/img/blank.gif" data-src="'.$new.'" data-srcset="'.$new.'" alt="" class="lazyload">', $this->render(self::LAZY));

        app(SiteContentService::class)->restoreLocale('home.hero.photo', 'vi', null);
        $this->assertSame('<img src="/img/blank.gif" data-src="/img/real.jpg" data-srcset="/img/real-2x.jpg 2x" alt="" class="lazyload">', $this->render(self::LAZY));
    }

    public function test_an_editor_sees_the_real_lazy_picture_at_once(): void
    {
        $this->actingAsEditor();

        $html = $this->render(self::LAZY);

        $this->assertStringContainsString('src="/img/real.jpg" srcset="/img/real-2x.jpg 2x"', $html);
        $this->assertStringNotContainsString('data-src=', $html);
        $this->assertStringContainsString('data-block-key="home.hero.photo"', $html);
    }

    public function test_a_plain_image_is_unchanged_by_the_new_props(): void
    {
        $this->assertSame('<img src="/img/a.jpg" alt="A" class="x">', $this->render('<x-client::editable-image key="home.a" src="/img/a.jpg" alt="A" class="x" />'));
    }

    public function test_a_stored_value_is_escaped_in_every_attribute(): void
    {
        $this->replace('/storage/media/a.jpg?x=1&y=2');

        $html = $this->render(self::PICTURE);

        $this->assertStringContainsString('srcset="'.url('/storage/media/a.jpg').'?x=1&amp;y=2"', $html);
        $this->assertStringNotContainsString('&y=2', $html);
    }

    /**
     * A section with an editable background still lets every child be edited on its own: three
     * separate hooks, and the section itself never becomes a `data-block-key` region.
     */
    public function test_a_background_section_keeps_its_children_independently_editable(): void
    {
        $this->actingAsEditor();

        $html = $this->render(<<<'BLADE'
        <section class="hero" @clientBackground('home.hero.bg', 'background-image: url(/img/bg.jpg)')>
            <x-client::editable key="home.hero.title" tag="h1">Tiêu đề</x-client::editable>
            <x-client::editable-image key="home.hero.photo" src="/img/hero.jpg" alt="" />
        </section>
        BLADE);

        $this->assertMatchesRegularExpression('/<section class="hero" style="[^"]*" data-client-background="home.hero.bg" data-client-background-src="\/img\/bg.jpg">/', $html);
        $this->assertSame(1, substr_count($html, 'data-client-background="'));
        $this->assertSame(2, substr_count($html, 'data-block-key="'));
        $this->assertStringContainsString('data-block-key="home.hero.title"', $html);
        $this->assertStringContainsString('data-block-key="home.hero.photo"', $html);
        $this->assertDoesNotMatchRegularExpression('/<section[^>]*data-block-key/', $html);
    }
}

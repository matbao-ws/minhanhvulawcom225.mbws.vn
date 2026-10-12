<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\User;
use App\Services\SiteContentService;
use App\Support\ClientBackground;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Editable CSS backgrounds: only the picture inside the one content url() changes, nothing else
 * in the theme's style moves, and nothing about the editor reaches a visitor.
 */
class ClientBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private const STYLE = "background: linear-gradient(90deg, rgba(0,0,0,.6), transparent), url('/client-assets/img/hero.jpg') center top / cover no-repeat; min-height: 480px; color: #fff";

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();
    }

    private function actingWith(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Role '.md5(implode(',', $permissions)),
            'permissions' => $permissions,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);

        return $user;
    }

    private function render(string $blade, array $data = []): string
    {
        app(SiteContentService::class)->forget();

        return Blade::render($blade, $data);
    }

    private function hero(): string
    {
        return $this->render('<section class="hero" @clientBackground(\'home.hero.bg\', $style)></section>', ['style' => self::STYLE]);
    }

    private function store(string $value, string $type = SiteBlock::TYPE_BACKGROUND): void
    {
        app(SiteContentService::class)->updateLocale('home.hero.bg', $type, 'vi', $value, null);
    }

    public function test_a_visitor_gets_the_theme_style_unchanged_and_no_editor_hooks(): void
    {
        $this->assertSame('<section class="hero" style="'.e(self::STYLE).'"></section>', $this->hero());
    }

    public function test_a_replaced_picture_changes_only_what_is_inside_url(): void
    {
        $this->store('/storage/media/new.jpg');

        $html = $this->hero();
        preg_match('/style="([^"]*)"/', $html, $match);
        $printed = html_entity_decode($match[1], ENT_QUOTES);

        $expected = str_replace("'/client-assets/img/hero.jpg'", "'".url('/storage/media/new.jpg')."'", self::STYLE);
        $this->assertSame($expected, $printed);
        // Gradient, position/size, repeat and the other declarations survive byte for byte.
        $this->assertStringStartsWith('background: linear-gradient(90deg, rgba(0,0,0,.6), transparent), url(', $printed);
        $this->assertStringEndsWith(') center top / cover no-repeat; min-height: 480px; color: #fff', $printed);
        $this->assertStringNotContainsString('data-client-background', $html);
    }

    public function test_restoring_prints_the_original_style_again(): void
    {
        $this->store('/storage/media/new.jpg');
        app(SiteContentService::class)->restoreLocale('home.hero.bg', 'vi', null);

        $this->assertSame('<section class="hero" style="'.e(self::STYLE).'"></section>', $this->hero());
    }

    public function test_an_admin_with_media_permission_gets_the_background_hook_only(): void
    {
        $this->actingWith(['pages.update', 'media.view']);

        $html = $this->hero();

        $this->assertStringContainsString('data-client-background="home.hero.bg"', $html);
        $this->assertStringContainsString('data-client-background-src="/client-assets/img/hero.jpg"', $html);
        // Never data-block-key: that would make the whole section one region and swallow its children's clicks.
        $this->assertStringNotContainsString('data-block-key', $html);
    }

    public function test_an_admin_without_media_permission_and_a_customer_get_no_hook(): void
    {
        $this->actingWith(['pages.update']);
        $this->assertStringNotContainsString('data-client-background', $this->hero());

        $this->actingAs(User::factory()->create(['role_id' => null]));
        $this->assertSame('<section class="hero" style="'.e(self::STYLE).'"></section>', $this->hero());
    }

    public function test_the_editor_preview_frame_gets_no_hook(): void
    {
        $this->actingWith(['pages.update', 'media.view']);
        request()->query->set('client_chrome', 'off');
        $this->assertStringNotContainsString('data-client-background', $this->hero());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ambiguousStyles(): array
    {
        return [
            'no picture' => ['background: linear-gradient(#000, #fff)'],
            'two pictures' => ['background: url(/a.jpg), url(/b.jpg)'],
            'picture outside background' => ['border-image: url(/a.png) 30; color: red'],
            'font only' => ['background-image: url(/fonts/a.woff2)'],
        ];
    }

    #[DataProvider('ambiguousStyles')]
    public function test_a_style_without_exactly_one_picture_is_refused(string $style): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClientBackground::locateContentUrl($style);
    }

    public function test_a_lazy_background_keeps_the_theme_loader_attribute_for_visitors(): void
    {
        $blade = '<div class="banner" @clientBackground(\'home.hero.bg\', \'\', [\'lazy\' => \'data-bg\', \'src\' => \'/client-assets/img/lazy.jpg\'])></div>';

        $this->assertSame('<div class="banner" data-bg="/client-assets/img/lazy.jpg"></div>', $this->render($blade));

        $this->store('/storage/media/new.jpg');
        $this->assertSame('<div class="banner" data-bg="'.url('/storage/media/new.jpg').'"></div>', $this->render($blade));

        // In edit mode the loader may never run, so the admin sees the picture as a style at once.
        $this->actingWith(['pages.update', 'media.view']);
        $html = $this->render($blade);
        $this->assertStringContainsString('style="background-image: url(&#039;'.url('/storage/media/new.jpg').'&#039;)"', $html);
        $this->assertStringNotContainsString('data-bg=', $html);
    }

    public function test_a_dirty_database_value_never_reaches_the_style(): void
    {
        // Written around the request validation, the way an old row or a restored revision could be.
        DB::table('site_blocks')->insert([
            'key' => 'home.hero.bg',
            'type' => SiteBlock::TYPE_BACKGROUND,
            'content' => json_encode(['vi' => "/x.jpg') ; } body { background: red"]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('<section class="hero" style="'.e(self::STYLE).'"></section>', $this->hero());
    }

    public function test_a_picture_with_special_characters_is_quoted_and_escaped(): void
    {
        $this->store('/storage/media/a&b.jpg');

        $html = $this->hero();

        $this->assertStringContainsString('url(&#039;'.url('/storage/media/a&amp;b.jpg').'&#039;)', $html);
        // Still one tag with one style attribute: nothing in the picture could open another.
        $this->assertMatchesRegularExpression('/\A<section class="hero" style="[^"<>]*"><\/section>\z/', $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafePictures(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data svg' => ['data:image/svg+xml,<svg onload=alert(1)>'],
            'closes the declaration' => ["x') ;} body{"],
            'breaks the attribute' => ['"onmouseover=alert(1)'],
            'protocol relative' => ['//evil.example/a.jpg'],
            'whitespace' => ['/a b.jpg'],
            'parenthesis' => ['/a).jpg'],
        ];
    }

    #[DataProvider('unsafePictures')]
    public function test_an_unsafe_picture_is_rejected_by_the_server(string $value): void
    {
        $this->actingWith(['pages.update', 'media.view']);

        foreach ([SiteBlock::TYPE_BACKGROUND, SiteBlock::TYPE_IMAGE] as $type) {
            $this->patchJson('/vi/admin/site-blocks', [
                'key' => 'home.hero.bg',
                'type' => $type,
                'content_locale' => 'vi',
                'value' => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors('value');
        }

        $this->assertDatabaseCount('site_blocks', 0);
    }

    public function test_a_background_is_stored_as_a_relative_path(): void
    {
        $this->actingWith(['pages.update', 'media.view']);

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'home.hero.bg',
            'type' => SiteBlock::TYPE_BACKGROUND,
            'content_locale' => 'vi',
            'value' => url('/storage/media/new.jpg'),
        ])->assertOk();

        $this->assertSame('/storage/media/new.jpg', SiteBlock::query()->where('key', 'home.hero.bg')->first()->rawTranslations()['vi']);
    }

    public function test_writing_a_background_needs_the_media_permission(): void
    {
        $payload = [
            'key' => 'home.hero.bg',
            'type' => SiteBlock::TYPE_BACKGROUND,
            'content_locale' => 'vi',
            'value' => '/storage/media/new.jpg',
        ];

        $this->patchJson('/vi/admin/site-blocks', $payload)->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role_id' => null]));
        $this->patchJson('/vi/admin/site-blocks', $payload)->assertForbidden();

        $this->actingWith(['pages.update']);
        $this->patchJson('/vi/admin/site-blocks', $payload)->assertForbidden();

        $this->assertDatabaseCount('site_blocks', 0);
    }

    public function test_restoring_a_background_needs_the_media_permission(): void
    {
        $this->store('/storage/media/one.jpg');
        $this->travel(11)->minutes();
        $this->store('/storage/media/two.jpg');
        $revision = SiteBlock::query()->where('key', 'home.hero.bg')->first()->revisions()->first();

        $this->actingWith(['pages.update']);

        $this->deleteJson('/vi/admin/site-blocks', ['key' => 'home.hero.bg', 'content_locale' => 'vi'])->assertForbidden();
        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")->assertForbidden();

        $this->assertSame('/storage/media/two.jpg', SiteBlock::query()->where('key', 'home.hero.bg')->first()->rawTranslations()['vi']);
    }
}

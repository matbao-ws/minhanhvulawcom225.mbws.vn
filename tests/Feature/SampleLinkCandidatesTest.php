<?php

namespace Tests\Feature;

use App\Models\AiSuggestion;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** ai:link-candidates: production candidate sets for the eval, nothing applied to the site. */
class SampleLinkCandidatesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    private int $quotaAfter = PHP_INT_MAX;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);

        Http::fake([
            'gw.test/v1/tasks/content.internal_links' => function (Request $request) {
                if (count($this->sent) >= $this->quotaAfter) {
                    return Http::response(['ok' => false, 'reason' => 'quota_exceeded'], 429);
                }
                $this->sent[] = $request->data();

                return Http::response(['ok' => true, 'suggestion_id' => count($this->sent), 'result' => ['links' => []],
                    'usage' => ['credits_charged' => 1, 'credits_used' => count($this->sent), 'credits_limit' => 100]]);
            },
        ]);

        Post::query()->create(['title' => ['vi' => 'Cách chọn ống'], 'slug' => 'cach-chon-ong', 'content' => ['vi' => '<p>Nội dung ống nhựa.</p>'], 'is_active' => true]);
        Post::query()->create(['title' => ['vi' => 'Bài trống'], 'slug' => 'trong', 'content' => ['vi' => '<p> </p>'], 'is_active' => true]);
        Post::query()->create(['title' => ['vi' => 'Bài ẩn'], 'slug' => 'an', 'content' => ['vi' => '<p>Ẩn.</p>'], 'is_active' => false]);
        Product::query()->create(['name' => ['vi' => 'Ống nhựa uPVC'], 'slug' => 'ong', 'sku' => 'S-1', 'price' => 1, 'is_active' => true,
            'description' => ['vi' => '<p>Ống uPVC.</p>']]);
    }

    public function test_saved_bodies_are_sent_like_the_editor_button_and_nothing_is_applied(): void
    {
        $this->artisan('ai:link-candidates', ['--pause' => 0])->assertSuccessful();

        // Active records with text only, the full saved body as the editor button sends it.
        $this->assertSame([['post', 'Cách chọn ống'], ['product', 'Ống nhựa uPVC']], array_map(fn ($r) => [$r['entity_type'], $r['title']], $this->sent));
        $this->assertSame('<p>Nội dung ống nhựa.</p>', $this->sent[0]['html']);
        // No suggestion is stored on the site and no body changes.
        $this->assertSame(0, AiSuggestion::query()->count());
        $this->assertSame('<p>Nội dung ống nhựa.</p>', Post::query()->where('slug', 'cach-chon-ong')->first()->getTranslation('content', 'vi'));
    }

    public function test_it_stops_when_the_month_runs_out(): void
    {
        $this->quotaAfter = 1;

        $this->artisan('ai:link-candidates', ['--pause' => 0])->expectsOutputToContain('Stopped: quota_exceeded')->assertFailed();
        $this->assertCount(1, $this->sent);
    }
}

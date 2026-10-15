<?php

namespace Tests\Feature;

use App\Models\AiBulkJob;
use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AiGateway\AltTextService;
use App\Services\AiGateway\MetaSuggestionService;
use App\Services\AiGateway\SeoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bulk SEO: one gateway job, results reviewed and applied through the single-suggestion path. */
class AiBulkSeoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    private User $editor;

    /** @var list<Product> */
    private array $products = [];

    /** @var array<string, mixed>|null */
    private ?array $jobRequest = null;

    private string $remoteStatus = 'running';

    private int $jobReads = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'catalog'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token', 'ai_gateway.webhook_secret' => self::SECRET]);

        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['products.view', 'products.update', 'ai.use', 'ai.apply']]);
        $this->editor = User::factory()->create(['role_id' => $role->id]);

        foreach ([1, 2, 3] as $i) {
            $this->products[] = Product::query()->create([
                'name' => ['vi' => 'Ống nhựa '.$i], 'slug' => 'ong-'.$i, 'sku' => 'S-'.$i, 'price' => 1, 'is_active' => true,
                'description' => ['vi' => '<p>Ống nhựa uPVC số '.$i.'.</p>'],
            ]);
        }
        // Already complete: never sent.
        Product::query()->create(['name' => ['vi' => 'Đủ meta'], 'slug' => 'du', 'sku' => 'S-9', 'price' => 1, 'is_active' => true,
            'meta_title' => ['vi' => 'Có'], 'meta_description' => ['vi' => 'Có mô tả']]);

        Http::fake([
            'gw.test/v1/jobs/bulk.meta*' => function (Request $request) {
                if (str_contains($request->url(), 'dry_run=1')) {
                    return Http::response(['ok' => true, 'job_id' => null, 'credits_estimated' => count($request['items']), 'credits_remaining' => 50]);
                }
                $this->jobRequest = $request->data();

                return Http::response(['ok' => true, 'job_id' => 501, 'credits_estimated' => count($request['items']), 'credits_remaining' => 50], 202);
            },
            'gw.test/v1/jobs/501' => function () {
                $this->jobReads++;
                $items = array_map(fn (Product $product, int $i) => [
                    'position' => $i, 'entity_type' => 'product', 'entity_id' => $product->id, 'locale' => 'vi',
                    'status' => 'done', 'suggestion_id' => 900 + $i, 'error_reason' => null,
                    'result' => ['options' => [['title' => 'Ống nhựa uPVC '.$product->id.' bền, dễ lắp', 'description' => 'Ống nhựa uPVC chịu áp tốt cho cấp thoát nước.', 'angle' => 'x']]],
                ], $this->products, array_keys($this->products));

                return Http::response(['ok' => true, 'job' => ['id' => 501, 'status' => $this->remoteStatus, 'stop_reason' => null]
                    + ($this->remoteStatus === 'completed' ? ['items' => $items] : [])]);
            },
            'gw.test/v1/suggestions/*/events' => Http::response(['ok' => true]),
        ]);
    }

    public function test_missing_meta_is_counted_estimated_run_reviewed_and_applied(): void
    {
        $this->actingAs($this->editor)->get('/vi/admin/ai/bulk')->assertOk()->assertSee(__('ai.bulk.types.product').': <strong>3</strong>', false);

        $this->actingAs($this->editor)->postJson('/vi/admin/ai/bulk/estimate', ['kind' => 'meta', 'content_locale' => 'vi', 'types' => ['product']])
            ->assertOk()->assertJsonPath('data.count', 3)->assertJsonPath('data.credits_estimated', 3);

        $this->actingAs($this->editor)->post('/vi/admin/ai/bulk', ['kind' => 'meta', 'content_locale' => 'vi', 'types' => ['product']])->assertRedirect();
        $this->assertCount(3, $this->jobRequest['items']);
        $job = AiBulkJob::query()->sole();

        // Still running: nothing to review yet.
        $this->actingAs($this->editor)->get("/vi/admin/ai/bulk/{$job->id}")->assertOk()->assertSee(__('ai.bulk.running'));

        $this->remoteStatus = 'completed';
        $this->actingAs($this->editor)->get("/vi/admin/ai/bulk/{$job->id}")->assertOk()->assertSee('Ống nhựa uPVC '.$this->products[0]->id.' bền, dễ lắp');
        $this->assertSame(3, AiSuggestion::query()->where('status', AiSuggestion::PROPOSED)->count());

        // Someone edits the second product's meta after the run.
        $this->products[1]->setTranslation('meta_title', 'vi', 'Sửa tay')->save();

        $ids = AiSuggestion::query()->orderBy('id')->pluck('id')->all();
        $this->actingAs($this->editor)->post("/vi/admin/ai/bulk/{$job->id}/apply", [
            'chosen' => [$ids[0], $ids[1]],
            'title' => [$ids[0] => 'Tiêu đề đã sửa', $ids[1] => 'X'],
            'description' => [$ids[0] => 'Mô tả đã sửa cho ống nhựa.', $ids[1] => 'Y'],
        ])->assertRedirect()->assertSessionHas('warning', __('ai.bulk.applied', ['applied' => 1, 'conflicts' => 1, 'closed' => 0]));

        $this->assertSame('Tiêu đề đã sửa', $this->products[0]->fresh()->getTranslation('meta_title', 'vi'));
        $this->assertSame('Sửa tay', $this->products[1]->fresh()->getTranslation('meta_title', 'vi'));
        // Unchosen stays open for later.
        $this->assertSame(AiSuggestion::PROPOSED, AiSuggestion::query()->find($ids[2])->status);
    }

    public function test_a_signed_webhook_imports_the_results_and_a_bad_one_is_refused(): void
    {
        $this->actingAs($this->editor)->post('/vi/admin/ai/bulk', ['kind' => 'meta', 'content_locale' => 'vi', 'types' => ['product']]);
        $this->remoteStatus = 'completed';
        $body = json_encode(['event' => 'job.finished', 'job_id' => 501, 'status' => 'completed']);
        $time = (string) time();

        $this->call('POST', '/api/ai/webhook', [], [], [], $this->headers($time, 'bad-signature'), $body)->assertStatus(401);
        $this->call('POST', '/api/ai/webhook', [], [], [], $this->headers((string) (time() - 600), hash_hmac('sha256', (time() - 600).'.'.$body, self::SECRET)), $body)->assertStatus(401);
        $this->assertSame(0, $this->jobReads);

        $this->call('POST', '/api/ai/webhook', [], [], [], $this->headers($time, hash_hmac('sha256', $time.'.'.$body, self::SECRET)), $body)->assertNoContent();
        $this->assertNotNull(AiBulkJob::query()->sole()->imported_at);
        $this->assertSame(3, AiSuggestion::query()->count());

        config(['ai_gateway.webhook_secret' => null]);
        $this->call('POST', '/api/ai/webhook', [], [], [], $this->headers($time, hash_hmac('sha256', $time.'.'.$body, '')), $body)->assertStatus(401);
    }

    public function test_every_record_type_bulk_can_list_has_a_name_in_every_language(): void
    {
        $types = array_unique([
            ...array_keys(MetaSuggestionService::ENTITIES),
            ...array_keys(SeoFixService::ENTITY_TYPES),
            ...AltTextService::ENTITY_TYPES,
        ]);

        foreach (['vi', 'en'] as $locale) {
            foreach ($types as $type) {
                $this->assertNotSame("ai.bulk.types.$type", __("ai.bulk.types.$type", [], $locale), "$locale: ai.bulk.types.$type");
            }
        }
    }

    public function test_bulk_needs_the_permissions(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::query()->create(['name' => 'V', 'permissions' => ['products.view', 'ai.use']])->id]);

        $this->actingAs($viewer)->get('/vi/admin/ai/bulk')->assertOk()->assertSee(__('ai.bulk.kinds.meta.none'));
        $this->actingAs($viewer)->postJson('/vi/admin/ai/bulk/estimate', ['kind' => 'meta', 'content_locale' => 'vi', 'types' => ['product']])->assertStatus(422);
        $this->assertNull($this->jobRequest);
    }

    private function headers(string $timestamp, string $signature): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_MB_TIMESTAMP' => $timestamp, 'HTTP_X_MB_SIGNATURE' => $signature];
    }
}

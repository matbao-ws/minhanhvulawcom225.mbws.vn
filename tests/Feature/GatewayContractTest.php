<?php

namespace Tests\Feature;

use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\MetaSuggestionService;
use App\Support\CoreManifest;
use App\Support\CoreUpdate\CoreUpdater;
use App\Support\CoreUpdate\CoreUpdateService;
use App\Support\CoreUpdate\ReleaseDownloader;
use App\Support\CoreUpdate\ReleaseVerifier;
use App\Support\ErrorReporting\GatewayErrorReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;
use Tests\Support\FakeArtisan;
use Tests\TestCase;

/**
 * The website's side of the MatBao gateway contract. tests/Fixtures/gateway-contract/v1 is a
 * copy of the gateway's contracts/v1 at the commit in CONTRACT_REF (CI checks the copy is
 * exact). Every request built here comes from the real code path and must follow the
 * gateway's schema. Every response the code reads comes from the gateway's own examples.
 */
class GatewayContractTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'https://gateway.matbaows/contracts/v1/';

    private const GATEWAY = 'https://gateway.example';

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_gateway.url' => self::GATEWAY, 'ai_gateway.token' => 'mbp_contract_token', 'ai_gateway.report_errors' => true]);
        $this->site = sys_get_temp_dir().'/gateway-contract-'.uniqid();
        File::ensureDirectoryExists($this->site);
        File::put($this->site.'/VERSION', '1.4.0-beta.3');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->site);

        parent::tearDown();
    }

    public function test_the_update_check_sends_the_contract_query_and_reads_the_gateways_answers(): void
    {
        $answers = [$this->example('core-update.response'), $this->example('core-update.none')];
        Http::fake([self::GATEWAY.'/v1/core/update*' => function () use (&$answers) {
            return Http::response(array_shift($answers));
        }]);
        $service = $this->updateService(new ReleaseDownloader);

        $offer = $service->offer();
        $this->assertSame('1.4.0', $offer['version']);
        $this->assertSame('https://gateway.example/v1/core/releases/1.4.0', $service->packageUrl($offer['base_url']));
        $this->assertNull($service->offer(), 'update: null means up to date');

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertValid('core-update.query.json', (object) $query, 'GET v1/core/update query');

            return true;
        });
    }

    public function test_a_failed_update_reports_what_the_contract_expects(): void
    {
        $reports = [];
        Http::fake([self::GATEWAY.'/v1/core/report' => function (Request $request) use (&$reports) {
            $reports[] = $request->body();

            return Http::response($this->example('ok'));
        }]);
        $downloader = new class extends ReleaseDownloader
        {
            public function download(string $baseUrl, string $dir, ?string $token = null, ?string $currentLockHash = null): void
            {
                throw new RuntimeException('connection reset');
            }
        };

        $result = $this->updateService($downloader)->run($this->example('core-update.response')['update']);

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $reports);
        $report = json_decode($reports[0]);
        $this->assertSame('failed', $report->event);
        $this->assertValid('core-report.request.json', $report, 'POST v1/core/report');
    }

    public function test_error_reports_follow_the_contract(): void
    {
        $sent = [];
        Http::fake([self::GATEWAY.'/v1/errors' => function (Request $request) use (&$sent) {
            $sent[] = $request->body();

            return Http::response($this->example('ok'));
        }]);
        $reporter = new GatewayErrorReporter(new GatewayClient, new CoreManifest($this->site));

        $reporter->capture(new RuntimeException('Không đọc được tồn kho của khach@example.com'));
        $reporter->captureWithReference(new RuntimeException('extension failed to boot'), 'EXT-20261011101010-AB12');
        $reporter->flush();

        $this->assertCount(2, $sent);
        foreach ($sent as $body) {
            $this->assertValid('errors.request.json', json_decode($body), 'POST v1/errors');
        }
    }

    public function test_an_ai_task_sends_the_contract_request_and_reads_the_gateways_answer(): void
    {
        foreach (['ai_seo', 'catalog'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['products.view', 'products.update', 'ai.use', 'ai.apply']]);
        $editor = User::factory()->create(['role_id' => $role->id]);
        $product = Product::query()->create(['name' => ['vi' => 'Ống nhựa uPVC'], 'slug' => 'ong-nhua', 'sku' => 'UPVC', 'price' => 1000, 'is_active' => true, 'description' => ['vi' => '<p>Ống nhựa uPVC dùng cho cấp thoát nước.</p>']]);
        $answer = $this->example('task.meta.suggest.response');
        Http::fake([self::GATEWAY.'/v1/tasks/meta.suggest' => Http::response($answer)]);

        [$suggestion, $usage] = app(MetaSuggestionService::class)->suggest('product', $product->id, 'vi', $editor);

        $this->assertInstanceOf(AiSuggestion::class, $suggestion);
        $this->assertSame($answer['suggestion_id'], $suggestion->gateway_suggestion_id);
        $this->assertCount(3, $suggestion->options);
        $this->assertSame($answer['usage'], $usage);
        Http::assertSent(function (Request $request) {
            $this->assertValid('task.headers.json', (object) ['Idempotency-Key' => $request->header('Idempotency-Key')[0] ?? null], 'task headers');
            $this->assertValid('task.meta.suggest.request.json', json_decode($request->body()), 'POST v1/tasks/meta.suggest');

            return true;
        });
    }

    public function test_the_client_turns_the_error_envelope_into_its_reason(): void
    {
        Http::fake([self::GATEWAY.'/*' => Http::sequence()
            ->push($this->example('error.invalid_input'), 422)
            ->push($this->example('error.quota_exceeded'), 429)]);

        foreach (['error.invalid_input' => 'invalid_input', 'error.quota_exceeded' => 'quota_exceeded'] as $example => $reason) {
            try {
                (new GatewayClient)->get('v1/core/update', ['version' => '1.4.0-beta.3']);
                $this->fail("{$example} must raise");
            } catch (GatewayUnavailable $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
    }

    public function test_the_job_finished_webhook_is_accepted_only_when_signed_and_fresh(): void
    {
        $secret = 'contract-webhook-secret';
        config(['ai_gateway.webhook_secret' => $secret]);
        $body = json_encode($this->example('webhook.job-finished')['body'], JSON_UNESCAPED_SLASHES);
        $now = (string) time();

        $this->webhook($body, $now, hash_hmac('sha256', $now.'.'.$body, $secret))->assertNoContent();
        $this->webhook($body, $now, str_repeat('0', 64))->assertUnauthorized();
        $old = (string) (time() - 301);
        $this->webhook($body, $old, hash_hmac('sha256', $old.'.'.$body, $secret))->assertUnauthorized();
        $this->webhook($body.' ', $now, hash_hmac('sha256', $now.'.'.$body, $secret))->assertUnauthorized(); // body changed after signing
    }

    public function test_console_commands_are_accepted_as_the_gateway_sends_them_and_answered_per_contract(): void
    {
        $secret = 'contract-webhook-secret';
        config(['ai_gateway.webhook_secret' => $secret]);
        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true]);
        User::factory()->create(['id' => 1, 'role_id' => $role->id]);

        foreach (['list' => 'superadmins', 'reset' => 'id'] as $name => $field) {
            $body = json_encode($this->example("site-command.request.{$name}")['body'], JSON_UNESCAPED_SLASHES);
            $now = (string) time();

            $response = $this->call('POST', '/api/gateway/command', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MB_TIMESTAMP' => $now,
                'HTTP_X_MB_SIGNATURE' => hash_hmac('sha256', $now.'.'.$body, $secret),
            ], content: $body)->assertOk();

            $this->assertNotNull($response->json($field), "{$name} answers {$field}");
            $this->assertValid('site-command.response.json', json_decode((string) $response->getContent()), "POST /api/gateway/command {$name}");
        }

        $refused = $this->call('POST', '/api/gateway/command', server: ['CONTENT_TYPE' => 'application/json'], content: '{}')->assertUnauthorized();
        $this->assertValid('site-command.response.json', json_decode((string) $refused->getContent()), 'unsigned command');
    }

    public function test_the_copy_matches_its_checksums(): void
    {
        $dir = base_path('tests/Fixtures/gateway-contract');
        [$repo, $ref] = file("{$dir}/CONTRACT_REF", FILE_IGNORE_NEW_LINES);
        $this->assertSame('M-t-Bao-WS/MatBaoAI', $repo);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $ref, 'pinned by full commit SHA');

        foreach (file("{$dir}/SHA256SUMS", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$sum, $file] = preg_split('/\s+\*?/', trim($line), 2);
            $this->assertSame($sum, hash_file('sha256', "{$dir}/{$file}"), "{$file} differs from the gateway at {$ref}: copy it again, do not edit it");
        }
    }

    private function updateService(ReleaseDownloader $downloader): CoreUpdateService
    {
        $manifest = new CoreManifest($this->site);
        $updater = new CoreUpdater($manifest, app(ReleaseVerifier::class), new FakeArtisan);

        return new CoreUpdateService(new GatewayClient, $updater, $downloader, $manifest);
    }

    private function webhook(string $body, string $timestamp, string $signature)
    {
        return $this->call('POST', '/api/ai/webhook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MB_TIMESTAMP' => $timestamp,
            'HTTP_X_MB_SIGNATURE' => $signature,
        ], content: $body);
    }

    private function example(string $name): array
    {
        return json_decode((string) file_get_contents(base_path("tests/Fixtures/gateway-contract/v1/examples/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertValid(string $schema, mixed $data, string $what): void
    {
        $validator = new Validator;
        $validator->resolver()->registerPrefix(self::ID, base_path('tests/Fixtures/gateway-contract/v1/schemas'));
        $result = $validator->validate($data, self::ID.$schema);

        $this->assertTrue($result->isValid(), "{$what} breaks the gateway's {$schema}: "
            .json_encode($result->isValid() ? [] : (new ErrorFormatter)->format($result->error()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

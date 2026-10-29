<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Signed commands from the MatBao console: list superadmins, reset one's password. */
class GatewayCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'console-webhook-secret';

    private const PASSWORD = 'Gw!7xQ2mPz9#Lk4v';

    private User $root;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_gateway.webhook_secret' => self::SECRET]);
        $superadmin = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true]);
        $editor = Role::query()->create(['name' => 'Editor', 'permissions' => ['products.view']]);
        $this->root = User::factory()->create(['name' => 'Chủ shop', 'email' => 'owner@shop.test', 'role_id' => $superadmin->id, 'password' => 'Old-password-1!']);
        $this->editor = User::factory()->create(['email' => 'editor@shop.test', 'role_id' => $editor->id]);
        User::factory()->create(['email' => 'customer@shop.test', 'role_id' => null]);
    }

    private function command(array $body, ?string $secret = self::SECRET, ?int $time = null): TestResponse
    {
        $body += ['nonce' => Str::random(40)];
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $time = (string) ($time ?? time());

        return $this->call('POST', '/api/gateway/command', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MB_TIMESTAMP' => $time,
            'HTTP_X_MB_SIGNATURE' => hash_hmac('sha256', $time.'.'.$json, (string) $secret),
        ], content: $json);
    }

    public function test_it_lists_only_superadmins_and_no_password_data(): void
    {
        $response = $this->command(['action' => 'superadmins.list'])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'superadmins')
            ->assertJsonPath('superadmins.0.email', 'owner@shop.test')
            ->assertJsonPath('superadmins.0.name', 'Chủ shop')
            ->assertJsonPath('superadmins.0.role', 'Superadmin')
            ->assertJsonPath('superadmins.0.active', true);

        $this->assertSame(['id', 'name', 'email', 'active', 'role', 'last_login_at'], array_keys($response->json('superadmins.0')));
        $this->assertStringNotContainsString('$2y$', $response->getContent());
    }

    public function test_a_reset_sets_the_password_signs_the_account_out_and_is_logged(): void
    {
        $this->root->createToken('admin', ['admin']);
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $this->root->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
        $remember = $this->root->remember_token;

        $this->command(['action' => 'superadmins.reset_password', 'user_id' => $this->root->id, 'password' => self::PASSWORD])
            ->assertOk()->assertExactJson(['ok' => true, 'id' => $this->root->id]);

        $this->root->refresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $this->root->password));
        $this->assertSame(0, $this->root->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->root->id)->count());
        $this->assertNotSame($remember, $this->root->remember_token);

        $log = AdminActivityLog::query()->where('action', 'superadmin.password_reset_by_gateway')->sole();
        $this->assertSame($this->root->id, (int) $log->subject_id);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode($log->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_only_a_superadmin_can_be_reset(): void
    {
        $before = $this->editor->password;

        $this->command(['action' => 'superadmins.reset_password', 'user_id' => $this->editor->id, 'password' => self::PASSWORD])
            ->assertNotFound()->assertJsonPath('error', 'not_superadmin');

        $this->assertSame($before, $this->editor->refresh()->password);
    }

    public function test_a_weak_password_is_refused(): void
    {
        $before = $this->root->password;

        $this->command(['action' => 'superadmins.reset_password', 'user_id' => $this->root->id, 'password' => 'short'])
            ->assertUnprocessable()->assertJsonPath('error', 'invalid_input');
        $this->command(['action' => 'superadmins.reset_password', 'user_id' => $this->root->id, 'password' => 'alllowercaseletters'])
            ->assertUnprocessable();

        $this->assertSame($before, $this->root->refresh()->password);
    }

    public function test_unsigned_stale_or_unconfigured_requests_are_refused(): void
    {
        $this->command(['action' => 'superadmins.list'], 'wrong-secret')->assertUnauthorized();
        $this->command(['action' => 'superadmins.list'], time: time() - 301)->assertUnauthorized();

        config(['ai_gateway.webhook_secret' => '']);
        $this->command(['action' => 'superadmins.list'], '')->assertUnauthorized();
    }

    public function test_a_captured_request_cannot_be_sent_again(): void
    {
        $body = ['action' => 'superadmins.list', 'nonce' => str_repeat('a', 40)];

        $this->command($body)->assertOk();
        $this->command($body)->assertStatus(409)->assertJsonPath('error', 'replayed');
        $this->command(['action' => 'superadmins.list', 'nonce' => 'short'])->assertUnprocessable();
    }

    public function test_an_unknown_action_does_nothing(): void
    {
        $this->command(['action' => 'users.delete', 'user_id' => $this->root->id])
            ->assertUnprocessable()->assertJsonPath('error', 'unknown_action');

        $this->assertNotNull($this->root->fresh());
    }
}

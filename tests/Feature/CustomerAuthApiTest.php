<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Customer registration and login on /api/public/auth. A registration either succeeds
 * completely (account + token) or leaves nothing behind.
 */
class CustomerAuthApiTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'MatKhau#2026x';

    public function test_registration_creates_the_account_and_a_working_token(): void
    {
        $response = $this->register(['email' => '  Khach.Hang@Example.COM '])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'khach.hang@example.com')
            ->assertJsonMissingPath('data.user.password');

        $user = User::query()->sole();
        $this->assertSame('khach.hang@example.com', $user->email, 'stored trimmed and lowercase');
        $this->assertNull($user->role_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        $this->withToken($response->json('data.token'))->getJson('/api/public/auth/me')
            ->assertOk()->assertJsonPath('data.email', 'khach.hang@example.com');
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidRegistrations(): array
    {
        $long = str_repeat('a', 250).'@example.com';

        return [
            'no name' => [['name' => ''], 'name'],
            'name of one letter' => [['name' => 'A'], 'name'],
            'name with markup' => [['name' => '<script>alert(1)</script>'], 'name'],
            'name with an invisible character' => [['name' => "Khách\u{200B}Hàng"], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'no email' => [['email' => ''], 'email'],
            'not an email' => [['email' => 'khach-at-example.com'], 'email'],
            'email too long' => [['email' => $long], 'email'],
            'email not a string' => [['email' => ['a@example.com']], 'email'],
            'no password' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'password too short' => [['password' => 'Ab1#', 'password_confirmation' => 'Ab1#'], 'password'],
            'password without uppercase' => [['password' => 'matkhau#2026x', 'password_confirmation' => 'matkhau#2026x'], 'password'],
            'password without a digit' => [['password' => 'MatKhau#xxxxx', 'password_confirmation' => 'MatKhau#xxxxx'], 'password'],
            'password without a symbol' => [['password' => 'MatKhau2026xx', 'password_confirmation' => 'MatKhau2026xx'], 'password'],
            'password too long' => [['password' => 'Aa1#'.str_repeat('x', 61), 'password_confirmation' => 'Aa1#'.str_repeat('x', 61)], 'password'],
            'confirmation does not match' => [['password_confirmation' => 'MatKhau#2026y'], 'password'],
            'no confirmation' => [['password_confirmation' => null], 'password'],
            'password is the email' => [['email' => 'Khach#2026Ab@example.com', 'password' => 'khach#2026ab@example.com', 'password_confirmation' => 'khach#2026ab@example.com'], 'password'],
        ];
    }

    #[DataProvider('invalidRegistrations')]
    public function test_an_invalid_registration_is_refused_and_saves_nothing(array $overrides, string $field): void
    {
        $this->register($overrides)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('controller.api.invalid_data'))
            ->assertJsonValidationErrors($field, 'errors');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_an_email_already_used_is_refused_whatever_its_case(): void
    {
        $this->register()->assertOk();
        // An account saved before emails were lowercased.
        User::factory()->create(['role_id' => null, 'email' => 'Old.Customer@Example.com']);

        $this->register(['email' => 'KHACH@example.com'])->assertStatus(422)->assertJsonValidationErrors('email', 'errors');
        $this->register(['email' => 'old.customer@example.com'])->assertStatus(422)->assertJsonValidationErrors('email', 'errors');

        $this->assertSame(2, User::query()->count());
    }

    public function test_a_failure_after_the_account_row_leaves_no_account_behind(): void
    {
        // The token is the last write; if it fails, the account must not stay (it used to,
        // and the customer's retry then said the email was taken).
        PersonalAccessToken::creating(fn () => throw new RuntimeException('token store unavailable'));

        $this->register()->assertStatus(500);

        $this->assertSame(0, User::query()->count());
        $this->register()->assertStatus(500); // still refused for the same reason, not "email taken"
    }

    public function test_a_double_submit_that_loses_the_race_is_a_clear_refusal_not_an_error(): void
    {
        // Both clicks passed validation; the other one inserted first, so this insert hits the
        // unique index on users.email.
        User::creating(fn () => throw new UniqueConstraintViolationException('sqlite', 'insert into users', [], new PDOException('UNIQUE constraint failed: users.email')));

        $this->register()
            ->assertStatus(422)
            ->assertJsonPath('message', __('controller.api.invalid_data'))
            ->assertJsonValidationErrors('email', 'errors');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_login_accepts_the_email_in_any_case_and_returns_a_working_token(): void
    {
        $this->register(['email' => 'khach@example.com'])->assertOk();

        $response = $this->login(' KHACH@Example.com ', self::PASSWORD)->assertOk()->assertJsonPath('data.user.email', 'khach@example.com');

        $this->withToken($response->json('data.token'))->getJson('/api/public/auth/me')->assertOk();
        $this->assertNotNull(User::query()->sole()->last_login_at);
    }

    public function test_an_account_saved_with_a_mixed_case_email_can_still_log_in(): void
    {
        User::factory()->create(['role_id' => null, 'email' => 'Old.Customer@Example.com', 'password' => Hash::make(self::PASSWORD), 'is_active' => true]);

        $this->login('old.customer@example.com', self::PASSWORD)->assertOk();
    }

    public function test_login_refuses_wrong_unknown_inactive_and_admin_accounts_alike(): void
    {
        $this->register(['email' => 'khach@example.com'])->assertOk();
        User::factory()->create(['role_id' => null, 'email' => 'locked@example.com', 'password' => Hash::make(self::PASSWORD), 'is_active' => false]);
        $role = Role::query()->create(['name' => 'Staff', 'permissions' => ['*']]);
        User::factory()->create(['role_id' => $role->id, 'email' => 'staff@example.com', 'password' => Hash::make(self::PASSWORD), 'is_active' => true]);

        foreach ([
            ['khach@example.com', 'MatKhau#2026y'],
            ['nobody@example.com', self::PASSWORD],
            ['locked@example.com', self::PASSWORD],
            ['staff@example.com', self::PASSWORD],
        ] as [$email, $password]) {
            // The same answer for each: nothing tells which emails have accounts.
            $this->login($email, $password)->assertStatus(401)
                ->assertJsonPath('message', __('controller.auth.invalid_credentials'))
                ->assertJsonMissingPath('data.token');
        }
        $this->assertSame(1, PersonalAccessToken::query()->count(), 'only the registration token exists');
    }

    public function test_login_validates_its_input(): void
    {
        $this->login('', self::PASSWORD)->assertStatus(422)->assertJsonValidationErrors('email', 'errors');
        $this->login('not-an-email', self::PASSWORD)->assertStatus(422)->assertJsonValidationErrors('email', 'errors');
        $this->login('khach@example.com', '')->assertStatus(422)->assertJsonValidationErrors('password', 'errors');
        $this->login('khach@example.com', str_repeat('x', 256))->assertStatus(422)->assertJsonValidationErrors('password', 'errors');
    }

    public function test_a_malformed_email_is_refused_not_a_server_error(): void
    {
        // The login rate limiters read the email before validation runs; an array used to crash them.
        $this->postJson('/api/public/auth/login', ['email' => ['a@example.com'], 'password' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('email', 'errors');
        $this->postJson('/api/public/auth/register', ['name' => 'Khách', 'email' => ['a@example.com'], 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertStatus(422);
        $this->assertNotSame(500, $this->postJson('/api/admin/login', ['email' => ['a@example.com'], 'password' => 'x'])->status());
    }

    public function test_the_per_email_login_limit_ignores_case_and_spaces(): void
    {
        // Created directly: a registration request would count towards the same limit.
        User::factory()->create(['role_id' => null, 'email' => 'khach@example.com', 'password' => Hash::make(self::PASSWORD), 'is_active' => true]);

        foreach (['khach@example.com', 'KHACH@example.com', ' Khach@Example.com', 'khach@EXAMPLE.com ', 'KhAcH@example.com'] as $variant) {
            $this->login($variant, 'MatKhau#2026y')->assertStatus(401);
        }

        $this->login('KHACH@EXAMPLE.COM', self::PASSWORD)->assertStatus(429);
    }

    private function register(array $overrides = []): TestResponse
    {
        $payload = array_merge([
            'name' => 'Khách Hàng',
            'email' => 'khach@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides);

        return $this->postJson('/api/public/auth/register', array_filter($payload, fn ($v) => $v !== null));
    }

    private function login(string $email, string $password): TestResponse
    {
        return $this->postJson('/api/public/auth/login', ['email' => $email, 'password' => $password]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The reset form must not tell a stranger which emails are staff accounts,
 * and must not take guesses at full speed.
 */
class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'N3w!Passw0rd-Strong';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Staff', 'permissions' => []])->id, 'email' => 'staff@example.com']);
    }

    public function test_an_unknown_email_and_a_bad_token_read_the_same(): void
    {
        $unknown = $this->reset('nobody@example.com', 'bad-token')->assertSessionHasErrors('email');
        $staff = $this->reset('staff@example.com', 'bad-token')->assertSessionHasErrors('email');

        $this->assertSame(session()->get('errors')->first('email'), __('controller.auth.invalid_reset_token'));
        $this->assertSame(
            $unknown->baseResponse->getSession()->get('errors')->first('email'),
            $staff->baseResponse->getSession()->get('errors')->first('email'),
        );
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $token = Password::createToken($this->staff);

        $this->reset('staff@example.com', $token)->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check(self::PASSWORD, $this->staff->fresh()->password));
    }

    public function test_reset_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->reset('staff@example.com', 'guess-'.$attempt)->assertSessionHasErrors('email');
        }

        $this->reset('staff@example.com', 'guess-6')->assertStatus(429);
    }

    private function reset(string $email, string $token)
    {
        return $this->from('/vi/admin/reset-password/'.$token)->post('/vi/admin/reset-password', [
            'email' => $email,
            'token' => $token,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);
    }
}

<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\PasswordCheck;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Skipping bcrypt for an unknown email answers faster and so tells a timing
 * attacker which emails have an account.
 */
class PasswordCheckTest extends TestCase
{
    public function test_an_unknown_account_still_costs_a_hash_check(): void
    {
        Hash::spy();

        $this->assertFalse(PasswordCheck::matches(null, 'guess'));

        Hash::shouldHaveReceived('check')->once();
    }

    public function test_a_known_account_is_checked_against_its_own_hash(): void
    {
        $user = new User(['password' => 'Corr3ct!Horse']);

        $this->assertTrue(PasswordCheck::matches($user, 'Corr3ct!Horse'));
        $this->assertFalse(PasswordCheck::matches($user, 'wrong'));
    }
}

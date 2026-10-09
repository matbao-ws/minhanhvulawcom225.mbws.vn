<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A password check that costs the same whether or not the account exists.
 * Skipping bcrypt for an unknown email answered measurably faster, which
 * told anyone timing the login form which emails have an account.
 */
final class PasswordCheck
{
    private static ?string $decoy = null;

    public static function matches(?User $user, string $password): bool
    {
        if ($user === null) {
            // Same algorithm and cost as a real check; the result is thrown away.
            Hash::check($password, self::$decoy ??= Hash::make(Str::random(40)));

            return false;
        }

        return Hash::check($password, (string) $user->password);
    }
}

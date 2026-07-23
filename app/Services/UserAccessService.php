<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserAccessService
{
    public function revoke(User $user, ?string $exceptSessionId = null): void
    {
        $user->tokens()->delete();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}

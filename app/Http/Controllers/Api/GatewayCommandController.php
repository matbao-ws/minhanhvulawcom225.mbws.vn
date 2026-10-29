<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiGateway\GatewaySignature;
use App\Services\SuperadminAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * `POST /api/gateway/command`: what the MatBao console asks this website to do, signed as
 * GatewaySignature describes. Each body carries a one-time nonce, so a captured request cannot be
 * sent again inside the signature window.
 *
 * - `superadmins.list`: the superadmin accounts, without any password data.
 * - `superadmins.reset_password`: a new password the console generated, for one superadmin; every
 *   session and token of that account is revoked.
 */
class GatewayCommandController extends Controller
{
    public function __invoke(Request $request, SuperadminAccounts $accounts): JsonResponse
    {
        if (! GatewaySignature::valid($request)) {
            return $this->error('bad_signature', 401);
        }

        $nonce = (string) $request->json('nonce');
        if (! preg_match('/^[A-Za-z0-9]{32,64}$/', $nonce)) {
            return $this->error('invalid_input', 422);
        }
        if (! Cache::add('gateway-command-nonce:'.$nonce, true, GatewaySignature::MAX_SKEW * 2)) {
            return $this->error('replayed', 409);
        }

        return match ($request->json('action')) {
            'superadmins.list' => response()->json(['ok' => true, 'superadmins' => $accounts->list()]),
            'superadmins.reset_password' => $this->resetPassword($request, $accounts),
            default => $this->error('unknown_action', 422),
        };
    }

    private function resetPassword(Request $request, SuperadminAccounts $accounts): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'user_id' => ['required', 'integer', 'min:1'],
            'password' => ['required', 'string', 'max:255', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            return $this->error('invalid_input', 422);
        }

        $user = $accounts->find((int) $request->json('user_id'));
        if (! $user) {
            return $this->error('not_superadmin', 404);
        }

        $accounts->resetPassword($user, (string) $request->json('password'));

        return response()->json(['ok' => true, 'id' => (int) $user->getKey()]);
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $code], $status);
    }
}

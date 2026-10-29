<?php

namespace App\Services\AiGateway;

use Illuminate\Http\Request;

/**
 * A request the MatBao gateway sends to this website: HMAC-SHA256 of "timestamp.body" keyed with
 * the project's webhook secret (`MATBAO_AI_WEBHOOK_SECRET`), in X-MB-Timestamp / X-MB-Signature.
 * Older than five minutes, or no secret configured, is refused.
 */
class GatewaySignature
{
    public const MAX_SKEW = 300;

    public static function valid(Request $request): bool
    {
        $secret = (string) config('ai_gateway.webhook_secret');
        $timestamp = (string) $request->header('X-MB-Timestamp');
        $signature = (string) $request->header('X-MB-Signature');

        return $secret !== '' && ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= self::MAX_SKEW
            && hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), $signature);
    }
}

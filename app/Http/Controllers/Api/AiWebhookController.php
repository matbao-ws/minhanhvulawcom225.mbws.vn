<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiBulkJob;
use App\Services\AiGateway\BulkSeoService;
use App\Services\AiGateway\GatewayUnavailable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `POST /api/ai/webhook` from the MatBao gateway. A signal only: it names a
 * job, and this website then reads the job with its own token — nothing in
 * the body is trusted beyond the id. Signed with the project's webhook secret
 * (HMAC-SHA256 of "timestamp.body"); older than five minutes is refused.
 */
class AiWebhookController extends Controller
{
    public const MAX_SKEW = 300;

    public function __invoke(Request $request, BulkSeoService $bulk): Response
    {
        $secret = (string) config('ai_gateway.webhook_secret');
        $timestamp = (string) $request->header('X-MB-Timestamp');
        $signature = (string) $request->header('X-MB-Signature');

        if ($secret === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_SKEW
            || ! hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), $signature)) {
            return response()->noContent(401);
        }

        $jobId = (int) $request->json('job_id');
        if ($request->json('event') === 'job.finished' && $jobId > 0) {
            $job = AiBulkJob::query()->where('gateway_job_id', $jobId)->first();
            if ($job) {
                try {
                    $bulk->refresh($job);
                } catch (GatewayUnavailable) {
                    // The page fetches it when opened.
                }
            }
        }

        return response()->noContent();
    }
}

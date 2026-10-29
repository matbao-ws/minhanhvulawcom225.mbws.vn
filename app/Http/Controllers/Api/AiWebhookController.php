<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiBulkJob;
use App\Services\AiGateway\BulkSeoService;
use App\Services\AiGateway\GatewaySignature;
use App\Services\AiGateway\GatewayUnavailable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `POST /api/ai/webhook` from the MatBao gateway. A signal only: it names a
 * job, and this website then reads the job with its own token — nothing in
 * the body is trusted beyond the id. Signed as GatewaySignature describes.
 */
class AiWebhookController extends Controller
{
    public function __invoke(Request $request, BulkSeoService $bulk): Response
    {
        if (! GatewaySignature::valid($request)) {
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

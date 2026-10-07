<?php

namespace App\Support\ErrorReporting;

use App\Services\AiGateway\GatewayClient;
use App\Support\CoreManifest;
use App\Support\ErrorReference;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Sends the gateway a summary of an error a page showed a reference code for (ERR-…, EXT-…), so
 * the internal console finds it by that code instead of someone opening this hosting's
 * laravel.log (MatBaoAI-SEO docs/CONSOLE.md).
 *
 * Only after the response is sent, with a short timeout, and never able to fail the request. It
 * sends no request body, cookie, header or query string; emails and phone numbers in the
 * message are masked first. The full detail stays in the local log under the same code.
 */
class GatewayErrorReporter
{
    /** Per minute: a page stuck in an error loop must not hammer the gateway. */
    public const PER_MINUTE = 30;

    /** @var list<array<string, mixed>> */
    private array $pending = [];

    private bool $scheduled = false;

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly CoreManifest $manifest,
    ) {}

    /** An error Laravel reports during a web request, under the request's ERR- code. */
    public function capture(Throwable $e): void
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        if ($status < 500) {
            return;
        }

        $this->queue($e, ErrorReference::current(), $status);
    }

    /** An error with its own code (an extension that failed to load: EXT-…). */
    public function captureWithReference(Throwable $e, string $ref): void
    {
        $this->queue($e, $ref, null);
    }

    /** Called when the application terminates, after the response went out. */
    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        try {
            foreach ($pending as $error) {
                if (! RateLimiter::attempt('gateway-error-reports', self::PER_MINUTE, fn () => true, 60)) {
                    return;
                }
                $this->gateway->postQuietly('v1/errors', $error, timeout: 3);
            }
        } catch (Throwable) {
            // The cache (rate limiter) may be what failed. The local log has the error anyway.
        }
    }

    public static function mask(string $text): string
    {
        $text = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $text);

        // Vietnamese and international phone numbers, with or without separators.
        return (string) preg_replace('/(?<![\w.])(?:\+?84|0)(?:[\s.-]?\d){8,10}(?![\w.])/', '[phone]', $text);
    }

    private function queue(Throwable $e, string $ref, ?int $status): void
    {
        try {
            // Web requests only: nobody reads a code off an artisan command or a queued job.
            $cli = app()->runningInConsole() && ! app()->runningUnitTests();
            if ($cli || ! $this->gateway->configured() || ! config('ai_gateway.report_errors', true)) {
                return;
            }

            $request = request();
            $file = str_replace('\\', '/', $e->getFile());
            $base = str_replace('\\', '/', base_path()).'/';

            $this->pending[$ref] = array_filter([
                'ref' => $ref,
                'occurred_at' => now()->toIso8601String(),
                'status' => $status,
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 300),
                'route' => $request->route()?->getName(),
                'exception' => mb_substr($e::class, 0, 200),
                'message' => mb_substr(self::mask($e->getMessage()), 0, 1000),
                'file' => mb_substr(str_starts_with($file, $base) ? substr($file, strlen($base)) : basename($file), 0, 300),
                'line' => $e->getLine(),
                'core_version' => $this->manifest->version(),
            ], fn ($value) => $value !== null && $value !== '');

            if (! $this->scheduled) {
                $this->scheduled = true;
                app()->terminating(fn () => $this->flush());
            }
        } catch (Throwable) {
            // Reporting an error must never cause one.
        }
    }
}

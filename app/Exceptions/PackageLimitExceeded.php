<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Creating a record would go past the quantity the shop's package allows.
 *
 * Extends RuntimeException on purpose: the CSV and WordPress importers catch
 * per row, so a row past the limit is reported as a failed row instead of
 * aborting the whole import.
 */
class PackageLimitExceeded extends RuntimeException
{
    /**
     * Session key the admin layout turns into a blocking dialog rather than the
     * self-dismissing toast used for ordinary errors: a refusal the user did not
     * notice looks like a save that silently failed.
     */
    public const FLASH_KEY = 'package_limit';

    public function __construct(
        public readonly string $metric,
        public readonly int $limit,
    ) {
        parent::__construct(__('admin.package.limit_exceeded.'.$metric, ['limit' => number_format($limit)]));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $this->getMessage()], 422);
        }

        return back()->withInput()->with(self::FLASH_KEY, $this->getMessage());
    }
}

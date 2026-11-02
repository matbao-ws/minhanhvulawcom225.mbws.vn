<?php

namespace Tests\Support\Architecture;

/**
 * The rules of AI_BUILD_PROMPT.md / CLAUDE.md that code can check. Each rule takes one
 * file (its source and its path relative to the project) and returns the places that
 * break it, keyed `Class::method` (or the path for code outside any class).
 *
 * An ALLOWED entry is a known, reviewed exception: it names one method and says why.
 * ArchitectureTest fails when an entry no longer matches anything, so the lists only shrink.
 */
final class ArchitectureRules
{
    /** Eloquent calls that write a new row. */
    public const CREATING_METHODS = [
        'create', 'createquietly', 'forcecreate', 'forcecreatequietly', 'createorfirst',
        'firstorcreate', 'updateorcreate', 'insert', 'insertgetid', 'insertorignore', 'upsert',
    ];

    public const ORDER_MODELS = ['App\\Models\\Order', 'App\\Models\\OrderItem'];

    /** Orders are created in one place: Orders\OrderCreationService (CLAUDE.md). */
    public const ORDER_CREATION_ALLOWED = [
        'App\\Services\\Orders\\OrderCreationService::create' => 'the single place an order and its items are written',
        'App\\Mail\\EmailPreviewSamples::order' => 'an in-memory sample for the email design preview; never saved (EmailDesignAdminTest checks no order row)',
    ];

    /** Controllers that serve the storefront (the public API and the client Blade pages). */
    public const STOREFRONT_CONTROLLERS = ['app/Http/Controllers/Api/', 'app/Http/Controllers/Client/'];

    /** Shared product reads go through Catalog\ProductQueryService, not inline queries (CLAUDE.md). */
    public const STOREFRONT_PRODUCT_QUERY_ALLOWED = [
        'App\\Http\\Controllers\\Api\\PublicController::checkout' => 'write path: lockForUpdate() on each ordered product row, not a catalog read',
        'App\\Http\\Controllers\\Api\\PublicController::storeReview' => 'write path: lockForUpdate() on the product row serializes duplicate-review checks',
    ];

    public function __construct(private readonly ClassUsageScanner $scanner = new ClassUsageScanner) {}

    /**
     * Order or OrderItem rows written outside the allowed methods, including through an
     * alias, a fully qualified name, `::query()->create()` or `new Order`.
     *
     * @return array<string, list<int>> place => lines
     */
    public function orderCreation(string $code, string $path): array
    {
        $found = [];
        foreach ($this->scanner->scan($code)['calls'] as $call) {
            if (! in_array($call['class'], self::ORDER_MODELS, true)) {
                continue;
            }
            $methods = array_map('strtolower', array_filter([$call['method'], ...$call['chain']]));
            if ($call['kind'] === 'new' || array_intersect($methods, self::CREATING_METHODS) !== []) {
                $found[$this->place($call['scope'], $path)][] = $call['line'];
            }
        }

        return $found;
    }

    /**
     * Static calls on App\Models\Product from a storefront controller.
     *
     * @return array<string, list<int>> place => lines
     */
    public function storefrontProductQueries(string $code, string $path): array
    {
        if (! $this->under($path, self::STOREFRONT_CONTROLLERS)) {
            return [];
        }

        $found = [];
        foreach ($this->scanner->scan($code)['calls'] as $call) {
            if ($call['class'] === 'App\\Models\\Product' && $call['kind'] === 'static') {
                $found[$this->place($call['scope'], $path)][] = $call['line'];
            }
        }

        return $found;
    }

    /**
     * Anything under App\Http reached from the public extension API (app/Extension):
     * extensions build on it, so it must not drag in controllers, requests or middleware.
     *
     * @return array<string, list<int>> place => lines
     */
    public function extensionApiHttp(string $code, string $path): array
    {
        if (! $this->under($path, ['app/Extension/'])) {
            return [];
        }

        $found = [];
        foreach ($this->scanner->scan($code)['references'] as $reference) {
            if (str_starts_with($reference['class'], 'App\\Http\\')) {
                $found[$this->place($reference['scope'], $path)][] = $reference['line'];
            }
        }

        return $found;
    }

    /** @param  list<string>  $prefixes */
    private function under(string $path, array $prefixes): bool
    {
        $path = str_replace('\\', '/', $path);
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function place(string $scope, string $path): string
    {
        return $scope !== '' ? $scope : str_replace('\\', '/', $path);
    }
}

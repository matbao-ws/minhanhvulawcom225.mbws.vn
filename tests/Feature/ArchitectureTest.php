<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\Support\Architecture\ArchitectureRules;
use Tests\TestCase;

/**
 * Architecture rules from AI_BUILD_PROMPT.md and CLAUDE.md, checked on the source in app/
 * and routes/. Each rule also runs against tests/Fixtures/architecture, which must keep
 * producing violations: a rule that stops finding them is broken, not passing.
 */
class ArchitectureTest extends TestCase
{
    private const FIXTURES = 'tests/Fixtures/architecture/';

    public function test_orders_are_created_only_by_the_order_creation_service(): void
    {
        $this->assertNoNewViolations('orderCreation', ArchitectureRules::ORDER_CREATION_ALLOWED,
            'Create orders through App\Services\Orders\OrderCreationService (CLAUDE.md).');
    }

    public function test_storefront_controllers_read_products_through_the_shared_query_service(): void
    {
        $this->assertNoNewViolations('storefrontProductQueries', ArchitectureRules::STOREFRONT_PRODUCT_QUERY_ALLOWED,
            'Storefront product reads go through App\Services\Catalog\ProductQueryService (CLAUDE.md).');
    }

    public function test_the_extension_api_does_not_depend_on_http(): void
    {
        $this->assertNoNewViolations('extensionApiHttp', [],
            'app/Extension is the public extension API: it must not reach App\Http.');
    }

    public function test_every_allowed_exception_still_matches_a_violation(): void
    {
        foreach ([
            'orderCreation' => ArchitectureRules::ORDER_CREATION_ALLOWED,
            'storefrontProductQueries' => ArchitectureRules::STOREFRONT_PRODUCT_QUERY_ALLOWED,
        ] as $rule => $allowed) {
            $stale = array_diff(array_keys($allowed), array_keys($this->violations($rule)));

            $this->assertSame([], array_values($stale),
                "ArchitectureRules allows {$rule} in places that no longer do it; remove these entries: ".implode(', ', $stale));
        }
    }

    public function test_order_creation_rule_sees_aliases_qualified_names_chains_and_new(): void
    {
        $found = $this->fixture('orderCreation', 'OrderCreation.php', 'app/Http/Controllers/Admin/FixtureOrderController.php');

        $this->assertSame([
            'App\Http\Controllers\Admin\FixtureOrderController::viaAlias',
            'App\Http\Controllers\Admin\FixtureOrderController::viaClosure',
            'App\Http\Controllers\Admin\FixtureOrderController::viaFullyQualifiedQuery',
            'App\Http\Controllers\Admin\FixtureOrderController::viaNew',
            'App\Http\Controllers\Admin\FixtureOrderController::viaPartiallyQualified',
        ], $found, 'readOnly() only reads and must not be reported.');
    }

    public function test_storefront_product_rule_sees_aliases_and_qualified_names(): void
    {
        $found = $this->fixture('storefrontProductQueries', 'StorefrontProductQuery.php', 'app/Http/Controllers/Client/FixtureProductController.php');

        $this->assertSame([
            'App\Http\Controllers\Client\FixtureProductController::viaAlias',
            'App\Http\Controllers\Client\FixtureProductController::viaFullyQualified',
        ], $found, 'Item::class is not a query and must not be reported.');
    }

    public function test_storefront_product_rule_ignores_admin_controllers(): void
    {
        $this->assertSame([], $this->fixture('storefrontProductQueries', 'StorefrontProductQuery.php', 'app/Http/Controllers/Admin/FixtureProductController.php'));
    }

    public function test_extension_api_rule_sees_imports_class_constants_and_strings(): void
    {
        $code = $this->read(self::FIXTURES.'ExtensionHttp.php');
        $found = (new ArchitectureRules)->extensionApiHttp($code, 'app/Extension/Registries/FixtureRegistry.php');

        $this->assertSame([
            'App\Extension\Registries\FixtureRegistry::kernel' => [11],
            'App\Extension\Registries\FixtureRegistry::middleware' => [16],
            'app/Extension/Registries/FixtureRegistry.php' => [5],
        ], $this->sorted($found));
    }

    /** @param  array<string, string>  $allowed */
    private function assertNoNewViolations(string $rule, array $allowed, string $hint): void
    {
        $new = array_diff_key($this->violations($rule), $allowed);

        $lines = [];
        foreach ($this->sorted($new) as $place => $at) {
            $lines[] = "  {$place} (".implode(', ', array_map(fn (array $hit) => "{$hit[0]}:{$hit[1]}", $at)).')';
        }

        $this->assertSame([], $lines, $hint."\nViolations:\n".implode("\n", $lines)
            ."\nFix the code. Only add an ArchitectureRules exception after review, with the reason.");
    }

    /** @return array<string, list<array{0: string, 1: int}>> place => [file, line] */
    private function violations(string $rule): array
    {
        $rules = new ArchitectureRules;
        $found = [];

        foreach ((new Finder)->files()->in([base_path('app'), base_path('routes')])->name('*.php') as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            foreach ($rules->{$rule}($file->getContents(), $path) as $place => $lines) {
                foreach ($lines as $line) {
                    $found[$place][] = [$path, $line];
                }
            }
        }

        return $found;
    }

    /** @return list<string> the places reported for a fixture read under a made-up path */
    private function fixture(string $rule, string $file, string $as): array
    {
        $places = array_keys((new ArchitectureRules)->{$rule}($this->read(self::FIXTURES.$file), $as));
        sort($places);

        return $places;
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }

    private function sorted(array $found): array
    {
        ksort($found);

        return $found;
    }
}

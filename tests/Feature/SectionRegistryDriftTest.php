<?php

namespace Tests\Feature;

use App\Support\SectionRegistry;
use Tests\TestCase;

/**
 * The registry against the two things it can silently drift from.
 *
 * There is no build step on the client side and no schema to catch either
 * mismatch: a variant declared for a section whose Blade file was deleted, or a
 * token whose class was never written into the stylesheet, both fail by simply
 * doing nothing. This is the same job PermissionRegistryDriftTest does for
 * permission codes.
 *
 * The reverse direction is deliberately *not* asserted. A section absent from the
 * registry is a section that offers no settings, which is a legal state — the
 * same contract as a region with no `format`, where the tag the template authored
 * still wins.
 */
class SectionRegistryDriftTest extends TestCase
{
    private function registry(): SectionRegistry
    {
        return app(SectionRegistry::class);
    }

    public function test_every_declared_section_has_a_blade_partial(): void
    {
        foreach (array_keys($this->registry()->sections()) as $section) {
            $this->assertFileExists(
                resource_path('views/client/sections/'.$section.'.blade.php'),
                "registry khai báo section {$section} nhưng không có Blade tương ứng",
            );
        }
    }

    public function test_every_class_the_registry_can_emit_exists_in_the_stylesheet(): void
    {
        $css = file_get_contents(public_path('client-assets/css/storefront.css'));

        foreach ($this->registry()->allClasses() as $class) {
            $this->assertStringContainsString(
                '.'.$class,
                $css,
                "token/variant sinh ra class {$class} nhưng storefront.css chưa định nghĩa",
            );
        }
    }

    public function test_every_setting_a_section_opts_into_exists_in_the_catalogue(): void
    {
        $catalogue = $this->registry()->settingCatalogue();

        foreach ($this->registry()->sections() as $section => $definition) {
            foreach ($definition['settings'] as $setting) {
                $this->assertArrayHasKey(
                    $setting,
                    $catalogue,
                    "section {$section} xin tuỳ chọn {$setting} không có trong catalogue",
                );
            }
        }
    }

    public function test_every_token_setting_declares_a_class_for_each_of_its_values(): void
    {
        foreach ($this->registry()->settingCatalogue() as $name => $setting) {
            if ($setting['type'] !== SectionRegistry::TYPE_TOKEN) {
                $this->assertArrayNotHasKey('values', $setting, "tuỳ chọn {$name} không phải token nhưng vẫn khai báo values");

                continue;
            }

            $this->assertNotEmpty($setting['values'] ?? [], "tuỳ chọn token {$name} không có giá trị nào");

            foreach ($setting['values'] as $token => $value) {
                $this->assertArrayHasKey('class', $value, "token {$name}.{$token} thiếu class");
                $this->assertArrayHasKey('label', $value, "token {$name}.{$token} thiếu label");
            }
        }
    }

    public function test_a_section_name_can_never_address_another_sections_storage(): void
    {
        /*
         * The storage key is `<list key>.<section name>`, so a name carrying a dot
         * would reach into a different section's row. The id rule that governs
         * section names is what forbids it, and it is the same rule the section-list
         * component filters `:sections` through.
         */
        $lists = app(\App\Services\SiteListService::class);

        foreach (array_keys($this->registry()->sections()) as $section) {
            $this->assertTrue($lists->isValidId($section), "tên section {$section} không hợp lệ");
        }

        $this->assertFalse($lists->isValidId('home.hero'));
        $this->assertFalse($lists->isValidId('Hero'));
    }
}

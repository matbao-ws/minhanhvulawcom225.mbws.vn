<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PostCategory;
use Illuminate\Database\Seeder;

/**
 * Sample storefront navigation.
 *
 * Additive and idempotent: a menu that already has items is left alone, so this
 * is safe to re-run and safe on an installation someone has already configured.
 * It never truncates.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        foreach ($this->menus() as $key => $definition) {
            $menu = Menu::query()->firstOrCreate(
                ['key' => $key],
                ['name' => $definition['name'], 'is_active' => true],
            );

            // Someone has already arranged this menu; leave their work alone.
            if ($menu->items()->exists()) {
                $this->command?->warn("Menu [{$key}] đã có mục, bỏ qua.");

                continue;
            }

            $created += $this->createItems($menu, $definition['items']);
        }

        $this->command?->info("Menu: đã tạo {$created} mục menu mẫu.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createItems(Menu $menu, array $items, ?int $parentId = null): int
    {
        $created = 0;
        $order = 0;

        foreach ($items as $definition) {
            $attributes = $this->resolveTarget($definition);

            // A target that does not exist in this installation (no catalog
            // yet, for instance) is skipped rather than seeded as a dead link.
            if ($attributes === null) {
                continue;
            }

            $item = $menu->items()->create([
                'parent_id' => $parentId,
                'label' => $definition['label'],
                'target_blank' => false,
                'is_active' => true,
                'sort_order' => $order++,
            ] + $attributes);
            $created++;

            if (! empty($definition['children'])) {
                $created += $this->createItems($menu, $definition['children'], $item->id);
            }
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function resolveTarget(array $definition): ?array
    {
        return match ($definition['type']) {
            MenuItem::TYPE_URL => ['type' => MenuItem::TYPE_URL, 'url' => $definition['url']],
            MenuItem::TYPE_PAGE => ($id = Page::query()->where('slug', $definition['slug'])->value('id'))
                ? ['type' => MenuItem::TYPE_PAGE, 'page_id' => $id]
                : null,
            MenuItem::TYPE_CATEGORY => ($id = Category::query()->where('slug', '!=', 'chua-phan-loai')->orderBy('sort_order')->value('id'))
                ? ['type' => MenuItem::TYPE_CATEGORY, 'category_id' => $id]
                : null,
            MenuItem::TYPE_POST_CATEGORY => ($id = PostCategory::query()->orderBy('sort_order')->value('id'))
                ? ['type' => MenuItem::TYPE_POST_CATEGORY, 'post_category_id' => $id]
                : null,
            default => null,
        };
    }

    /** @return array<string, array{name: string, items: array<int, array<string, mixed>>}> */
    private function menus(): array
    {
        return [
            'primary' => [
                'name' => 'Menu chính',
                'items' => [
                    [
                        'label' => ['vi' => 'Trang chủ', 'en' => 'Home'],
                        'type' => MenuItem::TYPE_URL,
                        'url' => '/',
                    ],
                    [
                        'label' => ['vi' => 'Giới thiệu', 'en' => 'About us'],
                        'type' => MenuItem::TYPE_PAGE,
                        'slug' => 'gioi-thieu',
                        'children' => [
                            [
                                'label' => ['vi' => 'Chính sách giao hàng', 'en' => 'Shipping policy'],
                                'type' => MenuItem::TYPE_PAGE,
                                'slug' => 'chinh-sach-giao-hang',
                            ],
                        ],
                    ],
                    [
                        'label' => ['vi' => 'Sản phẩm', 'en' => 'Products'],
                        'type' => MenuItem::TYPE_CATEGORY,
                    ],
                    [
                        'label' => ['vi' => 'Tin tức', 'en' => 'News'],
                        'type' => MenuItem::TYPE_POST_CATEGORY,
                    ],
                    [
                        'label' => ['vi' => 'Liên hệ', 'en' => 'Contact'],
                        'type' => MenuItem::TYPE_PAGE,
                        'slug' => 'lien-he',
                    ],
                ],
            ],
            'footer' => [
                'name' => 'Menu chân trang',
                'items' => [
                    [
                        'label' => ['vi' => 'Giới thiệu', 'en' => 'About us'],
                        'type' => MenuItem::TYPE_PAGE,
                        'slug' => 'gioi-thieu',
                    ],
                    [
                        'label' => ['vi' => 'Chính sách giao hàng', 'en' => 'Shipping policy'],
                        'type' => MenuItem::TYPE_PAGE,
                        'slug' => 'chinh-sach-giao-hang',
                    ],
                    [
                        'label' => ['vi' => 'Liên hệ', 'en' => 'Contact'],
                        'type' => MenuItem::TYPE_PAGE,
                        'slug' => 'lien-he',
                    ],
                ],
            ],
        ];
    }
}

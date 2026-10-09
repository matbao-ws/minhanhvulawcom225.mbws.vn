<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = $request->query('q');
        $locale = app()->getLocale();
        $results = [];
        $user = $request->user();

        if (! is_string($q) || mb_strlen($q) < 2 || mb_strlen($q) > 100) {
            return response()->json(['results' => []]);
        }

        if ($user->can('products.view')) {
        $products = Product::query()
            ->where('name', 'like', "%{$q}%")
            ->orWhere('sku', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        if ($products->count() > 0) {
            $items = [];
            foreach ($products as $product) {
                $items[] = [
                    'title' => $product->getTranslation('name', $locale) ?: $product->name,
                    'subtitle' => 'SKU: ' . ($product->sku ?? 'N/A') . ' | ' . number_format($product->price, 0, ',', '.') . ' ₫',
                    'link' => route('admin.products.edit', ['product' => $product->id, 'locale' => $locale])
                ];
            }
            $results[] = [
                'category' => __('admin.menu.products') ?: 'Products',
                'icon' => 'solar:box-bold-duotone',
                'items' => $items
            ];
        }
        }

        if ($user->can('orders.view')) {
        $orders = Order::query()
            ->where('order_number', 'like', "%{$q}%")
            ->orWhere('customer_name', 'like', "%{$q}%")
            ->orWhere('customer_phone', 'like', "%{$q}%")
            ->orWhere('customer_email', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        if ($orders->count() > 0) {
            $items = [];
            foreach ($orders as $order) {
                $items[] = [
                    'title' => '#' . $order->order_number . ' - ' . $order->customer_name,
                    'subtitle' => $order->customer_phone . ' | ' . number_format($order->grand_total, 0, ',', '.') . ' ₫',
                    'link' => route('admin.orders.show', ['order' => $order->id, 'locale' => $locale])
                ];
            }
            $results[] = [
                'category' => __('admin.menu.orders') ?: 'Orders',
                'icon' => 'solar:bill-list-bold-duotone',
                'items' => $items
            ];
        }
        }

        if ($user->can('products.view')) {
        $categories = Category::query()
            ->where('name', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        if ($categories->count() > 0) {
            $items = [];
            foreach ($categories as $category) {
                $items[] = [
                    'title' => $category->getTranslation('name', $locale) ?: $category->name,
                    'subtitle' => 'Slug: ' . $category->slug,
                    'link' => route('admin.categories.edit', ['category' => $category->id, 'locale' => $locale])
                ];
            }
            $results[] = [
                'category' => __('admin.menu.categories') ?: 'Categories',
                'icon' => 'solar:folder-open-bold-duotone',
                'items' => $items
            ];
        }

        // 4. Brands
        $brands = Brand::query()
            ->where('name', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        if ($brands->count() > 0) {
            $items = [];
            foreach ($brands as $brand) {
                $items[] = [
                    'title' => $brand->getTranslation('name', $locale) ?: $brand->name,
                    'subtitle' => 'Slug: ' . $brand->slug,
                    'link' => route('admin.brands.edit', ['brand' => $brand->id, 'locale' => $locale])
                ];
            }
            $results[] = [
                'category' => __('admin.menu.brands') ?: 'Brands',
                'icon' => 'solar:tag-bold-duotone',
                'items' => $items
            ];
        }
        }

        if ($user->can('users.view')) {
        // Staff only, and the same accounts the users screen shows: customers belong to
        // customers.view, and privileged accounts stay hidden from non-superadmins.
        $staff = User::query()
            ->whereNotNull('role_id')
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereHas('role', fn ($role) => $role->where('is_system', false)->where('is_superadmin', false)))
            ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
            ->limit(5)
            ->get();

        if ($staff->count() > 0) {
            $items = [];
            foreach ($staff as $member) {
                $items[] = [
                    'title' => $member->name,
                    'subtitle' => $member->email,
                    'link' => route('admin.users.edit', ['user' => $member->id, 'locale' => $locale])
                ];
            }
            $results[] = [
                'category' => __('admin.menu.users') ?: 'Users',
                'icon' => 'solar:users-group-two-rounded-bold-duotone',
                'items' => $items
            ];
        }
        }

        if ($user->can('posts.view')) {
        $posts = Post::query()
            ->where('title', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        if ($posts->count() > 0) {
            $items = [];
            foreach ($posts as $post) {
                $items[] = [
                    'title' => $post->getTranslation('title', $locale) ?: $post->title,
                    'subtitle' => 'Slug: ' . $post->slug,
                    'link' => route('admin.posts.edit', ['post' => $post->id, 'locale' => $locale])
                ];
            }
            $results[] = [
                'category' => __('admin.menu.blog_posts') ?: 'Blog Posts',
                'icon' => 'solar:document-bold-duotone',
                'items' => $items
            ];
        }
        }

        return response()->json(['results' => $results]);
    }
}

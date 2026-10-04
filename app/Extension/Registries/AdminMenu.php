<?php

namespace App\Extension\Registries;

use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * Public extension API (stable): an extension's entries in the admin sidebar.
 *
 *     AdminMenu::register('dealers', [
 *         'key' => 'dealers.index',                 // "<id>." prefix, unique
 *         'label' => __('dealers::menu.dealers'),
 *         'icon' => 'solar:handshake-line-duotone', // iconify name, optional
 *         'route' => 'ext.dealers.index',           // a route name; or 'children' instead
 *         'permission' => 'dealers.view',           // optional: its own permission or a core one
 *         'children' => [ ['key' => 'dealers.commissions', 'label' => '...', 'route' => '...', 'permission' => '...'] ],
 *     ]);
 *
 * Call it from the extension provider's boot(). The core sidebar draws the entries after its own
 * menu, each only for an account allowed to open it. A clashing or malformed entry throws, and the
 * extension is marked failed — never silently overridden.
 */
final class AdminMenu
{
    /** @var array<string, array{extension: string, key: string, label: string, icon: ?string, route: ?string, permission: ?string, children: list<array{key: string, label: string, route: string, permission: ?string}>}> */
    private array $items = [];

    /** @var array<string, true> every key in use, children included */
    private array $keys = [];

    /** @param  array<string, mixed>  $item */
    public static function register(string $extension, array $item): void
    {
        app(self::class)->add($extension, $item);
    }

    /** @param  array<string, mixed>  $item */
    public function add(string $extension, array $item): void
    {
        $entry = $this->entry($extension, $item);
        $children = [];
        foreach ((array) ($item['children'] ?? []) as $child) {
            if (! is_array($child)) {
                throw new InvalidArgumentException("AdminMenu: children of {$entry['key']} must be arrays");
            }
            $children[] = $this->entry($extension, $child, child: true);
        }
        if ($entry['route'] === null && $children === []) {
            throw new InvalidArgumentException("AdminMenu: {$entry['key']} needs a route or children");
        }

        foreach (array_merge([$entry], $children) as $registered) {
            $this->keys[$registered['key']] = true;
        }
        $this->items[$entry['key']] = $entry + ['extension' => $extension, 'children' => $children];
    }

    /**
     * Entries the account may open, in registration order; children it may not open are left out,
     * a parent with nothing left too. Routes that do not exist (any more) are skipped.
     *
     * @return list<array{key: string, label: string, icon: ?string, url: ?string, children: list<array{key: string, label: string, url: string}>}>
     */
    public function visibleTo(?User $user): array
    {
        $visible = [];
        foreach ($this->items as $item) {
            if (! $this->allowed($user, $item['permission'])) {
                continue;
            }
            $children = [];
            foreach ($item['children'] as $child) {
                $childUrl = $this->allowed($user, $child['permission']) ? $this->url($child['route']) : null;
                if ($childUrl !== null) {
                    $children[] = ['key' => $child['key'], 'label' => $child['label'], 'url' => $childUrl];
                }
            }
            $url = $item['route'] !== null ? $this->url($item['route']) : null;
            if ($url === null && $children === []) {
                continue;
            }
            $visible[] = ['key' => $item['key'], 'label' => $item['label'], 'icon' => $item['icon'], 'url' => $url, 'children' => $children];
        }

        return $visible;
    }

    /** Drop an extension's entries (it failed after registering some). */
    public function forget(string $extension): void
    {
        foreach ($this->items as $key => $item) {
            if ($item['extension'] === $extension) {
                foreach (array_merge([$item], $item['children']) as $registered) {
                    unset($this->keys[$registered['key']]);
                }
                unset($this->items[$key]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{key: string, label: string, icon: ?string, route: ?string, permission: ?string}
     */
    private function entry(string $extension, array $item, bool $child = false): array
    {
        $key = is_string($item['key'] ?? null) ? $item['key'] : '';
        if (! str_starts_with($key, $extension.'.') || ! preg_match('/^[a-z0-9]+(\.[a-z0-9_-]+)+$/', $key)) {
            throw new InvalidArgumentException("AdminMenu: key \"{$key}\" must start with \"{$extension}.\"");
        }
        if (isset($this->keys[$key])) {
            throw new InvalidArgumentException("AdminMenu: key \"{$key}\" is already registered");
        }
        $label = is_string($item['label'] ?? null) ? trim($item['label']) : '';
        if ($label === '') {
            throw new InvalidArgumentException("AdminMenu: {$key} needs a label");
        }
        $route = is_string($item['route'] ?? null) && $item['route'] !== '' ? $item['route'] : null;
        if ($child && $route === null) {
            throw new InvalidArgumentException("AdminMenu: {$key} needs a route");
        }
        $permission = is_string($item['permission'] ?? null) && $item['permission'] !== '' ? $item['permission'] : null;
        if ($permission !== null && ! str_starts_with($permission, $extension.'.') && ! in_array($permission, PermissionRegistry::coreCodes(), true)) {
            throw new InvalidArgumentException("AdminMenu: {$key} asks for unknown permission \"{$permission}\"");
        }

        return [
            'key' => $key,
            'label' => $label,
            'icon' => is_string($item['icon'] ?? null) ? $item['icon'] : null,
            'route' => $route,
            'permission' => $permission,
        ];
    }

    /** The route's URL, or null when it does not exist or cannot be built: never break the sidebar. */
    private function url(string $route): ?string
    {
        if (! Route::has($route)) {
            return null;
        }
        try {
            return route($route);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    private function allowed(?User $user, ?string $permission): bool
    {
        return $permission === null ? $user !== null : (bool) $user?->can($permission);
    }
}

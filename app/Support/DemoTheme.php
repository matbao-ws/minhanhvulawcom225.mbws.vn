<?php

namespace App\Support;

/**
 * A storefront theme plugged into the core checkout itself, to try core changes against a real
 * theme (DEMO_THEME_PATH=demo-theme). Nothing of it is in git: the folder and its public assets
 * are ignored, so `team.py new` (a git clone) and core releases never carry it, and a website
 * without the variable behaves exactly as before. Never active in production.
 *
 * Layout of the folder:
 *   <path>/views/client/...   Blade views, found before resources/views/client
 *   <path>/routes.php         the theme's routes, used instead of routes/theme.php
 * Its images, fonts and CSS live in public/demo-theme-assets (served as static files).
 */
final class DemoTheme
{
    public static function path(): ?string
    {
        $path = config('app.demo_theme');
        if (! is_string($path) || $path === '' || app()->environment('production')) {
            return null;
        }

        $path = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) ? $path : base_path($path);

        return is_dir($path) ? rtrim($path, '/\\') : null;
    }

    /** The theme routes in use: the demo theme's, else the website's routes/theme.php, else none. */
    public static function routes(): ?string
    {
        $demo = self::path();
        if ($demo !== null && is_file($demo.'/routes.php')) {
            return $demo.'/routes.php';
        }

        return is_file(base_path('routes/theme.php')) ? base_path('routes/theme.php') : null;
    }

    public static function views(): ?string
    {
        $demo = self::path();

        return $demo !== null && is_dir($demo.'/views') ? $demo.'/views' : null;
    }
}

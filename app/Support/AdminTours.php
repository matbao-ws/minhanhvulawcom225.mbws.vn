<?php

namespace App\Support;

/**
 * The guided tours of the admin (driver.js, public/admin-assets/js/admin-tour.js): which page
 * has one, and its steps in order. The data is app/Support/AdminTours/tours.php:
 *
 *   'routes' => route name => tour id (create and edit pages sharing a form share its tour),
 *   'tours'  => tour id => steps [text key, data-tour target or null, popover side, optional].
 *
 * A step points at the element carrying `data-tour="<target>"`, or at nothing (a centred
 * popover). Its text is lang/<locale>/tours.php under the step's key. A step marked optional
 * points at something only some admins, features or states have; the browser skips any step
 * whose element is not on the page. AdminToursTest renders each page and fails when a required
 * target is missing, and fails when a page of the sidebar has no tour.
 *
 * A tour runs only from the header's "?" button (public/admin-assets/js/admin-tour.js), never
 * by itself.
 */
final class AdminTours
{
    /** @var array{routes: array<string, string>, tours: array<string, list<array{0: string, 1: ?string, 2: string, 3: bool}>>}|null */
    private static ?array $data = null;

    /** The tour of the page this route renders, ready for the browser, or null when it has none. */
    public static function forRoute(?string $route): ?array
    {
        $tour = $route !== null ? (self::routes()[$route] ?? null) : null;
        if ($tour === null) {
            return null;
        }

        return [
            'id' => $tour,
            'steps' => array_map(fn (array $step) => [
                'target' => $step[1],
                'side' => $step[2],
                // driver.js renders these as HTML: send text only.
                'title' => e(__("tours.{$step[0]}.title")),
                'description' => e(__("tours.{$step[0]}.description")),
            ], self::data()['tours'][$tour]),
            'ui' => [
                'next' => e(__('tours.ui.next')),
                'prev' => e(__('tours.ui.prev')),
                'done' => e(__('tours.ui.done')),
                'skip' => e(__('tours.ui.skip')),
                'progress' => e(__('tours.ui.progress')),
            ],
        ];
    }

    /** @return array<string, string> route name => tour id */
    public static function routes(): array
    {
        return self::data()['routes'];
    }

    /** @return list<string> every tour id */
    public static function tours(): array
    {
        return array_keys(self::data()['tours']);
    }

    /**
     * @return list<array{key: string, target: ?string, optional: bool}>
     */
    public static function steps(string $tour): array
    {
        return array_map(fn (array $step) => [
            'key' => $step[0],
            'target' => $step[1],
            'optional' => $step[3],
        ], self::data()['tours'][$tour]);
    }

    private static function data(): array
    {
        return self::$data ??= require __DIR__.'/AdminTours/tours.php';
    }
}

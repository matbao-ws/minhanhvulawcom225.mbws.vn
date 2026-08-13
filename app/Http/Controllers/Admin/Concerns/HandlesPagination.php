<?php

namespace App\Http\Controllers\Admin\Concerns;

trait HandlesPagination
{
    /**
     * Page sizes offered by the shared admin pagination partial.
     *
     * @var array<int, int>
     */
    public static array $perPageOptions = [15, 25, 50, 100];

    /**
     * Resolve the requested page size, falling back to the screen default when
     * the value is missing or outside the allow-list.
     */
    protected function perPage(int $default = 15): int
    {
        $requested = (int) request('per_page');

        return in_array($requested, static::$perPageOptions, true) ? $requested : $default;
    }
}

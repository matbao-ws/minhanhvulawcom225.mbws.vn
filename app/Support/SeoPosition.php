<?php

namespace App\Support;

/**
 * An average Google position in words a shop owner reads: page 1 is the first ten results,
 * page 2 the next ten, anything below is practically unseen.
 */
final class SeoPosition
{
    public static function where(float $position): string
    {
        return __(match (true) {
            $position <= 10.5 => 'ai.tasks.where.page1',
            $position <= 20.5 => 'ai.tasks.where.page2',
            default => 'ai.tasks.where.deep',
        });
    }
}

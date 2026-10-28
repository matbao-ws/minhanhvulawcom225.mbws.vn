<?php

namespace App\Support;

/**
 * A small trend line (inline SVG) for a figure's daily values: a line and a soft area under it.
 * Drawn on the server, so the page needs no chart library and the line is there before any script.
 */
final class Sparkline
{
    private const WIDTH = 120;

    private const HEIGHT = 34;

    /**
     * @param  list<int|float|null>  $values  oldest first; a missing day repeats the day before
     * @param  bool  $lowerIsBetter  drawn upside down, so a better trend still rises
     */
    public static function svg(array $values, string $color, bool $lowerIsBetter = false): string
    {
        $points = [];
        $last = null;
        foreach ($values as $value) {
            $last = $value ?? $last;
            $points[] = $last;
        }
        $points = array_values(array_filter($points, fn ($value) => $value !== null));
        if (count($points) < 2) {
            return '';
        }

        if ($lowerIsBetter) {
            $points = array_map(fn ($value) => -$value, $points);
        }
        $min = min($points);
        $range = (max($points) - $min) ?: 1;
        $step = self::WIDTH / (count($points) - 1);
        $coordinates = [];
        foreach ($points as $index => $value) {
            // 2px margin top and bottom so the stroke is never clipped.
            $coordinates[] = round($index * $step, 1).','.round(self::HEIGHT - 2 - ($value - $min) / $range * (self::HEIGHT - 4), 1);
        }
        $line = implode(' ', $coordinates);
        $id = 'spark-'.substr(md5($line.$color), 0, 8);
        $color = e($color);

        return '<svg class="seo-spark" viewBox="0 0 '.self::WIDTH.' '.self::HEIGHT.'" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
            .'<defs><linearGradient id="'.$id.'" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$color.'" stop-opacity=".28"/><stop offset="1" stop-color="'.$color.'" stop-opacity="0"/></linearGradient></defs>'
            .'<polygon points="0,'.self::HEIGHT.' '.$line.' '.self::WIDTH.','.self::HEIGHT.'" fill="url(#'.$id.')"/>'
            .'<polyline points="'.$line.'" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
            .'</svg>';
    }
}

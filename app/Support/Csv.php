<?php

namespace App\Support;

/**
 * Shared CSV export helpers.
 *
 * The escaping rule below is a security control, not formatting: a spreadsheet
 * treats a cell starting with `=`, `+`, `-` or `@` as a formula, so an attacker who
 * gets such a string into a customer name turns the exported file into code that
 * runs on the admin's machine. It lives here because two controllers already export
 * and a duplicated control is one that eventually drifts.
 */
class Csv
{
    /**
     * @return array<string, string>
     */
    public static function downloadHeaders(string $filename): array
    {
        return [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];
    }

    public static function safeValue(mixed $value): string|int|float|null
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Write one row with every cell escaped.
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    public static function writeRow($handle, array $row): void
    {
        fputcsv($handle, array_map(static fn (mixed $value) => self::safeValue($value), $row));
    }

    /**
     * Excel reads a CSV as the system locale unless the file opens with a BOM, which
     * turns Vietnamese names into mojibake on a default Windows install.
     *
     * @param  resource  $handle
     */
    public static function writeUtf8Bom($handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
    }
}

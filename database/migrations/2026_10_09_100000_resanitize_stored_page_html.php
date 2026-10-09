<?php

use App\Support\PageHtmlSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PageHtmlSanitizer used to unwrap <style>, <noscript> and other raw-text
 * elements, which turned their text back into live markup: a stored
 * `<style><img onerror=…></style>` came out as an executable <img>. HTML saved
 * before the fix is cleaned again with the fixed sanitizer — the page itself
 * and every revision, since a revision can be restored. Rows that come out
 * unchanged are not written.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sanitizer = new PageHtmlSanitizer();

        foreach (['pages', 'page_revisions'] as $table) {
            DB::table($table)->select(['id', 'published_html'])->orderBy('id')->each(function (object $row) use ($table, $sanitizer): void {
                $cleaned = $this->clean($row->published_html, $sanitizer);
                if ($cleaned !== null) {
                    DB::table($table)->where('id', $row->id)->update(['published_html' => $cleaned]);
                }
            });
        }
    }

    public function down(): void
    {
        // Nothing to restore: the removed markup must not come back.
    }

    /** The column's new raw value, or null when cleaning changes nothing. */
    private function clean(?string $raw, PageHtmlSanitizer $sanitizer): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            // A plain HTML string from before the column held one value per locale.
            $cleaned = $sanitizer->clean($raw);

            return $cleaned === $raw ? null : $cleaned;
        }

        $changed = false;
        foreach ($decoded as $locale => $html) {
            if (is_string($html) && $html !== '') {
                $cleaned = $sanitizer->clean($html);
                $changed = $changed || $cleaned !== $html;
                $decoded[$locale] = $cleaned;
            }
        }

        return $changed ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }
};

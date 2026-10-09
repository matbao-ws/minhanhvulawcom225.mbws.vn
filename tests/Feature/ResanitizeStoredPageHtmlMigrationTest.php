<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HTML stored before the sanitizer stopped unwrapping raw-text elements is
 * cleaned again on deploy, in the page and in every revision.
 */
class ResanitizeStoredPageHtmlMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<p>hi</p><style><img src=x onerror=alert(1)></style>';

    public function test_stored_payloads_are_cleaned_in_pages_and_revisions(): void
    {
        $pageId = DB::table('pages')->insertGetId([
            'title' => json_encode(['vi' => 'Trang']),
            'slug' => json_encode(['vi' => 'trang']),
            'published_html' => json_encode(['vi' => self::PAYLOAD, 'en' => '<p>safe</p>']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $revisionId = DB::table('page_revisions')->insertGetId([
            'page_id' => $pageId,
            'published_html' => json_encode(['vi' => self::PAYLOAD]),
            'created_at' => now(),
        ]);

        $this->runMigration();

        $page = json_decode(DB::table('pages')->where('id', $pageId)->value('published_html'), true);
        $revision = json_decode(DB::table('page_revisions')->where('id', $revisionId)->value('published_html'), true);

        $this->assertSame('<p>hi</p>', $page['vi']);
        $this->assertSame('<p>safe</p>', $page['en']);
        $this->assertSame('<p>hi</p>', $revision['vi']);
    }

    public function test_clean_rows_are_left_untouched(): void
    {
        $raw = json_encode(['vi' => '<p>Xin chào</p>']);
        $pageId = DB::table('pages')->insertGetId([
            'title' => json_encode(['vi' => 'Trang']),
            'slug' => json_encode(['vi' => 'trang']),
            'published_html' => $raw,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame($raw, DB::table('pages')->where('id', $pageId)->value('published_html'));
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_09_100000_resanitize_stored_page_html.php');
        $migration->up();
    }
}

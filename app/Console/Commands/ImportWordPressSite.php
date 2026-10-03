<?php

namespace App\Console\Commands;

use App\Services\WordPressSiteImportService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Copies a live WordPress site's public posts, pages and WooCommerce products into this
 * site, keeping slugs (so that site's Search Console URLs match the copies). For staging
 * and local test sites; refused in production unless forced.
 */
class ImportWordPressSite extends Command
{
    protected $signature = 'wp:import
        {url : The WordPress site, e.g. https://example.vn}
        {--types=posts,pages,products : What to copy}
        {--limit=200 : At most this many items per type}
        {--update : Update records whose slug already exists (default: skip them)}
        {--user-agent= : Client name sent to the site, for hosts that block unknown clients (only with the owner\'s agreement)}
        {--force : Allow running in production}';

    protected $description = 'Copy a WordPress site\'s public content (REST API) into this site, keeping slugs — for test sites';

    public function handle(WordPressSiteImportService $importer): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('This copies another site\'s content: run it on a staging or local site, or pass --force.');

            return self::FAILURE;
        }

        $types = array_map('trim', explode(',', (string) $this->option('types')));

        try {
            $report = $importer->import((string) $this->argument('url'), $types, max(1, (int) $this->option('limit')),
                $this->option('update') ? 'update' : 'skip',
                fn (string $type, string $title, string $result) => $this->line("  [$type] $result: ".mb_substr($title, 0, 80)),
                $this->option('user-agent') ?: null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (WordPressSiteImportService::TYPES as $type) {
            if (isset($report[$type])) {
                $c = $report[$type];
                $this->info("$type: {$c['imported']} imported, {$c['updated']} updated, {$c['skipped']} skipped, {$c['failed']} failed");
            }
        }
        $this->line("images: {$report['images']['downloaded']} downloaded, {$report['images']['failed']} failed");
        foreach (array_slice($report['images']['errors'], 0, 5) as $error) {
            $this->warn('  image: '.$error);
        }
        foreach (array_slice($report['errors'], 0, 10) as $error) {
            $this->warn('  '.$error);
        }
        $this->line('Next: php artisan ai:site-index');

        return self::SUCCESS;
    }
}

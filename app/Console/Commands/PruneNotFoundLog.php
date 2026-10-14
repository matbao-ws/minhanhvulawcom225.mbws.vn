<?php

namespace App\Console\Commands;

use App\Services\Seo\RedirectService;
use Illuminate\Console\Command;

class PruneNotFoundLog extends Command
{
    protected $signature = 'redirects:prune-404 {--days=90 : Remove URLs not seen for this many days}';

    protected $description = 'Forget 404 URLs nobody has requested lately (admin/redirects, "Lỗi 404" tab)';

    public function handle(RedirectService $redirects): int
    {
        $removed = $redirects->pruneNotFound(max(1, (int) $this->option('days')));
        $this->components->info("Removed {$removed} old 404 URL(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs;

use App\Services\Seo\IndexNow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** The URLs one request published, changed or removed, sent to IndexNow (App\Services\Seo\IndexNow). */
class SubmitIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** @param list<string> $urls */
    public function __construct(public readonly array $urls) {}

    public function handle(IndexNow $indexNow): void
    {
        $indexNow->submit($this->urls);
    }
}

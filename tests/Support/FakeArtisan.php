<?php

namespace Tests\Support;

use App\Support\CoreUpdate\ArtisanRunner;
use RuntimeException;

/** Records the artisan commands the core updater would run in a fresh process; fails one on demand. */
class FakeArtisan extends ArtisanRunner
{
    public array $calls = [];

    public ?string $failOn = null;

    public function __construct()
    {
        parent::__construct('');
    }

    public function run(array $arguments, int $timeout = 600): string
    {
        $this->calls[] = implode(' ', $arguments);
        if ($arguments[0] === $this->failOn) {
            throw new RuntimeException('simulated failure');
        }

        return '';
    }

    public function warm(): void {}
}

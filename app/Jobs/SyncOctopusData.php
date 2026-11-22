<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Sync\RunFullSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SyncOctopusData implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function handle(RunFullSync $sync): void
    {
        $sync->handle();
    }
}

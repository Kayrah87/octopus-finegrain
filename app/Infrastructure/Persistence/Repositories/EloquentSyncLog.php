<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Data\SyncStatus;
use App\Application\Ports\SyncLog;
use App\Infrastructure\Persistence\Eloquent\SyncRunRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EloquentSyncLog implements SyncLog
{
    public function start(): int
    {
        return SyncRunRecord::query()->create(['started_at' => CarbonImmutable::now('UTC')])->id;
    }

    public function finish(int $runId, bool $succeeded, string $summary): void
    {
        SyncRunRecord::query()->whereKey($runId)->update([
            'finished_at' => CarbonImmutable::now('UTC'),
            'succeeded' => $succeeded,
            'summary' => Str::limit($summary, 1000),
        ]);
    }

    public function latest(): ?SyncStatus
    {
        $run = SyncRunRecord::query()->latest('id')->first();

        return $run === null ? null : new SyncStatus(
            $run->started_at->toIso8601String(),
            $run->finished_at?->toIso8601String(),
            $run->succeeded,
            $run->summary,
        );
    }
}

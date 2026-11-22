<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Data\SyncStatus;

interface SyncLog
{
    public function start(): int;

    public function finish(int $runId, bool $succeeded, string $summary): void;

    public function latest(): ?SyncStatus;
}

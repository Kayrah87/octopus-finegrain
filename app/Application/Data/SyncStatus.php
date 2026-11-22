<?php

declare(strict_types=1);

namespace App\Application\Data;

final readonly class SyncStatus
{
    public function __construct(
        public string $startedAt,
        public ?string $finishedAt,
        public ?bool $succeeded,
        public ?string $summary,
    ) {}
}

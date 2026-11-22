<?php

declare(strict_types=1);

namespace App\Application\Data;

final readonly class HeatmapCell
{
    public function __construct(
        public string $date,
        public int $slot,
        public float $kwh,
        public float $cost,
    ) {}
}

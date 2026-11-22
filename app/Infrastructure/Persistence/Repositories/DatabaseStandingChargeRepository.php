<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pricing\DailyStandingCharge;
use App\Domain\Pricing\StandingChargeRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseStandingChargeRepository implements StandingChargeRepository
{
    public function save(int $meterPointId, array $charges): void
    {
        $rows = array_map(static fn (DailyStandingCharge $c): array => [
            'meter_point_id' => $meterPointId,
            'local_date' => $c->date,
            'amount_micropence' => $c->amount->toMicropence(),
        ], $charges);

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('daily_standing_charges')->upsert($chunk, ['meter_point_id', 'local_date'], ['amount_micropence']);
        }
    }
}

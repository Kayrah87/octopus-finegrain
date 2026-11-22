<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Application\Ports\OctopusAccountGateway;
use App\Domain\Metering\MeterPoint;
use App\Domain\Metering\MeterPointRepository;

/**
 * Pulls meter points, meters and tariff agreements from the account.
 */
final readonly class SyncAccount
{
    public function __construct(
        private OctopusAccountGateway $account,
        private MeterPointRepository $meterPoints,
    ) {}

    /** @return list<MeterPoint> */
    public function handle(): array
    {
        return array_map(
            fn (MeterPoint $meterPoint): MeterPoint => $this->meterPoints->save($meterPoint),
            $this->account->meterPoints(),
        );
    }
}

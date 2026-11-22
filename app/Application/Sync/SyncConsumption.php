<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Application\Ports\ConsumptionGateway;
use App\Domain\Metering\ConsumptionReading;
use App\Domain\Metering\Interval;
use App\Domain\Metering\IntervalRepository;
use App\Domain\Metering\MeterPoint;
use App\Domain\Metering\ReadingNormaliser;
use App\Domain\Shared\DateRange;
use Carbon\CarbonImmutable;
use Generator;

/**
 * Backfills all history on first run, then catches up incrementally.
 * Re-reads a short overlap because Octopus revises recent readings.
 */
final readonly class SyncConsumption
{
    private const OVERLAP_DAYS = 2;

    private const DEFAULT_BACKFILL_YEARS = 3;

    public function __construct(
        private ConsumptionGateway $gateway,
        private IntervalRepository $intervals,
        private ReadingNormaliser $normaliser,
    ) {}

    /** @return int Number of intervals written. */
    public function handle(MeterPoint $meterPoint): int
    {
        assert($meterPoint->id !== null);

        $range = new DateRange($this->startFor($meterPoint), CarbonImmutable::now('UTC'));
        $written = 0;

        foreach ($meterPoint->meterSerials as $serial) {
            $readings = $this->gateway->readings($meterPoint->fuel, $meterPoint->mpxn, $serial, $range);
            $written += $this->intervals->upsert($meterPoint->id, $this->normalise($meterPoint, $readings));
        }

        return $written;
    }

    private function startFor(MeterPoint $meterPoint): CarbonImmutable
    {
        assert($meterPoint->id !== null);

        $latest = $this->intervals->latestStart($meterPoint->id);

        if ($latest !== null) {
            return $latest->subDays(self::OVERLAP_DAYS);
        }

        return $meterPoint->earliestAgreementStart()?->utc()
            ?? CarbonImmutable::now('UTC')->subYears(self::DEFAULT_BACKFILL_YEARS);
    }

    /**
     * @param  iterable<ConsumptionReading>  $readings
     * @return Generator<int, Interval>
     */
    private function normalise(MeterPoint $meterPoint, iterable $readings): Generator
    {
        foreach ($readings as $reading) {
            yield $this->normaliser->normalise($meterPoint->fuel, $reading);
        }
    }
}

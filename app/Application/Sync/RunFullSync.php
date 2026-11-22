<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Application\Ports\SyncLog;
use Throwable;

/**
 * The whole sync pipeline: account → consumption → tariffs → pricing.
 */
final readonly class RunFullSync
{
    public function __construct(
        private SyncAccount $account,
        private SyncConsumption $consumption,
        private SyncTariffRates $tariffRates,
        private PriceIntervals $pricing,
        private SyncLog $log,
    ) {}

    public function handle(): string
    {
        $runId = $this->log->start();

        try {
            $meterPoints = $this->account->handle();
            $readings = 0;
            $priced = 0;

            foreach ($meterPoints as $meterPoint) {
                $readings += $this->consumption->handle($meterPoint);
            }

            $rates = $this->tariffRates->handle($meterPoints);

            foreach ($meterPoints as $meterPoint) {
                $priced += $this->pricing->handle($meterPoint);
            }

            $summary = sprintf(
                '%d meter point(s), %d reading(s), %d rate(s), %d interval(s) priced.',
                count($meterPoints), $readings, $rates, $priced,
            );
            $this->log->finish($runId, true, $summary);

            return $summary;
        } catch (Throwable $e) {
            $this->log->finish($runId, false, $e->getMessage());

            throw $e;
        }
    }
}

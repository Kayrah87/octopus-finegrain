<?php

declare(strict_types=1);

namespace App\Infrastructure\Octopus;

use App\Application\Ports\TariffGateway;
use App\Domain\Shared\DateRange;
use App\Domain\Shared\Money;
use App\Domain\Tariffs\Rate;
use App\Domain\Tariffs\RateKind;
use App\Domain\Tariffs\TariffCode;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final readonly class RestTariffGateway implements TariffGateway
{
    private const PAGE_SIZE = 1500;

    public function __construct(private OctopusRestClient $client) {}

    public function rates(TariffCode $tariff, RateKind $kind, DateRange $range): iterable
    {
        $path = sprintf(
            'products/%s/%s-tariffs/%s/%s/',
            rawurlencode($tariff->productCode),
            $tariff->fuel->value,
            rawurlencode((string) $tariff),
            self::endpoint($kind),
        );

        $rows = $this->client->paginate($path, [
            'period_from' => $range->from->utc()->toIso8601ZuluString(),
            'period_to' => $range->to->utc()->toIso8601ZuluString(),
            'page_size' => self::PAGE_SIZE,
        ]);

        foreach ($rows as $row) {
            if (($row['payment_method'] ?? null) === 'NON_DIRECT_DEBIT') {
                continue;
            }

            yield new Rate(
                Money::ofPence(BigDecimal::of((string) $row['value_inc_vat'])),
                CarbonImmutable::parse((string) $row['valid_from'])->utc(),
                isset($row['valid_to']) ? CarbonImmutable::parse((string) $row['valid_to'])->utc() : null,
            );
        }
    }

    private static function endpoint(RateKind $kind): string
    {
        return match ($kind) {
            RateKind::Standard => 'standard-unit-rates',
            RateKind::Day => 'day-unit-rates',
            RateKind::Night => 'night-unit-rates',
            RateKind::StandingCharge => 'standing-charges',
        };
    }
}

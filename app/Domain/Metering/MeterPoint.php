<?php

declare(strict_types=1);

namespace App\Domain\Metering;

use Carbon\CarbonImmutable;

/**
 * An MPAN (electricity) or MPRN (gas) with its physical meters and tariff history.
 */
final readonly class MeterPoint
{
    /**
     * @param  list<string>  $meterSerials
     * @param  list<Agreement>  $agreements
     */
    public function __construct(
        public ?int $id,
        public Fuel $fuel,
        public FlowDirection $direction,
        public string $mpxn,
        public array $meterSerials,
        public array $agreements,
    ) {}

    public function withId(int $id): self
    {
        return new self($id, $this->fuel, $this->direction, $this->mpxn, $this->meterSerials, $this->agreements);
    }

    public function agreementAt(CarbonImmutable $instant): ?Agreement
    {
        foreach ($this->agreements as $agreement) {
            if ($agreement->covers($instant)) {
                return $agreement;
            }
        }

        return null;
    }

    public function earliestAgreementStart(): ?CarbonImmutable
    {
        $starts = array_map(static fn (Agreement $a): CarbonImmutable => $a->validFrom, $this->agreements);

        return $starts === [] ? null : min($starts);
    }

    public function label(): string
    {
        return $this->fuel === Fuel::Gas
            ? 'Gas'
            : ($this->direction === FlowDirection::Export ? 'Electricity export' : 'Electricity');
    }
}

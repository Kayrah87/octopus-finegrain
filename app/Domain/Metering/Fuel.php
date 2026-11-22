<?php

declare(strict_types=1);

namespace App\Domain\Metering;

enum Fuel: string
{
    case Electricity = 'electricity';
    case Gas = 'gas';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => 'Electricity',
            self::Gas => 'Gas',
        };
    }
}

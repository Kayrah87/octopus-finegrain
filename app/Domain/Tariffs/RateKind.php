<?php

declare(strict_types=1);

namespace App\Domain\Tariffs;

enum RateKind: string
{
    case Standard = 'standard';
    case Day = 'day';
    case Night = 'night';
    case StandingCharge = 'standing';
}

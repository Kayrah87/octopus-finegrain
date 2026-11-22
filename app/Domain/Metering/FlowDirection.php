<?php

declare(strict_types=1);

namespace App\Domain\Metering;

enum FlowDirection: string
{
    case Import = 'import';
    case Export = 'export';
}

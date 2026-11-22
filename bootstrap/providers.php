<?php

declare(strict_types=1);

use App\Infrastructure\OctopusServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    OctopusServiceProvider::class,
];

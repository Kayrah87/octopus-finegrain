<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Dashboard\ComparePeriods;
use App\Http\Requests\CompareRequest;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CompareController
{
    public function __invoke(CompareRequest $request, ComparePeriods $compare): Response
    {
        return Inertia::render('Compare', $compare->handle(
            $request->meter(),
            $request->granularity(),
            $request->periods(),
        ));
    }
}

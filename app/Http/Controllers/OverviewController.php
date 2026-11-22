<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Dashboard\GetOverview;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OverviewController
{
    public function __invoke(GetOverview $overview): Response
    {
        return Inertia::render('Overview', $overview->handle());
    }
}

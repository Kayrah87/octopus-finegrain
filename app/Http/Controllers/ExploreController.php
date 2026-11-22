<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Dashboard\ExploreUsage;
use App\Http\Requests\ExploreRequest;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ExploreController
{
    public function __invoke(ExploreRequest $request, ExploreUsage $explore): Response
    {
        return Inertia::render('Explore', $explore->handle(
            $request->meter(),
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->granularity(),
        ));
    }
}

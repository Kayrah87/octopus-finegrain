<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Dashboard\GetTariffHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TariffsController
{
    public function __invoke(Request $request, GetTariffHistory $tariffs): Response
    {
        $request->validate(['meter' => ['nullable', 'integer', 'exists:meter_points,id']]);

        return Inertia::render('Tariffs', $tariffs->handle($request->filled('meter') ? $request->integer('meter') : null));
    }
}

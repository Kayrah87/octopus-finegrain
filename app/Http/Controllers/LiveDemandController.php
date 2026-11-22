<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Live\GetLiveDemand;
use Illuminate\Http\JsonResponse;
use Throwable;

final readonly class LiveDemandController
{
    public function __invoke(GetLiveDemand $live): JsonResponse
    {
        try {
            return response()->json($live->handle());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['enabled' => true, 'latest' => null, 'points' => [], 'error' => 'Live data is unavailable right now.'], 503);
        }
    }
}

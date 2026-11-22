<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncOctopusData;
use Illuminate\Http\RedirectResponse;

final readonly class SyncController
{
    public function __invoke(): RedirectResponse
    {
        SyncOctopusData::dispatch();

        return back()->with('status', 'Sync started. New data will appear in a minute or two.');
    }
}

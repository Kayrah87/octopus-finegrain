<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Auth\DashboardGate;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly DashboardGate $gate) {}

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'timezone' => config('dashboard.timezone'),
                'loginEnabled' => $this->gate->enabled(),
                'configured' => filled(config('octopus.api_key')) && filled(config('octopus.account_number')),
            ],
            'flash' => [
                'status' => fn (): mixed => $request->session()->get('status'),
            ],
        ];
    }
}

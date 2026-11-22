<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Auth\DashboardGate;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class LoginController
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private DashboardGate $gate) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if ($this->gate->isAuthenticated($request->session())) {
            return redirect()->route('overview');
        }

        return Inertia::render('auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => sprintf('Too many attempts. Try again in %d seconds.', RateLimiter::availableIn($key)),
            ]);
        }

        if (! $this->gate->attempt($request->string('username')->toString(), $request->string('password')->toString())) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['username' => 'Those details are not right.']);
        }

        RateLimiter::clear($key);
        $this->gate->login($request->session());

        return redirect()->intended(route('overview'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->gate->logout($request->session());

        return redirect()->route($this->gate->enabled() ? 'login' : 'overview');
    }
}

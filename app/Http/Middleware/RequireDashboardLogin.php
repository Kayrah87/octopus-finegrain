<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Auth\DashboardGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireDashboardLogin
{
    public function __construct(private DashboardGate $gate) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->gate->isAuthenticated($request->session())) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(401);
        }

        return redirect()->guest(route('login'));
    }
}

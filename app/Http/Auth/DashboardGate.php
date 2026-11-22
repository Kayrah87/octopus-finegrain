<?php

declare(strict_types=1);

namespace App\Http\Auth;

use Illuminate\Contracts\Session\Session;

/**
 * Optional single-user login, configured purely from env. Disabled unless
 * both DASHBOARD_USERNAME and DASHBOARD_PASSWORD are set.
 */
final readonly class DashboardGate
{
    private const SESSION_KEY = 'dashboard.authenticated';

    public function __construct(
        private ?string $username,
        private ?string $password,
    ) {}

    public function enabled(): bool
    {
        return filled($this->username) && filled($this->password);
    }

    public function attempt(string $username, string $password): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        // Evaluate both comparisons so timing does not reveal which part was wrong.
        $userOk = hash_equals((string) $this->username, $username);
        $passOk = hash_equals((string) $this->password, $password);

        return $userOk && $passOk;
    }

    public function isAuthenticated(Session $session): bool
    {
        return ! $this->enabled() || $session->get(self::SESSION_KEY) === true;
    }

    public function login(Session $session): void
    {
        $session->regenerate();
        $session->put(self::SESSION_KEY, true);
    }

    public function logout(Session $session): void
    {
        $session->invalidate();
        $session->regenerateToken();
    }
}

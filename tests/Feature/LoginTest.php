<?php

declare(strict_types=1);

use App\Http\Auth\DashboardGate;

function enableLogin(): void
{
    app()->instance(DashboardGate::class, new DashboardGate('kay', 'secret-pass'));
}

it('is open when no credentials are configured', function (): void {
    $this->get('/')->assertOk();
    $this->get('/login')->assertRedirect('/');
});

it('stays open when only one credential is set', function (): void {
    app()->instance(DashboardGate::class, new DashboardGate('kay', null));

    $this->get('/')->assertOk();
});

it('redirects guests to login when credentials are set', function (): void {
    enableLogin();

    $this->get('/')->assertRedirect('/login');
    $this->get('/explore')->assertRedirect('/login');
    $this->getJson('/live')->assertUnauthorized();
    $this->get('/login')->assertOk();
});

it('logs in with the right details and out again', function (): void {
    enableLogin();

    $this->post('/login', ['username' => 'kay', 'password' => 'secret-pass'])->assertRedirect('/');
    $this->get('/')->assertOk();

    $this->post('/logout')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/login');
});

it('rejects wrong details and rate-limits attempts', function (): void {
    enableLogin();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['username' => 'kay', 'password' => 'nope'])->assertSessionHasErrors('username');
    }

    $this->post('/login', ['username' => 'kay', 'password' => 'secret-pass'])
        ->assertSessionHasErrors(['username' => 'Too many attempts. Try again in 60 seconds.']);
    $this->get('/')->assertRedirect('/login');
});

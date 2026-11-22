<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\LiveDemandController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TariffsController;
use App\Http\Middleware\RequireDashboardLogin;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware(RequireDashboardLogin::class)->group(function (): void {
    Route::get('/', OverviewController::class)->name('overview');
    Route::get('/explore', ExploreController::class)->name('explore');
    Route::get('/compare', CompareController::class)->name('compare');
    Route::get('/tariffs', TariffsController::class)->name('tariffs');
    Route::get('/live', LiveDemandController::class)->name('live');
    Route::post('/sync', SyncController::class)->middleware('throttle:6,1')->name('sync');
});

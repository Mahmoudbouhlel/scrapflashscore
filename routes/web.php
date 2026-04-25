<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BettingTipsController;
use App\Http\Controllers\BetSlipGeneratorController;
use App\Http\Controllers\GoalPredictionsController;
use App\Http\Controllers\SoloPredictionsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('betting-tips', BettingTipsController::class)
    ->middleware(['auth', 'verified'])
    ->name('betting-tips');

Route::get('betslip-generator', BetSlipGeneratorController::class)
    ->middleware(['auth', 'verified'])
    ->name('betslip-generator');

Route::get('goal-predictions', GoalPredictionsController::class)
    ->middleware(['auth', 'verified'])
    ->name('goal-predictions');

Route::get('solo-predictions', SoloPredictionsController::class)
    ->middleware(['auth', 'verified'])
    ->name('solo-predictions');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

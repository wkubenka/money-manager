<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('spending-plans', 'spending-plans/dashboard');

    Route::livewire('spending-plans/dashboard', 'pages::spending-plans.dashboard')
        ->name('spending-plans.dashboard');

    Route::livewire('spending-plans/create', 'pages::spending-plans.create')
        ->name('spending-plans.create');

    Route::livewire('spending-plans/{spendingPlan}', 'pages::spending-plans.show')
        ->name('spending-plans.show');

    Route::livewire('spending-plans/{spendingPlan}/edit', 'pages::spending-plans.edit')
        ->name('spending-plans.edit');
});

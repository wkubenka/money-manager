<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::livewire('admin', 'pages::admin.dashboard')
        ->name('admin.dashboard');
});

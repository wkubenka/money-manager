<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('expenses', 'pages::expenses.index')
        ->name('expenses.index');
});

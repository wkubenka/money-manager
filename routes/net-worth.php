<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('net-worth', 'pages::net-worth.index')
        ->name('net-worth.index');
});

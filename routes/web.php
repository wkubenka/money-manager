<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::view('/privacy', 'privacy')->name('privacy');

Route::get('/offline', function () {
    return view('offline');
});

Route::livewire('dashboard', 'pages::dashboard')
    ->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/spending-plans.php';
require __DIR__.'/net-worth.php';
require __DIR__.'/expenses.php';

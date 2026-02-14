<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('guest')->name('home');

if (app()->environment('local')) {
    Route::post('/dev/login', function () {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')],
        );

        auth()->login($user);

        return redirect()->route('dashboard');
    })->middleware('guest')->name('dev.login');
}

Route::view('/privacy', 'privacy')->name('privacy');

Route::get('/offline', function () {
    return view('offline');
});

Route::livewire('dashboard', 'pages::dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/spending-plans.php';
require __DIR__.'/net-worth.php';
require __DIR__.'/expenses.php';

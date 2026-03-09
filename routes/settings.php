<?php

use Illuminate\Support\Facades\Route;

Route::redirect('settings', 'settings/appearance');

Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
Route::livewire('settings/data', 'pages::settings.data')->name('data.edit');

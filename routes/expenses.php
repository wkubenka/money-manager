<?php

use Illuminate\Support\Facades\Route;

Route::livewire('expenses', 'pages::expenses.index')
    ->name('expenses.index');

<?php

use App\Models\SpendingPlan;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('dashboard is accessible', function () {
    $this->get(route('spending-plans.dashboard'))
        ->assertOk();
});

test('dashboard shows plans', function () {
    $plan = SpendingPlan::factory()->create([
        'name' => 'My Test Plan',
    ]);

    $this->get(route('spending-plans.dashboard'))
        ->assertSee('My Test Plan');
});

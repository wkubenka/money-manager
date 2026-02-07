<?php

use App\Models\SpendingPlan;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('spending-plans.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spending-plans.dashboard'))
        ->assertOk();
});

test('dashboard shows user plans', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Test Budget',
    ]);

    $this->actingAs($user)
        ->get(route('spending-plans.dashboard'))
        ->assertSee('My Test Budget');
});

test('dashboard does not show other users plans', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    SpendingPlan::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Secret Budget',
    ]);

    $this->actingAs($user)
        ->get(route('spending-plans.dashboard'))
        ->assertDontSee('Secret Budget');
});

<?php

use App\Models\SpendingPlan;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('first plan created is automatically marked as current', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My First Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    expect(SpendingPlan::first()->is_current)->toBeTrue();
});

test('second plan created is not automatically marked as current', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->current()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'Second Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    $second = SpendingPlan::where('name', 'Second Plan')->first();
    expect($second->is_current)->toBeFalse();
});

test('deleting a plan marks the remaining plan as current', function () {
    $user = User::factory()->create();
    $planA = SpendingPlan::factory()->current()->create(['user_id' => $user->id]);
    $planB = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.show', ['spendingPlan' => $planA])
        ->call('deletePlan');

    $planB->refresh();
    expect($planB->is_current)->toBeTrue();
});

test('deleting a plan does not change current when multiple remain', function () {
    $user = User::factory()->create();
    $planA = SpendingPlan::factory()->create(['user_id' => $user->id]);
    $planB = SpendingPlan::factory()->create(['user_id' => $user->id]);
    $planC = SpendingPlan::factory()->current()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.show', ['spendingPlan' => $planA])
        ->call('deletePlan');

    $planB->refresh();
    $planC->refresh();
    expect($planB->is_current)->toBeFalse();
    expect($planC->is_current)->toBeTrue();
});

test('user can mark a plan as current', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $plan->id);

    $plan->refresh();
    expect($plan->is_current)->toBeTrue();
});

test('marking a plan as current unmarks the previous one', function () {
    $user = User::factory()->create();
    $planA = SpendingPlan::factory()->current()->create(['user_id' => $user->id]);
    $planB = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $planB->id);

    $planA->refresh();
    $planB->refresh();
    expect($planA->is_current)->toBeFalse();
    expect($planB->is_current)->toBeTrue();
});

test('user cannot delete their only plan', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->current()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.show', ['spendingPlan' => $plan])
        ->call('deletePlan')
        ->assertStatus(422);

    expect(SpendingPlan::where('user_id', $user->id)->count())->toBe(1);
});

test('deleting current plan marks oldest remaining as current', function () {
    $user = User::factory()->create();
    $planA = SpendingPlan::factory()->current()->create(['user_id' => $user->id]);
    $planB = SpendingPlan::factory()->create(['user_id' => $user->id]);
    $planC = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.show', ['spendingPlan' => $planA])
        ->call('deletePlan');

    $planB->refresh();
    $planC->refresh();
    expect($planB->is_current)->toBeTrue();
    expect($planC->is_current)->toBeFalse();
});

test('user cannot mark another users plan as current', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $plan->id)
        ->assertForbidden();
});

test('only one plan per user can be current', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->current()->create(['user_id' => $user->id]);
    SpendingPlan::factory()->current()->create(['user_id' => $user->id]);
    $planC = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $planC->id);

    expect(SpendingPlan::where('user_id', $user->id)->where('is_current', true)->count())->toBe(1);
});

test('current plan from another user is not affected', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherPlan = SpendingPlan::factory()->current()->create(['user_id' => $otherUser->id]);
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $plan->id);

    $otherPlan->refresh();
    expect($otherPlan->is_current)->toBeTrue();
});

test('spending plans dashboard shows current badge', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'name' => 'My Active Plan',
    ]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->assertSee('My Active Plan')
        ->assertSee('Current');
});

<?php

use App\Models\SpendingPlan;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('first plan created is automatically marked as current', function () {
    Livewire::test('pages::spending-plans.create')
        ->set('name', 'My First Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    expect(SpendingPlan::first()->is_current)->toBeTrue();
});

test('second plan created is not automatically marked as current', function () {
    SpendingPlan::factory()->current()->create();

    Livewire::test('pages::spending-plans.create')
        ->set('name', 'Second Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    $second = SpendingPlan::where('name', 'Second Plan')->first();
    expect($second->is_current)->toBeFalse();
});

test('deleting a plan marks the remaining plan as current', function () {
    $planA = SpendingPlan::factory()->current()->create();
    $planB = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.show', ['spendingPlan' => $planA])
        ->call('deletePlan');

    $planB->refresh();
    expect($planB->is_current)->toBeTrue();
});

test('deleting a plan does not change current when multiple remain', function () {
    $planA = SpendingPlan::factory()->create();
    $planB = SpendingPlan::factory()->create();
    $planC = SpendingPlan::factory()->current()->create();

    Livewire::test('pages::spending-plans.show', ['spendingPlan' => $planA])
        ->call('deletePlan');

    $planB->refresh();
    $planC->refresh();
    expect($planB->is_current)->toBeFalse();
    expect($planC->is_current)->toBeTrue();
});

test('user can mark a plan as current', function () {
    $plan = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $plan->id);

    $plan->refresh();
    expect($plan->is_current)->toBeTrue();
});

test('marking a plan as current unmarks the previous one', function () {
    $planA = SpendingPlan::factory()->current()->create();
    $planB = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $planB->id);

    $planA->refresh();
    $planB->refresh();
    expect($planA->is_current)->toBeFalse();
    expect($planB->is_current)->toBeTrue();
});

test('user cannot delete their only plan', function () {
    $plan = SpendingPlan::factory()->current()->create();

    Livewire::test('pages::spending-plans.show', ['spendingPlan' => $plan])
        ->call('deletePlan')
        ->assertStatus(422);

    expect(SpendingPlan::count())->toBe(1);
});

test('deleting current plan marks oldest remaining as current', function () {
    $planA = SpendingPlan::factory()->current()->create();
    $planB = SpendingPlan::factory()->create();
    $planC = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.show', ['spendingPlan' => $planA])
        ->call('deletePlan');

    $planB->refresh();
    $planC->refresh();
    expect($planB->is_current)->toBeTrue();
    expect($planC->is_current)->toBeFalse();
});

test('only one plan can be current', function () {
    SpendingPlan::factory()->current()->create();
    SpendingPlan::factory()->current()->create();
    $planC = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $planC->id);

    expect(SpendingPlan::where('is_current', true)->count())->toBe(1);
});

test('spending plans dashboard shows current badge', function () {
    SpendingPlan::factory()->current()->create([
        'name' => 'My Active Plan',
    ]);

    Livewire::test('pages::spending-plans.dashboard')
        ->assertSee('My Active Plan')
        ->assertSee('Current');
});

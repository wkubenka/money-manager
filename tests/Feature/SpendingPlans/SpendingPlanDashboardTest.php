<?php

use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('dashboard is accessible', function () {
    $this->get(route('spending-plans.dashboard'))
        ->assertOk();
});

test('dashboard shows plans', function () {
    SpendingPlan::factory()->create(['name' => 'My Test Plan']);

    $this->get(route('spending-plans.dashboard'))
        ->assertSee('My Test Plan');
});

test('dashboard shows empty state when no plans exist', function () {
    Livewire::test('pages::spending-plans.dashboard')
        ->assertSee('No spending plans yet')
        ->assertSee('Create Your First Plan');
});

test('dashboard shows current badge on current plan', function () {
    SpendingPlan::factory()->current()->create(['name' => 'Active Plan']);

    Livewire::test('pages::spending-plans.dashboard')
        ->assertSee('Active Plan')
        ->assertSee('Current');
});

test('dashboard shows category percentages for each plan', function () {
    $plan = SpendingPlan::factory()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => 'fixed_costs',
        'amount' => 250000,
    ]);

    Livewire::test('pages::spending-plans.dashboard')
        ->assertSee('Fixed Costs');
});

test('mark a plan as current', function () {
    $plan = SpendingPlan::factory()->create(['name' => 'Plan A', 'is_current' => false]);

    Livewire::test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $plan->id);

    expect($plan->fresh()->is_current)->toBeTrue();
});

test('marking a plan as current unmarks the previous current plan', function () {
    $oldCurrent = SpendingPlan::factory()->current()->create(['name' => 'Old']);
    $newCurrent = SpendingPlan::factory()->create(['name' => 'New']);

    Livewire::test('pages::spending-plans.dashboard')
        ->call('markAsCurrent', $newCurrent->id);

    expect($oldCurrent->fresh()->is_current)->toBeFalse();
    expect($newCurrent->fresh()->is_current)->toBeTrue();
});

test('copy a plan redirects to edit page', function () {
    $plan = SpendingPlan::factory()->create(['name' => 'Original']);

    Livewire::test('pages::spending-plans.dashboard')
        ->call('copyPlan', $plan->id)
        ->assertRedirect();

    expect(SpendingPlan::count())->toBe(2);
    expect(SpendingPlan::where('name', 'Copy of Original')->exists())->toBeTrue();
});

test('copy a plan duplicates its items', function () {
    $plan = SpendingPlan::factory()->create(['name' => 'Original']);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'name' => 'Rent',
        'amount' => 150000,
        'category' => 'fixed_costs',
    ]);

    Livewire::test('pages::spending-plans.dashboard')
        ->call('copyPlan', $plan->id);

    $copy = SpendingPlan::where('name', 'Copy of Original')->first();
    expect($copy->items)->toHaveCount(1);
    expect($copy->items->first()->name)->toBe('Rent');
    expect($copy->items->first()->amount)->toBe(150000);
});

test('create new plan button is hidden at limit', function () {
    SpendingPlan::factory()->count(SpendingPlan::MAX_PER_USER)->create();

    Livewire::test('pages::spending-plans.dashboard')
        ->assertDontSee('Create New Plan');
});

test('copy button is hidden at limit', function () {
    SpendingPlan::factory()->count(SpendingPlan::MAX_PER_USER)->create();

    Livewire::test('pages::spending-plans.dashboard')
        ->assertDontSeeHtml('wire:click="copyPlan');
});

test('dashboard shows monthly income for each plan', function () {
    SpendingPlan::factory()->create([
        'name' => 'Budget Plan',
        'monthly_income' => 450000,
    ]);

    Livewire::test('pages::spending-plans.dashboard')
        ->assertSee('$4,500/mo');
});

<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can copy a plan from the dashboard', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Plan',
        'monthly_income' => 500000,
        'gross_monthly_income' => 700000,
        'pre_tax_investments' => 50000,
        'is_current' => true,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 150000,
        'sort_order' => 0,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Investments,
        'name' => '401k',
        'amount' => 50000,
        'sort_order' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('copyPlan', $plan->id);

    $copy = SpendingPlan::where('name', 'Copy of My Plan')->first();
    expect($copy)->not->toBeNull();
    expect($copy->monthly_income)->toBe(500000);
    expect($copy->gross_monthly_income)->toBe(700000);
    expect($copy->pre_tax_investments)->toBe(50000);
    expect($copy->is_current)->toBeFalse();
    expect($copy->items)->toHaveCount(2);

    $rentItem = $copy->items->where('name', 'Rent')->first();
    expect($rentItem->category)->toBe(SpendingCategory::FixedCosts);
    expect($rentItem->amount)->toBe(150000);
    expect($rentItem->sort_order)->toBe(0);
});

test('user can copy a plan from the show page', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original',
        'monthly_income' => 400000,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Savings,
        'name' => 'Emergency Fund',
        'amount' => 25000,
        'sort_order' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.show', ['spendingPlan' => $plan])
        ->call('copyPlan');

    $copy = SpendingPlan::where('name', 'Copy of Original')->first();
    expect($copy)->not->toBeNull();
    expect($copy->monthly_income)->toBe(400000);
    expect($copy->items)->toHaveCount(1);
    expect($copy->items->first()->name)->toBe('Emergency Fund');
});

test('copied plan is not marked as current', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'is_current' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('copyPlan', $plan->id);

    $copy = SpendingPlan::where('id', '!=', $plan->id)->where('user_id', $user->id)->first();
    expect($copy->is_current)->toBeFalse();
});

test('user cannot copy another users plan from dashboard', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('copyPlan', $plan->id)
        ->assertForbidden();
});

test('user cannot copy another users plan from show page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertForbidden();
});

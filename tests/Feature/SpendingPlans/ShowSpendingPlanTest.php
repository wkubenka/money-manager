<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('owner can view their plan', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertOk();
});

test('user cannot view another users plan', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertForbidden();
});

test('plan detail shows all categories', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->get(route('spending-plans.show', $plan));

    foreach (SpendingCategory::cases() as $category) {
        $response->assertSee($category->label());
    }
});

test('plan detail shows line items', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Monthly Rent',
        'amount' => 150000,
    ]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertSee('Monthly Rent')
        ->assertSee('1,500');
});

test('fixed costs shows miscellaneous line with correct amount', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'monthly_income' => 500000,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 200000,
    ]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertSee('Miscellaneous')
        ->assertSee('15%')
        ->assertSee('300');
});

test('fixed costs total includes miscellaneous buffer', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 200000,
    ]);

    $plan->load('items');

    expect($plan->fixedCostsMiscellaneous())->toBe(30000);
    expect($plan->categoryTotal(SpendingCategory::FixedCosts))->toBe(230000);
    expect($plan->categoryPercent(SpendingCategory::FixedCosts))->toBe(46.0);
});

test('guilt free accounts for fixed costs miscellaneous', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 200000,
    ]);

    $plan->load('items');

    expect($plan->plannedTotal())->toBe(230000);
    expect($plan->categoryTotal(SpendingCategory::GuiltFree))->toBe(270000);
});

test('miscellaneous line is hidden when percentage is zero', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 200000,
    ]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertDontSee('Miscellaneous');
});

test('miscellaneous uses plan configured percentage', function () {
    $plan = SpendingPlan::factory()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 10,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 200000,
    ]);

    $plan->load('items');

    expect($plan->fixedCostsMiscellaneous())->toBe(20000);
    expect($plan->categoryTotal(SpendingCategory::FixedCosts))->toBe(220000);
});

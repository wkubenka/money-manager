<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('category total is calculated correctly', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 100000,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 50000,
    ]);

    $plan->load('items');
    expect($plan->categoryTotal(SpendingCategory::FixedCosts))->toBe(150000);
});

test('category percent is calculated correctly', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000,
    ]);

    $plan->load('items');
    expect($plan->categoryPercent(SpendingCategory::FixedCosts))->toBe(50.0);
});

test('category percent returns zero when income is zero', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 0]);

    $plan->load('items');
    expect($plan->categoryPercent(SpendingCategory::FixedCosts))->toBe(0.0);
});

test('guilt free total is auto-calculated from remaining income', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Investments,
        'amount' => 50000,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Savings,
        'amount' => 50000,
    ]);

    $plan->load('items');
    expect($plan->categoryTotal(SpendingCategory::GuiltFree))->toBe(150000);
    expect($plan->categoryPercent(SpendingCategory::GuiltFree))->toBe(30.0);
});

test('planned total sums non-guilt-free categories', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 300000,
    ]);

    $plan->load('items');
    expect($plan->plannedTotal())->toBe(300000);
});

test('spending category ideal ranges are correct', function () {
    expect(SpendingCategory::FixedCosts->idealRange())->toBe([50, 60]);
    expect(SpendingCategory::Investments->idealRange())->toBe([10, 10]);
    expect(SpendingCategory::Savings->idealRange())->toBe([5, 10]);
    expect(SpendingCategory::GuiltFree->idealRange())->toBe([20, 35]);
});

test('is within ideal returns true when in range', function () {
    expect(SpendingCategory::FixedCosts->isWithinIdeal(55.0))->toBeTrue();
    expect(SpendingCategory::Investments->isWithinIdeal(10.0))->toBeTrue();
    expect(SpendingCategory::Savings->isWithinIdeal(7.5))->toBeTrue();
    expect(SpendingCategory::GuiltFree->isWithinIdeal(25.0))->toBeTrue();
});

test('fixed costs below ideal range is still acceptable', function () {
    expect(SpendingCategory::FixedCosts->isWithinIdeal(40.0))->toBeTrue();
    expect(SpendingCategory::FixedCosts->isWithinIdeal(65.0))->toBeFalse();
});

test('is within ideal returns false when outside range', function () {
    expect(SpendingCategory::Investments->isWithinIdeal(5.0))->toBeFalse();
    expect(SpendingCategory::Savings->isWithinIdeal(15.0))->toBeFalse();
    expect(SpendingCategory::GuiltFree->isWithinIdeal(40.0))->toBeFalse();
});

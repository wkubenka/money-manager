<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('plan page is accessible', function () {
    $plan = SpendingPlan::factory()->create();

    $this->get(route('spending-plans.show', $plan))
        ->assertOk();
});

test('plan detail shows all categories', function () {
    $plan = SpendingPlan::factory()->create();

    $response = $this->get(route('spending-plans.show', $plan));

    foreach (SpendingCategory::spendingCases() as $category) {
        $response->assertSee($category->label());
    }
});

test('plan detail shows line items', function () {
    $plan = SpendingPlan::factory()->create();

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Monthly Rent',
        'amount' => 150000,
    ]);

    $this->get(route('spending-plans.show', $plan))
        ->assertSee('Monthly Rent')
        ->assertSee('1,500');
});

test('fixed costs shows miscellaneous line with correct amount', function () {
    $plan = SpendingPlan::factory()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 15,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 200000,
    ]);

    $this->get(route('spending-plans.show', $plan))
        ->assertSee('Miscellaneous')
        ->assertSee('15%')
        ->assertSee('300');
});

test('fixed costs total includes miscellaneous buffer', function () {
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000, 'fixed_costs_misc_percent' => 15]);

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
    $plan = SpendingPlan::factory()->create(['monthly_income' => 500000, 'fixed_costs_misc_percent' => 15]);

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
    $plan = SpendingPlan::factory()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 200000,
    ]);

    $this->get(route('spending-plans.show', $plan))
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

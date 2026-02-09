<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('create page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spending-plans.create'))
        ->assertOk();
});

test('user can create a spending plan', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    expect(SpendingPlan::where('user_id', $user->id)->count())->toBe(1);
    expect(SpendingPlan::first()->name)->toBe('My Plan');
});

test('name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', '')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasErrors(['name' => 'required']);
});

test('monthly income is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '')
        ->call('createPlan')
        ->assertHasErrors(['monthly_income' => 'required']);
});

test('monthly income must be positive', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '0')
        ->call('createPlan')
        ->assertHasErrors(['monthly_income' => 'min']);
});

test('income is stored as cents', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan');

    expect(SpendingPlan::first()->monthly_income)->toBe(500000);
});

test('gross income and pre-tax investments are stored as cents', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->set('gross_monthly_income', '7000.00')
        ->set('pre_tax_investments', '500.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    $plan = SpendingPlan::first();
    expect($plan->gross_monthly_income)->toBe(700000);
    expect($plan->pre_tax_investments)->toBe(50000);
});

test('gross income and pre-tax investments are optional', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    $plan = SpendingPlan::first();
    expect($plan->gross_monthly_income)->toBe(0);
    expect($plan->pre_tax_investments)->toBe(0);
});

test('plan is created with default miscellaneous percentage', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    expect(SpendingPlan::first()->fixed_costs_misc_percent)->toBe(15);
});

test('plan can be created with custom miscellaneous percentage', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->set('fixed_costs_misc_percent', '10')
        ->call('createPlan')
        ->assertHasNoErrors();

    expect(SpendingPlan::first()->fixed_costs_misc_percent)->toBe(10);
});

test('miscellaneous percentage cannot exceed 30', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->set('fixed_costs_misc_percent', '31')
        ->call('createPlan')
        ->assertHasErrors(['fixed_costs_misc_percent' => 'max']);
});

test('user cannot create more than max plans', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->count(SpendingPlan::MAX_PER_USER)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'One Too Many')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertStatus(422);

    expect(SpendingPlan::where('user_id', $user->id)->count())->toBe(SpendingPlan::MAX_PER_USER);
});

test('creating with defaults populates default items', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->set('includeDefaults', true)
        ->call('createPlan')
        ->assertHasNoErrors();

    $plan = SpendingPlan::first();
    $expectedCount = collect(SpendingPlan::DEFAULT_ITEMS)->flatten()->count();

    expect($plan->items)->toHaveCount($expectedCount);

    // Spot-check items exist with correct categories
    expect(SpendingPlanItem::where('spending_plan_id', $plan->id)
        ->where('name', 'Rent / Mortgage')
        ->where('category', SpendingCategory::FixedCosts)
        ->exists())->toBeTrue();

    expect(SpendingPlanItem::where('spending_plan_id', $plan->id)
        ->where('name', 'Post-Tax Retirement Savings')
        ->where('category', SpendingCategory::Investments)
        ->exists())->toBeTrue();

    expect(SpendingPlanItem::where('spending_plan_id', $plan->id)
        ->where('name', 'Vacations')
        ->where('category', SpendingCategory::Savings)
        ->exists())->toBeTrue();

    // All items should have zero amount
    expect($plan->items->every(fn ($item) => $item->amount === 0))->toBeTrue();
});

test('creating without defaults produces zero items', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->set('includeDefaults', false)
        ->call('createPlan')
        ->assertHasNoErrors();

    $plan = SpendingPlan::first();
    expect($plan->items)->toHaveCount(0);
});

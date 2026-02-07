<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\NetWorthAccount;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard displays net worth', function () {
    $user = User::factory()->create();

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Assets,
        'balance' => 50000000, // $500,000
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Debt,
        'balance' => 20000000, // $200,000
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Net Worth')
        ->assertSee('$300,000')
        ->assertSee('Assets')
        ->assertSee('Debt');
});

test('dashboard shows zero net worth with no accounts', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('$0');
});

test('dashboard has manage accounts link', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Manage');
});

test('dashboard shows current spending plan', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'name' => 'My Active Plan',
        'monthly_income' => 500000,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Current Plan')
        ->assertSee('Fixed Costs');
});

test('dashboard shows prompt when no current plan', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('No current spending plan')
        ->assertSee('Choose a Plan');
});

test('dashboard shows negative guilt-free spending in red', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 500000, // $5,000
    ]);
    // Overspend: $3,000 + $1,500 + $1,000 = $5,500 > $5,000
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 300000,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Investments,
        'amount' => 150000,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Savings,
        'amount' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Guilt-Free Spending')
        ->assertSeeHtml('text-red-600');
});

test('dashboard renders with zero monthly income plan', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertOk()
        ->assertSee('Current Plan')
        ->assertSee('0%');
});

test('dashboard shows rounded percentages', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 300000, // $3,000
    ]);
    // $1,000 / $3,000 = 33.333...% → rounds to 33%
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('33%');
});

test('deleting a user cascades to spending plans and items', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);
    SpendingPlanItem::factory()->count(2)->create(['spending_plan_id' => $plan->id]);

    $userId = $user->id;
    $planId = $plan->id;

    $user->delete();

    expect(SpendingPlan::where('user_id', $userId)->count())->toBe(0);
    expect(SpendingPlanItem::where('spending_plan_id', $planId)->count())->toBe(0);
});

test('dashboard does not show non-current plans', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'name' => 'Not Current Plan',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertDontSee('Not Current Plan')
        ->assertSee('No current spending plan');
});

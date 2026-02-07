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
        ->assertSee('Manage Accounts');
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
        ->assertSee('My Active Plan')
        ->assertSee('Fixed Costs');
});

test('dashboard shows prompt when no current plan', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('No current spending plan')
        ->assertSee('Choose a Plan');
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

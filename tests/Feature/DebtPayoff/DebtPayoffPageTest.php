<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\NetWorthAccount;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('debt payoff page is accessible when debts exist', function () {
    NetWorthAccount::factory()->debt()->create();

    $this->get(route('debt-payoff.index'))
        ->assertOk();
});

test('sidebar shows debt payoff link when debts exist', function () {
    NetWorthAccount::factory()->debt()->create();

    $this->get(route('dashboard'))
        ->assertSee('Debt Payoff');
});

test('sidebar hides debt payoff link when no debts exist', function () {
    $this->get(route('dashboard'))
        ->assertDontSee('Debt Payoff');
});

test('sidebar hides debt payoff link when only non-debt accounts exist', function () {
    NetWorthAccount::factory()->category(AccountCategory::Assets)->create();

    $this->get(route('dashboard'))
        ->assertDontSee('Debt Payoff');
});

test('page shows empty state when no debts exist', function () {
    Livewire::test('pages::debt-payoff.index')
        ->assertSee('No debt accounts found');
});

test('page loads baseline scenario from spending plan', function () {
    $plan = SpendingPlan::factory()->current()->create();
    SpendingPlanItem::factory()->for($plan)->create([
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Debt Payments',
        'amount' => 85000,
    ]);

    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Credit Card',
        'balance' => 1000000,
        'interest_rate' => 20.0,
        'minimum_payment' => 20000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->assertSee('Current Plan')
        ->assertSee('Credit Card');
});

test('page falls back to sum of minimums when no spending plan', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Car Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 30000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->assertSee('Current Plan')
        ->assertSee('$300/mo');
});

test('user can add a scenario', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->set('newScenario.name', 'Extra $200')
        ->set('newScenario.strategy', 'avalanche')
        ->set('newScenario.extra_payment', '200')
        ->set('newScenario.lump_sum', '0')
        ->set('newScenario.lump_sum_month', '1')
        ->call('addScenario')
        ->assertSee('Extra $200');
});

test('user can remove a scenario', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->set('newScenario.name', 'Test Scenario')
        ->set('newScenario.strategy', 'avalanche')
        ->set('newScenario.extra_payment', '0')
        ->set('newScenario.lump_sum', '0')
        ->set('newScenario.lump_sum_month', '1')
        ->call('addScenario')
        ->assertSee('Test Scenario')
        ->call('removeScenario', 1)
        ->assertDontSee('Test Scenario');
});

test('maximum five scenarios allowed', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000,
    ]);

    $component = Livewire::test('pages::debt-payoff.index');

    for ($i = 1; $i <= 4; $i++) {
        $component
            ->set('newScenario.name', "Scenario {$i}")
            ->set('newScenario.strategy', 'avalanche')
            ->set('newScenario.extra_payment', (string) ($i * 100))
            ->set('newScenario.lump_sum', '0')
            ->set('newScenario.lump_sum_month', '1')
            ->call('addScenario');
    }

    $component
        ->set('newScenario.name', 'Too Many')
        ->set('newScenario.strategy', 'avalanche')
        ->set('newScenario.extra_payment', '500')
        ->set('newScenario.lump_sum', '0')
        ->set('newScenario.lump_sum_month', '1')
        ->call('addScenario')
        ->assertDontSee('Too Many');
});

test('shows warning when budget is less than minimums', function () {
    $plan = SpendingPlan::factory()->current()->create();
    SpendingPlanItem::factory()->for($plan)->create([
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Debt Payments',
        'amount' => 10000,
    ]);

    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->assertSee("doesn't cover all minimum payments");
});

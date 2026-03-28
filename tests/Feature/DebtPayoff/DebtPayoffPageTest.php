<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;

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

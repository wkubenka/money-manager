<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('net worth is calculated as assets + investments + savings - debt', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Assets,
        'balance' => 50000000, // $500,000
    ]);
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Investments,
        'balance' => 10000000, // $100,000
    ]);
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Savings,
        'balance' => 2000000, // $20,000
    ]);
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'balance' => 30000000, // $300,000
    ]);

    $component = Livewire::test('pages::net-worth.index');

    expect($component->get('netWorth'))->toBe(32000000); // $320,000
});

test('net worth can be negative', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Assets,
        'balance' => 1000000, // $10,000
    ]);
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'balance' => 5000000, // $50,000
    ]);

    $component = Livewire::test('pages::net-worth.index');

    expect($component->get('netWorth'))->toBe(-4000000); // -$40,000
});

test('net worth is zero with no accounts', function () {
    $component = Livewire::test('pages::net-worth.index');

    expect($component->get('netWorth'))->toBe(0);
});

test('category total sums accounts in that category', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Assets,
        'balance' => 30000000,
    ]);
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Assets,
        'balance' => 20000000,
    ]);

    $component = Livewire::test('pages::net-worth.index');

    expect($component->call('categoryTotal', AccountCategory::Assets))->not->toBeNull();

    // Verify via the page content
    $component->assertSee('$500,000');
});

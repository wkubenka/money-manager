<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('net worth is calculated as assets + investments + savings - debt', function () {
    $user = User::factory()->create();

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Assets,
        'balance' => 50000000, // $500,000
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Investments,
        'balance' => 10000000, // $100,000
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Savings,
        'balance' => 2000000, // $20,000
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Debt,
        'balance' => 30000000, // $300,000
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::net-worth.index');

    expect($component->get('netWorth'))->toBe(32000000); // $320,000
});

test('net worth can be negative', function () {
    $user = User::factory()->create();

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Assets,
        'balance' => 1000000, // $10,000
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Debt,
        'balance' => 5000000, // $50,000
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::net-worth.index');

    expect($component->get('netWorth'))->toBe(-4000000); // -$40,000
});

test('net worth is zero with no accounts', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::net-worth.index');

    expect($component->get('netWorth'))->toBe(0);
});

test('category total sums accounts in that category', function () {
    $user = User::factory()->create();

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Assets,
        'balance' => 30000000,
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Assets,
        'balance' => 20000000,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::net-worth.index');

    expect($component->call('categoryTotal', AccountCategory::Assets))->not->toBeNull();

    // Verify via the page content
    $component->assertSee('$500,000');
});

test('accounts from other users are not included', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Assets,
        'balance' => 1000000,
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $otherUser->id,
        'category' => AccountCategory::Assets,
        'balance' => 9999999,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::net-worth.index');

    expect($component->get('netWorth'))->toBe(1000000);
});

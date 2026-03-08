<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('net worth page is accessible', function () {
    $this->get(route('net-worth.index'))
        ->assertOk();
});

test('user can add an account', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.assets', 'House')
        ->set('newAccountBalances.assets', '250000.00')
        ->call('addAccount', 'assets')
        ->assertHasNoErrors();

    $account = NetWorthAccount::where('is_emergency_fund', false)->first();
    expect($account)->not->toBeNull();
    expect($account->name)->toBe('House');
    expect($account->balance)->toBe(25000000);
    expect($account->category)->toBe(AccountCategory::Assets);
});

test('balance is stored as cents', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.savings', 'Emergency Fund')
        ->set('newAccountBalances.savings', '1500.50')
        ->call('addAccount', 'savings')
        ->assertHasNoErrors();

    expect(NetWorthAccount::where('is_emergency_fund', false)->first()->balance)->toBe(150050);
});

test('name is required', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.assets', '')
        ->set('newAccountBalances.assets', '100.00')
        ->call('addAccount', 'assets')
        ->assertHasErrors(['newAccountNames.assets' => 'required']);
});

test('account balance is required', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.assets', 'House')
        ->set('newAccountBalances.assets', '')
        ->call('addAccount', 'assets')
        ->assertHasErrors(['newAccountBalances.assets' => 'required']);
});

test('user can edit an account', function () {
    $account = NetWorthAccount::factory()->create([
        'name' => 'Old Name',
        'balance' => 100000,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->set('editingAccountName', 'New Name')
        ->set('editingAccountBalance', '2000.00')
        ->call('updateAccount')
        ->assertHasNoErrors();

    $account->refresh();
    expect($account->name)->toBe('New Name');
    expect($account->balance)->toBe(200000);
});

test('user can remove an account', function () {
    $account = NetWorthAccount::factory()->create();

    Livewire::test('pages::net-worth.index')
        ->call('removeAccount', $account->id)
        ->assertHasNoErrors();

    expect(NetWorthAccount::find($account->id))->toBeNull();
});

test('user can cancel editing', function () {
    $account = NetWorthAccount::factory()->create([
        'name' => 'Original',
        'balance' => 100000,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->assertSet('editingAccountId', $account->id)
        ->call('cancelEdit')
        ->assertSet('editingAccountId', null);

    $account->refresh();
    expect($account->name)->toBe('Original');
});

test('update account rejects empty name', function () {
    $account = NetWorthAccount::factory()->create([
        'name' => 'Original',
        'balance' => 100000,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->set('editingAccountName', '')
        ->set('editingAccountBalance', '2000.00')
        ->call('updateAccount')
        ->assertHasErrors(['editingAccountName' => 'required']);

    $account->refresh();
    expect($account->name)->toBe('Original');
});

test('update account rejects invalid balance', function () {
    $account = NetWorthAccount::factory()->create([
        'name' => 'Test',
        'balance' => 100000,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->set('editingAccountName', 'Test')
        ->set('editingAccountBalance', '0')
        ->call('updateAccount')
        ->assertHasErrors(['editingAccountBalance' => 'min']);
});

test('emergency fund cannot be deleted', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 0,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('removeAccount', $ef->id)
        ->assertStatus(422);

    expect(NetWorthAccount::find($ef->id))->not->toBeNull();
});

test('emergency fund name cannot be changed', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 0,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('editAccount', $ef->id)
        ->set('editingAccountName', 'Renamed')
        ->set('editingAccountBalance', '5000.00')
        ->call('updateAccount')
        ->assertHasNoErrors();

    $ef->refresh();
    expect($ef->name)->toBe('Emergency Fund');
    expect($ef->balance)->toBe(500000);
});

test('emergency fund balance can be updated', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 0,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('editAccount', $ef->id)
        ->set('editingAccountBalance', '10000.00')
        ->call('updateAccount')
        ->assertHasNoErrors();

    $ef->refresh();
    expect($ef->balance)->toBe(1000000);
});

test('inputs are cleared after adding an account', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.debt', 'Student Loan')
        ->set('newAccountBalances.debt', '50000.00')
        ->call('addAccount', 'debt')
        ->assertSet('newAccountNames.debt', '')
        ->assertSet('newAccountBalances.debt', '');
});

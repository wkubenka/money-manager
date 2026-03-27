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

test('user can add a debt account with minimum payment and interest rate', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.debt', 'Credit Card')
        ->set('newAccountBalances.debt', '5000.00')
        ->set('newAccountMinPayments.debt', '150.00')
        ->set('newAccountInterestRates.debt', '24.99')
        ->call('addAccount', 'debt')
        ->assertHasNoErrors();

    $account = NetWorthAccount::where('name', 'Credit Card')->first();
    expect($account)->not->toBeNull();
    expect($account->category)->toBe(AccountCategory::Debt);
    expect($account->balance)->toBe(500000);
    expect($account->minimum_payment)->toBe(15000);
    expect($account->interest_rate)->toBe('24.99');
});

test('user can edit debt account minimum payment and interest rate', function () {
    $account = NetWorthAccount::factory()->debt()->create([
        'name' => 'Student Loan',
        'balance' => 2000000,
        'minimum_payment' => 20000,
        'interest_rate' => 6.50,
    ]);

    Livewire::test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->assertSet('editingMinPayment', '200.00')
        ->assertSet('editingInterestRate', '6.50')
        ->set('editingMinPayment', '250.00')
        ->set('editingInterestRate', '5.25')
        ->call('updateAccount')
        ->assertHasNoErrors();

    $account->refresh();
    expect($account->minimum_payment)->toBe(25000);
    expect($account->interest_rate)->toBe('5.25');
});

test('interest rate must be between 0 and 100', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.debt', 'Bad Loan')
        ->set('newAccountBalances.debt', '1000.00')
        ->set('newAccountInterestRates.debt', '150')
        ->call('addAccount', 'debt')
        ->assertHasErrors(['newAccountInterestRates.debt' => 'max']);
});

test('debt fields are optional when adding a debt account', function () {
    Livewire::test('pages::net-worth.index')
        ->set('newAccountNames.debt', 'Mortgage')
        ->set('newAccountBalances.debt', '200000.00')
        ->call('addAccount', 'debt')
        ->assertHasNoErrors();

    $account = NetWorthAccount::where('name', 'Mortgage')->first();
    expect($account)->not->toBeNull();
    expect($account->minimum_payment)->toBeNull();
    expect($account->interest_rate)->toBeNull();
});

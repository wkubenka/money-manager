<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('net-worth.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view net worth page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('net-worth.index'))
        ->assertOk();
});

test('user can add an account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->set('newAccountNames.assets', 'House')
        ->set('newAccountBalances.assets', '250000.00')
        ->call('addAccount', 'assets')
        ->assertHasNoErrors();

    $account = NetWorthAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull();
    expect($account->name)->toBe('House');
    expect($account->balance)->toBe(25000000);
    expect($account->category)->toBe(AccountCategory::Assets);
});

test('balance is stored as cents', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->set('newAccountNames.savings', 'Emergency Fund')
        ->set('newAccountBalances.savings', '1500.50')
        ->call('addAccount', 'savings')
        ->assertHasNoErrors();

    expect(NetWorthAccount::first()->balance)->toBe(150050);
});

test('name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->set('newAccountNames.assets', '')
        ->set('newAccountBalances.assets', '100.00')
        ->call('addAccount', 'assets')
        ->assertHasErrors(['newAccountNames.assets' => 'required']);
});

test('account balance is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->set('newAccountNames.assets', 'House')
        ->set('newAccountBalances.assets', '')
        ->call('addAccount', 'assets')
        ->assertHasErrors(['newAccountBalances.assets' => 'required']);
});

test('user can edit an account', function () {
    $user = User::factory()->create();
    $account = NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old Name',
        'balance' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
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
    $user = User::factory()->create();
    $account = NetWorthAccount::factory()->create([
        'user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->call('removeAccount', $account->id)
        ->assertHasNoErrors();

    expect(NetWorthAccount::find($account->id))->toBeNull();
});

test('user cannot edit another users account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = NetWorthAccount::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->assertForbidden();
});

test('user cannot remove another users account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = NetWorthAccount::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->call('removeAccount', $account->id)
        ->assertForbidden();
});

test('user can cancel editing', function () {
    $user = User::factory()->create();
    $account = NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original',
        'balance' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->assertSet('editingAccountId', $account->id)
        ->call('cancelEdit')
        ->assertSet('editingAccountId', null);

    $account->refresh();
    expect($account->name)->toBe('Original');
});

test('update account rejects empty name', function () {
    $user = User::factory()->create();
    $account = NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original',
        'balance' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->set('editingAccountName', '')
        ->set('editingAccountBalance', '2000.00')
        ->call('updateAccount')
        ->assertHasErrors(['editingAccountName' => 'required']);

    $account->refresh();
    expect($account->name)->toBe('Original');
});

test('update account rejects invalid balance', function () {
    $user = User::factory()->create();
    $account = NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test',
        'balance' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->call('editAccount', $account->id)
        ->set('editingAccountName', 'Test')
        ->set('editingAccountBalance', '0')
        ->call('updateAccount')
        ->assertHasErrors(['editingAccountBalance' => 'min']);
});

test('deleting a user cascades to net worth accounts', function () {
    $user = User::factory()->create();
    NetWorthAccount::factory()->count(3)->create(['user_id' => $user->id]);

    expect(NetWorthAccount::where('user_id', $user->id)->count())->toBe(3);

    $user->delete();

    expect(NetWorthAccount::where('user_id', $user->id)->count())->toBe(0);
});

test('inputs are cleared after adding an account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::net-worth.index')
        ->set('newAccountNames.debt', 'Student Loan')
        ->set('newAccountBalances.debt', '50000.00')
        ->call('addAccount', 'debt')
        ->assertSet('newAccountNames.debt', '')
        ->assertSet('newAccountBalances.debt', '');
});

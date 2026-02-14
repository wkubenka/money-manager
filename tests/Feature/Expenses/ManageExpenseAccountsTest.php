<?php

use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('empty state prompts to create first account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->assertSee('Get started')
        ->assertSee('Create your first expense account');
});

test('user can create first account from setup prompt', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('firstAccountName', 'Chase Checking')
        ->call('createFirstAccount')
        ->assertHasNoErrors();

    $account = ExpenseAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull();
    expect($account->name)->toBe('Chase Checking');
});

test('first account name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('firstAccountName', '')
        ->call('createFirstAccount')
        ->assertHasErrors(['firstAccountName' => 'required']);
});

test('creating first account switches to it', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('firstAccountName', 'Amex Card')
        ->call('createFirstAccount');

    $account = ExpenseAccount::where('user_id', $user->id)->first();
    $component->assertSet('selectedAccountId', (string) $account->id);
    $component->assertSet('newAccountId', (string) $account->id);
});

test('user can add an expense account via new tab', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('addAccount')
        ->assertHasNoErrors();

    $account = ExpenseAccount::where('user_id', $user->id)->first();
    expect($account)->not->toBeNull();
    expect($account->name)->toBe('New');
});

test('adding account switches to new account tab', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('addAccount');

    $account = ExpenseAccount::where('user_id', $user->id)->first();
    $component->assertSet('selectedAccountId', (string) $account->id);
    $component->assertSet('isRenamingAccount', true);
    $component->assertSet('renamingAccountName', 'New');
});

test('user can rename an expense account', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old Name',
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('startRenamingAccount')
        ->assertSet('isRenamingAccount', true)
        ->assertSet('renamingAccountName', 'Old Name')
        ->set('renamingAccountName', 'New Name')
        ->call('renameAccount')
        ->assertHasNoErrors()
        ->assertSet('isRenamingAccount', false);

    expect($account->refresh()->name)->toBe('New Name');
});

test('account rename requires a name', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('startRenamingAccount')
        ->set('renamingAccountName', '')
        ->call('renameAccount')
        ->assertHasErrors(['renamingAccountName' => 'required']);
});

test('user can cancel renaming', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id, 'name' => 'Original']);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('startRenamingAccount')
        ->set('renamingAccountName', 'Changed')
        ->call('cancelRename')
        ->assertSet('isRenamingAccount', false)
        ->assertSet('renamingAccountName', '');

    expect($account->refresh()->name)->toBe('Original');
});

test('user can delete the selected expense account', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('removeAccount')
        ->assertHasNoErrors()
        ->assertSet('selectedAccountId', 'all');

    expect(ExpenseAccount::find($account->id))->toBeNull();
});

test('user cannot rename another users account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = ExpenseAccount::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Original',
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('startRenamingAccount')
        ->assertForbidden();

    expect($account->refresh()->name)->toBe('Original');
});

test('user cannot delete another users account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('removeAccount')
        ->assertForbidden();

    expect(ExpenseAccount::find($account->id))->not->toBeNull();
});

test('switching tabs cancels rename', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('startRenamingAccount')
        ->assertSet('isRenamingAccount', true)
        ->set('selectedAccountId', 'all')
        ->assertSet('isRenamingAccount', false);
});

test('deleting an expense account cascades to its expenses', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    Expense::factory()->count(3)->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
    ]);

    $accountId = $account->id;
    $account->delete();

    expect(Expense::where('expense_account_id', $accountId)->count())->toBe(0);
});

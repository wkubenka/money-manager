<?php

use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('expenses.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view expenses page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertOk();
});

test('user can add an expense', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Trader Joe\'s')
        ->set('newAmount', '42.50')
        ->set('newCategory', SpendingCategory::FixedCosts->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasNoErrors();

    $expense = Expense::where('user_id', $user->id)->first();
    expect($expense)->not->toBeNull();
    expect($expense->merchant)->toBe('Trader Joe\'s');
    expect($expense->amount)->toBe(4250);
    expect($expense->category)->toBe(SpendingCategory::FixedCosts);
    expect($expense->date->format('Y-m-d'))->toBe('2026-02-10');
    expect($expense->expense_account_id)->toBe($account->id);
});

test('expense amount is stored as cents', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '1500.99')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasNoErrors();

    expect(Expense::first()->amount)->toBe(150099);
});

test('merchant is required', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', '')
        ->set('newAmount', '10.00')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newMerchant' => 'required']);
});

test('amount is required', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newAmount' => 'required']);
});

test('category is required', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '10.00')
        ->set('newCategory', '')
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newCategory' => 'required']);
});

test('date is required', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '10.00')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '')
        ->call('addExpense')
        ->assertHasErrors(['newDate' => 'required']);
});

test('account is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', '')
        ->set('newMerchant', 'Test')
        ->set('newAmount', '10.00')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newAccountId' => 'required']);
});

test('user cannot use another users expense account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherAccount = ExpenseAccount::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', $otherAccount->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '10.00')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertForbidden();
});

test('user can edit an expense', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Old Merchant',
        'amount' => 1000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('editExpense', $expense->id)
        ->set('editingMerchant', 'New Merchant')
        ->set('editingAmount', '25.00')
        ->call('updateExpense')
        ->assertHasNoErrors();

    $expense->refresh();
    expect($expense->merchant)->toBe('New Merchant');
    expect($expense->amount)->toBe(2500);
});

test('user can remove an expense', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('removeExpense', $expense->id)
        ->assertHasNoErrors();

    expect(Expense::find($expense->id))->toBeNull();
});

test('user cannot edit another users expense', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $otherUser->id]);
    $expense = Expense::factory()->create([
        'user_id' => $otherUser->id,
        'expense_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('editExpense', $expense->id)
        ->assertForbidden();
});

test('user cannot remove another users expense', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $otherUser->id]);
    $expense = Expense::factory()->create([
        'user_id' => $otherUser->id,
        'expense_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('removeExpense', $expense->id)
        ->assertForbidden();

    expect(Expense::find($expense->id))->not->toBeNull();
});

test('user can cancel editing', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('editExpense', $expense->id)
        ->assertSet('editingExpenseId', $expense->id)
        ->call('cancelEdit')
        ->assertSet('editingExpenseId', null);
});

test('inputs are cleared after adding expense except account and date', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newAccountId', (string) $account->id)
        ->set('newMerchant', 'Test Merchant')
        ->set('newAmount', '50.00')
        ->set('newCategory', SpendingCategory::FixedCosts->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertSet('newMerchant', '')
        ->assertSet('newAmount', '')
        ->assertSet('newCategory', '')
        ->assertSet('newAccountId', (string) $account->id)
        ->assertSet('newDate', '2026-02-10');
});

test('merchant auto-categorization sets category from previous expense', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Netflix',
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subDays(5),
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newMerchant', 'Netflix')
        ->assertSet('newCategory', SpendingCategory::FixedCosts->value);
});

test('merchant auto-categorization does not set category when merchant not found', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newMerchant', 'Unknown Merchant')
        ->assertSet('newCategory', '');
});

test('merchant auto-categorization does not override manually set category', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Netflix',
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subDays(5),
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newMerchant', 'Netflix')
        ->assertSet('newCategory', SpendingCategory::GuiltFree->value);
});

test('load more increases visible expenses', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    Expense::factory()->count(30)->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->assertSet('perPage', 25)
        ->call('loadMore')
        ->assertSet('perPage', 50);
});

test('switching tabs resets per page', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('perPage', 50)
        ->set('selectedAccountId', (string) $account->id)
        ->assertSet('perPage', 25);
});

test('deleting a user cascades to expenses', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    Expense::factory()->count(3)->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
    ]);

    $userId = $user->id;
    $user->delete();

    expect(Expense::where('user_id', $userId)->count())->toBe(0);
    expect(ExpenseAccount::where('user_id', $userId)->count())->toBe(0);
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

test('category tab filters expenses to that category', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::GuiltFree,
        'merchant' => 'Coffee Shop',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::FixedCosts,
        'merchant' => 'Electric Company',
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', 'category:guilt_free')
        ->assertSee('Coffee Shop')
        ->assertDontSee('Electric Company');
});

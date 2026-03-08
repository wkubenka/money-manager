<?php

use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('expenses page is accessible', function () {
    $this->get(route('expenses.index'))
        ->assertOk();
});

test('user can add an expense', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Trader Joe\'s')
        ->set('newAmount', '42.50')
        ->set('newCategory', SpendingCategory::FixedCosts->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasNoErrors();

    $expense = Expense::first();
    expect($expense)->not->toBeNull();
    expect($expense->merchant)->toBe('Trader Joe\'s');
    expect($expense->amount)->toBe(4250);
    expect($expense->category)->toBe(SpendingCategory::FixedCosts);
    expect($expense->date->format('Y-m-d'))->toBe('2026-02-10');
    expect($expense->expense_account_id)->toBe($account->id);
});

test('expense amount is stored as cents', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
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
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', '')
        ->set('newAmount', '10.00')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newMerchant' => 'required']);
});

test('amount is required', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newAmount' => 'required']);
});

test('category is required', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '10.00')
        ->set('newCategory', '')
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newCategory' => 'required']);
});

test('date is required', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Test')
        ->set('newAmount', '10.00')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '')
        ->call('addExpense')
        ->assertHasErrors(['newDate' => 'required']);
});

test('account is required', function () {
    Livewire::test('pages::expenses.index')
        ->set('newAccountId', '')
        ->set('newMerchant', 'Test')
        ->set('newAmount', '10.00')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasErrors(['newAccountId' => 'required']);
});

test('user can edit an expense', function () {
    $account = ExpenseAccount::factory()->create();
    $expense = Expense::factory()->create([
        'expense_account_id' => $account->id,
        'merchant' => 'Old Merchant',
        'amount' => 1000,
    ]);

    Livewire::test('pages::expenses.index')
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
    $account = ExpenseAccount::factory()->create();
    $expense = Expense::factory()->create([
        'expense_account_id' => $account->id,
    ]);

    Livewire::test('pages::expenses.index')
        ->call('removeExpense', $expense->id)
        ->assertHasNoErrors();

    expect(Expense::find($expense->id))->toBeNull();
});

test('user can cancel editing', function () {
    $account = ExpenseAccount::factory()->create();
    $expense = Expense::factory()->create([
        'expense_account_id' => $account->id,
    ]);

    Livewire::test('pages::expenses.index')
        ->call('editExpense', $expense->id)
        ->assertSet('editingExpenseId', $expense->id)
        ->call('cancelEdit')
        ->assertSet('editingExpenseId', null);
});

test('inputs are cleared after adding expense except account and date', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
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
    $account = ExpenseAccount::factory()->create();
    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'merchant' => 'Netflix',
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subDays(5),
    ]);

    Livewire::test('pages::expenses.index')
        ->set('newMerchant', 'Netflix')
        ->assertSet('newCategory', SpendingCategory::FixedCosts->value);
});

test('merchant auto-categorization does not set category when merchant not found', function () {
    Livewire::test('pages::expenses.index')
        ->set('newMerchant', 'Unknown Merchant')
        ->assertSet('newCategory', '');
});

test('merchant auto-categorization does not override manually set category', function () {
    $account = ExpenseAccount::factory()->create();
    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'merchant' => 'Netflix',
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subDays(5),
    ]);

    Livewire::test('pages::expenses.index')
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->set('newMerchant', 'Netflix')
        ->assertSet('newCategory', SpendingCategory::GuiltFree->value);
});

test('load more increases visible expenses', function () {
    $account = ExpenseAccount::factory()->create();
    Expense::factory()->count(30)->create([
        'expense_account_id' => $account->id,
    ]);

    Livewire::test('pages::expenses.index')
        ->assertSet('perPage', 25)
        ->call('loadMore')
        ->assertSet('perPage', 50);
});

test('switching tabs resets per page', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('perPage', 50)
        ->set('selectedAccountId', (string) $account->id)
        ->assertSet('perPage', 25);
});

test('deleting an expense account cascades to its expenses', function () {
    $account = ExpenseAccount::factory()->create();
    Expense::factory()->count(3)->create([
        'expense_account_id' => $account->id,
    ]);

    $accountId = $account->id;
    $account->delete();

    expect(Expense::where('expense_account_id', $accountId)->count())->toBe(0);
});

test('ignored expenses are excluded from monthly total', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 5000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now(),
    ]);

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 3000,
        'category' => SpendingCategory::Ignored,
        'date' => now(),
    ]);

    $component = Livewire::test('pages::expenses.index');

    expect($component->get('monthlyTotal'))->toBe(5000);
});

test('ignored expenses are not counted as uncategorized', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::Ignored,
        'date' => now(),
    ]);

    $component = Livewire::test('pages::expenses.index');

    expect($component->get('uncategorizedCount'))->toBe(0);
});

test('user can add an expense with ignored category', function () {
    $account = ExpenseAccount::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('newAccountId', $account->id)
        ->set('newMerchant', 'Transfer')
        ->set('newAmount', '100.00')
        ->set('newCategory', SpendingCategory::Ignored->value)
        ->set('newDate', '2026-02-10')
        ->call('addExpense')
        ->assertHasNoErrors();

    $expense = Expense::first();
    expect($expense->category)->toBe(SpendingCategory::Ignored);
});

test('category tab filters expenses to that category', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::GuiltFree,
        'merchant' => 'Coffee Shop',
    ]);

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::FixedCosts,
        'merchant' => 'Electric Company',
    ]);

    Livewire::test('pages::expenses.index')
        ->set('selectedAccountId', 'category:guilt_free')
        ->assertSee('Coffee Shop')
        ->assertDontSee('Electric Company');
});

test('monthly history shows previous months totals', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 5000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subMonth()->startOfMonth()->addDays(5),
    ]);

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 3000,
        'category' => SpendingCategory::GuiltFree,
        'date' => now()->subMonth()->startOfMonth()->addDays(10),
    ]);

    $component = Livewire::test('pages::expenses.index');

    $history = $component->get('monthlyHistory');

    expect($history)->toHaveCount(1);
    expect($history[0]['total'])->toBe(8000);
    expect($history[0]['categories'][SpendingCategory::FixedCosts->value])->toBe(5000);
    expect($history[0]['categories'][SpendingCategory::GuiltFree->value])->toBe(3000);
});

test('monthly history excludes current month', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 5000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now(),
    ]);

    $component = Livewire::test('pages::expenses.index');

    expect($component->get('monthlyHistory'))->toBeEmpty();
});

test('monthly history excludes ignored expenses', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 5000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subMonth(),
    ]);

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 2000,
        'category' => SpendingCategory::Ignored,
        'date' => now()->subMonth(),
    ]);

    $component = Livewire::test('pages::expenses.index');

    $history = $component->get('monthlyHistory');

    expect($history)->toHaveCount(1);
    expect($history[0]['total'])->toBe(5000);
    expect($history[0]['categories'])->not->toHaveKey(SpendingCategory::Ignored->value);
});

test('monthly history respects account tab filter', function () {
    $account1 = ExpenseAccount::factory()->create();
    $account2 = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account1->id,
        'amount' => 5000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subMonth(),
    ]);

    Expense::factory()->create([
        'expense_account_id' => $account2->id,
        'amount' => 3000,
        'category' => SpendingCategory::GuiltFree,
        'date' => now()->subMonth(),
    ]);

    $component = Livewire::test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account1->id);

    $history = $component->get('monthlyHistory');

    expect($history)->toHaveCount(1);
    expect($history[0]['total'])->toBe(5000);
});

test('monthly history orders by most recent month first', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 1000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subMonths(3),
    ]);

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 2000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subMonth(),
    ]);

    $component = Livewire::test('pages::expenses.index');

    $history = $component->get('monthlyHistory');

    expect($history)->toHaveCount(2);
    expect($history[0]['total'])->toBe(2000);
    expect($history[1]['total'])->toBe(1000);
});

test('monthly history toggle shows and hides previous months', function () {
    $account = ExpenseAccount::factory()->create();

    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'amount' => 5000,
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subMonth(),
    ]);

    Livewire::test('pages::expenses.index')
        ->assertSee('Previous months')
        ->assertDontSee(now()->subMonth()->format('F Y'))
        ->toggle('showMonthlyHistory')
        ->assertSee(now()->subMonth()->format('F Y'));
});

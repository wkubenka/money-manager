<?php

use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createCsvFile(array $headers, array $rows): \Illuminate\Http\Testing\File
{
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, $headers);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    return UploadedFile::fake()->createWithContent('import.csv', $content);
}

test('user can open import modal from account tab', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->assertSet('showImportModal', true)
        ->assertSet('importAccountId', $account->id);
});

test('import modal sets null account id when on all tab', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', 'all')
        ->call('openImportModal')
        ->assertSet('importAccountId', null);
});

test('csv file is parsed on upload', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],
            ['2026-02-02', 'Amazon', '42.99'],
        ]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->assertSet('showImportModal', true);

    // Check that parsed rows are populated (accessing via component property)
    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(2);
    expect($component->get('parsedRows')[0]['merchant'])->toBe('Starbucks');
    expect($component->get('parsedRows')[0]['amount'])->toBe(550);
    expect($component->get('parsedRows')[1]['merchant'])->toBe('Amazon');
    expect($component->get('parsedRows')[1]['amount'])->toBe(4299);
});

test('csv import detects common header names', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Transaction Date', 'Merchant', 'Debit'],
        [['2026-02-05', 'Target', '15.00']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('parsedRows')[0]['merchant'])->toBe('Target');
});

test('csv import converts negative amounts to positive', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Starbucks', '-5.50']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('parsedRows')[0]['amount'])->toBe(550);
});

test('csv import filters out manual entries with matching amount', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    // Manual entry with same amount but different date (bank posting date differs)
    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'date' => '2026-01-30',
        'amount' => 550,
        'merchant' => 'Coffee Shop',
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],  // matches manual entry by amount
            ['2026-02-02', 'Amazon', '42.99'],     // new
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('parsedRows')[0]['merchant'])->toBe('Amazon');
});

test('csv import auto-categorizes known merchants', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Netflix',
        'category' => SpendingCategory::FixedCosts,
        'date' => now()->subDays(10),
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-10', 'Netflix', '15.99']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows')[0]['category'])->toBe(SpendingCategory::FixedCosts->value);
});

test('csv import defaults unknown merchants to uncategorized', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-10', 'Unknown Store', '10.00']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows')[0]['category'])->toBeNull();
});

test('all parsed rows are selected by default', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],
            ['2026-02-02', 'Amazon', '42.99'],
            ['2026-02-03', 'Target', '15.00'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('selectedRows'))->toBe([0, 1, 2]);
});

test('user can import selected expenses', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],
            ['2026-02-02', 'Amazon', '42.99'],
            ['2026-02-03', 'Target', '15.00'],
        ]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->set('selectedRows', [0, 2]) // Only import Starbucks and Target
        ->call('importExpenses')
        ->assertSet('showImportModal', false)
        ->assertSet('parsedRows', []);

    expect(Expense::where('user_id', $user->id)->count())->toBe(2);

    $expenses = Expense::where('user_id', $user->id)->orderBy('date')->get();
    expect($expenses[0]->merchant)->toBe('Starbucks');
    expect($expenses[0]->amount)->toBe(550);
    expect($expenses[0]->expense_account_id)->toBe($account->id);
    expect($expenses[1]->merchant)->toBe('Target');
    expect($expenses[1]->amount)->toBe(1500);
});

test('import assigns expenses to the correct account', function () {
    $user = User::factory()->create();
    $account1 = ExpenseAccount::factory()->create(['user_id' => $user->id, 'name' => 'Checking']);
    $account2 = ExpenseAccount::factory()->create(['user_id' => $user->id, 'name' => 'Credit Card']);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Groceries', '50.00']]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account2->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->call('importExpenses');

    $expense = Expense::where('user_id', $user->id)->first();
    expect($expense->expense_account_id)->toBe($account2->id);
});

test('user cannot import into another users account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherAccount = ExpenseAccount::factory()->create(['user_id' => $otherUser->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Test', '10.00']]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('importAccountId', $otherAccount->id)
        ->set('parsedRows', [['date' => '2026-02-01', 'merchant' => 'Test', 'amount' => 1000, 'category' => SpendingCategory::GuiltFree->value]])
        ->set('selectedRows', [0])
        ->call('importExpenses')
        ->assertForbidden();
});

test('user can cancel import', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->assertSet('showImportModal', true)
        ->call('cancelImport')
        ->assertSet('showImportModal', false)
        ->assertSet('parsedRows', [])
        ->assertSet('selectedRows', []);
});

test('csv import skips rows with zero amount', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Refund', '0.00'],
            ['2026-02-02', 'Starbucks', '5.50'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('parsedRows')[0]['merchant'])->toBe('Starbucks');
});

test('csv import handles dollar signs and commas in amounts', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Big Purchase', '$1,500.99']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('parsedRows')[0]['amount'])->toBe(150099);
});

test('csv import detects posting date header', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Posting Date', 'Description', 'Amount'],
        [['2/10/2026', 'Electric Bill', '-105.52']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('parsedRows')[0]['merchant'])->toBe('Electric Bill');
    expect($component->get('parsedRows')[0]['date'])->toBe('2026-02-10');
    expect($component->get('parsedRows')[0]['amount'])->toBe(10552);
});

test('csv import filters out credits in signed-amount format', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Posted Date', 'Payee', 'Amount'],
        [
            ['02/12/2026', 'TACO BELL', '-20.66'],
            ['02/02/2026', 'CASH REWARDS STATEMENT CREDIT', '29.59'],
            ['02/07/2026', 'CHOCOLATE SHOP', '-24.00'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(2);
    expect($component->get('parsedRows')[0]['merchant'])->toBe('TACO BELL');
    expect($component->get('parsedRows')[0]['amount'])->toBe(2066);
    expect($component->get('parsedRows')[1]['merchant'])->toBe('CHOCOLATE SHOP');
    expect($component->get('parsedRows')[1]['amount'])->toBe(2400);
});

test('csv import keeps all positive amounts when no negatives exist', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],
            ['2026-02-02', 'Amazon', '42.99'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(2);
});

// --- Import tracking tests ---

test('imported expenses are saved with is_imported and reference_number', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Starbucks', '5.50']]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->call('importExpenses');

    $expense = Expense::where('user_id', $user->id)->first();
    expect($expense->is_imported)->toBeTrue();
    expect($expense->reference_number)->not->toBeNull();
});

test('manually created expenses default to not imported', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->set('newMerchant', 'Coffee Shop')
        ->set('newAmount', '5.50')
        ->set('newDate', '2026-02-01')
        ->set('newAccountId', (string) $account->id)
        ->set('newCategory', SpendingCategory::GuiltFree->value)
        ->call('addExpense');

    $expense = Expense::where('user_id', $user->id)->first();
    expect($expense->is_imported)->toBeFalse();
    expect($expense->reference_number)->toBeNull();
});

// --- Reference number dedup tests ---

test('re-importing the same csv filters all rows via reference number', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csvContent = [
        ['2026-02-01', 'Starbucks', '5.50'],
        ['2026-02-02', 'Amazon', '42.99'],
    ];

    $csv1 = createCsvFile(['Date', 'Description', 'Amount'], $csvContent);

    // First import
    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv1)
        ->call('importExpenses');

    expect(Expense::where('user_id', $user->id)->count())->toBe(2);

    // Re-import same CSV
    $csv2 = createCsvFile(['Date', 'Description', 'Amount'], $csvContent);

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv2);

    expect($component->get('parsedRows'))->toHaveCount(0);
    expect($component->get('importFeedback'))->toBe('All transactions in this file have already been imported.');
});

test('bank-provided reference number is detected and stored', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount', 'Reference Number'],
        [['2026-02-01', 'Starbucks', '5.50', 'REF-12345']]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->call('importExpenses');

    $expense = Expense::where('user_id', $user->id)->first();
    expect($expense->reference_number)->toBe('REF-12345');
});

test('two identical csv lines get distinct hash-based reference numbers', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],
            ['2026-02-01', 'Starbucks', '5.50'],
        ]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->call('importExpenses');

    $expenses = Expense::where('user_id', $user->id)->get();
    expect($expenses)->toHaveCount(2);
    expect($expenses[0]->reference_number)->not->toBe($expenses[1]->reference_number);
});

// --- Amount matching tests ---

test('matched expenses include both manual and csv details', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'date' => '2026-01-29',
        'amount' => 550,
        'merchant' => 'Coffee',
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Starbucks', '5.50']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    $matches = $component->get('matchedExpenses');
    expect($matches)->toHaveCount(1);
    expect($matches[0]['expense_merchant'])->toBe('Coffee');
    expect($matches[0]['expense_date'])->toBe('2026-01-29');
    expect($matches[0]['csv_merchant'])->toBe('Starbucks');
    expect($matches[0]['csv_date'])->toBe('2026-02-01');
    expect($matches[0]['amount'])->toBe(550);
});

test('selected matches default to all indices', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'amount' => 550,
        'is_imported' => false,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'amount' => 4299,
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],
            ['2026-02-02', 'Amazon', '42.99'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('selectedMatches'))->toBe([0, 1]);
});

test('approved match updates manual entry on import', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $manual = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'date' => '2026-01-29',
        'amount' => 550,
        'merchant' => 'Coffee',
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Starbucks', '5.50']]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->call('importExpenses');

    $manual->refresh();
    expect($manual->is_imported)->toBeTrue();
    expect($manual->reference_number)->not->toBeNull();
    expect(Expense::where('user_id', $user->id)->count())->toBe(1);
});

test('deselecting a match prevents manual entry from being updated', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $manual = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'amount' => 550,
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],
            ['2026-02-02', 'Amazon', '42.99'],
        ]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->set('selectedMatches', []) // Deselect the match
        ->call('importExpenses');

    $manual->refresh();
    expect($manual->is_imported)->toBeFalse();
    expect($manual->reference_number)->toBeNull();

    // The new CSV row (Amazon) should still be imported
    expect(Expense::where('user_id', $user->id)->count())->toBe(2);
});

test('two manual entries with same amount match csv rows one-to-one', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $manual1 = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'amount' => 550,
        'is_imported' => false,
    ]);

    $manual2 = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'amount' => 550,
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks A', '5.50'],
            ['2026-02-02', 'Starbucks B', '5.50'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    // Both CSV rows should be consumed by the two manual entries
    expect($component->get('parsedRows'))->toHaveCount(0);
    expect($component->get('matchedExpenses'))->toHaveCount(2);
});

test('one manual entry consumes only one csv row when multiple match', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'amount' => 550,
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks A', '5.50'],
            ['2026-02-02', 'Starbucks B', '5.50'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    // One consumed by manual entry, one shows in preview
    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('matchedExpenses'))->toHaveCount(1);
});

test('matched manual entries are not updated if import is cancelled', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $manual = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'amount' => 550,
        'is_imported' => false,
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Starbucks', '5.50']]
    );

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv)
        ->call('cancelImport');

    $manual->refresh();
    expect($manual->is_imported)->toBeFalse();
    expect($manual->reference_number)->toBeNull();
});

// --- Status column filtering tests ---

test('pending transactions are filtered out when status column is present', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $csv = createCsvFile(
        ['Status', 'Date', 'Description', 'Amount'],
        [
            ['Cleared', '2026-02-01', 'Starbucks', '5.50'],
            ['Pending', '2026-02-02', 'Amazon', '42.99'],
            ['Cleared', '2026-02-03', 'Target', '15.00'],
        ]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(2);
    expect($component->get('parsedRows')[0]['merchant'])->toBe('Starbucks');
    expect($component->get('parsedRows')[1]['merchant'])->toBe('Target');
});

// --- Scoping tests ---

test('imported expense in one account does not affect dedup for another', function () {
    $user = User::factory()->create();
    $account1 = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    $account2 = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    // Manual entry in account 1
    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account1->id,
        'amount' => 550,
        'is_imported' => false,
    ]);

    // Import into account 2 — should not match account 1's expense
    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [['2026-02-01', 'Starbucks', '5.50']]
    );

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', (string) $account2->id)
        ->call('openImportModal')
        ->set('csvFile', $csv);

    expect($component->get('parsedRows'))->toHaveCount(1);
    expect($component->get('matchedExpenses'))->toHaveCount(0);
});

test('uncategorized tab appears when uncategorized expenses exist', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->assertSee('Uncategorized')
        ->assertSee('1 expense needs categorizing');
});

test('uncategorized tab hidden when no uncategorized expenses', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::FixedCosts,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->assertDontSee('expense needs categorizing')
        ->assertDontSee('expenses need categorizing');
});

test('uncategorized tab filters to only uncategorized expenses', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::FixedCosts,
        'merchant' => 'Categorized One',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => null,
        'merchant' => 'Uncategorized One',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('selectedAccountId', 'uncategorized');

    $expenses = $component->get('expenses');
    expect($expenses)->toHaveCount(1);
    expect($expenses[0]->merchant)->toBe('Uncategorized One');
});

test('uncategorized expenses can be categorized via inline select', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => null,
        'merchant' => 'New Store',
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, SpendingCategory::GuiltFree->value);

    expect($expense->fresh()->category)->toBe(SpendingCategory::GuiltFree);
});

test('categorizeExpense rejects invalid category', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, 'InvalidCategory');

    expect($expense->fresh()->category)->toBeNull();
});

test('categorizeExpense checks authorization', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $otherUser->id]);

    $expense = Expense::factory()->create([
        'user_id' => $otherUser->id,
        'expense_account_id' => $account->id,
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, SpendingCategory::GuiltFree->value)
        ->assertForbidden();
});

test('categorizing shows bulk prompt when other uncategorized expenses exist for same merchant', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    Expense::factory()->count(3)->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, SpendingCategory::GuiltFree->value)
        ->assertSet('showBulkCategorizeModal', true)
        ->assertSet('bulkCategorizeMerchant', 'Starbucks')
        ->assertSet('bulkCategorizeCategory', SpendingCategory::GuiltFree->value)
        ->assertSet('bulkCategorizeCount', 3);
});

test('categorizing does not show bulk prompt when no other uncategorized expenses exist', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, SpendingCategory::GuiltFree->value)
        ->assertSet('showBulkCategorizeModal', false);
});

test('bulk categorize updates all uncategorized expenses for the merchant', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    $others = Expense::factory()->count(3)->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, SpendingCategory::GuiltFree->value)
        ->call('bulkCategorize');

    foreach ($others as $other) {
        expect($other->fresh()->category)->toBe(SpendingCategory::GuiltFree);
    }
});

test('bulk categorize does not overwrite already categorized expenses', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $uncategorized = Expense::factory()->count(2)->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    $alreadyCategorized = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => SpendingCategory::FixedCosts,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $uncategorized[0]->id, SpendingCategory::GuiltFree->value)
        ->call('bulkCategorize');

    expect($alreadyCategorized->fresh()->category)->toBe(SpendingCategory::FixedCosts);
});

test('cancelling bulk categorize leaves other expenses uncategorized', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    $other = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, SpendingCategory::GuiltFree->value)
        ->assertSet('showBulkCategorizeModal', true)
        ->call('cancelBulkCategorize')
        ->assertSet('showBulkCategorizeModal', false)
        ->assertSet('bulkCategorizeMerchant', '')
        ->assertSet('bulkCategorizeCount', 0);

    expect($other->fresh()->category)->toBeNull();
});

test('bulk categorize only affects current user expenses', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);
    $otherAccount = ExpenseAccount::factory()->create(['user_id' => $otherUser->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    $otherUserExpense = Expense::factory()->create([
        'user_id' => $otherUser->id,
        'expense_account_id' => $otherAccount->id,
        'merchant' => 'Starbucks',
        'category' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('categorizeExpense', $expense->id, SpendingCategory::GuiltFree->value)
        ->call('bulkCategorize');

    expect($otherUserExpense->fresh()->category)->toBeNull();
});

test('uncategorized tab category buttons do not have wire:confirm after switching from display mode', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'category' => null,
        'merchant' => 'Test Store',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->assertSet('selectedAccountId', 'all')
        ->set('selectedAccountId', 'uncategorized');

    $html = $component->html();

    foreach (SpendingCategory::cases() as $cat) {
        expect($html)->toContain("categorizeExpense({$expense->id}, '{$cat->value}')");
    }

    // wire:confirm should only appear on the delete button, not on category buttons
    preg_match_all('/wire:confirm/', $html, $matches);
    expect($matches[0])->toHaveCount(1);

    // The single wire:confirm should be on the removeExpense action
    expect($html)->toContain("removeExpense({$expense->id})");
});

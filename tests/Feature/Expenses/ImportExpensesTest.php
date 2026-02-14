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

test('csv import filters out duplicate transactions', function () {
    $user = User::factory()->create();
    $account = ExpenseAccount::factory()->create(['user_id' => $user->id]);

    // Create an existing expense that matches one of the CSV rows
    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_account_id' => $account->id,
        'date' => '2026-02-01',
        'amount' => 550,
        'merchant' => 'Starbucks',
    ]);

    $csv = createCsvFile(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-02-01', 'Starbucks', '5.50'],  // duplicate: same date + amount
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

test('csv import defaults unknown merchants to guilt free', function () {
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

    expect($component->get('parsedRows')[0]['category'])->toBe(SpendingCategory::GuiltFree->value);
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

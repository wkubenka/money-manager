<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\NetWorthAccount;
use App\Models\Profile;
use App\Models\RichLifeVision;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Services\DataExporter;
use App\Services\DataImporter;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function seedTestData(): array
{
    $profile = Profile::factory()->create([
        'date_of_birth' => '1993-09-19',
        'retirement_age' => 65,
        'expected_return' => 7.0,
        'withdrawal_rate' => 4.0,
    ]);

    $plan = SpendingPlan::factory()->current()->create(['name' => 'Test Plan']);
    $item = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Rent',
        'amount' => 150000,
        'sort_order' => 0,
    ]);

    // Update the migration-seeded Emergency Fund rather than creating a second one
    $netWorthAccount = NetWorthAccount::where('is_emergency_fund', true)->first();
    $netWorthAccount->update(['balance' => 500000]);

    $vision = RichLifeVision::factory()->create(['text' => 'Travel the world']);

    $expenseAccount = ExpenseAccount::factory()->create(['name' => 'Amex']);
    $expense = Expense::factory()->create([
        'expense_account_id' => $expenseAccount->id,
        'merchant' => 'Netflix',
        'amount' => 1799,
        'category' => SpendingCategory::FixedCosts,
        'date' => '2026-03-01',
    ]);

    return compact('profile', 'plan', 'item', 'netWorthAccount', 'vision', 'expenseAccount', 'expense');
}

function buildValidBackupData(array $overrides = []): array
{
    return array_merge([
        'meta' => [
            'app' => 'Astute Money',
            'migration' => '2026_03_08_203425_seed_default_emergency_fund',
            'exported_at' => now()->toIso8601String(),
        ],
        'profile' => [
            'date_of_birth' => '1990-01-15',
            'retirement_age' => 60,
            'expected_return' => '8.0',
            'withdrawal_rate' => '3.5',
        ],
        'spending_plans' => [
            [
                'name' => 'Imported Plan',
                'monthly_income' => 500000,
                'gross_monthly_income' => 800000,
                'pre_tax_investments' => 100000,
                'is_current' => true,
                'fixed_costs_misc_percent' => 10,
                'items' => [
                    [
                        'category' => 'fixed_costs',
                        'name' => 'Mortgage',
                        'amount' => 200000,
                        'sort_order' => 0,
                    ],
                ],
            ],
        ],
        'net_worth_accounts' => [
            [
                'category' => 'savings',
                'name' => 'Emergency Fund',
                'balance' => 1000000,
                'sort_order' => 0,
                'is_emergency_fund' => true,
            ],
        ],
        'rich_life_visions' => [
            [
                'text' => 'Be generous',
                'sort_order' => 0,
            ],
        ],
        'expense_accounts' => [
            [
                'name' => 'Chase',
                'expenses' => [
                    [
                        'merchant' => 'Costco',
                        'amount' => 15000,
                        'category' => 'guilt_free',
                        'date' => '2026-02-15',
                        'is_imported' => false,
                        'reference_number' => null,
                    ],
                ],
            ],
        ],
    ], $overrides);
}

// --- Export Tests ---

test('export includes all models with correct structure', function () {
    seedTestData();

    $data = (new DataExporter)->export();

    expect($data)->toHaveKeys(['meta', 'profile', 'spending_plans', 'net_worth_accounts', 'rich_life_visions', 'expense_accounts']);
    expect($data['meta'])->toHaveKeys(['app', 'migration', 'exported_at']);
    expect($data['meta']['app'])->toBe('Astute Money');
    expect($data['spending_plans'])->toHaveCount(1);
    expect($data['spending_plans'][0]['items'])->toHaveCount(1);
    expect($data['net_worth_accounts'])->toHaveCount(1);
    expect($data['rich_life_visions'])->toHaveCount(1);
    expect($data['expense_accounts'])->toHaveCount(1);
    expect($data['expense_accounts'][0]['expenses'])->toHaveCount(1);
});

test('export includes metadata with latest migration', function () {
    $data = (new DataExporter)->export();

    expect($data['meta']['migration'])->toBeString()->not->toBeEmpty();
    expect($data['meta']['exported_at'])->toBeString();
});

test('export nests spending plan items under their plans', function () {
    $plan = SpendingPlan::factory()->current()->create();
    SpendingPlanItem::factory()->count(3)->create(['spending_plan_id' => $plan->id]);

    $data = (new DataExporter)->export();

    expect($data['spending_plans'])->toHaveCount(1);
    expect($data['spending_plans'][0]['items'])->toHaveCount(3);
    expect($data['spending_plans'][0]['items'][0])->toHaveKeys(['category', 'name', 'amount', 'sort_order']);
});

test('export nests expenses under their accounts', function () {
    $account = ExpenseAccount::factory()->create();
    Expense::factory()->count(2)->create(['expense_account_id' => $account->id]);

    $data = (new DataExporter)->export();

    expect($data['expense_accounts'])->toHaveCount(1);
    expect($data['expense_accounts'][0]['expenses'])->toHaveCount(2);
    expect($data['expense_accounts'][0]['expenses'][0])->toHaveKeys(['merchant', 'amount', 'category', 'date', 'is_imported', 'reference_number']);
});

test('export serializes enum values as strings', function () {
    $plan = SpendingPlan::factory()->current()->create();
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
    ]);

    $account = NetWorthAccount::factory()->category(AccountCategory::Debt)->create();

    $data = (new DataExporter)->export();

    expect($data['spending_plans'][0]['items'][0]['category'])->toBe('fixed_costs');

    $debtAccount = collect($data['net_worth_accounts'])->firstWhere('category', 'debt');
    expect($debtAccount)->not->toBeNull();
    expect($debtAccount['category'])->toBe('debt');
});

test('export handles empty database gracefully', function () {
    $data = (new DataExporter)->export();

    expect($data['spending_plans'])->toBeEmpty();
    // Migration seeds a default Emergency Fund, so 1 account always exists
    expect($data['net_worth_accounts'])->toHaveCount(1);
    expect($data['rich_life_visions'])->toBeEmpty();
    expect($data['expense_accounts'])->toBeEmpty();
    expect($data['profile'])->toHaveKeys(['date_of_birth', 'retirement_age', 'expected_return', 'withdrawal_rate']);
});

// --- Import Tests ---

test('import replaces all existing data', function () {
    seedTestData();

    expect(SpendingPlan::count())->toBe(1);
    expect(Expense::count())->toBe(1);

    $backupData = buildValidBackupData();
    (new DataImporter)->import($backupData);

    expect(SpendingPlan::count())->toBe(1);
    expect(SpendingPlan::first()->name)->toBe('Imported Plan');
    expect(Expense::count())->toBe(1);
    expect(Expense::first()->merchant)->toBe('Costco');
});

test('import creates spending plans with their items', function () {
    $backupData = buildValidBackupData();
    (new DataImporter)->import($backupData);

    $plan = SpendingPlan::first();
    expect($plan->name)->toBe('Imported Plan');
    expect($plan->items)->toHaveCount(1);
    expect($plan->items->first()->name)->toBe('Mortgage');
    expect($plan->items->first()->category)->toBe(SpendingCategory::FixedCosts);
});

test('import creates expense accounts with their expenses', function () {
    $backupData = buildValidBackupData();
    (new DataImporter)->import($backupData);

    $account = ExpenseAccount::first();
    expect($account->name)->toBe('Chase');
    expect($account->expenses)->toHaveCount(1);
    expect($account->expenses->first()->merchant)->toBe('Costco');
});

test('import updates profile data', function () {
    Profile::factory()->create(['retirement_age' => 65]);

    $backupData = buildValidBackupData();
    (new DataImporter)->import($backupData);

    $profile = Profile::instance();
    expect($profile->retirement_age)->toBe(60);
    expect($profile->date_of_birth->format('Y-m-d'))->toBe('1990-01-15');
});

test('import validates required meta fields', function () {
    $errors = (new DataImporter)->validate(['profile' => []]);

    expect($errors)->not->toBeEmpty();
});

test('import validates enum values', function () {
    $data = buildValidBackupData();
    $data['spending_plans'][0]['items'][0]['category'] = 'invalid_category';

    $errors = (new DataImporter)->validate($data);

    expect($errors)->not->toBeEmpty();
});

test('import rejects invalid json structure', function () {
    $errors = (new DataImporter)->validate(['meta' => ['app' => 'Astute Money', 'migration' => 'test']]);

    expect($errors)->not->toBeEmpty();
});

test('import wraps data insertion in a transaction', function () {
    $data = buildValidBackupData();
    // Make expenses invalid so the transaction fails during data insertion
    $data['expense_accounts'] = [['name' => 'Test', 'expenses' => [['invalid' => true]]]];

    try {
        (new DataImporter)->import($data);
    } catch (Throwable) {
        // Expected to fail during data insertion
    }

    // Transaction rolled back — no partial data should exist
    expect(SpendingPlan::count())->toBe(0);
    expect(ExpenseAccount::count())->toBe(0);
});

test('import re-seeds emergency fund if not present in backup', function () {
    $data = buildValidBackupData();
    $data['net_worth_accounts'] = [
        [
            'category' => 'assets',
            'name' => 'House',
            'balance' => 30000000,
            'sort_order' => 0,
            'is_emergency_fund' => false,
        ],
    ];

    (new DataImporter)->import($data);

    expect(NetWorthAccount::where('is_emergency_fund', true)->exists())->toBeTrue();
    expect(NetWorthAccount::count())->toBe(2);
});

test('import preserves emergency fund flag from backup', function () {
    $data = buildValidBackupData();

    (new DataImporter)->import($data);

    $emergencyFund = NetWorthAccount::where('is_emergency_fund', true)->first();
    expect($emergencyFund)->not->toBeNull();
    expect($emergencyFund->balance)->toBe(1000000);
});

test('import rejects backup from newer app version', function () {
    $data = buildValidBackupData();
    $data['meta']['migration'] = '9999_12_31_999999_future_migration';

    $errors = (new DataImporter)->validate($data);

    expect($errors)->toContain('This backup is from a newer version of the app. Please update the app before importing.');
});

// --- Edge Case Tests ---

test('import handles uncategorized expenses', function () {
    $data = buildValidBackupData();
    $data['expense_accounts'][0]['expenses'][0]['category'] = null;

    (new DataImporter)->import($data);

    expect(Expense::first()->category)->toBeNull();
});

test('import handles imported expenses with reference numbers', function () {
    $data = buildValidBackupData();
    $data['expense_accounts'][0]['expenses'][0]['is_imported'] = true;
    $data['expense_accounts'][0]['expenses'][0]['reference_number'] = 'REF-12345';

    (new DataImporter)->import($data);

    $expense = Expense::first();
    expect($expense->is_imported)->toBeTrue();
    expect($expense->reference_number)->toBe('REF-12345');
});

test('import handles spending plan with no items', function () {
    $data = buildValidBackupData();
    $data['spending_plans'][0]['items'] = [];

    (new DataImporter)->import($data);

    $plan = SpendingPlan::first();
    expect($plan->name)->toBe('Imported Plan');
    expect($plan->items)->toBeEmpty();
});

test('import handles expense account with no expenses', function () {
    $data = buildValidBackupData();
    $data['expense_accounts'][0]['expenses'] = [];

    (new DataImporter)->import($data);

    $account = ExpenseAccount::first();
    expect($account->name)->toBe('Chase');
    expect($account->expenses)->toBeEmpty();
});

test('import handles null profile date of birth', function () {
    $data = buildValidBackupData();
    $data['profile']['date_of_birth'] = null;

    (new DataImporter)->import($data);

    expect(Profile::instance()->date_of_birth)->toBeNull();
});

test('import ensures a current spending plan exists', function () {
    $data = buildValidBackupData();
    $data['spending_plans'][0]['is_current'] = false;

    (new DataImporter)->import($data);

    expect(SpendingPlan::where('is_current', true)->count())->toBe(1);
});

test('import with no spending plans skips current plan enforcement', function () {
    $data = buildValidBackupData();
    $data['spending_plans'] = [];

    (new DataImporter)->import($data);

    expect(SpendingPlan::count())->toBe(0);
});

test('import from older backup runs remaining migrations to fill defaults', function () {
    $data = buildValidBackupData();
    // Simulate a backup from before the emergency fund seed migration
    $data['meta']['migration'] = '2026_03_08_162930_remove_auth_and_user_scoping';

    // Backup doesn't include an emergency fund
    $data['net_worth_accounts'] = [
        [
            'category' => 'assets',
            'name' => 'House',
            'balance' => 30000000,
            'sort_order' => 0,
            'is_emergency_fund' => false,
        ],
    ];

    $errors = (new DataImporter)->validate($data);
    expect($errors)->toBeEmpty();

    (new DataImporter)->import($data);

    // Spending plan imported correctly
    $plan = SpendingPlan::first();
    expect($plan->name)->toBe('Imported Plan');

    // Emergency Fund seeded by remaining seed_default_emergency_fund migration
    expect(NetWorthAccount::where('is_emergency_fund', true)->exists())->toBeTrue();
    expect(NetWorthAccount::count())->toBe(2); // House + seeded Emergency Fund

    // Imported data preserved
    expect(Expense::first()->merchant)->toBe('Costco');

    // ensureCurrentPlan ran
    expect(SpendingPlan::where('is_current', true)->exists())->toBeTrue();
});

// --- Round-Trip Test ---

test('exported data can be re-imported identically', function () {
    seedTestData();

    $exported = (new DataExporter)->export();
    $originalNetWorthCount = NetWorthAccount::count();

    // Clear all data
    Expense::query()->delete();
    ExpenseAccount::query()->delete();
    RichLifeVision::query()->delete();
    NetWorthAccount::query()->delete();
    SpendingPlanItem::query()->delete();
    SpendingPlan::query()->delete();

    (new DataImporter)->import($exported);

    expect(SpendingPlan::count())->toBe(1);
    expect(SpendingPlan::first()->name)->toBe('Test Plan');
    expect(SpendingPlanItem::count())->toBe(1);
    expect(SpendingPlanItem::first()->name)->toBe('Rent');
    expect(NetWorthAccount::count())->toBe($originalNetWorthCount);
    expect(RichLifeVision::first()->text)->toBe('Travel the world');
    expect(ExpenseAccount::first()->name)->toBe('Amex');
    expect(Expense::first()->merchant)->toBe('Netflix');
});

// --- Livewire Page Tests ---

test('data settings page is accessible', function () {
    $this->get(route('data.edit'))->assertOk();
});

test('export triggers file download', function () {
    Livewire::test('pages::settings.data')
        ->call('exportData')
        ->assertFileDownloaded();
});

test('uploading valid json shows confirmation modal', function () {
    $data = buildValidBackupData();
    $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($data));

    Livewire::test('pages::settings.data')
        ->set('importFile', $file)
        ->assertSet('showConfirmModal', true)
        ->assertSet('importErrors', []);
});

test('uploading invalid json shows validation errors', function () {
    $file = UploadedFile::fake()->createWithContent('backup.json', 'not valid json');

    Livewire::test('pages::settings.data')
        ->set('importFile', $file)
        ->assertSet('showConfirmModal', false)
        ->assertNotSet('importErrors', []);
});

test('uploading invalid structure shows validation errors', function () {
    $file = UploadedFile::fake()->createWithContent('backup.json', json_encode(['foo' => 'bar']));

    Livewire::test('pages::settings.data')
        ->set('importFile', $file)
        ->assertSet('showConfirmModal', false)
        ->assertNotSet('importErrors', []);
});

test('confirming import replaces data and shows success', function () {
    seedTestData();

    $data = buildValidBackupData();
    $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($data));

    Livewire::test('pages::settings.data')
        ->set('importFile', $file)
        ->assertSet('showConfirmModal', true)
        ->call('confirmImport')
        ->assertSet('showConfirmModal', false)
        ->assertSet('importSuccess', true);

    expect(SpendingPlan::first()->name)->toBe('Imported Plan');
});

test('cancelling import clears state', function () {
    $data = buildValidBackupData();
    $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($data));

    Livewire::test('pages::settings.data')
        ->set('importFile', $file)
        ->assertSet('showConfirmModal', true)
        ->call('cancelImport')
        ->assertSet('showConfirmModal', false)
        ->assertSet('importErrors', [])
        ->assertSet('importSummary', []);
});

<?php

namespace App\Services;

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\ExpenseAccount;
use App\Models\NetWorthAccount;
use App\Models\Profile;
use App\Models\RichLifeVision;
use App\Models\SpendingPlan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DataImporter
{
    /** @return array<int, string> */
    public function validate(array $data): array
    {
        $spendingCategoryValues = array_column(SpendingCategory::cases(), 'value');
        $accountCategoryValues = array_column(AccountCategory::cases(), 'value');

        $validator = Validator::make($data, [
            'meta' => 'required|array',
            'meta.app' => ['required', Rule::in(['Astute Money'])],
            'meta.migration' => 'required|string',

            'profile' => 'required|array',
            'profile.date_of_birth' => 'nullable|date_format:Y-m-d',
            'profile.retirement_age' => 'nullable|integer|min:1',
            'profile.expected_return' => 'nullable|numeric',
            'profile.withdrawal_rate' => 'nullable|numeric',

            'spending_plans' => 'present|array',
            'spending_plans.*.name' => 'required|string|max:255',
            'spending_plans.*.monthly_income' => 'required|integer|min:0',
            'spending_plans.*.gross_monthly_income' => 'sometimes|integer|min:0',
            'spending_plans.*.pre_tax_investments' => 'sometimes|integer|min:0',
            'spending_plans.*.is_current' => 'sometimes|boolean',
            'spending_plans.*.fixed_costs_misc_percent' => 'sometimes|integer|min:0|max:100',
            'spending_plans.*.items' => 'present|array',
            'spending_plans.*.items.*.category' => ['required', Rule::in($spendingCategoryValues)],
            'spending_plans.*.items.*.name' => 'required|string|max:255',
            'spending_plans.*.items.*.amount' => 'required|integer|min:0',
            'spending_plans.*.items.*.sort_order' => 'required|integer|min:0',

            'net_worth_accounts' => 'present|array',
            'net_worth_accounts.*.category' => ['required', Rule::in($accountCategoryValues)],
            'net_worth_accounts.*.name' => 'required|string|max:255',
            'net_worth_accounts.*.balance' => 'required|integer',
            'net_worth_accounts.*.sort_order' => 'required|integer|min:0',
            'net_worth_accounts.*.is_emergency_fund' => 'sometimes|boolean',

            'rich_life_visions' => 'present|array',
            'rich_life_visions.*.text' => 'required|string|max:255',
            'rich_life_visions.*.sort_order' => 'required|integer|min:0',

            'expense_accounts' => 'present|array',
            'expense_accounts.*.name' => 'required|string|max:255',
            'expense_accounts.*.expenses' => 'present|array',
            'expense_accounts.*.expenses.*.merchant' => 'required|string|max:255',
            'expense_accounts.*.expenses.*.amount' => 'required|integer|min:0',
            'expense_accounts.*.expenses.*.category' => ['nullable', Rule::in($spendingCategoryValues)],
            'expense_accounts.*.expenses.*.date' => 'required|date_format:Y-m-d',
            'expense_accounts.*.expenses.*.is_imported' => 'sometimes|boolean',
            'expense_accounts.*.expenses.*.reference_number' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->all();
        }

        $backupMigration = $data['meta']['migration'];
        $currentMigration = DB::table('migrations')
            ->orderBy('id', 'desc')
            ->value('migration');

        if ($backupMigration > $currentMigration) {
            return ['This backup is from a newer version of the app. Please update the app before importing.'];
        }

        return [];
    }

    public function import(array $data): void
    {
        $backupMigration = $data['meta']['migration'];

        // 1. Drop all tables and rebuild schema up to the backup's migration
        $this->dropAllTables();
        Artisan::call('migrate:install');
        $this->runMigrationsUpTo($backupMigration);

        // 2. Clear any data seeded by migrations before importing backup data
        $dataTables = collect(
            DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'")
        )->pluck('name');

        DB::statement('PRAGMA foreign_keys = OFF');
        foreach ($dataTables as $table) {
            DB::table($table)->delete();
        }
        DB::statement('PRAGMA foreign_keys = ON');

        // 3. Import data into the schema that matches the backup
        DB::transaction(function () use ($data) {
            if (Schema::hasTable('profiles')) {
                $this->importProfile($data['profile']);
            }
            if (Schema::hasTable('spending_plans')) {
                $this->importSpendingPlans($data['spending_plans']);
            }
            if (Schema::hasTable('net_worth_accounts')) {
                $this->importNetWorthAccounts($data['net_worth_accounts']);
            }
            if (Schema::hasTable('rich_life_visions')) {
                $this->importRichLifeVisions($data['rich_life_visions']);
            }
            if (Schema::hasTable('expense_accounts')) {
                $this->importExpenseAccounts($data['expense_accounts']);
            }
        });

        // 4. Run remaining migrations to bring schema and data up to current
        Artisan::call('migrate', ['--force' => true]);

        // 5. Safety nets for data invariants not covered by migrations
        SpendingPlan::ensureCurrentPlan();
        $this->ensureEmergencyFund();
    }

    private function dropAllTables(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name');

        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS \"{$table}\"");
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function runMigrationsUpTo(string $targetMigration): void
    {
        $files = glob(database_path('migrations/*.php'));
        sort($files);

        $batch = 1;

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            $migration = require $file;
            $migration->up();

            DB::table('migrations')->insert([
                'migration' => $name,
                'batch' => $batch,
            ]);

            if ($name === $targetMigration) {
                break;
            }

            $batch++;
        }
    }

    private function ensureEmergencyFund(): void
    {
        if (! NetWorthAccount::where('is_emergency_fund', true)->exists()) {
            NetWorthAccount::create([
                'category' => AccountCategory::Savings,
                'name' => 'Emergency Fund',
                'balance' => 0,
                'sort_order' => 0,
                'is_emergency_fund' => true,
            ]);
        }
    }

    private function importProfile(array $data): void
    {
        Profile::instance()->update([
            'date_of_birth' => $data['date_of_birth'],
            'retirement_age' => $data['retirement_age'],
            'expected_return' => $data['expected_return'],
            'withdrawal_rate' => $data['withdrawal_rate'],
        ]);
    }

    private function importSpendingPlans(array $plans): void
    {
        foreach ($plans as $planData) {
            $items = $planData['items'] ?? [];
            unset($planData['items']);

            $plan = SpendingPlan::create($planData);

            foreach ($items as $itemData) {
                $plan->items()->create($itemData);
            }
        }
    }

    private function importNetWorthAccounts(array $accounts): void
    {
        foreach ($accounts as $accountData) {
            NetWorthAccount::create($accountData);
        }
    }

    private function importRichLifeVisions(array $visions): void
    {
        foreach ($visions as $visionData) {
            RichLifeVision::create($visionData);
        }
    }

    private function importExpenseAccounts(array $accounts): void
    {
        foreach ($accounts as $accountData) {
            $expenses = $accountData['expenses'] ?? [];
            unset($accountData['expenses']);

            $account = ExpenseAccount::create($accountData);

            foreach ($expenses as $expenseData) {
                $account->expenses()->create($expenseData);
            }
        }
    }
}

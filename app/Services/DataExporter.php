<?php

namespace App\Services;

use App\Models\ExpenseAccount;
use App\Models\NetWorthAccount;
use App\Models\Profile;
use App\Models\RichLifeVision;
use App\Models\RichLifeVisionCategory;
use App\Models\SpendingPlan;
use Illuminate\Support\Facades\DB;

class DataExporter
{
    /** @return array<string, mixed> */
    public function export(): array
    {
        return [
            'meta' => $this->exportMeta(),
            'profile' => $this->exportProfile(),
            'spending_plans' => $this->exportSpendingPlans(),
            'net_worth_accounts' => $this->exportNetWorthAccounts(),
            'rich_life_vision_categories' => $this->exportRichLifeVisionCategories(),
            'rich_life_visions' => $this->exportRichLifeVisions(),
            'expense_accounts' => $this->exportExpenseAccounts(),
        ];
    }

    /** @return array<string, mixed> */
    private function exportMeta(): array
    {
        $latestMigration = DB::table('migrations')
            ->orderBy('id', 'desc')
            ->value('migration');

        return [
            'app' => 'Astute Money',
            'migration' => $latestMigration,
            'exported_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function exportProfile(): array
    {
        $profile = Profile::instance();

        return [
            'date_of_birth' => $profile->date_of_birth?->format('Y-m-d'),
            'retirement_age' => $profile->retirement_age,
            'expected_return' => $profile->expected_return,
            'withdrawal_rate' => $profile->withdrawal_rate,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function exportSpendingPlans(): array
    {
        return SpendingPlan::with('items')->get()->map(fn (SpendingPlan $plan) => [
            'name' => $plan->name,
            'monthly_income' => $plan->monthly_income,
            'gross_monthly_income' => $plan->gross_monthly_income,
            'pre_tax_investments' => $plan->pre_tax_investments,
            'is_current' => $plan->is_current,
            'fixed_costs_misc_percent' => $plan->fixed_costs_misc_percent,
            'items' => $plan->items->map(fn ($item) => [
                'category' => $item->category->value,
                'name' => $item->name,
                'amount' => $item->amount,
                'sort_order' => $item->sort_order,
            ])->toArray(),
        ])->toArray();
    }

    /** @return list<array<string, mixed>> */
    private function exportNetWorthAccounts(): array
    {
        return NetWorthAccount::all()->map(fn (NetWorthAccount $account) => [
            'category' => $account->category->value,
            'name' => $account->name,
            'balance' => $account->balance,
            'sort_order' => $account->sort_order,
            'is_emergency_fund' => $account->is_emergency_fund,
        ])->toArray();
    }

    /** @return list<array<string, mixed>> */
    private function exportRichLifeVisionCategories(): array
    {
        return RichLifeVisionCategory::all()->map(fn (RichLifeVisionCategory $category) => [
            'name' => $category->name,
            'sort_order' => $category->sort_order,
        ])->toArray();
    }

    /** @return list<array<string, mixed>> */
    private function exportRichLifeVisions(): array
    {
        return RichLifeVision::with('category')->get()->map(fn (RichLifeVision $vision) => [
            'text' => $vision->text,
            'sort_order' => $vision->sort_order,
            'category_name' => $vision->category?->name,
        ])->toArray();
    }

    /** @return list<array<string, mixed>> */
    private function exportExpenseAccounts(): array
    {
        return ExpenseAccount::with('expenses')->get()->map(fn (ExpenseAccount $account) => [
            'name' => $account->name,
            'expenses' => $account->expenses->map(fn ($expense) => [
                'merchant' => $expense->merchant,
                'amount' => $expense->amount,
                'category' => $expense->category?->value,
                'date' => $expense->date->format('Y-m-d'),
                'is_imported' => $expense->is_imported,
                'reference_number' => $expense->reference_number,
            ])->toArray(),
        ])->toArray();
    }
}

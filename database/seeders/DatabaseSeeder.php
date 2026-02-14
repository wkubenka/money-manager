<?php

namespace Database\Seeders;

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\NetWorthAccount;
use App\Models\RichLifeVision;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'date_of_birth' => '1993-09-19',
            'retirement_age' => 65,
            'expected_return' => 7.0,
            'withdrawal_rate' => 4.0,
        ]);

        $this->seedSpendingPlans($user);
        $this->seedNetWorthAccounts($user);
        $this->seedRichLifeVisions($user);
        $this->seedExpenses($user);
    }

    private function seedSpendingPlans(User $user): void
    {
        $sharedPlanFields = [
            'monthly_income' => 624400,
            'gross_monthly_income' => 1028000,
            'pre_tax_investments' => 204500,
            'fixed_costs_misc_percent' => 5,
        ];

        $sharedFixedCosts = [
            ['name' => 'Groceries', 'amount' => 40000, 'sort_order' => 2],
            ['name' => 'Pets', 'amount' => 30000, 'sort_order' => 3],
            ['name' => 'Transportation', 'amount' => 30000, 'sort_order' => 4],
            ['name' => 'Phone', 'amount' => 2000, 'sort_order' => 5],
            ['name' => 'Clothes', 'amount' => 5000, 'sort_order' => 6],
            ['name' => 'Subscriptions', 'amount' => 10000, 'sort_order' => 7],
        ];

        $sharedSavings = [
            ['name' => 'Medical', 'amount' => 40000, 'sort_order' => 0],
            ['name' => 'Emergency Fund', 'amount' => 40000, 'sort_order' => 1],
        ];

        // Plan 1: "Real" (current)
        $real = SpendingPlan::create([
            'user_id' => $user->id,
            'name' => 'Real',
            'is_current' => true,
            ...$sharedPlanFields,
        ]);

        $this->createPlanItems($real, SpendingCategory::FixedCosts, [
            ['name' => 'Rent', 'amount' => 165000, 'sort_order' => 0],
            ['name' => 'Utilities', 'amount' => 15800, 'sort_order' => 1],
            ...$sharedFixedCosts,
        ]);
        $this->createPlanItems($real, SpendingCategory::Investments, [
            ['name' => 'HSA', 'amount' => 36700, 'sort_order' => 0],
        ]);
        $this->createPlanItems($real, SpendingCategory::Savings, $sharedSavings);

        // Plan 2: "2 bed on my own"
        $solo = SpendingPlan::create([
            'user_id' => $user->id,
            'name' => '2 bed on my own',
            'is_current' => false,
            ...$sharedPlanFields,
        ]);

        $this->createPlanItems($solo, SpendingCategory::FixedCosts, [
            ['name' => 'Rent', 'amount' => 220000, 'sort_order' => 0],
            ['name' => 'Utilities', 'amount' => 20000, 'sort_order' => 1],
            ...$sharedFixedCosts,
        ]);
        $this->createPlanItems($solo, SpendingCategory::Investments, [
            ['name' => 'HSA', 'amount' => 36700, 'sort_order' => 0],
        ]);
        $this->createPlanItems($solo, SpendingCategory::Savings, $sharedSavings);

        // Plan 3: "2 bed split"
        $split = SpendingPlan::create([
            'user_id' => $user->id,
            'name' => '2 bed split',
            'is_current' => false,
            ...$sharedPlanFields,
        ]);

        $this->createPlanItems($split, SpendingCategory::FixedCosts, [
            ['name' => 'Rent', 'amount' => 110000, 'sort_order' => 0],
            ['name' => 'Utilities', 'amount' => 10000, 'sort_order' => 1],
            ...$sharedFixedCosts,
        ]);
        $this->createPlanItems($split, SpendingCategory::Investments, [
            ['name' => 'HSA', 'amount' => 36700, 'sort_order' => 0],
            ['name' => 'IRA', 'amount' => 60000, 'sort_order' => 1],
        ]);
        $this->createPlanItems($split, SpendingCategory::Savings, $sharedSavings);
    }

    /**
     * @param  list<array{name: string, amount: int, sort_order: int}>  $items
     */
    private function createPlanItems(SpendingPlan $plan, SpendingCategory $category, array $items): void
    {
        foreach ($items as $item) {
            SpendingPlanItem::create([
                'spending_plan_id' => $plan->id,
                'category' => $category,
                ...$item,
            ]);
        }
    }

    private function seedNetWorthAccounts(User $user): void
    {
        // Update the Emergency Fund created by migration with the real balance
        $user->emergencyFund()->update(['balance' => 2105000]);

        $accounts = [
            ['category' => AccountCategory::Assets, 'name' => 'Car', 'balance' => 1559000],
            ['category' => AccountCategory::Assets, 'name' => 'Average Checking - CC Balance', 'balance' => 500000],
            ['category' => AccountCategory::Investments, 'name' => 'Fidelity', 'balance' => 4453900],
            ['category' => AccountCategory::Investments, 'name' => 'Guideline', 'balance' => 5251100],
            ['category' => AccountCategory::Investments, 'name' => 'TSP', 'balance' => 795900],
            ['category' => AccountCategory::Investments, 'name' => 'Vanguard', 'balance' => 26364800],
            ['category' => AccountCategory::Savings, 'name' => 'Medical', 'balance' => 362700],
            ['category' => AccountCategory::Savings, 'name' => 'Car', 'balance' => 108292],
            ['category' => AccountCategory::Savings, 'name' => 'General', 'balance' => 100000],
            ['category' => AccountCategory::Savings, 'name' => 'Vacation', 'balance' => 172804],
            ['category' => AccountCategory::Savings, 'name' => 'Sport', 'balance' => 153383],
        ];

        foreach ($accounts as $account) {
            NetWorthAccount::create([
                'user_id' => $user->id,
                'sort_order' => 0,
                ...$account,
            ]);
        }
    }

    private function seedRichLifeVisions(User $user): void
    {
        $visions = [
            'I am healthy and active',
            'I prioritize peace and simplicity',
            'I am engaged with my community',
            'I shop local if at all possible',
            'I only have clothes that I look and feel good in',
            'I have a beautiful home that I love',
            'I take a month-long vacation every year',
            'I am the most generous of all my friends',
        ];

        foreach ($visions as $index => $text) {
            RichLifeVision::create([
                'user_id' => $user->id,
                'text' => $text,
                'sort_order' => $index,
            ]);
        }
    }

    private function seedExpenses(User $user): void
    {
        $boa = ExpenseAccount::create(['user_id' => $user->id, 'name' => 'BoA']);
        $amex = ExpenseAccount::create(['user_id' => $user->id, 'name' => 'Amex']);
        $checking = ExpenseAccount::create(['user_id' => $user->id, 'name' => 'Checking']);
        $cash = ExpenseAccount::create(['user_id' => $user->id, 'name' => 'Cash']);

        $today = Carbon::today();

        // Expenses are listed as [merchant, amount_cents, category, days_ago, account]
        // Organized by month, covering the last ~60 days
        $expenses = [
            // === THIS MONTH (last ~13 days) ===

            // 1st of month - recurring bills
            [$checking, 'Landlord', 165000, SpendingCategory::FixedCosts, $today->copy()->startOfMonth()],
            [$boa, 'T-Mobile', 2000, SpendingCategory::FixedCosts, $today->copy()->startOfMonth()],
            [$boa, 'HSA Contribution', 36700, SpendingCategory::Investments, $today->copy()->startOfMonth()],
            [$boa, 'Medical Savings Transfer', 40000, SpendingCategory::Savings, $today->copy()->startOfMonth()],
            [$boa, 'Emergency Fund Transfer', 40000, SpendingCategory::Savings, $today->copy()->startOfMonth()],

            // Subscriptions throughout month
            [$amex, 'Netflix', 1799, SpendingCategory::FixedCosts, $today->copy()->startOfMonth()->addDays(1)],
            [$amex, 'Spotify', 1199, SpendingCategory::FixedCosts, $today->copy()->startOfMonth()->addDays(2)],
            [$amex, 'Planet Fitness', 2500, SpendingCategory::FixedCosts, $today->copy()->startOfMonth()->addDays(3)],

            // Groceries
            [$amex, "Trader Joe's", 6847, SpendingCategory::FixedCosts, $today->copy()->subDays(11)],
            [$amex, 'Whole Foods', 4523, SpendingCategory::FixedCosts, $today->copy()->subDays(7)],
            [$amex, "Trader Joe's", 7891, SpendingCategory::FixedCosts, $today->copy()->subDays(3)],

            // Pets
            [$amex, 'Chewy', 8500, SpendingCategory::FixedCosts, $today->copy()->subDays(10)],
            [$amex, 'PetSmart', 4200, SpendingCategory::FixedCosts, $today->copy()->subDays(5)],

            // Transportation
            [$amex, 'Shell Gas', 4800, SpendingCategory::FixedCosts, $today->copy()->subDays(9)],
            [$amex, 'Shell Gas', 5100, SpendingCategory::FixedCosts, $today->copy()->subDays(2)],

            // Clothes
            [$amex, 'Uniqlo', 3500, SpendingCategory::FixedCosts, $today->copy()->subDays(6)],

            // Guilt-Free this month
            [$amex, 'Blue Bottle Coffee', 650, SpendingCategory::GuiltFree, $today->copy()->subDays(12)],
            [$amex, 'Chipotle', 1247, SpendingCategory::GuiltFree, $today->copy()->subDays(11)],
            [$cash, 'Farmers Market', 2800, SpendingCategory::GuiltFree, $today->copy()->subDays(10)],
            [$amex, 'Thai Basil', 3450, SpendingCategory::GuiltFree, $today->copy()->subDays(9)],
            [$amex, 'Amazon', 3299, SpendingCategory::GuiltFree, $today->copy()->subDays(8)],
            [$amex, 'Blue Bottle Coffee', 700, SpendingCategory::GuiltFree, $today->copy()->subDays(7)],
            [$amex, 'AMC Theaters', 1800, SpendingCategory::GuiltFree, $today->copy()->subDays(6)],
            [$amex, 'Uber', 1450, SpendingCategory::GuiltFree, $today->copy()->subDays(5)],
            [$cash, 'Tipping at bar', 3500, SpendingCategory::GuiltFree, $today->copy()->subDays(4)],
            [$amex, 'Target', 4275, SpendingCategory::GuiltFree, $today->copy()->subDays(3)],
            [$amex, 'Starbucks', 575, SpendingCategory::GuiltFree, $today->copy()->subDays(2)],
            [$amex, 'Sushi Roku', 6800, SpendingCategory::GuiltFree, $today->copy()->subDays(1)],
            [$amex, 'Blue Bottle Coffee', 625, SpendingCategory::GuiltFree, $today],

            // === LAST MONTH ===

            // 1st of last month - recurring bills
            [$checking, 'Landlord', 165000, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()],
            [$boa, 'T-Mobile', 2000, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()],
            [$boa, 'HSA Contribution', 36700, SpendingCategory::Investments, $today->copy()->subMonth()->startOfMonth()],
            [$boa, 'Medical Savings Transfer', 40000, SpendingCategory::Savings, $today->copy()->subMonth()->startOfMonth()],
            [$boa, 'Emergency Fund Transfer', 40000, SpendingCategory::Savings, $today->copy()->subMonth()->startOfMonth()],

            // Subscriptions
            [$amex, 'Netflix', 1799, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(1)],
            [$amex, 'Spotify', 1199, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(2)],
            [$amex, 'Planet Fitness', 2500, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(3)],

            // Utilities
            [$checking, 'Electric Company', 9800, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(14)],
            [$checking, 'Water Utility', 6000, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(14)],

            // Groceries
            [$amex, "Trader Joe's", 5632, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(2)],
            [$amex, 'Kroger', 9245, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(6)],
            [$amex, 'Whole Foods', 5180, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(10)],
            [$amex, "Trader Joe's", 7340, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(14)],
            [$amex, 'Kroger', 6890, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(20)],
            [$amex, "Trader Joe's", 8120, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(25)],

            // Pets
            [$amex, 'Chewy', 7500, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(5)],
            [$amex, 'Banfield Vet', 18500, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(12)],
            [$cash, 'Dog Walker', 4000, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(18)],

            // Transportation
            [$amex, 'Shell Gas', 4500, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(4)],
            [$amex, 'Chevron', 5200, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(11)],
            [$amex, 'Shell Gas', 4800, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(19)],
            [$amex, 'Jiffy Lube', 8900, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(22)],
            [$amex, 'Shell Gas', 5100, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(26)],

            // Clothes
            [$amex, 'Nordstrom Rack', 4500, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(8)],
            [$amex, 'Uniqlo', 2800, SpendingCategory::FixedCosts, $today->copy()->subMonth()->startOfMonth()->addDays(20)],

            // Guilt-Free last month
            [$amex, 'Blue Bottle Coffee', 650, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(1)],
            [$amex, 'Chipotle', 1350, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(2)],
            [$amex, 'Amazon', 2499, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(3)],
            [$amex, 'Uber', 1875, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(4)],
            [$amex, 'Blue Bottle Coffee', 700, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(5)],
            [$amex, 'Pho 88', 2250, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(6)],
            [$cash, 'Farmers Market', 3200, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(7)],
            [$amex, 'REI', 8900, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(8)],
            [$amex, 'Starbucks', 625, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(9)],
            [$amex, "Applebee's", 3500, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(10)],
            [$amex, 'BookShop.org', 1899, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(11)],
            [$amex, 'Blue Bottle Coffee', 650, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(13)],
            [$amex, 'Uber Eats', 3200, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(14)],
            [$cash, 'Brewery', 4200, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(15)],
            [$amex, 'AMC Theaters', 3200, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(16)],
            [$amex, 'Supercuts', 3500, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(17)],
            [$amex, 'Blue Bottle Coffee', 625, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(19)],
            [$amex, 'Target', 5600, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(20)],
            [$amex, 'Olive Garden', 4200, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(21)],
            [$amex, 'Uber', 1250, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(22)],
            [$amex, 'Starbucks', 575, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(23)],
            [$amex, 'Amazon', 4599, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(24)],
            [$cash, 'Bar tab', 3800, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(25)],
            [$amex, 'Blue Bottle Coffee', 700, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(27)],
            [$amex, 'Sushi Roku', 7200, SpendingCategory::GuiltFree, $today->copy()->subMonth()->startOfMonth()->addDays(28)],
        ];

        foreach ($expenses as [$account, $merchant, $amount, $category, $date]) {
            Expense::create([
                'user_id' => $user->id,
                'expense_account_id' => $account->id,
                'merchant' => $merchant,
                'amount' => $amount,
                'category' => $category,
                'date' => $date,
            ]);
        }
    }
}

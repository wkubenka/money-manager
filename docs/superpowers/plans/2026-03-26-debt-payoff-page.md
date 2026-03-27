# Debt Payoff Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a scenario-based debt payoff calculator page with Chart.js visualizations, supporting avalanche/snowball strategies, extra monthly payments, and lump sum modeling.

**Architecture:** Extends the existing `DebtPayoffCalculator` service with snowball, lump sum, and timeline support. A single Livewire page manages ephemeral scenarios as component state. Chart.js renders three chart types (balance over time, payoff order, interest comparison) via Alpine.js, reacting to Livewire state changes. Sidebar conditionally shows the nav item when debt accounts exist.

**Tech Stack:** Laravel 12, Livewire 4, Flux UI Free, Alpine.js, Chart.js (CDN), Pest 3

---

## File Map

| Action | File | Responsibility |
|--------|------|---------------|
| Modify | `app/Services/DebtPayoffCalculator.php` | Add snowball, lump sum, timeline, payoff order |
| Modify | `tests/Feature/DebtPayoffCalculatorTest.php` | New tests for snowball, lump sum, timeline |
| Create | `routes/debt-payoff.php` | Route definition |
| Modify | `routes/web.php` | Require new route file |
| Create | `resources/views/pages/debt-payoff/⚡index.blade.php` | Livewire page component + template |
| Modify | `resources/views/layouts/app/sidebar.blade.php` | Conditional "Debt Payoff" nav item |
| Create | `tests/Feature/DebtPayoff/DebtPayoffPageTest.php` | Page feature tests |

---

### Task 1: Extend Calculator — Snowball Method

**Files:**
- Modify: `tests/Feature/DebtPayoffCalculatorTest.php`
- Modify: `app/Services/DebtPayoffCalculator.php`

- [ ] **Step 1: Write failing test for snowball method**

Add to `tests/Feature/DebtPayoffCalculatorTest.php`:

```php
test('snowball method pays smallest balance first', function () {
    $calculator = new DebtPayoffCalculator;

    // Small balance ($2,000 at 5%) and large balance ($8,000 at 25%)
    // Snowball targets the $2,000 first despite lower rate
    $debts = collect([
        ['name' => 'Big Loan', 'balance' => 800000, 'interest_rate' => 25.0, 'minimum_payment' => 10000],
        ['name' => 'Small Loan', 'balance' => 200000, 'interest_rate' => 5.0, 'minimum_payment' => 10000],
    ]);

    $result = $calculator->calculate($debts, 60000, strategy: 'snowball');

    expect($result)->not->toBeNull();
    // Snowball pays more total interest than avalanche
    $avalancheResult = $calculator->calculate($debts, 60000, strategy: 'avalanche');
    expect($result['total_interest_paid'])->toBeGreaterThan($avalancheResult['total_interest_paid']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact --filter="snowball method pays smallest balance first"`
Expected: FAIL — `calculate()` doesn't accept `strategy` parameter yet.

- [ ] **Step 3: Add strategy parameter to calculator**

Update `app/Services/DebtPayoffCalculator.php` — change the `calculate` method signature and sorting logic:

```php
public function calculate(Collection $debts, int $totalMonthlyPaymentCents, string $strategy = 'avalanche', int $lumpSumCents = 0, int $lumpSumMonth = 1): ?array
{
    if ($debts->isEmpty() || $totalMonthlyPaymentCents <= 0) {
        return null;
    }

    // Build working array sorted by strategy
    $working = $debts
        ->map(fn (array $debt) => [
            'name' => $debt['name'] ?? 'Debt',
            'balance' => (float) $debt['balance'],
            'interest_rate' => (float) $debt['interest_rate'],
            'minimum_payment' => (int) $debt['minimum_payment'],
            'monthly_rate' => ((float) $debt['interest_rate']) / 100 / 12,
        ]);

    $working = match ($strategy) {
        'snowball' => $working->sortBy('balance')->values()->all(),
        default => $working->sortByDesc('interest_rate')->values()->all(),
    };

    $totalInterestPaid = 0;
    $months = 0;

    while ($months < self::MAX_MONTHS) {
        $totalRemaining = array_sum(array_column($working, 'balance'));
        if ($totalRemaining <= 0) {
            break;
        }

        $months++;

        // Accrue interest
        foreach ($working as &$debt) {
            if ($debt['balance'] <= 0) {
                continue;
            }
            $interest = $debt['balance'] * $debt['monthly_rate'];
            $debt['balance'] += $interest;
            $totalInterestPaid += $interest;
        }
        unset($debt);

        // Apply lump sum in the specified month
        if ($lumpSumCents > 0 && $months === $lumpSumMonth) {
            $remaining = $lumpSumCents;
            foreach ($working as &$debt) {
                if ($debt['balance'] <= 0 || $remaining <= 0) {
                    continue;
                }
                $payment = min($remaining, $debt['balance']);
                $debt['balance'] -= $payment;
                $remaining -= $payment;
            }
            unset($debt);
        }

        // Calculate surplus
        $activeMinimums = 0;
        foreach ($working as &$debt) {
            if ($debt['balance'] <= 0) {
                continue;
            }
            $activeMinimums += $debt['minimum_payment'];
        }
        unset($debt);

        $surplus = max(0, $totalMonthlyPaymentCents - $activeMinimums);

        // Apply minimum payments
        foreach ($working as &$debt) {
            if ($debt['balance'] <= 0) {
                continue;
            }
            $payment = min($debt['minimum_payment'], $debt['balance']);
            $debt['balance'] -= $payment;
        }
        unset($debt);

        // Apply surplus to target debt (already sorted by strategy)
        foreach ($working as &$debt) {
            if ($debt['balance'] <= 0 || $surplus <= 0) {
                continue;
            }
            $extraPayment = min($surplus, $debt['balance']);
            $debt['balance'] -= $extraPayment;
            $surplus -= $extraPayment;
        }
        unset($debt);
    }

    return [
        'payoff_date' => Carbon::now()->addMonthsNoOverflow($months),
        'months_to_payoff' => $months,
        'total_interest_paid' => (int) round($totalInterestPaid),
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact --filter="snowball method pays smallest balance first"`
Expected: PASS

- [ ] **Step 5: Run all existing calculator tests to verify no regressions**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoffCalculatorTest.php`
Expected: All tests pass. The existing tests don't pass `strategy:` so they default to `'avalanche'`, preserving current behavior. However, existing tests don't pass `name` in their debt arrays — update the existing test data to include `'name' => 'Debt'` in each debt array, or make the `name` key optional in the map (already handled with `$debt['name'] ?? 'Debt'`).

- [ ] **Step 6: Commit**

```bash
git add app/Services/DebtPayoffCalculator.php tests/Feature/DebtPayoffCalculatorTest.php
git commit -m "Add snowball strategy and lump sum support to DebtPayoffCalculator"
```

---

### Task 2: Extend Calculator — Timeline & Payoff Order

**Files:**
- Modify: `tests/Feature/DebtPayoffCalculatorTest.php`
- Modify: `app/Services/DebtPayoffCalculator.php`

- [ ] **Step 1: Write failing tests for timeline and payoff order**

Add to `tests/Feature/DebtPayoffCalculatorTest.php`:

```php
test('returns monthly timeline data', function () {
    $calculator = new DebtPayoffCalculator;

    $debts = collect([
        ['name' => 'Credit Card', 'balance' => 500000, 'interest_rate' => 20.0, 'minimum_payment' => 20000],
    ]);

    $result = $calculator->calculate($debts, 20000);

    expect($result)->toHaveKey('timeline');
    expect($result['timeline'])->toBeArray();
    expect($result['timeline'])->not->toBeEmpty();

    $firstMonth = $result['timeline'][0];
    expect($firstMonth)->toHaveKeys(['month', 'balances', 'interest']);
    expect($firstMonth['month'])->toBe(1);
    expect($firstMonth['balances'])->toHaveKey('Credit Card');
    expect($firstMonth['balances']['Credit Card'])->toBeLessThan(500000);
    expect($firstMonth['interest'])->toBeGreaterThan(0);
});

test('returns payoff order tracking', function () {
    $calculator = new DebtPayoffCalculator;

    $debts = collect([
        ['name' => 'Small Debt', 'balance' => 100000, 'interest_rate' => 10.0, 'minimum_payment' => 10000],
        ['name' => 'Big Debt', 'balance' => 500000, 'interest_rate' => 15.0, 'minimum_payment' => 10000],
    ]);

    $result = $calculator->calculate($debts, 50000);

    expect($result)->toHaveKey('payoff_order');
    expect($result['payoff_order'])->toHaveCount(2);

    // Each entry has name and paid_off_month
    $first = $result['payoff_order'][0];
    expect($first)->toHaveKeys(['name', 'paid_off_month']);
    expect($first['paid_off_month'])->toBeGreaterThan(0);

    // First debt paid off should be before the second
    expect($result['payoff_order'][0]['paid_off_month'])
        ->toBeLessThan($result['payoff_order'][1]['paid_off_month']);
});

test('timeline balances reach zero at payoff', function () {
    $calculator = new DebtPayoffCalculator;

    $debts = collect([
        ['name' => 'Loan', 'balance' => 500000, 'interest_rate' => 10.0, 'minimum_payment' => 20000],
    ]);

    $result = $calculator->calculate($debts, 20000);
    $lastMonth = end($result['timeline']);

    expect($lastMonth['balances']['Loan'])->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact --filter="returns monthly timeline|returns payoff order|timeline balances reach"`
Expected: FAIL — no `timeline` or `payoff_order` keys in result.

- [ ] **Step 3: Add timeline and payoff order tracking to calculator**

Update the `calculate` method in `app/Services/DebtPayoffCalculator.php`. Add tracking arrays before the while loop and record data each month. Replace the return statement.

Before the while loop, add:

```php
$timeline = [];
$payoffOrder = [];
$paidOff = [];
```

At the end of each month iteration (after surplus application), add:

```php
// Record timeline
$monthBalances = [];
$monthInterest = 0;
foreach ($working as &$debt) {
    $balance = max(0, (int) round($debt['balance']));
    $monthBalances[$debt['name']] = $balance;
}
unset($debt);

// Track which debts were just paid off
foreach ($working as &$debt) {
    if ($debt['balance'] <= 0 && ! isset($paidOff[$debt['name']])) {
        $paidOff[$debt['name']] = true;
        $payoffOrder[] = ['name' => $debt['name'], 'paid_off_month' => $months];
    }
}
unset($debt);

$timeline[] = [
    'month' => $months,
    'balances' => $monthBalances,
    'interest' => (int) round($totalInterestPaid),
];
```

Update the return to include:

```php
return [
    'payoff_date' => Carbon::now()->addMonthsNoOverflow($months),
    'months_to_payoff' => $months,
    'total_interest_paid' => (int) round($totalInterestPaid),
    'timeline' => $timeline,
    'payoff_order' => $payoffOrder,
];
```

- [ ] **Step 4: Run all calculator tests**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoffCalculatorTest.php`
Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/DebtPayoffCalculator.php tests/Feature/DebtPayoffCalculatorTest.php
git commit -m "Add timeline and payoff order tracking to DebtPayoffCalculator"
```

---

### Task 3: Extend Calculator — Lump Sum Tests

**Files:**
- Modify: `tests/Feature/DebtPayoffCalculatorTest.php`

- [ ] **Step 1: Write failing tests for lump sum**

Add to `tests/Feature/DebtPayoffCalculatorTest.php`:

```php
test('lump sum payment reduces payoff time', function () {
    $calculator = new DebtPayoffCalculator;

    $debts = collect([
        ['name' => 'Car Loan', 'balance' => 1000000, 'interest_rate' => 10.0, 'minimum_payment' => 20000],
    ]);

    $withoutLump = $calculator->calculate($debts, 30000);
    $withLump = $calculator->calculate($debts, 30000, lumpSumCents: 500000, lumpSumMonth: 1);

    expect($withLump['months_to_payoff'])->toBeLessThan($withoutLump['months_to_payoff']);
    expect($withLump['total_interest_paid'])->toBeLessThan($withoutLump['total_interest_paid']);
});

test('lump sum applied in correct month', function () {
    $calculator = new DebtPayoffCalculator;

    $debts = collect([
        ['name' => 'Loan', 'balance' => 1000000, 'interest_rate' => 10.0, 'minimum_payment' => 20000],
    ]);

    // Lump sum in month 3
    $result = $calculator->calculate($debts, 20000, lumpSumCents: 500000, lumpSumMonth: 3);

    // Balance should drop significantly between month 2 and month 3
    $month2Balance = $result['timeline'][1]['balances']['Loan'];
    $month3Balance = $result['timeline'][2]['balances']['Loan'];
    $drop = $month2Balance - $month3Balance;

    // The drop should be much larger than a normal payment (~$200 min + interest)
    expect($drop)->toBeGreaterThan(400000);
});
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact --filter="lump sum"`
Expected: PASS — lump sum logic was already added in Task 1 Step 3.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DebtPayoffCalculatorTest.php
git commit -m "Add lump sum tests for DebtPayoffCalculator"
```

---

### Task 4: Route & Empty Page Scaffold

**Files:**
- Create: `routes/debt-payoff.php`
- Modify: `routes/web.php`
- Create: `resources/views/pages/debt-payoff/⚡index.blade.php`

- [ ] **Step 1: Create route file**

Create `routes/debt-payoff.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::livewire('debt-payoff', 'pages::debt-payoff.index')
    ->name('debt-payoff.index');
```

- [ ] **Step 2: Require route file from web.php**

Add to the end of `routes/web.php`:

```php
require __DIR__.'/debt-payoff.php';
```

- [ ] **Step 3: Create minimal Livewire page**

Create `resources/views/pages/debt-payoff/⚡index.blade.php`:

```php
<?php

use Livewire\Component;

new class extends Component {
    //
}; ?>

<section class="w-full">
    <x-page-heading title="Debt Payoff" subtitle="Plan your path to debt freedom" />

    <p class="text-sm text-zinc-500 dark:text-zinc-400">Coming soon.</p>
</section>
```

- [ ] **Step 4: Verify route works**

Run: `/usr/local/opt/php@8.3/bin/php artisan route:list --path=debt-payoff`
Expected: Shows `GET debt-payoff` route named `debt-payoff.index`.

- [ ] **Step 5: Commit**

```bash
git add routes/debt-payoff.php routes/web.php resources/views/pages/debt-payoff/
git commit -m "Add debt payoff route and empty page scaffold"
```

---

### Task 5: Conditional Sidebar Nav Item

**Files:**
- Modify: `resources/views/layouts/app/sidebar.blade.php`
- Create: `tests/Feature/DebtPayoff/DebtPayoffPageTest.php`

- [ ] **Step 1: Write tests for sidebar visibility**

Create `tests/Feature/DebtPayoff/DebtPayoffPageTest.php`:

```php
<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('debt payoff page is accessible when debts exist', function () {
    NetWorthAccount::factory()->debt()->create();

    $this->get(route('debt-payoff.index'))
        ->assertOk();
});

test('sidebar shows debt payoff link when debts exist', function () {
    NetWorthAccount::factory()->debt()->create();

    $this->get(route('dashboard'))
        ->assertSee('Debt Payoff');
});

test('sidebar hides debt payoff link when no debts exist', function () {
    $this->get(route('dashboard'))
        ->assertDontSee('Debt Payoff');
});

test('sidebar hides debt payoff link when only non-debt accounts exist', function () {
    NetWorthAccount::factory()->category(AccountCategory::Assets)->create();

    $this->get(route('dashboard'))
        ->assertDontSee('Debt Payoff');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoff/DebtPayoffPageTest.php`
Expected: FAIL — sidebar doesn't have "Debt Payoff" link yet.

- [ ] **Step 3: Add conditional sidebar item**

In `resources/views/layouts/app/sidebar.blade.php`, add after the Expenses sidebar item (inside `<flux:sidebar.group class="grid">`):

```blade
@if (\App\Models\NetWorthAccount::where('category', 'debt')->exists())
    <flux:sidebar.item icon="calculator" :href="route('debt-payoff.index')" :current="request()->routeIs('debt-payoff.*')" wire:navigate>
        {{ __('Debt Payoff') }}
    </flux:sidebar.item>
@endif
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoff/DebtPayoffPageTest.php`
Expected: All 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app/sidebar.blade.php tests/Feature/DebtPayoff/DebtPayoffPageTest.php
git commit -m "Add conditional Debt Payoff sidebar nav item"
```

---

### Task 6: Livewire Page — Data Loading & Scenario State

**Files:**
- Modify: `resources/views/pages/debt-payoff/⚡index.blade.php`
- Modify: `tests/Feature/DebtPayoff/DebtPayoffPageTest.php`

- [ ] **Step 1: Write tests for page data and scenarios**

Add to `tests/Feature/DebtPayoff/DebtPayoffPageTest.php`:

```php
use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use Livewire\Livewire;

test('page shows empty state when no debts exist', function () {
    Livewire::test('pages::debt-payoff.index')
        ->assertSee('No debt accounts found');
});

test('page loads baseline scenario from spending plan', function () {
    $plan = SpendingPlan::factory()->current()->create();
    SpendingPlanItem::factory()->for($plan)->create([
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Debt Payments',
        'amount' => 85000, // $850
    ]);

    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Credit Card',
        'balance' => 1000000,
        'interest_rate' => 20.0,
        'minimum_payment' => 20000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->assertSee('Current Plan')
        ->assertSee('Credit Card');
});

test('page falls back to sum of minimums when no spending plan', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Car Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 30000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->assertSee('Current Plan')
        ->assertSee('$300/mo');
});

test('user can add a scenario', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->set('newScenario.name', 'Extra $200')
        ->set('newScenario.strategy', 'avalanche')
        ->set('newScenario.extra_payment', '200')
        ->set('newScenario.lump_sum', '0')
        ->set('newScenario.lump_sum_month', '1')
        ->call('addScenario')
        ->assertSee('Extra $200');
});

test('user can remove a scenario', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000,
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->set('newScenario.name', 'Test Scenario')
        ->set('newScenario.strategy', 'avalanche')
        ->set('newScenario.extra_payment', '0')
        ->set('newScenario.lump_sum', '0')
        ->set('newScenario.lump_sum_month', '1')
        ->call('addScenario')
        ->assertSee('Test Scenario')
        ->call('removeScenario', 1)
        ->assertDontSee('Test Scenario');
});

test('maximum five scenarios allowed', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000,
    ]);

    $component = Livewire::test('pages::debt-payoff.index');

    // Baseline is scenario 0, add 4 more to hit the max of 5
    for ($i = 1; $i <= 4; $i++) {
        $component
            ->set('newScenario.name', "Scenario {$i}")
            ->set('newScenario.strategy', 'avalanche')
            ->set('newScenario.extra_payment', (string) ($i * 100))
            ->set('newScenario.lump_sum', '0')
            ->set('newScenario.lump_sum_month', '1')
            ->call('addScenario');
    }

    // Try to add a 6th — should not appear
    $component
        ->set('newScenario.name', 'Too Many')
        ->set('newScenario.strategy', 'avalanche')
        ->set('newScenario.extra_payment', '500')
        ->set('newScenario.lump_sum', '0')
        ->set('newScenario.lump_sum_month', '1')
        ->call('addScenario')
        ->assertDontSee('Too Many');
});

test('shows warning when budget is less than minimums', function () {
    $plan = SpendingPlan::factory()->current()->create();
    SpendingPlanItem::factory()->for($plan)->create([
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Debt Payments',
        'amount' => 10000, // $100 — less than $200 minimum
    ]);

    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'name' => 'Loan',
        'balance' => 500000,
        'interest_rate' => 10.0,
        'minimum_payment' => 20000, // $200 minimum
    ]);

    Livewire::test('pages::debt-payoff.index')
        ->assertSee("doesn't cover all minimum payments");
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoff/DebtPayoffPageTest.php`
Expected: Most fail — page logic not implemented yet.

- [ ] **Step 3: Implement the Livewire component PHP logic**

Replace the PHP section of `resources/views/pages/debt-payoff/⚡index.blade.php`:

```php
<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\NetWorthAccount;
use App\Models\SpendingPlan;
use App\Services\DebtPayoffCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public const MAX_SCENARIOS = 5;

    public array $scenarios = [];

    public array $newScenario = [
        'name' => '',
        'strategy' => 'avalanche',
        'extra_payment' => '0',
        'lump_sum' => '0',
        'lump_sum_month' => '1',
    ];

    public function mount(): void
    {
        if ($this->debts->isEmpty()) {
            return;
        }

        $this->scenarios[] = [
            'name' => 'Current Plan',
            'strategy' => 'avalanche',
            'extra_payment' => 0,
            'lump_sum' => 0,
            'lump_sum_month' => 1,
            'is_baseline' => true,
        ];
    }

    #[Computed]
    public function debts()
    {
        return NetWorthAccount::query()
            ->where('category', AccountCategory::Debt)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function baselineMonthlyPaymentCents(): int
    {
        $plan = SpendingPlan::where('is_current', true)->with('items')->first();

        if ($plan) {
            $debtItem = $plan->items->first(fn ($item) => $item->category === SpendingCategory::FixedCosts && $item->name === 'Debt Payments');

            if ($debtItem) {
                return $debtItem->amount;
            }
        }

        return (int) $this->debts->sum('minimum_payment');
    }

    #[Computed]
    public function sumOfMinimums(): int
    {
        return (int) $this->debts->sum('minimum_payment');
    }

    #[Computed]
    public function budgetBelowMinimums(): bool
    {
        return $this->baselineMonthlyPaymentCents < $this->sumOfMinimums;
    }

    #[Computed]
    public function hasSpendingPlanSource(): bool
    {
        $plan = SpendingPlan::where('is_current', true)->with('items')->first();

        if (! $plan) {
            return false;
        }

        return $plan->items->contains(fn ($item) => $item->category === SpendingCategory::FixedCosts && $item->name === 'Debt Payments');
    }

    #[Computed]
    public function scenarioResults(): array
    {
        if ($this->debts->isEmpty()) {
            return [];
        }

        $calculator = new DebtPayoffCalculator;
        $results = [];

        $debtData = $this->debts->map(fn ($account) => [
            'name' => $account->name,
            'balance' => $account->balance,
            'interest_rate' => (float) ($account->interest_rate ?? 0),
            'minimum_payment' => $account->minimum_payment ?? 0,
        ]);

        foreach ($this->scenarios as $index => $scenario) {
            $extraCents = (int) round(($scenario['extra_payment'] ?? 0) * 100);
            $lumpCents = (int) round(($scenario['lump_sum'] ?? 0) * 100);
            $totalPayment = $this->baselineMonthlyPaymentCents + $extraCents;

            $result = $calculator->calculate(
                $debtData,
                $totalPayment,
                strategy: $scenario['strategy'],
                lumpSumCents: $lumpCents,
                lumpSumMonth: $scenario['lump_sum_month'] ?? 1,
            );

            $results[] = [
                'scenario' => $scenario,
                'result' => $result,
                'monthly_payment_cents' => $totalPayment,
            ];
        }

        return $results;
    }

    public function addScenario(): void
    {
        if (count($this->scenarios) >= self::MAX_SCENARIOS) {
            return;
        }

        $this->validate([
            'newScenario.name' => ['required', 'string', 'max:255'],
            'newScenario.strategy' => ['required', 'in:avalanche,snowball'],
            'newScenario.extra_payment' => ['required', 'numeric', 'min:0'],
            'newScenario.lump_sum' => ['required', 'numeric', 'min:0'],
            'newScenario.lump_sum_month' => ['required', 'integer', 'min:1'],
        ]);

        $this->scenarios[] = [
            'name' => $this->newScenario['name'],
            'strategy' => $this->newScenario['strategy'],
            'extra_payment' => (float) $this->newScenario['extra_payment'],
            'lump_sum' => (float) $this->newScenario['lump_sum'],
            'lump_sum_month' => (int) $this->newScenario['lump_sum_month'],
            'is_baseline' => false,
        ];

        $this->resetNewScenario();
        unset($this->scenarioResults);
    }

    public function removeScenario(int $index): void
    {
        if (isset($this->scenarios[$index]) && ! ($this->scenarios[$index]['is_baseline'] ?? false)) {
            unset($this->scenarios[$index]);
            $this->scenarios = array_values($this->scenarios);
            unset($this->scenarioResults);
        }
    }

    private function resetNewScenario(): void
    {
        $this->newScenario = [
            'name' => '',
            'strategy' => 'avalanche',
            'extra_payment' => '0',
            'lump_sum' => '0',
            'lump_sum_month' => '1',
        ];
    }
}; ?>
```

- [ ] **Step 4: Implement the minimal Blade template**

Replace the template section (everything after `?>`) with:

```blade
<section class="w-full">
    <x-page-heading title="Debt Payoff" subtitle="Plan your path to debt freedom" />

    @if ($this->debts->isEmpty())
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-8 text-center">
            <p class="text-zinc-500 dark:text-zinc-400">{{ __('No debt accounts found.') }}</p>
            <flux:link :href="route('net-worth.index')" wire:navigate class="text-sm mt-2 inline-block">
                {{ __('Add debts on the Net Worth page') }}
            </flux:link>
        </div>
    @else
        {{-- Budget warning --}}
        @if ($this->budgetBelowMinimums)
            <div class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4 mb-6 text-sm text-amber-800 dark:text-amber-200">
                {{ __("Your monthly payment doesn't cover all minimum payments. Increase your debt budget to avoid falling behind.") }}
            </div>
        @endif

        {{-- Spending plan tip --}}
        @if (! $this->hasSpendingPlanSource)
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-4 mb-6 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Tip: Add a Spending Plan with debt payments to set your monthly budget.') }}
            </div>
        @endif

        {{-- Scenario pills --}}
        <div class="flex items-center gap-2 flex-wrap mb-6">
            @foreach ($this->scenarioResults as $index => $data)
                @php
                    $scenario = $data['scenario'];
                    $result = $data['result'];
                    $isBaseline = $scenario['is_baseline'] ?? false;
                    $payoffLabel = $result && $result['months_to_payoff'] < \App\Services\DebtPayoffCalculator::MAX_MONTHS
                        ? $result['payoff_date']->format('M Y')
                        : '30+ years';
                @endphp
                <div class="relative rounded-xl border {{ $isBaseline ? 'border-blue-500 dark:border-blue-400' : 'border-zinc-200 dark:border-zinc-700' }} p-3 text-sm">
                    <div class="font-semibold {{ $isBaseline ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $scenario['name'] }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        ${{ format_cents($data['monthly_payment_cents']) }}/mo &bull; {{ ucfirst($scenario['strategy']) }} &bull; {{ $payoffLabel }}
                    </div>
                    @if (! $isBaseline)
                        <button
                            wire:click="removeScenario({{ $index }})"
                            class="absolute -top-2 -right-2 size-5 rounded-full bg-zinc-100 dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
                            aria-label="{{ __('Remove scenario') }}"
                        >&times;</button>
                    @endif
                </div>
            @endforeach

            @if (count($this->scenarios) < self::MAX_SCENARIOS)
                <flux:modal.trigger name="add-scenario">
                    <flux:button size="sm" variant="ghost" icon="plus">
                        {{ __('Scenario') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </div>

        {{-- Summary cards --}}
        @if (count($this->scenarioResults) > 0)
            @php
                $totalDebt = $this->debts->sum('balance');
                $debtCount = $this->debts->count();
                $bestResult = collect($this->scenarioResults)->sortBy('result.months_to_payoff')->first();
                $baselineResult = collect($this->scenarioResults)->firstWhere('scenario.is_baseline', true);
                $bestInterestSaved = 0;
                $bestInterestScenario = null;
                if ($baselineResult && $baselineResult['result']) {
                    foreach ($this->scenarioResults as $sr) {
                        if (($sr['scenario']['is_baseline'] ?? false) || ! $sr['result']) {
                            continue;
                        }
                        $saved = $baselineResult['result']['total_interest_paid'] - $sr['result']['total_interest_paid'];
                        if ($saved > $bestInterestSaved) {
                            $bestInterestSaved = $saved;
                            $bestInterestScenario = $sr['scenario']['name'];
                        }
                    }
                }
            @endphp
            <div class="grid grid-cols-3 gap-4 mb-8">
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 text-center">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Earliest Debt-Free') }}</div>
                    <div class="mt-1 text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        @if ($bestResult && $bestResult['result'] && $bestResult['result']['months_to_payoff'] < \App\Services\DebtPayoffCalculator::MAX_MONTHS)
                            {{ $bestResult['result']['payoff_date']->format('M Y') }}
                        @else
                            {{ __('30+ years') }}
                        @endif
                    </div>
                    @if ($bestResult && ! ($bestResult['scenario']['is_baseline'] ?? false))
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $bestResult['scenario']['name'] }}</div>
                    @endif
                </div>
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 text-center">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Most Interest Saved') }}</div>
                    <div class="mt-1 text-xl font-bold {{ $bestInterestSaved > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        @if ($bestInterestSaved > 0)
                            ${{ format_cents($bestInterestSaved) }}
                        @else
                            &mdash;
                        @endif
                    </div>
                    @if ($bestInterestScenario)
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('vs current plan') }}</div>
                    @endif
                </div>
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 text-center">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Debt') }}</div>
                    <div class="mt-1 text-xl font-bold text-zinc-900 dark:text-zinc-100">${{ format_cents($totalDebt) }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count account|:count accounts', $debtCount) }}</div>
                </div>
            </div>
        @endif

        {{-- Charts placeholder — implemented in Task 7 --}}
        <div id="charts-section" class="space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Charts loading...') }}</p>
            </div>
        </div>

        {{-- Add scenario modal --}}
        <flux:modal name="add-scenario" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Add Scenario') }}</flux:heading>
                    <flux:subheading>{{ __('Model a hypothetical payoff strategy') }}</flux:subheading>
                </div>

                <flux:input wire:model="newScenario.name" :label="__('Name')" :placeholder="__('e.g. Extra $200/mo')" />

                <flux:select wire:model="newScenario.strategy" :label="__('Strategy')">
                    <flux:select.option value="avalanche">{{ __('Avalanche (highest rate first)') }}</flux:select.option>
                    <flux:select.option value="snowball">{{ __('Snowball (smallest balance first)') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model="newScenario.extra_payment" :label="__('Extra Monthly Payment')" type="text" inputmode="decimal">
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>

                <flux:input wire:model="newScenario.lump_sum" :label="__('One-Time Lump Sum')" type="text" inputmode="decimal">
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>

                <flux:input wire:model="newScenario.lump_sum_month" :label="__('Apply Lump Sum In Month')" type="number" min="1" />

                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="addScenario" class="flex-1">{{ __('Add') }}</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
</section>
```

- [ ] **Step 5: Run tests**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoff/DebtPayoffPageTest.php`
Expected: All tests pass.

- [ ] **Step 6: Run pint**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add resources/views/pages/debt-payoff/ tests/Feature/DebtPayoff/DebtPayoffPageTest.php
git commit -m "Implement debt payoff page with scenario management"
```

---

### Task 7: Chart.js Integration

**Files:**
- Modify: `resources/views/pages/debt-payoff/⚡index.blade.php`

- [ ] **Step 1: Add Chart.js CDN to the page**

In the Blade template, add a `@push('scripts')` at the very end of the `<section>` (before `</section>`). Since the layout doesn't use a `@stack('scripts')`, instead load Chart.js via `@assets` directive which Livewire provides for component-specific assets. Add this at the top of the template (after `<section>`):

```blade
@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@endassets
```

- [ ] **Step 2: Replace charts placeholder with canvas elements and Alpine.js logic**

Replace the `{{-- Charts placeholder — implemented in Task 7 --}}` section and its content with:

```blade
{{-- Charts --}}
<div
    id="charts-section"
    class="space-y-6"
    x-data="debtCharts()"
    x-effect="updateCharts($wire.scenarioResults)"
>
    {{-- Balance Over Time --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
        <flux:heading>{{ __('Balance Over Time') }}</flux:heading>
        <flux:subheading class="mb-4">{{ __('Total remaining debt by scenario') }}</flux:subheading>
        <div class="relative" style="height: 300px;">
            <canvas x-ref="balanceChart"></canvas>
        </div>
    </div>

    {{-- Bottom row --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Payoff Order --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <flux:heading>{{ __('Payoff Order') }}</flux:heading>
            <flux:subheading class="mb-4">{{ __('When each debt gets eliminated') }}</flux:subheading>
            <div class="relative" style="height: 200px;">
                <canvas x-ref="payoffChart"></canvas>
            </div>
        </div>

        {{-- Interest Comparison --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <flux:heading>{{ __('Total Interest Paid') }}</flux:heading>
            <flux:subheading class="mb-4">{{ __('Comparison across scenarios') }}</flux:subheading>
            <div class="relative" style="height: 200px;">
                <canvas x-ref="interestChart"></canvas>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Add Alpine.js chart component**

Add this `<script>` block right before the closing `</section>` tag:

```blade
<script>
function debtCharts() {
    const colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ec4899', '#8b5cf6'];

    function monthLabel(monthOffset) {
        const d = new Date();
        d.setMonth(d.getMonth() + monthOffset);
        return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    }

    return {
        balanceChart: null,
        payoffChart: null,
        interestChart: null,

        updateCharts(scenarioResults) {
            if (!scenarioResults || scenarioResults.length === 0) return;

            this.$nextTick(() => {
                this.renderBalanceChart(scenarioResults);
                this.renderPayoffChart(scenarioResults);
                this.renderInterestChart(scenarioResults);
            });
        },

        renderBalanceChart(scenarioResults) {
            const ctx = this.$refs.balanceChart;
            if (!ctx) return;

            if (this.balanceChart) this.balanceChart.destroy();

            const maxMonths = Math.max(...scenarioResults.map(s => s.result?.months_to_payoff ?? 0));
            const labels = Array.from({ length: maxMonths }, (_, i) => monthLabel(i + 1));

            const datasets = scenarioResults.map((sr, i) => ({
                label: sr.scenario.name,
                data: (sr.result?.timeline ?? []).map(t => {
                    const total = Object.values(t.balances).reduce((sum, b) => sum + b, 0);
                    return Math.round(total / 100);
                }),
                borderColor: colors[i % colors.length],
                backgroundColor: 'transparent',
                tension: 0.3,
                pointRadius: 0,
                borderWidth: 2,
            }));

            this.balanceChart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    scales: {
                        x: {
                            ticks: { maxTicksLimit: 12 },
                            grid: { display: false },
                        },
                        y: {
                            ticks: {
                                callback: v => '$' + v.toLocaleString(),
                            },
                        },
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString(),
                            },
                        },
                    },
                },
            });
        },

        renderPayoffChart(scenarioResults) {
            const ctx = this.$refs.payoffChart;
            if (!ctx) return;

            if (this.payoffChart) this.payoffChart.destroy();

            // Use baseline scenario payoff order
            const baseline = scenarioResults.find(s => s.scenario.is_baseline) ?? scenarioResults[0];
            const payoffOrder = baseline.result?.payoff_order ?? [];

            const debtColors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#22c55e'];

            this.payoffChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: payoffOrder.map(d => d.name),
                    datasets: [{
                        data: payoffOrder.map(d => d.paid_off_month),
                        backgroundColor: payoffOrder.map((_, i) => debtColors[i % debtColors.length]),
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            ticks: {
                                callback: v => monthLabel(v),
                                maxTicksLimit: 6,
                            },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => monthLabel(ctx.parsed.x),
                            },
                        },
                    },
                },
            });
        },

        renderInterestChart(scenarioResults) {
            const ctx = this.$refs.interestChart;
            if (!ctx) return;

            if (this.interestChart) this.interestChart.destroy();

            this.interestChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: scenarioResults.map(s => s.scenario.name),
                    datasets: [{
                        data: scenarioResults.map(s => Math.round((s.result?.total_interest_paid ?? 0) / 100)),
                        backgroundColor: scenarioResults.map((_, i) => colors[i % colors.length]),
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            ticks: {
                                callback: v => '$' + v.toLocaleString(),
                            },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => '$' + ctx.parsed.x.toLocaleString(),
                            },
                        },
                    },
                },
            });
        },
    };
}
</script>
```

- [ ] **Step 4: Verify page renders with charts**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoff/DebtPayoffPageTest.php`
Expected: All tests still pass (charts render client-side, won't affect server-side tests).

- [ ] **Step 5: Commit**

```bash
git add resources/views/pages/debt-payoff/
git commit -m "Add Chart.js visualizations to debt payoff page"
```

---

### Task 8: Final Cleanup & Full Test Suite

**Files:**
- All modified files

- [ ] **Step 1: Run pint on all dirty files**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run full test suite**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/DebtPayoffCalculatorTest.php tests/Feature/DebtPayoff/DebtPayoffPageTest.php`
Expected: All tests pass.

- [ ] **Step 3: Run existing test suite to check for regressions**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/NetWorth/ tests/Feature/DashboardTest.php`
Expected: All existing tests still pass.

- [ ] **Step 4: Commit any pint fixes**

```bash
git add -A
git commit -m "Apply pint formatting fixes"
```

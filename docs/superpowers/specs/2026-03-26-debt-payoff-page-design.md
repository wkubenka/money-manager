# Debt Payoff Page Design

## Overview

A scenario-based debt payoff calculator page that lets users visualize their path to debt freedom. The current plan is the baseline scenario; users add hypothetical scenarios (extra payments, different strategies, lump sums) and compare them side-by-side via charts.

## Routing & Navigation

- **Route file**: `routes/debt-payoff.php`, required from `web.php`
- **Route**: `GET /debt-payoff` → `pages::debt-payoff.index`, named `debt-payoff.index`
- **Page**: `resources/views/pages/debt-payoff/⚡index.blade.php` (inline Livewire component)
- **Sidebar**: "Debt Payoff" item shown conditionally when `NetWorthAccount::where('category', 'debt')->exists()`

## Data Sources

- **Debt accounts**: `NetWorthAccount` records where `category = 'debt'`, with `balance`, `interest_rate`, and `minimum_payment` fields (already exist via prior migration)
- **Baseline monthly payment**: pulled from the current spending plan's "Debt Payments" item under Fixed Costs (`SpendingPlan::where('is_current', true)` → items where `name = 'Debt Payments'`). Falls back to sum of minimum payments if no current plan or no matching item.
- **No new models or migrations required**

## Scenario System (Ephemeral)

Scenarios are stored as Livewire component state — an array of parameter objects. They are not persisted to the database; the page always starts fresh with just the baseline.

### Baseline Scenario (always present, not removable)
- Name: "Current Plan"
- Strategy: Avalanche
- Monthly payment: from spending plan (or sum of minimums fallback)
- Extra payment: $0
- Lump sum: $0

### User-Created Scenarios
- Added via "+ Scenario" button → Flux modal
- Fields:
  - **Name** (text, auto-suggested based on changes)
  - **Strategy** (Avalanche or Snowball, default: Avalanche)
  - **Extra monthly payment** (dollar input, on top of baseline, default: $0)
  - **One-time lump sum amount** (dollar input, default: $0)
  - **Lump sum timing** (month picker, default: month 1)
- Removable via X button on scenario pill
- Max 5 scenarios to keep charts readable

## Calculator Enhancements

The existing `DebtPayoffCalculator` service needs these additions:

### Snowball Method
- New parameter: strategy (`avalanche` or `snowball`)
- Snowball sorts debts by balance ascending (smallest first) instead of interest rate descending

### Lump Sum Support
- New optional parameters: `lumpSumCents` (int) and `lumpSumMonth` (int)
- In the specified month, apply the lump sum to the target debt (highest rate for avalanche, lowest balance for snowball) before normal payments

### Monthly Timeline Data
- Return a month-by-month array for chart rendering
- Each entry: `{ month: int, balances: { debt_id: cents }, interest_accrued: cents }`

### Per-Debt Payoff Tracking
- Track which month each individual debt reaches zero balance
- Return as: `payoff_order: [{ name: string, paid_off_month: int }]`

### Updated Return Shape
```php
[
    'payoff_date' => Carbon,
    'months_to_payoff' => int,
    'total_interest_paid' => int,
    'timeline' => [
        ['month' => int, 'balances' => ['debt_name' => int], 'interest' => int],
    ],
    'payoff_order' => [
        ['name' => string, 'paid_off_month' => int],
    ],
]
```

## Page Layout

### 1. Page Heading
- `<x-page-heading title="Debt Payoff" subtitle="Plan your path to debt freedom" />`

### 2. Scenario Pills Row
- Horizontal row of pill-shaped cards, one per scenario
- Each shows: name, monthly payment, strategy, payoff date (month/year format)
- Baseline has a distinct border (e.g., blue). Others have a subtle border + X remove button.
- "+ Scenario" button at the end

### 3. Summary Cards
- Three cards in a row:
  - **Earliest Debt-Free**: best payoff date across all scenarios, names which scenario
  - **Most Interest Saved**: biggest interest savings vs baseline, shows dollar amount
  - **Total Debt**: sum of current debt balances, number of accounts

### 4. Balance Over Time Chart (Chart.js Line)
- One line per scenario, color-coded to match scenario pills
- X-axis: months (labeled as "Mon YYYY")
- Y-axis: total remaining debt in dollars
- Legend matches scenario names/colors

### 5. Bottom Row (two charts side by side)

#### Payoff Order (Chart.js Horizontal Bar)
- Shows the baseline scenario's per-debt payoff timeline
- Each debt is a horizontal bar from month 0 to its payoff month
- Labels show debt name, bar endpoint shows "Mon YYYY"
- Color-coded per debt

#### Total Interest Paid (Chart.js Horizontal Bar)
- One bar per scenario
- Color-coded to match scenario pills
- Labels show dollar amounts

## Charting

- **Library**: Chart.js, loaded via CDN (`<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>`)
- **Integration**: Charts rendered on `<canvas>` elements, initialized/updated via Alpine.js `x-init`/`x-effect` reacting to Livewire state changes
- **Responsiveness**: Chart.js responsive mode enabled, maintains aspect ratio

## Edge Cases

- **No debts**: sidebar item hidden. Direct URL access shows "No debt accounts found" with link to Net Worth page.
- **No current spending plan or no "Debt Payments" item**: baseline uses sum of minimum payments. Shows tip: "Add a Spending Plan with debt payments to set your monthly budget."
- **Missing interest rate on a debt**: defaults to 0% APR
- **Missing minimum payment on a debt**: defaults to $0 minimum (all payment from surplus)
- **Budget less than sum of minimums**: warning displayed: "Your monthly payment doesn't cover all minimum payments. Increase your debt budget to avoid falling behind."
- **All scenarios hit MAX_MONTHS (360)**: charts cap at 30 years, payoff date shows "30+ years"

## Testing

- **Calculator tests**: extend existing `DebtPayoffCalculatorTest.php` with snowball, lump sum, timeline, and payoff order tests
- **Page tests**: new `tests/Feature/DebtPayoff/DebtPayoffPageTest.php` — page loads with debts, empty state, scenario CRUD, conditional sidebar visibility

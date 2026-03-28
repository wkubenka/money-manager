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
        @if ($this->budgetBelowMinimums)
            <div class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4 mb-6 text-sm text-amber-800 dark:text-amber-200">
                {{ __("Your monthly payment doesn't cover all minimum payments. Increase your debt budget to avoid falling behind.") }}
            </div>
        @endif

        @if (! $this->hasSpendingPlanSource)
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-4 mb-6 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Tip: Add a Spending Plan with debt payments to set your monthly budget.') }}
            </div>
        @endif

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

        <div class="mb-8">
            <h2 class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-3">{{ __('Debts') }}</h2>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700 rounded-xl border border-zinc-200 dark:border-zinc-700">
                @foreach ($this->debts as $debt)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $debt->name }}</span>
                            <span class="text-zinc-500 dark:text-zinc-400 ml-2">{{ $debt->interest_rate }}% APR</span>
                        </div>
                        <div class="text-zinc-900 dark:text-zinc-100">${{ format_cents($debt->balance) }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Charts placeholder — implemented in Task 7 --}}
        <div id="charts-section" class="space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Charts loading...') }}</p>
            </div>
        </div>

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

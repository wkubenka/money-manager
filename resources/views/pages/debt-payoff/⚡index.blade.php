<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\DebtScenario;
use App\Models\NetWorthAccount;
use App\Models\SpendingPlan;
use App\Services\DebtPayoffCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public const MAX_SCENARIOS = 5;

    public const SCENARIO_COLORS = [
        '#4ebb78', // accent
        '#6da6d8', // blue
        '#c8a96e', // warm
        '#c084fc', // purple
        '#5ecc89', // accent-hov
    ];

    public array $scenarios = [];

    public int $activeScenarioIndex = 0;

    public bool $showNewScenario = false;

    public array $newScenario = [
        'name' => '',
        'strategy' => 'avalanche',
        'extra_payment' => '',
        'lump_sum' => '',
        'lump_sum_month' => '1',
    ];

    public ?int $confirmingDeleteScenarioIndex = null;

    public function mount(): void
    {
        if ($this->debts->isEmpty()) {
            return;
        }

        $this->loadScenarios();
    }

    private function loadScenarios(): void
    {
        $this->scenarios = [[
            'id' => null,
            'name' => 'Current Plan',
            'strategy' => 'avalanche',
            'extra_payment' => 0,
            'lump_sum' => 0,
            'lump_sum_month' => 1,
            'is_baseline' => true,
        ]];

        foreach (DebtScenario::orderBy('sort_order')->orderBy('id')->get() as $row) {
            $this->scenarios[] = [
                'id' => $row->id,
                'name' => $row->name,
                'strategy' => $row->strategy,
                'extra_payment' => $row->extra_payment_cents / 100,
                'lump_sum' => $row->lump_sum_cents / 100,
                'lump_sum_month' => $row->lump_sum_month,
                'is_baseline' => false,
            ];
        }
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
    public function totalDebtCents(): int
    {
        return (int) $this->debts->sum('balance');
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
                'color' => self::SCENARIO_COLORS[$index % count(self::SCENARIO_COLORS)],
            ];
        }

        return $results;
    }

    public function selectScenario(int $index): void
    {
        if (isset($this->scenarios[$index])) {
            $this->activeScenarioIndex = $index;
        }
    }

    public function toggleNewScenario(): void
    {
        $this->showNewScenario = ! $this->showNewScenario;
    }

    public function setStrategy(string $strategy): void
    {
        if (in_array($strategy, ['avalanche', 'snowball'], true)) {
            $this->newScenario['strategy'] = $strategy;
        }
    }

    public function addScenario(): void
    {
        if (count($this->scenarios) >= self::MAX_SCENARIOS) {
            return;
        }

        $this->validate([
            'newScenario.name' => ['required', 'string', 'max:255'],
            'newScenario.strategy' => ['required', 'in:avalanche,snowball'],
            'newScenario.extra_payment' => ['nullable', 'numeric', 'min:0'],
            'newScenario.lump_sum' => ['nullable', 'numeric', 'min:0'],
            'newScenario.lump_sum_month' => ['required', 'integer', 'min:1'],
        ]);

        DebtScenario::create([
            'name' => $this->newScenario['name'],
            'strategy' => $this->newScenario['strategy'],
            'extra_payment_cents' => (int) round((float) ($this->newScenario['extra_payment'] ?: 0) * 100),
            'lump_sum_cents' => (int) round((float) ($this->newScenario['lump_sum'] ?: 0) * 100),
            'lump_sum_month' => (int) $this->newScenario['lump_sum_month'],
            'sort_order' => DebtScenario::count(),
        ]);

        $this->loadScenarios();
        $this->activeScenarioIndex = count($this->scenarios) - 1;
        $this->resetNewScenario();
        $this->showNewScenario = false;
        unset($this->scenarioResults);
    }

    public function confirmRemoveScenario(int $index): void
    {
        if (! isset($this->scenarios[$index]) || ($this->scenarios[$index]['is_baseline'] ?? false)) {
            return;
        }

        $this->confirmingDeleteScenarioIndex = $index;
    }

    public function cancelRemoveScenario(): void
    {
        $this->confirmingDeleteScenarioIndex = null;
    }

    public function removeScenario(int $index): void
    {
        if (! isset($this->scenarios[$index]) || ($this->scenarios[$index]['is_baseline'] ?? false)) {
            return;
        }

        if ($id = $this->scenarios[$index]['id'] ?? null) {
            DebtScenario::where('id', $id)->delete();
        }

        $this->confirmingDeleteScenarioIndex = null;
        $this->loadScenarios();
        $this->activeScenarioIndex = 0;
        unset($this->scenarioResults);
    }

    private function resetNewScenario(): void
    {
        $this->newScenario = [
            'name' => '',
            'strategy' => 'avalanche',
            'extra_payment' => '',
            'lump_sum' => '',
            'lump_sum_month' => '1',
        ];
    }
}; ?>

<section class="w-full px-10 py-9 max-w-[1320px] mx-auto">
    <div class="flex justify-between items-start mb-7">
        <x-page-heading eyebrow="Debt Payoff" title="Plan your path to freedom" subtitle="Model different strategies and see when you'll be debt-free" />

        @if (! $this->debts->isEmpty() && count($scenarios) < self::MAX_SCENARIOS && ! $showNewScenario)
            <div class="pt-5">
                <flux:button variant="primary" wire:click="toggleNewScenario" icon="plus">
                    {{ __('Add scenario') }}
                </flux:button>
            </div>
        @endif
    </div>

    @if ($this->debts->isEmpty())
        <div class="rounded-2xl border border-vault-card-bd bg-vault-card text-center" style="padding: 56px 40px;">
            <div class="mx-auto mb-4 flex items-center justify-center rounded-xl"
                style="width: 44px; height: 44px; background: color-mix(in srgb, var(--color-vault-red) 12%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-red) 28%, transparent);">
                <flux:icon name="banknotes" variant="micro" class="!size-5" style="color: var(--color-vault-red);" />
            </div>
            <div class="font-display text-vault-text" style="font-size: 22px;">{{ __('No debt accounts yet') }}</div>
            <p class="text-[13px] text-vault-textsub mt-2 mb-5">{{ __('Add your debts on the Net Worth page to start modeling payoff strategies.') }}</p>
            <flux:button :href="route('net-worth.index')" wire:navigate variant="primary">{{ __('Add a debt') }}</flux:button>
        </div>
    @else
        @if ($this->budgetBelowMinimums)
            <div class="rounded-xl mb-6"
                style="padding: 14px 18px; background: color-mix(in srgb, var(--color-vault-warm) 10%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-warm) 32%, transparent);">
                <div class="text-[12px]" style="color: var(--color-vault-warm);">
                    {{ __("Your monthly payment doesn't cover all minimum payments. Increase your debt budget to avoid falling behind.") }}
                </div>
            </div>
        @endif

        @if (! $this->hasSpendingPlanSource)
            <div class="rounded-xl border border-vault-card-bd bg-vault-card mb-6" style="padding: 12px 18px;">
                <div class="text-[12px] text-vault-muted">
                    {{ __('Tip: Add a Spending Plan with Debt Payments to source your monthly budget automatically.') }}
                </div>
            </div>
        @endif

        @php
            $results = $this->scenarioResults;
            $totalDebt = $this->totalDebtCents;
            $baselineResult = collect($results)->firstWhere('scenario.is_baseline', true);
            $bestResult = collect($results)->filter(fn ($r) => $r['result'])->sortBy('result.months_to_payoff')->first() ?: ($baselineResult ?: ($results[0] ?? null));
            $bestInterestSaved = 0;
            if ($baselineResult && $baselineResult['result']) {
                foreach ($results as $sr) {
                    if (($sr['scenario']['is_baseline'] ?? false) || ! $sr['result']) {
                        continue;
                    }
                    $saved = $baselineResult['result']['total_interest_paid'] - $sr['result']['total_interest_paid'];
                    if ($saved > $bestInterestSaved) {
                        $bestInterestSaved = $saved;
                    }
                }
            }
            $stats = [
                ['label' => 'Total Debt', 'value' => '$'.format_cents($totalDebt), 'color' => 'var(--color-vault-red)'],
                ['label' => 'Monthly Payment', 'value' => '$'.format_cents($this->baselineMonthlyPaymentCents), 'color' => 'var(--color-vault-text)'],
                ['label' => 'Earliest Free', 'value' => $bestResult && $bestResult['result'] && $bestResult['result']['months_to_payoff'] < \App\Services\DebtPayoffCalculator::MAX_MONTHS ? $bestResult['result']['payoff_date']->format('M Y') : '30+ yrs', 'color' => 'var(--color-vault-accent)'],
                ['label' => 'Interest Saved', 'value' => $bestInterestSaved > 0 ? '$'.format_cents($bestInterestSaved) : '—', 'color' => 'var(--color-vault-warm)'],
            ];
        @endphp

        {{-- Stats strip --}}
        <div class="mb-6" style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px;">
            @foreach ($stats as $stat)
                <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 16px 20px;">
                    <div class="eyebrow mb-2">{{ __($stat['label']) }}</div>
                    <div class="font-display" style="font-size: 22px; color: {{ $stat['color'] }};">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-5" style="grid-template-columns: 1.2fr 0.8fr;">
            {{-- Scenarios column --}}
            <div class="flex flex-col gap-[14px]">
                <div class="eyebrow">{{ __('Scenarios') }}</div>

                @foreach ($results as $index => $data)
                    @php
                        $scenario = $data['scenario'];
                        $result = $data['result'];
                        $color = $data['color'];
                        $isBaseline = $scenario['is_baseline'] ?? false;
                        $isActive = $index === $activeScenarioIndex;
                        $months = $result['months_to_payoff'] ?? 0;
                        $payoffLabel = $result && $months < \App\Services\DebtPayoffCalculator::MAX_MONTHS
                            ? $result['payoff_date']->format('M Y')
                            : '30+ yrs';
                        $interestPaid = $result['total_interest_paid'] ?? 0;
                        $savedVsBaseline = ($baselineResult && $baselineResult['result'] && ! $isBaseline)
                            ? $baselineResult['result']['total_interest_paid'] - $interestPaid
                            : 0;
                    @endphp

                    <div
                        wire:click="selectScenario({{ $index }})"
                        wire:key="scenario-{{ $index }}"
                        role="button"
                        tabindex="0"
                        class="relative rounded-2xl bg-vault-card cursor-pointer transition-colors hover:bg-vault-card-hov"
                        style="padding: 18px 22px; border: 1px solid {{ $isActive ? $color : 'var(--color-vault-card-bd)' }};"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: {{ $color }};"></span>
                                    <span class="text-[13px] font-semibold text-vault-text">{{ $scenario['name'] }}</span>
                                    @if ($isBaseline)
                                        <span class="rounded" style="font-size: 9px; padding: 2px 7px; letter-spacing: 0.06em; background: color-mix(in srgb, var(--color-vault-textsub) 14%, transparent); color: var(--color-vault-textsub); text-transform: uppercase;">{{ __('Baseline') }}</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-vault-muted">
                                    {{ ucfirst($scenario['strategy']) }} &middot; ${{ format_cents($data['monthly_payment_cents']) }}/mo
                                    @if (($scenario['lump_sum'] ?? 0) > 0)
                                        &middot; ${{ number_format($scenario['lump_sum'], 0) }} lump sum
                                    @endif
                                </div>
                            </div>
                            <div class="text-right" style="padding-right: 26px;">
                                <div class="font-display" style="font-size: 20px; color: {{ $color }};">{{ $payoffLabel }}</div>
                                <div class="text-[11px] text-vault-muted mt-0.5">{{ $months }} {{ __('months') }}</div>
                            </div>
                        </div>

                        <div class="flex justify-between mt-3">
                            <span class="text-[10px] text-vault-muted">{{ __('Total interest:') }} ${{ format_cents($interestPaid) }}</span>
                            @if ($savedVsBaseline > 0)
                                <span class="text-[10px] text-vault-accent">{{ __('Save') }} ${{ format_cents($savedVsBaseline) }} {{ __('vs baseline') }}</span>
                            @endif
                        </div>

                        @if (! $isBaseline)
                            <button
                                type="button"
                                wire:click.stop="confirmRemoveScenario({{ $index }})"
                                aria-label="{{ __('Remove scenario') }}"
                                class="flex items-center justify-center rounded-full transition hover:brightness-125"
                                style="position: absolute; top: 12px; right: 12px; width: 22px; height: 22px; background: var(--color-vault-card-bd); color: var(--color-vault-textsub);"
                            >
                                <flux:icon name="x-mark" variant="micro" class="!size-3" />
                            </button>
                        @endif
                    </div>
                @endforeach

                {{-- New scenario form --}}
                @if ($showNewScenario && count($scenarios) < self::MAX_SCENARIOS)
                    <div class="rounded-2xl bg-vault-card" style="padding: 18px 22px; border: 1px dashed var(--color-vault-card-bd);">
                        <div class="flex items-center justify-between mb-3">
                            <div class="eyebrow">{{ __('New scenario') }}</div>
                            <button type="button" wire:click="toggleNewScenario" aria-label="{{ __('Close form') }}" class="text-vault-muted hover:text-vault-textsub transition">
                                <flux:icon name="x-mark" variant="micro" class="!size-4" />
                            </button>
                        </div>
                        <div class="flex flex-col gap-2.5">
                            <flux:field>
                                <flux:label class="!text-[10px] !uppercase !tracking-[0.13em] !text-vault-muted">{{ __('Scenario name') }}</flux:label>
                                <flux:input wire:model="newScenario.name" :placeholder="__('e.g. Aggressive payoff')" />
                            </flux:field>
                            @error('newScenario.name')<div class="text-[11px] text-vault-red">{{ $message }}</div>@enderror

                            <div class="grid grid-cols-2 gap-2.5">
                                <flux:field>
                                    <flux:label class="!text-[10px] !uppercase !tracking-[0.13em] !text-vault-muted">{{ __('Extra monthly') }}</flux:label>
                                    <flux:input wire:model="newScenario.extra_payment" :placeholder="__('e.g. 200')" type="text" inputmode="decimal">
                                        <x-slot:prefix>+$</x-slot:prefix>
                                    </flux:input>
                                </flux:field>
                                <flux:field>
                                    <flux:label class="!text-[10px] !uppercase !tracking-[0.13em] !text-vault-muted">{{ __('Lump sum') }}</flux:label>
                                    <flux:input wire:model="newScenario.lump_sum" :placeholder="__('e.g. 1,000')" type="text" inputmode="decimal">
                                        <x-slot:prefix>$</x-slot:prefix>
                                    </flux:input>
                                </flux:field>
                            </div>
                            @error('newScenario.extra_payment')<div class="text-[11px] text-vault-red">{{ $message }}</div>@enderror
                            @error('newScenario.lump_sum')<div class="text-[11px] text-vault-red">{{ $message }}</div>@enderror

                            <div class="flex gap-2">
                                @foreach (['avalanche' => __('Avalanche'), 'snowball' => __('Snowball')] as $value => $label)
                                    @php $isOn = $newScenario['strategy'] === $value; @endphp
                                    <button
                                        type="button"
                                        wire:click="setStrategy('{{ $value }}')"
                                        class="flex-1 rounded-[7px] transition"
                                        style="padding: 7px 0; font-size: 11px; border: 1px solid {{ $isOn ? 'var(--color-vault-accent)' : 'var(--color-vault-card-bd)' }}; background: {{ $isOn ? 'color-mix(in srgb, var(--color-vault-accent) 14%, transparent)' : 'transparent' }}; color: {{ $isOn ? 'var(--color-vault-accent)' : 'var(--color-vault-textsub)' }};"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>

                            <flux:button wire:click="addScenario" variant="primary" class="!w-full">{{ __('Add Scenario') }}</flux:button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right column: debts + payoff order --}}
            <div class="flex flex-col gap-[14px]">
                <div class="eyebrow">{{ __('Your debts') }}</div>
                <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 0 22px;">
                    @foreach ($this->debts as $i => $debt)
                        @php $share = $totalDebt > 0 ? ($debt->balance / $totalDebt) * 100 : 0; @endphp
                        @if ($i > 0)
                            <div class="border-t border-vault-card-bd"></div>
                        @endif
                        <div style="padding: 14px 0;">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-[13px] text-vault-text">{{ $debt->name }}</div>
                                    <div class="text-[10px] text-vault-muted mt-1">
                                        {{ rtrim(rtrim(number_format((float) ($debt->interest_rate ?? 0), 2), '0'), '.') }}% APR
                                        @if ($debt->minimum_payment)
                                            &middot; ${{ format_cents($debt->minimum_payment) }}/mo {{ __('min') }}
                                        @endif
                                    </div>
                                </div>
                                <span class="font-display" style="font-size: 16px; color: var(--color-vault-red);">${{ format_cents($debt->balance) }}</span>
                            </div>
                            <div class="mt-2 rounded-full" style="height: 3px; background: var(--color-vault-card-bd);">
                                <div class="rounded-full" style="height: 3px; width: {{ $share }}%; background: var(--color-vault-red); opacity: 0.6;"></div>
                            </div>
                            <div class="text-[10px] text-vault-muted mt-1">{{ round($share) }}% {{ __('of total debt') }}</div>
                        </div>
                    @endforeach
                </div>

                @php
                    $active = $results[$activeScenarioIndex] ?? null;
                @endphp

                @if ($active && $active['result'] && ! empty($active['result']['payoff_order']))
                    @php
                        $activeColor = $active['color'];
                        $activeName = $active['scenario']['name'];
                        $activeMonths = max(1, $active['result']['months_to_payoff']);
                        $payoffOrder = $active['result']['payoff_order'];
                    @endphp
                    <div class="eyebrow">{{ __('Payoff order') }} — {{ $activeName }}</div>
                    <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 18px 22px;">
                        @foreach ($payoffOrder as $i => $item)
                            @php
                                $month = $item['paid_off_month'];
                                $pct = ($month / $activeMonths) * 100;
                                $payoffDate = \Carbon\Carbon::now()->addMonthsNoOverflow($month);
                            @endphp
                            <div class="{{ $i < count($payoffOrder) - 1 ? 'mb-4' : '' }}">
                                <div class="flex justify-between mb-1.5">
                                    <span class="text-[12px] text-vault-textsub">{{ $item['name'] }}</span>
                                    <span class="text-[12px]" style="color: {{ $activeColor }};">{{ $payoffDate->format('M Y') }}</span>
                                </div>
                                <div class="rounded-full" style="height: 4px; background: var(--color-vault-card-bd);">
                                    <div class="rounded-full" style="height: 4px; width: {{ $pct }}%; background: {{ $activeColor }}; opacity: 0.75;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Delete Scenario Confirmation --}}
    <flux:modal wire:model.self="confirmingDeleteScenarioIndex" class="min-w-[22rem]">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Remove scenario') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Remove this scenario?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">{{ __('This action cannot be undone.') }}</div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelRemoveScenario">{{ __('Cancel') }}</flux:button>
                @if ($confirmingDeleteScenarioIndex !== null)
                    <flux:button variant="danger" wire:click="removeScenario({{ $confirmingDeleteScenarioIndex }})">{{ __('Remove') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</section>

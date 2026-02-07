<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\NetWorthAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function accounts()
    {
        return Auth::user()->netWorthAccounts()->get();
    }

    #[Computed]
    public function netWorthSummary(): array
    {
        $categories = [];

        foreach (AccountCategory::cases() as $category) {
            $categories[$category->value] = (int) $this->accounts
                ->where('category', $category)
                ->sum('balance');
        }

        $netWorth = $categories[AccountCategory::Assets->value]
            + $categories[AccountCategory::Investments->value]
            + $categories[AccountCategory::Savings->value]
            - $categories[AccountCategory::Debt->value];

        return [
            'categories' => $categories,
            'net_worth' => $netWorth,
        ];
    }

    #[Computed]
    public function currentPlan()
    {
        return Auth::user()->currentSpendingPlan()?->load('items');
    }

    #[Computed]
    public function emergencyFund(): ?NetWorthAccount
    {
        return Auth::user()->emergencyFund();
    }
}; ?>

<div class="grid w-full gap-6 lg:grid-cols-2">
    {{-- Left column: block on desktop, flattened on mobile via contents --}}
    <div class="contents lg:block lg:space-y-6">
        {{-- Net Worth --}}
        <div class="order-1 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:subheading>{{ __('Net Worth') }}</flux:subheading>
                    <div class="mt-1 text-3xl font-bold {{ $this->netWorthSummary['net_worth'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $this->netWorthSummary['net_worth'] < 0 ? '-' : '' }}${{ number_format(abs($this->netWorthSummary['net_worth']) / 100) }}
                    </div>
                </div>
                <flux:button variant="subtle" size="sm" icon="cog-6-tooth" :href="route('net-worth.index')" wire:navigate aria-label="{{ __('Manage accounts') }}" />
            </div>

            <div class="space-y-2">
                @foreach (AccountCategory::cases() as $category)
                    @php $total = $this->netWorthSummary['categories'][$category->value]; @endphp
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="size-3 rounded-full {{ $category->color() }}"></div>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $category->label() }}</span>
                        </div>
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($total / 100) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Emergency Fund --}}
        @if ($this->emergencyFund)
            @php
                $ef = $this->emergencyFund;
                $plan = $this->currentPlan;
                $monthsTotal = $plan && $plan->monthly_income > 0
                    ? round($ef->balance / $plan->monthly_income, 1)
                    : null;
                $fixedCosts = $plan ? $plan->categoryTotal(SpendingCategory::FixedCosts) : 0;
                $monthsFixed = $plan && $fixedCosts > 0
                    ? round($ef->balance / $fixedCosts, 1)
                    : null;
            @endphp
            <div class="order-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:subheading>{{ __('Emergency Fund') }}</flux:subheading>
                        <div class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                            ${{ number_format($ef->balance / 100) }}
                        </div>
                    </div>
                    <flux:button variant="subtle" size="sm" icon="pencil-square" :href="route('net-worth.index')" wire:navigate aria-label="{{ __('Edit emergency fund') }}" />
                </div>

                @if ($plan)
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Months of total spending') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $monthsTotal !== null ? $monthsTotal : __('N/A') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Months of fixed costs') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $monthsFixed !== null ? $monthsFixed : __('N/A') }}
                            </span>
                        </div>
                    </div>
                @else
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Set a current spending plan to see coverage months.') }}
                    </flux:text>
                @endif
            </div>
        @endif
    </div>

    {{-- Current Spending Plan --}}
    @if ($this->currentPlan)
        @php $plan = $this->currentPlan; @endphp
        <div class="order-2 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <flux:subheading>{{ __('Current Spending Plan') }}</flux:subheading>
                    <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                        ${{ number_format($plan->monthly_income / 100) }}/mo
                    </div>
                </div>
                <flux:button variant="subtle" size="sm" icon="pencil-square" :href="route('spending-plans.edit', $plan)" wire:navigate aria-label="{{ __('Edit plan') }}" />
            </div>

            <div class="space-y-5">
                @foreach (SpendingCategory::cases() as $category)
                    @php
                        $total = $plan->categoryTotal($category);
                        $percent = $plan->categoryPercent($category);
                        [$min, $max] = $category->idealRange();
                        $withinIdeal = $category->isWithinIdeal($percent);
                        $items = $category !== SpendingCategory::GuiltFree
                            ? $plan->items->where('category', $category)
                            : collect();
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <div class="size-3 rounded-full {{ $category->color() }}"></div>
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $category->label() }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($category !== SpendingCategory::GuiltFree)
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($total / 100) }}</span>
                                @else
                                    <span class="text-sm font-medium {{ $total < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                        {{ $total < 0 ? '-' : '' }}${{ number_format(abs($total) / 100) }}
                                    </span>
                                @endif
                                <flux:badge size="sm" color="{{ $percent < 0 ? 'red' : ($withinIdeal ? 'green' : 'amber') }}" class="w-10 justify-center">
                                    {{ round($percent) }}%
                                </flux:badge>
                            </div>
                        </div>

                        <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-700 overflow-hidden">
                            <div class="h-full rounded-full {{ $category->color() }}" style="width: {{ min(max($percent, 0), 100) }}%"></div>
                        </div>

                        @if ($items->isNotEmpty() || ($category === SpendingCategory::FixedCosts && $plan->fixed_costs_misc_percent > 0))
                            <div class="mt-2 space-y-0.5">
                                @foreach ($items as $item)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $item->name }}</span>
                                        <span class="text-zinc-600 dark:text-zinc-300">${{ number_format($item->amount / 100) }}</span>
                                    </div>
                                @endforeach
                                @if ($category === SpendingCategory::FixedCosts && $plan->fixed_costs_misc_percent > 0)
                                    <div class="flex items-center justify-between text-sm italic text-zinc-500 dark:text-zinc-400">
                                        <span>{{ __('Miscellaneous') }} ({{ $plan->fixed_costs_misc_percent }}%)</span>
                                        <span class="text-zinc-600 dark:text-zinc-300">${{ number_format($plan->fixedCostsMiscellaneous() / 100) }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="order-2 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 p-6 text-center">
            <flux:subheading class="mb-2">{{ __('No current spending plan') }}</flux:subheading>
            <flux:button variant="subtle" size="sm" :href="route('spending-plans.dashboard')" wire:navigate>
                {{ __('Choose a Plan') }}
            </flux:button>
        </div>
    @endif
</div>

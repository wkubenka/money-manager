<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    {{-- Net Worth --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <flux:subheading>{{ __('Net Worth') }}</flux:subheading>
                <div class="mt-1 text-3xl font-bold {{ $this->netWorthSummary['net_worth'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ $this->netWorthSummary['net_worth'] < 0 ? '-' : '' }}${{ number_format(abs($this->netWorthSummary['net_worth']) / 100) }}
                </div>
            </div>
            <flux:button variant="subtle" size="sm" icon="cog-6-tooth" :href="route('net-worth.index')" wire:navigate />
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

    {{-- Current Spending Plan --}}
    @if ($this->currentPlan)
        @php $plan = $this->currentPlan; @endphp
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <flux:subheading>{{ __('Current Plan') }}</flux:subheading>
                    <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                        ${{ number_format($plan->monthly_income / 100) }}/mo
                    </div>
                </div>
                <flux:button variant="subtle" size="sm" icon="pencil-square" :href="route('spending-plans.edit', $plan)" wire:navigate />
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

                        @if ($items->isNotEmpty())
                            <div class="mt-2 space-y-0.5">
                                @foreach ($items as $item)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $item->name }}</span>
                                        <span class="text-zinc-600 dark:text-zinc-300">${{ number_format($item->amount / 100) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 p-6 text-center">
            <flux:subheading class="mb-2">{{ __('No current spending plan') }}</flux:subheading>
            <flux:button variant="subtle" size="sm" :href="route('spending-plans.dashboard')" wire:navigate>
                {{ __('Choose a Plan') }}
            </flux:button>
        </div>
    @endif
</div>

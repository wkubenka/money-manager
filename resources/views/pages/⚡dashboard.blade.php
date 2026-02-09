<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\NetWorthAccount;
use App\Models\RichLifeVision;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $visionEditing = false;
    public string $newVisionText = '';
    public ?int $editingVisionId = null;
    public string $editingVisionText = '';

    #[Computed]
    public function visions()
    {
        return Auth::user()->richLifeVisions()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function addVision(): void
    {
        $this->validate([
            'newVisionText' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = Auth::user()->richLifeVisions()->max('sort_order') + 1;

        Auth::user()->richLifeVisions()->create([
            'text' => $this->newVisionText,
            'sort_order' => $nextOrder,
        ]);

        $this->newVisionText = '';
        unset($this->visions);
    }

    public function editVision(int $visionId): void
    {
        $vision = RichLifeVision::findOrFail($visionId);
        abort_unless($vision->user_id === Auth::id(), 403);

        $this->editingVisionId = $visionId;
        $this->editingVisionText = $vision->text;
    }

    public function updateVision(): void
    {
        $this->validate([
            'editingVisionText' => ['required', 'string', 'max:255'],
        ]);

        $vision = RichLifeVision::findOrFail($this->editingVisionId);
        abort_unless($vision->user_id === Auth::id(), 403);

        $vision->update(['text' => $this->editingVisionText]);

        $this->cancelEditVision();
        unset($this->visions);
    }

    public function cancelEditVision(): void
    {
        $this->editingVisionId = null;
        $this->editingVisionText = '';
    }

    public function removeVision(int $visionId): void
    {
        $vision = RichLifeVision::findOrFail($visionId);
        abort_unless($vision->user_id === Auth::id(), 403);

        $vision->delete();
        unset($this->visions);
    }

    public function reorderVisions(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $vision = RichLifeVision::findOrFail($id);
            abort_unless($vision->user_id === Auth::id(), 403);
            $vision->update(['sort_order' => $index]);
        }

        unset($this->visions);
    }

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
        {{-- Rich Life Vision --}}
        <div class="order-0 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading>{{ __('Rich Life Vision') }}</flux:heading>
                <flux:button
                    size="sm"
                    variant="subtle"
                    :icon="$visionEditing ? 'lock-open' : 'lock-closed'"
                    wire:click="$toggle('visionEditing')"
                    aria-label="{{ $visionEditing ? __('Lock list') : __('Unlock list') }}"
                />
            </div>

            @if ($this->visions->isNotEmpty())
                <ul class="space-y-1 {{ $visionEditing ? 'mb-4' : '' }}" data-sortable-visions>
                    @foreach ($this->visions as $vision)
                        <li class="flex items-center gap-2 py-1.5 group" data-vision-id="{{ $vision->id }}" wire:key="vision-{{ $vision->id }}">
                            @if ($visionEditing && $editingVisionId === $vision->id)
                                <div class="flex-1 flex items-center gap-2">
                                    <flux:input wire:model="editingVisionText" size="sm" wire:keydown.enter="updateVision" />
                                    <flux:button size="xs" variant="primary" wire:click="updateVision">{{ __('Save') }}</flux:button>
                                    <flux:button size="xs" variant="ghost" wire:click="cancelEditVision">{{ __('Cancel') }}</flux:button>
                                </div>
                            @elseif ($visionEditing)
                                <div class="drag-handle cursor-grab active:cursor-grabbing text-zinc-300 dark:text-zinc-600 hover:text-zinc-500 dark:hover:text-zinc-400 touch-none">
                                    <flux:icon.bars-3 variant="micro" />
                                </div>
                                <span class="flex-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $vision->text }}</span>
                                <div class="flex items-center gap-0.5">
                                    <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editVision({{ $vision->id }})" aria-label="{{ __('Edit vision') }}" />
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeVision({{ $vision->id }})" wire:confirm="{{ __('Remove this item?') }}" aria-label="{{ __('Remove vision') }}" />
                                </div>
                            @else
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $vision->text }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($visionEditing)
                <div class="flex items-center gap-2 {{ $this->visions->isNotEmpty() ? 'pt-3 border-t border-zinc-100 dark:border-zinc-700' : '' }}">
                    <div class="flex-1">
                        <flux:input
                            wire:model="newVisionText"
                            size="sm"
                            :placeholder="__('Add a vision...')"
                            wire:keydown.enter="addVision"
                        />
                    </div>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        wire:click="addVision"
                        aria-label="{{ __('Add vision') }}"
                    />
                </div>
            @endif
        </div>

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
                    ? $ef->balance / $plan->monthly_income
                    : null;
                $fixedCosts = $plan ? $plan->categoryTotal(SpendingCategory::FixedCosts) : 0;
                $monthsFixed = $plan && $fixedCosts > 0
                    ? $ef->balance / $fixedCosts
                    : null;
                $totalSavings = $this->netWorthSummary['categories'][AccountCategory::Savings->value];
                $monthsTotalAllSavings = $plan && $plan->monthly_income > 0
                    ? $totalSavings / $plan->monthly_income
                    : null;
                $monthsFixedAllSavings = $plan && $fixedCosts > 0
                    ? $totalSavings / $fixedCosts
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
                    @php
                        $efMetrics = [
                            ['months' => $monthsTotal, 'label' => 'total spending'],
                            ['months' => $monthsFixed, 'label' => 'fixed costs'],
                            ['months' => $monthsTotalAllSavings, 'label' => 'total spending (all savings)'],
                            ['months' => $monthsFixedAllSavings, 'label' => 'fixed costs (all savings)'],
                        ];
                    @endphp
                    <div class="space-y-2">
                        @foreach ($efMetrics as $metric)
                            <div class="flex items-center justify-between">
                                @if ($metric['months'] !== null && $metric['months'] < 2)
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Weeks of ' . $metric['label']) }}</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ (int) floor($metric['months'] * (52 / 12)) }}
                                    </span>
                                @else
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Months of ' . $metric['label']) }}</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $metric['months'] !== null ? (int) floor($metric['months']) : __('N/A') }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
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

@assets
<script src="/vendor/sortable.min.js"></script>
@endassets

@script
<script>
    function initVisionSortable() {
        const el = $wire.$el.querySelector('[data-sortable-visions]');
        if (!el || el._sortable) return;
        el._sortable = Sortable.create(el, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'opacity-30',
            onEnd() {
                $wire.reorderVisions(
                    Array.from(el.children)
                        .filter(child => child.dataset.visionId)
                        .map(child => child.dataset.visionId)
                );
            }
        });
    }

    initVisionSortable();

    new MutationObserver(() => initVisionSortable())
        .observe($wire.$el, { childList: true, subtree: true });
</script>
@endscript

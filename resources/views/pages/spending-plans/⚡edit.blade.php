<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public SpendingPlan $spendingPlan;

    // Plan details
    public string $name = '';
    public string $monthly_income = '';
    public string $gross_monthly_income = '';
    public string $pre_tax_investments = '';

    // Per-category new item form
    public array $newItemNames = [];
    public array $newItemAmounts = [];

    // Inline editing
    public ?int $editingItemId = null;
    public string $editingItemName = '';
    public string $editingItemAmount = '';

    public function mount(SpendingPlan $spendingPlan): void
    {
        abort_unless($spendingPlan->user_id === Auth::id(), 403);
        $this->spendingPlan = $spendingPlan;
        $this->name = $spendingPlan->name;
        $this->monthly_income = number_format($spendingPlan->monthly_income / 100, 2, '.', '');
        $this->gross_monthly_income = $spendingPlan->gross_monthly_income ? number_format($spendingPlan->gross_monthly_income / 100, 2, '.', '') : '';
        $this->pre_tax_investments = $spendingPlan->pre_tax_investments ? number_format($spendingPlan->pre_tax_investments / 100, 2, '.', '') : '';
    }

    #[Computed]
    public function plan(): SpendingPlan
    {
        return $this->spendingPlan->load('items');
    }

    /**
     * The three categories that accept manually-added items.
     *
     * @return list<SpendingCategory>
     */
    #[Computed]
    public function plannedCategories(): array
    {
        return [
            SpendingCategory::FixedCosts,
            SpendingCategory::Investments,
            SpendingCategory::Savings,
        ];
    }

    public function updatePlan(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'monthly_income' => ['required', 'numeric', 'min:0.01'],
            'gross_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'pre_tax_investments' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->spendingPlan->update([
            'name' => $validated['name'],
            'monthly_income' => (int) round($validated['monthly_income'] * 100),
            'gross_monthly_income' => (int) round(((float) $validated['gross_monthly_income']) * 100),
            'pre_tax_investments' => (int) round(((float) $validated['pre_tax_investments']) * 100),
        ]);

        unset($this->plan);
        $this->dispatch('plan-updated');
    }

    public function addItem(string $category): void
    {
        $this->validate([
            "newItemNames.{$category}" => ['required', 'string', 'max:255'],
            "newItemAmounts.{$category}" => ['required', 'numeric', 'min:0.01'],
        ], [], [
            "newItemNames.{$category}" => 'item name',
            "newItemAmounts.{$category}" => 'item amount',
        ]);

        abort_unless(in_array($category, array_column(SpendingCategory::cases(), 'value')), 422);
        abort_if($category === SpendingCategory::GuiltFree->value, 422);

        $maxSortOrder = $this->spendingPlan->items()
            ->where('category', $category)
            ->max('sort_order') ?? -1;

        $this->spendingPlan->items()->create([
            'category' => $category,
            'name' => $this->newItemNames[$category],
            'amount' => (int) round($this->newItemAmounts[$category] * 100),
            'sort_order' => $maxSortOrder + 1,
        ]);

        $this->newItemNames[$category] = '';
        $this->newItemAmounts[$category] = '';
        $this->spendingPlan->unsetRelation('items');
        unset($this->plan);
    }

    public function editItem(int $itemId): void
    {
        $item = SpendingPlanItem::findOrFail($itemId);
        abort_unless($item->spendingPlan->user_id === Auth::id(), 403);

        $this->editingItemId = $itemId;
        $this->editingItemName = $item->name;
        $this->editingItemAmount = number_format($item->amount / 100, 2, '.', '');
    }

    public function updateItem(): void
    {
        $validated = $this->validate([
            'editingItemName' => ['required', 'string', 'max:255'],
            'editingItemAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $item = SpendingPlanItem::findOrFail($this->editingItemId);
        abort_unless($item->spendingPlan->user_id === Auth::id(), 403);

        $item->update([
            'name' => $validated['editingItemName'],
            'amount' => (int) round($validated['editingItemAmount'] * 100),
        ]);

        $this->cancelEdit();
        $this->spendingPlan->unsetRelation('items');
        unset($this->plan);
    }

    public function cancelEdit(): void
    {
        $this->editingItemId = null;
        $this->editingItemName = '';
        $this->editingItemAmount = '';
    }

    public function removeItem(int $itemId): void
    {
        $item = SpendingPlanItem::findOrFail($itemId);
        abort_unless($item->spendingPlan->user_id === Auth::id(), 403);

        $item->delete();
        $this->spendingPlan->unsetRelation('items');
        unset($this->plan);
    }

    public function reorderItems(string $category, array $orderedIds): void
    {
        abort_unless(in_array($category, array_column(SpendingCategory::cases(), 'value')), 422);
        abort_if($category === SpendingCategory::GuiltFree->value, 422);

        $validIds = $this->spendingPlan->items()
            ->where('category', $category)
            ->pluck('id')
            ->all();

        foreach ($orderedIds as $position => $id) {
            abort_unless(in_array((int) $id, $validIds), 403);
            SpendingPlanItem::where('id', $id)->update(['sort_order' => $position]);
        }

        $this->spendingPlan->unsetRelation('items');
        unset($this->plan);
    }
}; ?>

<section class="w-full">
    @include('partials.spending-plans-heading')

    <div class="mb-6">
        <flux:link :href="route('spending-plans.show', $spendingPlan)" wire:navigate class="text-sm">
            &larr; {{ __('Back to plan') }}
        </flux:link>
    </div>

    {{-- Plan details form --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 mb-8">
        <flux:heading class="mb-4">{{ __('Plan Details') }}</flux:heading>

        <form wire:submit="updatePlan" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="name"
                    :label="__('Plan Name')"
                    type="text"
                    required
                />
                <flux:input
                    wire:model="monthly_income"
                    :label="__('Monthly Take-Home Income')"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>
                <flux:input
                    wire:model="gross_monthly_income"
                    :label="__('Gross Monthly Income')"
                    :description="__('Your total income before taxes and deductions.')"
                    type="number"
                    step="0.01"
                    min="0"
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>
                <flux:input
                    wire:model="pre_tax_investments"
                    :label="__('Pre-Tax Investments')"
                    :description="__('401(k), HSA, and other pre-tax contributions.')"
                    type="number"
                    step="0.01"
                    min="0"
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" size="sm">
                    {{ __('Save Details') }}
                </flux:button>
                <x-action-message class="me-3" on="plan-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </div>

    {{-- Line items for planned categories (Fixed Costs, Investments, Savings) --}}
    <div class="space-y-6">
        @foreach ($this->plannedCategories as $category)
            @php
                $catKey = $category->value;
                $items = $this->plan->items->where('category', $category);
                $total = $this->plan->categoryTotal($category);
                $percent = $this->plan->categoryPercent($category);
                [$min, $max] = $category->idealRange();
                $withinIdeal = $category->isWithinIdeal($percent);
            @endphp
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="size-3 rounded-full {{ $category->color() }}"></div>
                        <flux:heading>{{ $category->label() }}</flux:heading>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <flux:badge size="sm" color="{{ $withinIdeal ? 'green' : 'amber' }}">
                            {{ $percent }}%
                        </flux:badge>
                        <span class="text-zinc-500 dark:text-zinc-400">
                            {{ __('Ideal:') }} {{ $min == $max ? $min . '%' : $min . '–' . $max . '%' }}
                        </span>
                    </div>
                </div>

                {{-- Existing items --}}
                @if ($items->isNotEmpty())
                    <div class="space-y-1 mb-4" data-sortable-category="{{ $catKey }}">
                        @foreach ($items as $item)
                            <div class="flex items-center gap-2 py-1.5 group" data-item-id="{{ $item->id }}" wire:key="item-{{ $item->id }}">
                                @if ($editingItemId === $item->id)
                                    {{-- Inline edit mode --}}
                                    <div class="flex-1 space-y-2">
                                        <flux:input wire:model="editingItemName" size="sm" />
                                        <div class="flex items-center gap-2">
                                            <flux:input wire:model="editingItemAmount" type="number" step="0.01" min="0.01" size="sm" class="w-28">
                                                <x-slot:prefix>$</x-slot:prefix>
                                            </flux:input>
                                            <flux:button size="xs" variant="primary" wire:click="updateItem">{{ __('Save') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    </div>
                                @else
                                    {{-- Display mode --}}
                                    <div class="drag-handle cursor-grab active:cursor-grabbing text-zinc-300 dark:text-zinc-600 hover:text-zinc-500 dark:hover:text-zinc-400 touch-none">
                                        <flux:icon.bars-3 variant="micro" />
                                    </div>
                                    <span class="flex-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $item->name }}</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($item->amount / 100) }}</span>
                                    <div class="flex items-center gap-0.5">
                                        <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editItem({{ $item->id }})" aria-label="{{ __('Edit item') }}" />
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeItem({{ $item->id }})" wire:confirm="{{ __('Remove this item?') }}" aria-label="{{ __('Remove item') }}" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Add new item (per-category inputs) --}}
                <div class="flex items-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                    <div class="flex-1">
                        <flux:input
                            wire:model="newItemNames.{{ $catKey }}"
                            size="sm"
                            :placeholder="__('Item name')"
                            wire:keydown.enter="addItem('{{ $catKey }}')"
                        />
                    </div>
                    <div class="w-32">
                        <flux:input
                            wire:model="newItemAmounts.{{ $catKey }}"
                            type="number"
                            step="0.01"
                            min="0.01"
                            size="sm"
                            :placeholder="__('0.00')"
                        >
                            <x-slot:prefix>$</x-slot:prefix>
                        </flux:input>
                    </div>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        wire:click="addItem('{{ $catKey }}')"
                        aria-label="{{ __('Add item') }}"
                    />
                </div>

                {{-- Category subtotal --}}
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700 text-sm font-medium">
                    <span>{{ __('Subtotal') }}</span>
                    <span>${{ number_format($total / 100) }}</span>
                </div>
            </div>
        @endforeach

        {{-- Guilt-Free Spending (auto-calculated) --}}
        @php
            $guiltFree = SpendingCategory::GuiltFree;
            $guiltFreeTotal = $this->plan->categoryTotal($guiltFree);
            $guiltFreePercent = $this->plan->categoryPercent($guiltFree);
            [$gfMin, $gfMax] = $guiltFree->idealRange();
            $gfWithinIdeal = $guiltFree->isWithinIdeal($guiltFreePercent);
        @endphp
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="size-3 rounded-full {{ $guiltFree->color() }}"></div>
                    <flux:heading>{{ $guiltFree->label() }}</flux:heading>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <flux:badge size="sm" color="{{ $gfWithinIdeal ? 'green' : 'amber' }}">
                        {{ $guiltFreePercent }}%
                    </flux:badge>
                    <span class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Ideal:') }} {{ $gfMin . '–' . $gfMax . '%' }}
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Automatically calculated from remaining income') }}</span>
                <span class="text-lg font-bold {{ $guiltFreeTotal < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ $guiltFreeTotal < 0 ? '-' : '' }}${{ number_format(abs($guiltFreeTotal) / 100) }}
                </span>
            </div>
        </div>
    </div>

</section>

@assets
<script src="/vendor/sortable.min.js"></script>
@endassets

@script
<script>
    function initSortables() {
        $wire.$el.querySelectorAll('[data-sortable-category]').forEach(el => {
            if (el._sortable) return;
            el._sortable = Sortable.create(el, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'opacity-30',
                onEnd() {
                    $wire.reorderItems(
                        el.dataset.sortableCategory,
                        Array.from(el.children)
                            .filter(child => child.dataset.itemId)
                            .map(child => child.dataset.itemId)
                    );
                }
            });
        });
    }

    initSortables();

    // Re-init after Livewire updates (e.g. first item added to an empty category)
    new MutationObserver(mutations => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1) {
                    if (node.dataset?.sortableCategory) initSortables();
                    else if (node.querySelector?.('[data-sortable-category]')) initSortables();
                }
            }
        }
    }).observe($wire.$el, { childList: true, subtree: true });
</script>
@endscript

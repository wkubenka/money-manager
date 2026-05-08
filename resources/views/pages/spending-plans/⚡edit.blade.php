<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public SpendingPlan $spendingPlan;

    // Plan details
    public string $name = '';
    public string $monthly_income = '';
    public string $gross_monthly_income = '';
    public string $pre_tax_investments = '';
    public string $fixed_costs_misc_percent = '';

    // Per-category new item form
    public array $newItemNames = [];
    public array $newItemAmounts = [];

    // Inline editing
    public ?int $editingItemId = null;
    public string $editingItemName = '';
    public string $editingItemAmount = '';

    // Delete confirmation
    public ?int $confirmingDeleteItemId = null;

    public function mount(SpendingPlan $spendingPlan): void
    {
        $this->spendingPlan = $spendingPlan;
        $this->name = $spendingPlan->name;
        $this->monthly_income = (string) ($spendingPlan->monthly_income / 100);
        $this->gross_monthly_income = $spendingPlan->gross_monthly_income ? (string) ($spendingPlan->gross_monthly_income / 100) : '';
        $this->pre_tax_investments = $spendingPlan->pre_tax_investments ? (string) ($spendingPlan->pre_tax_investments / 100) : '';
        $this->fixed_costs_misc_percent = (string) $spendingPlan->fixed_costs_misc_percent;
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
        $this->monthly_income = sanitize_money_input($this->monthly_income);
        $this->gross_monthly_income = sanitize_money_input($this->gross_monthly_income);
        $this->pre_tax_investments = sanitize_money_input($this->pre_tax_investments);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'monthly_income' => ['required', 'numeric', 'min:0.01'],
            'gross_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'pre_tax_investments' => ['nullable', 'numeric', 'min:0'],
            'fixed_costs_misc_percent' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        $this->spendingPlan->update([
            'name' => $validated['name'],
            'monthly_income' => (int) round($validated['monthly_income'] * 100),
            'gross_monthly_income' => (int) round(((float) $validated['gross_monthly_income']) * 100),
            'pre_tax_investments' => (int) round(((float) $validated['pre_tax_investments']) * 100),
            'fixed_costs_misc_percent' => (int) $validated['fixed_costs_misc_percent'],
        ]);

        unset($this->plan);
        $this->dispatch('plan-updated');
    }

    public function addItem(string $category): void
    {
        $this->newItemAmounts[$category] = sanitize_money_input($this->newItemAmounts[$category] ?? '');

        $this->validate([
            "newItemNames.{$category}" => ['required', 'string', 'max:255'],
            "newItemAmounts.{$category}" => ['required', 'numeric', 'min:0.01'],
        ], [], [
            "newItemNames.{$category}" => 'item name',
            "newItemAmounts.{$category}" => 'item amount',
        ]);

        abort_unless(in_array($category, array_column(SpendingCategory::cases(), 'value')), 422);
        abort_if($category === SpendingCategory::GuiltFree->value, 422);
        abort_if($this->spendingPlan->items()->where('category', $category)->count() >= SpendingPlan::MAX_ITEMS_PER_CATEGORY, 422);

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

        $this->js("document.getElementById('new-item-name-{$category}')?.focus()");
    }

    public function editItem(int $itemId): void
    {
        $item = SpendingPlanItem::findOrFail($itemId);

        $this->editingItemId = $itemId;
        $this->editingItemName = $item->name;
        $this->editingItemAmount = (string) ($item->amount / 100);
    }

    public function updateItem(): void
    {
        $this->editingItemAmount = sanitize_money_input($this->editingItemAmount);

        $validated = $this->validate([
            'editingItemName' => ['required', 'string', 'max:255'],
            'editingItemAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $item = SpendingPlanItem::findOrFail($this->editingItemId);

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

    public function confirmRemoveItem(int $itemId): void
    {
        $this->confirmingDeleteItemId = $itemId;
    }

    public function cancelRemoveItem(): void
    {
        $this->confirmingDeleteItemId = null;
    }

    public function removeItem(int $itemId): void
    {
        $item = SpendingPlanItem::findOrFail($itemId);

        $item->delete();
        $this->confirmingDeleteItemId = null;
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

<section class="w-full px-10 py-9 max-w-[1320px] mx-auto">
    <div class="mb-6">
        <a href="{{ route('spending-plans.dashboard') }}" wire:navigate class="text-vault-textsub hover:text-vault-text transition-colors" style="font-size: 12px;">
            ← {{ __('Back to plan') }}
        </a>
    </div>

    <x-page-heading eyebrow="Edit Plan" :title="$spendingPlan->name" />

    {{-- Plan details form --}}
    <div class="rounded-2xl border border-vault-card-bd bg-vault-card mb-5" style="padding: 22px 26px;">
        <div class="eyebrow text-vault-textsub mb-4" style="letter-spacing: 0.13em;">{{ __('Plan Details') }}</div>

        <form wire:submit="updatePlan">
            <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
                <flux:input
                    wire:model="name"
                    :label="__('Plan name')"
                    type="text"
                    required
                />
                <flux:input
                    wire:model="monthly_income"
                    :label="__('Monthly take-home')"
                    type="text"
                    inputmode="decimal"
                    required
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>
                <flux:input
                    wire:model="gross_monthly_income"
                    :label="__('Gross monthly income')"
                    :description="__('Your total income before taxes and deductions.')"
                    type="text"
                    inputmode="decimal"
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>
                <flux:input
                    wire:model="pre_tax_investments"
                    :label="__('Pre-tax investments')"
                    :description="__('401(k), HSA, and other pre-tax contributions.')"
                    type="text"
                    inputmode="decimal"
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>
                <flux:input
                    wire:model="fixed_costs_misc_percent"
                    :label="__('Fixed costs buffer')"
                    :description="__('Percentage added to fixed costs for unexpected expenses.')"
                    type="number"
                    step="1"
                    min="0"
                    max="30"
                    required
                >
                    <x-slot:suffix>%</x-slot:suffix>
                </flux:input>
            </div>

            <div class="flex items-center gap-4 mt-5">
                <flux:button variant="primary" type="submit">
                    {{ __('Save details') }}
                </flux:button>
                <x-action-message on="plan-updated" class="text-vault-accent" style="font-size: 12px;">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </div>

    {{-- Line items for planned categories (Fixed Costs, Investments, Savings) --}}
    @php
        $gfPercent = $this->plan->categoryPercent(SpendingCategory::GuiltFree);
        $gfHealthy = SpendingCategory::GuiltFree->isWithinIdeal($gfPercent);
    @endphp
    <div class="flex flex-col gap-4">
        @foreach ($this->plannedCategories as $category)
            @php
                $catKey = $category->value;
                $items = $this->plan->items->where('category', $category);
                $total = $this->plan->categoryTotal($category);
                $percent = $this->plan->categoryPercent($category);
                $withinIdeal = $category->isWithinIdeal($percent, $gfHealthy);
                $catColor = $category->vaultColor();
            @endphp
            <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 20px 26px;">
                <div class="flex items-center justify-between mb-3.5">
                    <div class="flex items-center" style="gap: 10px;">
                        <span class="rounded-full" style="width: 7px; height: 7px; background: {{ $catColor }};"></span>
                        <span class="font-display text-vault-text" style="font-size: 15px; font-weight: 400;">{{ $category->label() }}</span>
                        <span class="rounded" style="background: color-mix(in srgb, {{ $withinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }} 15%, transparent); border: 1px solid color-mix(in srgb, {{ $withinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }} 35%, transparent); color: {{ $withinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }}; font-size: 9px; padding: 2px 6px; letter-spacing: 0.04em;">{{ $percent }}%</span>
                        <span class="text-vault-muted" style="font-size: 11px;">{{ __('ideal: :ideal', ['ideal' => $category->idealLabel()]) }}</span>
                    </div>
                </div>

                {{-- Existing items --}}
                @if ($items->isNotEmpty())
                    <div class="flex flex-col" data-sortable-category="{{ $catKey }}">
                        @foreach ($items as $i => $item)
                            <div
                                class="flex items-center group {{ $i > 0 ? 'border-t border-vault-card-bd' : '' }}"
                                style="padding: 8px 0; gap: 8px;"
                                data-item-id="{{ $item->id }}"
                                wire:key="item-{{ $item->id }}"
                            >
                                @if ($editingItemId === $item->id)
                                    <div class="flex-1 flex items-center gap-2 flex-wrap">
                                        <flux:input wire:model="editingItemName" size="sm" class="flex-1 min-w-0" wire:keydown.enter="updateItem" />
                                        <flux:input wire:model="editingItemAmount" type="text" inputmode="decimal" size="sm" class="w-28" wire:keydown.enter="updateItem">
                                            <x-slot:prefix>$</x-slot:prefix>
                                        </flux:input>
                                        <flux:button size="xs" variant="primary" wire:click="updateItem">{{ __('Save') }}</flux:button>
                                        <flux:button size="xs" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                    </div>
                                @else
                                    <button type="button" class="drag-handle cursor-grab active:cursor-grabbing text-vault-muted hover:text-vault-textsub touch-none" aria-label="{{ __('Drag to reorder') }}">
                                        <flux:icon.bars-3 variant="micro" />
                                    </button>
                                    <span class="flex-1 text-vault-textsub truncate" style="font-size: 13px;">{{ $item->name }}</span>
                                    <span class="text-vault-text" style="font-size: 13px;">${{ format_cents($item->amount) }}</span>
                                    <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                                        <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editItem({{ $item->id }})" aria-label="{{ __('Edit item') }}" />
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmRemoveItem({{ $item->id }})" aria-label="{{ __('Remove item') }}" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Add new item --}}
                @if ($items->count() < \App\Models\SpendingPlan::MAX_ITEMS_PER_CATEGORY)
                    <div class="flex items-end gap-2 pt-3 mt-2 border-t border-vault-card-bd">
                        <div class="flex-1">
                            <flux:input
                                id="new-item-name-{{ $catKey }}"
                                wire:model="newItemNames.{{ $catKey }}"
                                size="sm"
                                :placeholder="__('Add :label item', ['label' => strtolower($category->label())])"
                                wire:keydown.enter="addItem('{{ $catKey }}')"
                            />
                        </div>
                        <div class="w-32">
                            <flux:input
                                wire:model="newItemAmounts.{{ $catKey }}"
                                type="text"
                                inputmode="decimal"
                                size="sm"
                                :placeholder="__('0.00')"
                                wire:keydown.enter="addItem('{{ $catKey }}')"
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
                @endif

                {{-- Category subtotal --}}
                <div class="mt-3 pt-3 border-t border-vault-card-bd flex flex-col gap-1.5">
                    @if ($category === SpendingCategory::FixedCosts && $this->plan->fixed_costs_misc_percent > 0)
                        <div class="flex items-center justify-between italic text-vault-muted" style="font-size: 12px;">
                            <span>{{ __('Miscellaneous buffer') }} ({{ $this->plan->fixed_costs_misc_percent }}%)</span>
                            <span>${{ format_cents($this->plan->fixedCostsMiscellaneous()) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="eyebrow text-vault-textsub" style="letter-spacing: 0.1em;">{{ __('Subtotal') }}</span>
                        <span class="font-display text-vault-text" style="font-size: 16px; font-weight: 400;">${{ format_cents($total) }}</span>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Guilt-Free (auto-calculated) --}}
        @php
            $guiltFree = SpendingCategory::GuiltFree;
            $guiltFreeTotal = $this->plan->categoryTotal($guiltFree);
            $guiltFreePercent = $this->plan->categoryPercent($guiltFree);
            $gfWithinIdeal = $guiltFree->isWithinIdeal($guiltFreePercent);
            $gfColor = $guiltFree->vaultColor();
        @endphp
        <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 20px 26px;">
            <div class="flex items-center justify-between">
                <div class="flex items-center" style="gap: 10px;">
                    <span class="rounded-full" style="width: 7px; height: 7px; background: {{ $gfColor }};"></span>
                    <span class="font-display text-vault-text" style="font-size: 15px; font-weight: 400;">{{ $guiltFree->label() }}</span>
                    <span class="rounded" style="background: color-mix(in srgb, {{ $gfWithinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }} 15%, transparent); border: 1px solid color-mix(in srgb, {{ $gfWithinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }} 35%, transparent); color: {{ $gfWithinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }}; font-size: 9px; padding: 2px 6px; letter-spacing: 0.04em;">{{ $guiltFreePercent }}%</span>
                    <span class="text-vault-muted" style="font-size: 11px;">{{ __('ideal: :ideal', ['ideal' => $guiltFree->idealLabel()]) }}</span>
                </div>
                <span class="font-display" style="font-size: 18px; font-weight: 300; color: {{ $guiltFreeTotal < 0 ? 'var(--color-vault-red)' : 'var(--color-vault-text)' }};">
                    {{ $guiltFreeTotal < 0 ? '−' : '' }}${{ format_cents(abs($guiltFreeTotal)) }}
                </span>
            </div>
            <div class="text-vault-muted italic mt-2" style="font-size: 11px;">
                {{ __('Automatically calculated from remaining income') }}
            </div>
        </div>
    </div>

    {{-- Delete Item Confirmation --}}
    <flux:modal wire:model.self="confirmingDeleteItemId" class="min-w-[22rem]">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Remove item') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Remove this item?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">{{ __('This action cannot be undone.') }}</div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelRemoveItem">{{ __('Cancel') }}</flux:button>
                @if ($confirmingDeleteItemId)
                    <flux:button variant="danger" wire:click="removeItem({{ $confirmingDeleteItemId }})">{{ __('Remove') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
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

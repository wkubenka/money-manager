<?php

use App\Actions\CopySpendingPlan;
use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\WindfallPlan;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $activePlanId = null;
    public bool $confirmingNewPlan = false;
    public bool $confirmingDelete = false;

    public bool $windfallEditing = false;
    public int $windfallSavings = 0;
    public int $windfallInvestments = 0;
    public int $windfallGuiltFree = 0;
    public int $windfallDebt = 0;

    public function mount(): void
    {
        $current = SpendingPlan::where('is_current', true)->first();
        $this->activePlanId = $current?->id ?? SpendingPlan::oldest()->first()?->id;

        $windfall = WindfallPlan::instance();
        $this->windfallSavings = $windfall->savings_percent;
        $this->windfallInvestments = $windfall->investments_percent;
        $this->windfallGuiltFree = $windfall->guilt_free_percent;
        $this->windfallDebt = $windfall->debt_percent;
    }

    #[Computed]
    public function plans()
    {
        return SpendingPlan::query()->oldest()->with('items')->get();
    }

    #[Computed]
    public function activePlan(): ?SpendingPlan
    {
        return $this->plans->firstWhere('id', $this->activePlanId)
            ?? $this->plans->firstWhere('is_current', true)
            ?? $this->plans->first();
    }

    public function selectPlan(int $planId): void
    {
        $this->activePlanId = $planId;
    }

    public function copyPlan(int $planId): void
    {
        $plan = SpendingPlan::findOrFail($planId);
        $copy = app(CopySpendingPlan::class)($plan);

        $this->redirect(route('spending-plans.edit', $copy), navigate: true);
    }

    public function markAsCurrent(int $planId): void
    {
        $plan = SpendingPlan::findOrFail($planId);

        SpendingPlan::where('is_current', true)->update(['is_current' => false]);
        $plan->update(['is_current' => true]);
        unset($this->plans, $this->activePlan);
    }

    public function deletePlan(): void
    {
        abort_if(SpendingPlan::count() <= 1, 422);

        $plan = SpendingPlan::findOrFail($this->activePlanId);
        $plan->delete();
        SpendingPlan::ensureCurrentPlan();

        $this->confirmingDelete = false;
        $this->activePlanId = SpendingPlan::where('is_current', true)->first()?->id
            ?? SpendingPlan::oldest()->first()?->id;

        unset($this->plans, $this->activePlan);
    }

    public function saveWindfallPlan(): void
    {
        $this->validate([
            'windfallSavings'     => ['required', 'integer', 'min:0', 'max:100'],
            'windfallInvestments' => ['required', 'integer', 'min:0', 'max:100'],
            'windfallGuiltFree'   => ['required', 'integer', 'min:0', 'max:100'],
            'windfallDebt'        => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $total = $this->windfallSavings
               + $this->windfallInvestments
               + $this->windfallGuiltFree
               + $this->windfallDebt;

        if ($total !== 100) {
            $this->addError('windfallSavings', 'Splits must add up to 100%.');

            return;
        }

        WindfallPlan::instance()->update([
            'savings_percent'     => $this->windfallSavings,
            'investments_percent' => $this->windfallInvestments,
            'guilt_free_percent'  => $this->windfallGuiltFree,
            'debt_percent'        => $this->windfallDebt,
        ]);

        $this->windfallEditing = false;
    }

    public function cancelWindfall(): void
    {
        $plan = WindfallPlan::instance();
        $this->windfallSavings     = $plan->savings_percent;
        $this->windfallInvestments = $plan->investments_percent;
        $this->windfallGuiltFree   = $plan->guilt_free_percent;
        $this->windfallDebt        = $plan->debt_percent;
        $this->windfallEditing     = false;
    }
}; ?>

@php $atLimit = $this->plans->count() >= SpendingPlan::MAX_PER_USER; @endphp

<section class="w-full px-10 py-9 max-w-[1320px] mx-auto">
    <div class="flex justify-between items-start mb-6">
        <x-page-heading
            eyebrow="Spending Plans"
            title="Conscious Spending"
            subtitle="Allocate your take-home across four buckets"
        />
        <div class="flex items-center gap-2 pt-5">
            @php $headerPlan = $this->activePlan; @endphp
            @if ($headerPlan)
                <flux:button :href="route('spending-plans.edit', $headerPlan)" wire:navigate icon="pencil-square">
                    {{ __('Edit plan') }}
                </flux:button>
            @endif
            @unless ($atLimit)
                @if ($headerPlan)
                    <flux:button variant="primary" wire:click="$set('confirmingNewPlan', true)" icon="plus">
                        {{ __('New plan') }}
                    </flux:button>
                @else
                    <flux:button variant="primary" :href="route('spending-plans.create')" wire:navigate icon="plus">
                        {{ __('New plan') }}
                    </flux:button>
                @endif
            @endunless
        </div>
    </div>

    @if ($this->plans->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-vault-card-bd bg-vault-card py-16 px-6">
            <flux:icon name="banknotes" class="size-12 text-vault-muted mb-4" />
            <div class="font-display text-vault-text mb-1" style="font-size: 22px; font-weight: 300;">{{ __('No spending plans yet') }}</div>
            <div class="text-vault-textsub mb-6" style="font-size: 13px;">{{ __('Create your first conscious spending plan to start allocating your income.') }}</div>
            <flux:button variant="primary" :href="route('spending-plans.create')" wire:navigate>
                {{ __('Create your first plan') }}
            </flux:button>
        </div>
    @else
        {{-- Plan switcher tabs --}}
        <div class="flex flex-wrap gap-2.5 mb-6">
            @foreach ($this->plans as $plan)
                @php $active = $plan->id === $this->activePlanId; @endphp
                <button
                    type="button"
                    wire:click="selectPlan({{ $plan->id }})"
                    class="rounded-[10px] transition-all text-left cursor-pointer"
                    style="
                        background: {{ $active ? 'var(--color-vault-card)' : 'transparent' }};
                        border: 1px solid {{ $active ? 'var(--color-vault-accent)' : 'var(--color-vault-card-bd)' }};
                        padding: 12px 18px;
                        min-width: 200px;
                    "
                >
                    <div class="flex items-center mb-1" style="gap: 8px;">
                        <span class="font-display" style="font-size: 15px; font-weight: 400; color: {{ $active ? 'var(--color-vault-text)' : 'var(--color-vault-textsub)' }};">{{ $plan->name }}</span>
                        @if ($plan->is_current)
                            <span class="rounded text-vault-accent" style="background: color-mix(in srgb, var(--color-vault-accent) 15%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-accent) 35%, transparent); font-size: 9px; padding: 2px 6px; letter-spacing: 0.04em;">{{ __('Current') }}</span>
                        @endif
                    </div>
                    <div class="text-vault-muted mb-1" style="font-size: 11px;">
                        {{ count($plan->items) }} {{ Str::plural('item', count($plan->items)) }}
                    </div>
                    <div class="font-display" style="font-size: 13px; font-weight: 300; color: {{ $active ? 'var(--color-vault-accent)' : 'var(--color-vault-textsub)' }};">
                        ${{ format_cents($plan->monthly_income) }}<span class="text-vault-muted" style="font-size: 10px;">/mo</span>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Active plan detail --}}
        @php
            $currentPlan = $this->activePlan;
            $gfPercent = $currentPlan?->categoryPercent(SpendingCategory::GuiltFree) ?? 0;
            $gfHealthy = SpendingCategory::GuiltFree->isWithinIdeal($gfPercent);
        @endphp

        @if ($currentPlan)
            <div class="rounded-2xl border border-vault-card-bd bg-vault-card mb-6" style="padding: 24px 28px;">
                <div class="flex justify-between items-start mb-5">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="font-display text-vault-text" style="font-size: 22px; font-weight: 400;">{{ $currentPlan->name }}</span>
                            @if ($currentPlan->is_current)
                                <span class="rounded text-vault-accent" style="background: color-mix(in srgb, var(--color-vault-accent) 15%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-accent) 35%, transparent); font-size: 9px; padding: 2px 6px; letter-spacing: 0.04em;">{{ __('Current') }}</span>
                            @else
                                <span class="rounded text-vault-muted" style="background: color-mix(in srgb, var(--color-vault-muted) 12%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-muted) 30%, transparent); font-size: 9px; padding: 2px 6px; letter-spacing: 0.04em;">{{ __('Draft') }}</span>
                            @endif
                        </div>
                        <div class="flex gap-6 flex-wrap">
                            <div>
                                <div class="text-vault-muted mb-0.5" style="font-size: 10px; letter-spacing: 0.08em;">{{ __('TAKE-HOME') }}</div>
                                <div class="font-display text-vault-text" style="font-size: 20px; font-weight: 300;">
                                    ${{ format_cents($currentPlan->monthly_income) }}<span class="text-vault-muted" style="font-size: 12px;">/mo</span>
                                </div>
                            </div>
                            @if ($currentPlan->gross_monthly_income)
                                <div>
                                    <div class="text-vault-muted mb-0.5" style="font-size: 10px; letter-spacing: 0.08em;">{{ __('GROSS') }}</div>
                                    <div class="font-display text-vault-textsub" style="font-size: 20px; font-weight: 300;">
                                        ${{ format_cents($currentPlan->gross_monthly_income) }}<span class="text-vault-muted" style="font-size: 12px;">/mo</span>
                                    </div>
                                </div>
                            @endif
                            @if ($currentPlan->pre_tax_investments)
                                <div>
                                    <div class="text-vault-muted mb-0.5" style="font-size: 10px; letter-spacing: 0.08em;">{{ __('PRE-TAX INVEST') }}</div>
                                    <div class="font-display text-vault-textsub" style="font-size: 20px; font-weight: 300;">
                                        ${{ format_cents($currentPlan->pre_tax_investments) }}<span class="text-vault-muted" style="font-size: 12px;">/mo</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @if (! $currentPlan->is_current)
                            <flux:button size="sm" variant="primary" wire:click="markAsCurrent({{ $currentPlan->id }})">
                                {{ __('Make current') }}
                            </flux:button>
                        @endif
                        @if ($this->plans->count() > 1)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="$set('confirmingDelete', true)" aria-label="{{ __('Delete plan') }}" class="!text-vault-muted hover:!text-vault-red" />
                        @endif
                    </div>
                </div>
            </div>

            {{-- Category breakdown --}}
            <div class="flex flex-col gap-4">
                @foreach (SpendingCategory::spendingCases() as $category)
                    @php
                        $percent = $currentPlan->categoryPercent($category);
                        $total = $currentPlan->categoryTotal($category);
                        $withinIdeal = $category->isWithinIdeal($percent, $gfHealthy);
                        $catColor = $category->vaultColor();
                        $items = $currentPlan->items->where('category', $category);
                    @endphp
                    <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 20px 26px;">
                        <div class="flex justify-between items-center mb-3.5">
                            <div class="flex items-center" style="gap: 10px;">
                                <span class="rounded-full" style="width: 8px; height: 8px; background: {{ $catColor }};"></span>
                                <span class="font-display text-vault-text" style="font-size: 16px; font-weight: 400;">{{ $category->label() }}</span>
                                <span class="rounded" style="background: color-mix(in srgb, {{ $withinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }} 15%, transparent); border: 1px solid color-mix(in srgb, {{ $withinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }} 35%, transparent); color: {{ $withinIdeal ? 'var(--color-vault-accent)' : 'var(--color-vault-warm)' }}; font-size: 9px; padding: 2px 6px; letter-spacing: 0.04em;">{{ $percent }}%</span>
                                <span class="text-vault-muted" style="font-size: 11px;">{{ __('ideal: :ideal', ['ideal' => $category->idealLabel()]) }}</span>
                            </div>
                            <span class="font-display" style="font-size: 18px; font-weight: 300; color: {{ $total < 0 ? 'var(--color-vault-red)' : 'var(--color-vault-text)' }};">
                                {{ $total < 0 ? '−' : '' }}${{ format_cents(abs($total)) }}
                            </span>
                        </div>

                        {{-- Progress bar --}}
                        <div class="w-full overflow-hidden rounded-full" style="height: 4px; background: color-mix(in srgb, {{ $catColor }} 15%, transparent);">
                            <div class="h-full rounded-full" style="width: {{ min(max($percent, 0), 100) }}%; background: {{ $catColor }};"></div>
                        </div>

                        {{-- Line items --}}
                        @if ($items->isNotEmpty())
                            <div class="mt-3.5 flex flex-col">
                                @foreach ($items as $i => $item)
                                    @if ($i > 0)
                                        <div class="border-t border-vault-card-bd"></div>
                                    @endif
                                    <div class="flex justify-between" style="padding: 7px 0;">
                                        <span class="text-vault-textsub" style="font-size: 12px;">{{ $item->name }}</span>
                                        <span class="text-vault-text" style="font-size: 12px;">${{ format_cents($item->amount) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($category === SpendingCategory::GuiltFree)
                            <div class="text-vault-muted italic mt-2.5" style="font-size: 11px;">
                                {{ __("Automatically calculated — what's left after fixed costs, investments, and savings") }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Windfall plan --}}
            <div class="mt-6">
                @include('partials.windfall-plan')
            </div>
        @endif
    @endif

    @if ($this->activePlan)
        <flux:modal wire:model="confirmingDelete" focusable class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete :name?', ['name' => $this->activePlan->name]) }}</flux:heading>
                    <flux:subheading>
                        {{ __('This will permanently delete this spending plan and all of its items. This action cannot be undone.') }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deletePlan">
                        {{ __('Delete plan') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    @if ($this->activePlan && ! $atLimit)
        <flux:modal wire:model="confirmingNewPlan" focusable class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Start with :name?', ['name' => $this->activePlan->name]) }}</flux:heading>
                    <flux:subheading>
                        {{ __('Copy this plan as a starting point, or start with a blank plan.') }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button :href="route('spending-plans.create')" wire:navigate>
                        {{ __('Start blank') }}
                    </flux:button>
                    <flux:button variant="primary" wire:click="copyPlan({{ $this->activePlan->id }})">
                        {{ __('Copy :name', ['name' => $this->activePlan->name]) }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</section>

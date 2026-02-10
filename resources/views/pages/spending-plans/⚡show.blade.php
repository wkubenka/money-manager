<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public SpendingPlan $spendingPlan;

    public bool $confirmingDelete = false;

    public function mount(SpendingPlan $spendingPlan): void
    {
        abort_unless($spendingPlan->user_id === Auth::id(), 403);
        $this->spendingPlan = $spendingPlan->load('items');
    }

    public function deletePlan(): void
    {
        abort_unless($this->spendingPlan->user_id === Auth::id(), 403);
        abort_if(Auth::user()->spendingPlans()->count() <= 1, 422);

        $user = Auth::user();
        $this->spendingPlan->delete();
        SpendingPlan::ensureCurrentPlanForUser($user);

        $this->redirect(route('spending-plans.dashboard'), navigate: true);
    }

    public function copyPlan(): void
    {
        $plan = $this->spendingPlan;
        abort_unless($plan->user_id === Auth::id(), 403);
        abort_if(Auth::user()->spendingPlans()->count() >= SpendingPlan::MAX_PER_USER, 422);

        $copy = SpendingPlan::create([
            'user_id' => Auth::id(),
            'name' => "Copy of {$plan->name}",
            'monthly_income' => $plan->monthly_income,
            'gross_monthly_income' => $plan->gross_monthly_income,
            'pre_tax_investments' => $plan->pre_tax_investments,
            'fixed_costs_misc_percent' => $plan->fixed_costs_misc_percent,
            'is_current' => false,
        ]);

        foreach ($plan->items as $item) {
            $copy->items()->create([
                'category' => $item->category,
                'name' => $item->name,
                'amount' => $item->amount,
                'sort_order' => $item->sort_order,
            ]);
        }

        $this->redirect(route('spending-plans.edit', $copy), navigate: true);
    }
}; ?>

<section class="w-full">
    @include('partials.spending-plans-heading')

    <div class="mb-6">
        <flux:link :href="route('spending-plans.dashboard')" wire:navigate class="text-sm">
            &larr; {{ __('Back to all plans') }}
        </flux:link>
    </div>

    <div class="mb-8">
        <div>
            <flux:heading size="lg">{{ $spendingPlan->name }}</flux:heading>
            <flux:subheading>{{ __('Monthly take-home:') }} ${{ number_format($spendingPlan->monthly_income / 100) }}</flux:subheading>
            @if ($spendingPlan->gross_monthly_income || $spendingPlan->pre_tax_investments)
                <div class="mt-1 flex flex-wrap gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                    @if ($spendingPlan->gross_monthly_income)
                        <span>{{ __('Gross:') }} ${{ number_format($spendingPlan->gross_monthly_income / 100) }}</span>
                    @endif
                    @if ($spendingPlan->pre_tax_investments)
                        <span>{{ __('Investments deducted from paycheck:') }} ${{ number_format($spendingPlan->pre_tax_investments / 100) }}</span>
                    @endif
                </div>
            @endif
        </div>
        <div class="mt-3 flex items-center gap-2">
            @if (Auth::user()->spendingPlans()->count() < SpendingPlan::MAX_PER_USER)
                <flux:button variant="ghost" size="sm" icon="document-duplicate" wire:click="copyPlan" aria-label="{{ __('Copy plan') }}">
                    {{ __('Copy Plan') }}
                </flux:button>
            @endif
            <flux:button variant="primary" size="sm" icon="pencil" :href="route('spending-plans.edit', $spendingPlan)" wire:navigate>
                {{ __('Edit Plan') }}
            </flux:button>
        </div>
    </div>

    <div class="space-y-8">
        {{-- Planned categories (Fixed Costs, Investments, Savings) --}}
        @foreach ([SpendingCategory::FixedCosts, SpendingCategory::Investments, SpendingCategory::Savings] as $category)
            @php
                $items = $spendingPlan->items->where('category', $category);
                $total = $spendingPlan->categoryTotal($category);
                $percent = $spendingPlan->categoryPercent($category);
                [$min, $max] = $category->idealRange();
                $withinIdeal = $category->isWithinIdeal($percent);
            @endphp
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="size-3 rounded-full {{ $category->color() }}"></div>
                        <flux:heading>{{ $category->label() }}</flux:heading>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge size="sm" color="{{ $withinIdeal ? 'green' : 'amber' }}">
                            {{ $percent }}%
                        </flux:badge>
                        <flux:badge size="sm" variant="outline">
                            {{ __('Ideal:') }} {{ $min == $max ? $min . '%' : $min . '–' . $max . '%' }}
                        </flux:badge>
                    </div>
                </div>

                <div class="mb-4 h-2 rounded-full bg-zinc-100 dark:bg-zinc-700 overflow-hidden">
                    <div class="h-full rounded-full {{ $category->color() }}" style="width: {{ min($percent, 100) }}%"></div>
                </div>

                @if ($items->isEmpty())
                    <flux:text class="text-zinc-400 dark:text-zinc-500 italic">
                        {{ __('No items added yet.') }}
                    </flux:text>
                @else
                    <div class="space-y-2">
                        @foreach ($items as $item)
                            <div class="flex items-center justify-between py-1.5 text-sm">
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $item->name }}</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($item->amount / 100) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                    @if ($category === SpendingCategory::FixedCosts && $spendingPlan->fixed_costs_misc_percent > 0)
                        <div class="flex items-center justify-between py-1.5 text-sm italic">
                            <span>{{ __('Miscellaneous') }} ({{ $spendingPlan->fixed_costs_misc_percent }}%)</span>
                            <span>${{ number_format($spendingPlan->fixedCostsMiscellaneous() / 100) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between text-sm font-medium">
                        <span>{{ __('Subtotal') }}</span>
                        <span>${{ number_format($total / 100) }}</span>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Guilt-Free Spending (auto-calculated) --}}
        @php
            $guiltFree = SpendingCategory::GuiltFree;
            $guiltFreeTotal = $spendingPlan->categoryTotal($guiltFree);
            $guiltFreePercent = $spendingPlan->categoryPercent($guiltFree);
            [$gfMin, $gfMax] = $guiltFree->idealRange();
            $gfWithinIdeal = $guiltFree->isWithinIdeal($guiltFreePercent);
        @endphp
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="size-3 rounded-full {{ $guiltFree->color() }}"></div>
                    <flux:heading>{{ $guiltFree->label() }}</flux:heading>
                </div>
                <div class="flex items-center gap-3">
                    <flux:badge size="sm" color="{{ $gfWithinIdeal ? 'green' : 'amber' }}">
                        {{ $guiltFreePercent }}%
                    </flux:badge>
                    <flux:badge size="sm" variant="outline">
                        {{ __('Ideal:') }} {{ $gfMin . '–' . $gfMax . '%' }}
                    </flux:badge>
                </div>
            </div>

            <div class="mb-4 h-2 rounded-full bg-zinc-100 dark:bg-zinc-700 overflow-hidden">
                <div class="h-full rounded-full {{ $guiltFree->color() }}" style="width: {{ min(max($guiltFreePercent, 0), 100) }}%"></div>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Remaining after other categories') }}</span>
                <span class="font-bold {{ $guiltFreeTotal < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ $guiltFreeTotal < 0 ? '-' : '' }}${{ number_format(abs($guiltFreeTotal) / 100) }}
                </span>
            </div>
        </div>
    </div>

    @if (Auth::user()->spendingPlans()->count() > 1)
        <div class="mt-8">
            <flux:button variant="ghost" size="sm" icon="trash" wire:click="$set('confirmingDelete', true)" class="text-red-600! dark:text-red-400!">
                {{ __('Delete Plan') }}
            </flux:button>
        </div>
    @endif

    <flux:modal wire:model="confirmingDelete" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete spending plan?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will permanently delete this spending plan and all of its items. This action cannot be undone.') }}
                </flux:subheading>
            </div>

            <div class="flex justify-end space-x-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deletePlan">
                    {{ __('Delete Plan') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>

<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $deletingPlanId = null;

    #[Computed]
    public function plans()
    {
        return Auth::user()->spendingPlans()->latest()->with('items')->get();
    }

    public function confirmDelete(int $planId): void
    {
        $this->deletingPlanId = $planId;
    }

    public function deletePlan(): void
    {
        if (! $this->deletingPlanId) {
            return;
        }

        $plan = SpendingPlan::findOrFail($this->deletingPlanId);
        abort_unless($plan->user_id === Auth::id(), 403);

        $plan->delete();
        $this->deletingPlanId = null;
        unset($this->plans);
    }

    public function cancelDelete(): void
    {
        $this->deletingPlanId = null;
    }

    public function markAsCurrent(int $planId): void
    {
        $plan = SpendingPlan::findOrFail($planId);
        abort_unless($plan->user_id === Auth::id(), 403);

        Auth::user()->spendingPlans()->where('is_current', true)->update(['is_current' => false]);
        $plan->update(['is_current' => true]);
        unset($this->plans);
    }

    public function unmarkCurrent(int $planId): void
    {
        $plan = SpendingPlan::findOrFail($planId);
        abort_unless($plan->user_id === Auth::id(), 403);

        $plan->update(['is_current' => false]);
        unset($this->plans);
    }
}; ?>

<section class="w-full">
    @include('partials.spending-plans-heading')

    <div class="flex items-center justify-between mb-6">
        <flux:heading>{{ __('Your Plans') }}</flux:heading>
        <flux:button variant="primary" :href="route('spending-plans.create')" wire:navigate>
            {{ __('Create New Plan') }}
        </flux:button>
    </div>

    @if ($this->plans->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 py-16 px-6">
            <flux:icon name="banknotes" class="size-12 text-zinc-400 dark:text-zinc-500 mb-4" />
            <flux:heading size="lg">{{ __('No spending plans yet') }}</flux:heading>
            <flux:subheading class="mb-6">{{ __('Create your first conscious spending plan to start allocating your income.') }}</flux:subheading>
            <flux:button variant="primary" :href="route('spending-plans.create')" wire:navigate>
                {{ __('Create Your First Plan') }}
            </flux:button>
        </div>
    @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->plans as $plan)
                <div class="rounded-xl border {{ $plan->is_current ? 'border-emerald-500 dark:border-emerald-400' : 'border-zinc-200 dark:border-zinc-700' }} p-5 space-y-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                                @if ($plan->is_current)
                                    <flux:badge size="sm" color="emerald">{{ __('Current') }}</flux:badge>
                                @endif
                            </div>
                            <flux:subheading>${{ number_format($plan->monthly_income / 100) }}/mo</flux:subheading>
                        </div>
                        <div class="flex items-center gap-1">
                            @if ($plan->is_current)
                                <flux:button size="sm" variant="ghost" icon="star" class="text-emerald-500" wire:click="unmarkCurrent({{ $plan->id }})" />
                            @else
                                <flux:button size="sm" variant="ghost" icon="star" wire:click="markAsCurrent({{ $plan->id }})" />
                            @endif
                            <flux:button size="sm" variant="ghost" icon="pencil" :href="route('spending-plans.edit', $plan)" wire:navigate />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $plan->id }})" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        @foreach (SpendingCategory::cases() as $category)
                            @php
                                $percent = $plan->categoryPercent($category);
                                $total = $plan->categoryTotal($category);
                                [$min, $max] = $category->idealRange();
                                $withinIdeal = $category->isWithinIdeal($percent);
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-600 dark:text-zinc-400">{{ $category->label() }}</span>
                                    <span class="font-medium {{ $percent < 0 ? 'text-red-600 dark:text-red-400' : ($withinIdeal ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                        {{ $percent }}%
                                    </span>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-700 overflow-hidden">
                                    <div class="h-full rounded-full {{ $category->color() }}" style="width: {{ min($percent, 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <flux:button variant="subtle" class="w-full" :href="route('spending-plans.show', $plan)" wire:navigate>
                        {{ __('View Details') }}
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Delete confirmation modal --}}
    @if ($deletingPlanId)
        <flux:modal name="confirm-plan-deletion" :show="true" focusable class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete spending plan?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This will permanently delete this spending plan and all of its items. This action cannot be undone.') }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2">
                    <flux:button variant="filled" wire:click="cancelDelete">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="danger" wire:click="deletePlan">
                        {{ __('Delete Plan') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</section>

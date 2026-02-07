<?php

use App\Models\SpendingPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $monthly_income = '';

    public function createPlan(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'monthly_income' => ['required', 'numeric', 'min:0.01'],
        ]);

        $plan = Auth::user()->spendingPlans()->create([
            'name' => $validated['name'],
            'monthly_income' => (int) round($validated['monthly_income'] * 100),
        ]);

        $this->redirect(route('spending-plans.edit', $plan), navigate: true);
    }
}; ?>

<section class="w-full">
    @include('partials.spending-plans-heading')

    <x-pages::spending-plans.layout :heading="__('Create a New Plan')" :subheading="__('Give your plan a name and set your monthly take-home income.')">
        <form wire:submit="createPlan" class="my-6 w-full max-w-lg space-y-6">
            <flux:input
                wire:model="name"
                :label="__('Plan Name')"
                :placeholder="__('e.g. Current Plan')"
                type="text"
                required
                autofocus
            />

            <flux:input
                wire:model="monthly_income"
                :label="__('Monthly Take-Home Income')"
                :placeholder="__('5000.00')"
                type="number"
                step="0.01"
                min="0.01"
                required
            >
                <x-slot:prefix>$</x-slot:prefix>
            </flux:input>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Create Plan') }}
                </flux:button>
                <flux:link :href="route('spending-plans.dashboard')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:link>
            </div>
        </form>
    </x-pages::spending-plans.layout>
</section>

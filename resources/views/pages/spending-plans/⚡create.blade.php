<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $monthly_income = '';
    public string $gross_monthly_income = '';
    public string $pre_tax_investments = '';
    public string $fixed_costs_misc_percent = '15';
    public bool $includeDefaults = true;

    public function createPlan(): void
    {
        abort_if(SpendingPlan::count() >= SpendingPlan::MAX_PER_USER, 422);

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

        $plan = SpendingPlan::create([
            'name' => $validated['name'],
            'monthly_income' => (int) round($validated['monthly_income'] * 100),
            'gross_monthly_income' => (int) round(((float) $validated['gross_monthly_income']) * 100),
            'pre_tax_investments' => (int) round(((float) $validated['pre_tax_investments']) * 100),
            'fixed_costs_misc_percent' => (int) $validated['fixed_costs_misc_percent'],
        ]);

        if ($this->includeDefaults) {
            $sortOrder = 0;

            foreach (SpendingPlan::DEFAULT_ITEMS as $categoryValue => $items) {
                foreach ($items as $itemName) {
                    $plan->items()->create([
                        'category' => SpendingCategory::from($categoryValue),
                        'name' => $itemName,
                        'amount' => 0,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }
        }

        $plan->markCurrentIfOnly();

        $this->redirect(route('spending-plans.edit', $plan), navigate: true);
    }
}; ?>

<section class="w-full px-10 py-9 max-w-[760px] mx-auto">
    <div class="mb-6">
        <a href="{{ route('spending-plans.dashboard') }}" wire:navigate class="text-vault-textsub hover:text-vault-text transition-colors" style="font-size: 12px;">
            ← {{ __('Back to plans') }}
        </a>
    </div>

    <x-page-heading
        eyebrow="New Plan"
        title="Start a fresh plan"
        subtitle="Set your take-home and we'll do the math"
    />

    <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 28px 32px;">
        <form wire:submit="createPlan" class="flex flex-col gap-5">
            <flux:input
                wire:model="name"
                :label="__('Plan name')"
                :placeholder="__('e.g. Current Plan')"
                type="text"
                required
                autofocus
            />

            <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
                <flux:input
                    wire:model="monthly_income"
                    :label="__('Monthly take-home')"
                    :placeholder="__('5,000.00')"
                    type="text"
                    inputmode="decimal"
                    required
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>

                <flux:input
                    wire:model="gross_monthly_income"
                    :label="__('Gross monthly income')"
                    :description="__('Total income before taxes and deductions.')"
                    :placeholder="__('7,000.00')"
                    type="text"
                    inputmode="decimal"
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>

                <flux:input
                    wire:model="pre_tax_investments"
                    :label="__('Pre-tax investments')"
                    :description="__('401(k), HSA, and other pre-tax contributions.')"
                    :placeholder="__('500.00')"
                    type="text"
                    inputmode="decimal"
                >
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>

                <flux:input
                    wire:model="fixed_costs_misc_percent"
                    :label="__('Fixed costs buffer')"
                    :description="__('Percentage added for unexpected expenses.')"
                    type="number"
                    step="1"
                    min="0"
                    max="30"
                    required
                >
                    <x-slot:suffix>%</x-slot:suffix>
                </flux:input>
            </div>

            <div class="border-t border-vault-card-bd pt-5">
                <flux:field variant="inline">
                    <flux:checkbox wire:model="includeDefaults" />
                    <flux:label>{{ __('Include common expense line items') }}</flux:label>
                </flux:field>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <flux:button variant="primary" type="submit">
                    {{ __('Create plan') }}
                </flux:button>
                <flux:button variant="ghost" :href="route('spending-plans.dashboard')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>

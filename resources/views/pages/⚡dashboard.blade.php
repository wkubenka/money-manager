<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\NetWorthAccount;
use App\Models\Profile;
use App\Models\RichLifeVision;
use App\Models\RichLifeVisionCategory;
use App\Models\SpendingPlan;
use App\Models\WindfallPlan;
use App\Services\DebtPayoffCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $visionEditing = false;
    public string $newVisionText = '';
    public ?int $addVisionToCategoryId = null;
    public ?int $editingVisionId = null;
    public string $editingVisionText = '';

    public string $newCategoryName = '';
    public ?int $editingCategoryId = null;
    public string $editingCategoryName = '';

    public ?string $dateOfBirth = null;
    public ?int $retirementAge = null;
    public ?float $expectedReturn = null;
    public ?float $withdrawalRate = null;
    public bool $retirementEditing = false;

    public int $emergencyFundMonths = 6;

    public bool $windfallEditing = false;
    public int $windfallSavings = 0;
    public int $windfallInvestments = 0;
    public int $windfallGuiltFree = 0;
    public int $windfallDebt = 0;

    public ?int $confirmingDeleteCategoryId = null;
    public ?int $confirmingDeleteVisionId = null;

    public function mount(): void
    {
        $profile = Profile::instance();
        $this->dateOfBirth = $profile->date_of_birth?->format('Y-m-d');
        $this->retirementAge = $profile->retirement_age;
        $this->expectedReturn = (float) $profile->expected_return;
        $this->withdrawalRate = (float) $profile->withdrawal_rate;
        $this->emergencyFundMonths = $profile->emergency_fund_months ?? 6;

        $windfall = WindfallPlan::instance();
        $this->windfallSavings = $windfall->savings_percent;
        $this->windfallInvestments = $windfall->investments_percent;
        $this->windfallGuiltFree = $windfall->guilt_free_percent;
        $this->windfallDebt = $windfall->debt_percent;
    }

    #[Computed]
    public function visionCategories()
    {
        return RichLifeVisionCategory::query()
            ->with('visions')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function uncategorizedVisions()
    {
        return RichLifeVision::query()
            ->whereNull('rich_life_vision_category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function addCategory(): void
    {
        $this->validate([
            'newCategoryName' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = RichLifeVisionCategory::query()->max('sort_order') + 1;

        RichLifeVisionCategory::create([
            'name' => $this->newCategoryName,
            'sort_order' => $nextOrder,
        ]);

        $this->newCategoryName = '';
        unset($this->visionCategories);
    }

    public function editCategory(int $categoryId): void
    {
        $category = RichLifeVisionCategory::findOrFail($categoryId);

        $this->editingCategoryId = $categoryId;
        $this->editingCategoryName = $category->name;
    }

    public function updateCategory(): void
    {
        $this->validate([
            'editingCategoryName' => ['required', 'string', 'max:255'],
        ]);

        $category = RichLifeVisionCategory::findOrFail($this->editingCategoryId);
        $category->update(['name' => $this->editingCategoryName]);

        $this->cancelEditCategory();
        unset($this->visionCategories);
    }

    public function cancelEditCategory(): void
    {
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
    }

    public function confirmRemoveCategory(int $categoryId): void
    {
        $this->confirmingDeleteCategoryId = $categoryId;
    }

    public function cancelRemoveCategory(): void
    {
        $this->confirmingDeleteCategoryId = null;
    }

    public function removeCategory(int $categoryId): void
    {
        $category = RichLifeVisionCategory::findOrFail($categoryId);

        $category->delete();
        $this->confirmingDeleteCategoryId = null;
        unset($this->visionCategories, $this->uncategorizedVisions);
    }

    public function reorderCategories(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            RichLifeVisionCategory::where('id', $id)->update(['sort_order' => $index]);
        }

        unset($this->visionCategories);
    }

    public function addVision(?int $categoryId = null): void
    {
        $this->validate([
            'newVisionText' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = RichLifeVision::query()
            ->where('rich_life_vision_category_id', $categoryId)
            ->max('sort_order') + 1;

        RichLifeVision::create([
            'rich_life_vision_category_id' => $categoryId,
            'text' => $this->newVisionText,
            'sort_order' => $nextOrder,
        ]);

        $this->newVisionText = '';
        $this->addVisionToCategoryId = null;
        unset($this->visionCategories, $this->uncategorizedVisions);
    }

    public function editVision(int $visionId): void
    {
        $vision = RichLifeVision::findOrFail($visionId);

        $this->editingVisionId = $visionId;
        $this->editingVisionText = $vision->text;
    }

    public function updateVision(): void
    {
        $this->validate([
            'editingVisionText' => ['required', 'string', 'max:255'],
        ]);

        $vision = RichLifeVision::findOrFail($this->editingVisionId);

        $vision->update(['text' => $this->editingVisionText]);

        $this->cancelEditVision();
        unset($this->visionCategories, $this->uncategorizedVisions);
    }

    public function cancelEditVision(): void
    {
        $this->editingVisionId = null;
        $this->editingVisionText = '';
    }

    public function confirmRemoveVision(int $visionId): void
    {
        $this->confirmingDeleteVisionId = $visionId;
    }

    public function cancelRemoveVision(): void
    {
        $this->confirmingDeleteVisionId = null;
    }

    public function removeVision(int $visionId): void
    {
        $vision = RichLifeVision::findOrFail($visionId);

        $vision->delete();
        $this->confirmingDeleteVisionId = null;
        unset($this->visionCategories, $this->uncategorizedVisions);
    }

    public function reorderVisions(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            RichLifeVision::where('id', $id)->update(['sort_order' => $index]);
        }

        unset($this->visionCategories, $this->uncategorizedVisions);
    }

    #[Computed]
    public function accounts()
    {
        return NetWorthAccount::query()->get();
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
        return SpendingPlan::where('is_current', true)->first()?->load('items');
    }

    #[Computed]
    public function emergencyFund(): ?NetWorthAccount
    {
        return $this->accounts->firstWhere('is_emergency_fund', true);
    }

    #[Computed]
    public function monthlyExpenseTotals(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return Expense::query()
            ->whereNotNull('category')
            ->whereBetween('date', [$start, $end])
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($total) => (int) $total)
            ->toArray();
    }

    #[Computed]
    public function uncategorizedExpenseCount(): int
    {
        return Expense::query()->whereNull('category')->count();
    }

    #[Computed]
    public function debtPayoff(): ?array
    {
        $debtAccounts = $this->accounts
            ->where('category', AccountCategory::Debt)
            ->where('balance', '>', 0)
            ->filter(fn ($account) => $account->minimum_payment !== null && $account->interest_rate !== null);

        if ($debtAccounts->isEmpty()) {
            return null;
        }

        $plan = $this->currentPlan;

        $debtPaymentItem = $plan?->items
            ->where('category', SpendingCategory::FixedCosts)
            ->firstWhere('name', 'Debt Payments');

        $totalMonthlyPayment = $debtPaymentItem?->amount ?? 0;

        if ($totalMonthlyPayment <= 0) {
            return ['needs_plan_item' => true];
        }

        $debts = $debtAccounts->map(fn ($account) => [
            'balance' => $account->balance,
            'interest_rate' => (float) $account->interest_rate,
            'minimum_payment' => $account->minimum_payment,
        ]);

        $result = app(DebtPayoffCalculator::class)->calculate($debts, $totalMonthlyPayment);

        if ($result === null) {
            return null;
        }

        $result['total_debt'] = (int) $debtAccounts->sum('balance');
        $result['monthly_payment'] = $totalMonthlyPayment;

        return $result;
    }

    public function updatedEmergencyFundMonths(): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['emergencyFundMonths' => $this->emergencyFundMonths],
            ['emergencyFundMonths' => ['required', 'integer', 'min:3', 'max:24']],
        );

        if ($validator->fails()) {
            $this->emergencyFundMonths = Profile::instance()->emergency_fund_months ?? 6;

            return;
        }

        Profile::instance()->update(['emergency_fund_months' => $this->emergencyFundMonths]);
    }

    public function saveRetirementSettings(): void
    {
        $this->validate([
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'retirementAge' => ['nullable', 'integer', 'min:1', 'max:120'],
            'expectedReturn' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'withdrawalRate' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        Profile::instance()->update([
            'date_of_birth' => $this->dateOfBirth,
            'retirement_age' => $this->retirementAge,
            'expected_return' => $this->expectedReturn,
            'withdrawal_rate' => $this->withdrawalRate,
        ]);
    }

    public function saveWindfallPlan(): void
    {
        $this->validate([
            'windfallSavings' => ['required', 'integer', 'min:0', 'max:100'],
            'windfallInvestments' => ['required', 'integer', 'min:0', 'max:100'],
            'windfallGuiltFree' => ['required', 'integer', 'min:0', 'max:100'],
            'windfallDebt' => ['required', 'integer', 'min:0', 'max:100'],
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
            'savings_percent' => $this->windfallSavings,
            'investments_percent' => $this->windfallInvestments,
            'guilt_free_percent' => $this->windfallGuiltFree,
            'debt_percent' => $this->windfallDebt,
        ]);

        $this->windfallEditing = false;
        $this->dispatch('windfall-saved');
    }

    public function cancelWindfall(): void
    {
        $plan = WindfallPlan::instance();
        $this->windfallSavings = $plan->savings_percent;
        $this->windfallInvestments = $plan->investments_percent;
        $this->windfallGuiltFree = $plan->guilt_free_percent;
        $this->windfallDebt = $plan->debt_percent;
        $this->windfallEditing = false;
    }
}; ?>

<div class="w-full bg-vault-bg text-vault-text">
    @php
        $netWorth = $this->netWorthSummary['net_worth'];
        $cats = $this->netWorthSummary['categories'];
        $sumAbs = abs($cats['assets']) + abs($cats['investments']) + abs($cats['savings']) + abs($cats['debt']);
        $plan = $this->currentPlan;
    @endphp

    {{-- Hero: Net Worth --}}
    <div class="px-10 pt-8 pb-7 border-b border-vault-card-bd"
         style="background: linear-gradient(160deg, var(--color-vault-card) 0%, var(--color-vault-bg) 80%);">
        <div class="flex items-center justify-between mb-2.5">
            <div class="eyebrow" style="letter-spacing: 0.16em;">{{ __('Your Net Worth') }}</div>
            <a href="{{ route('net-worth.index') }}" wire:navigate
               class="text-[11px] text-vault-muted hover:text-vault-accent transition-colors">{{ __('Manage accounts →') }}</a>
        </div>
        <div class="flex items-end gap-5 mb-5">
            <div class="font-display text-[52px] leading-none {{ $netWorth < 0 ? 'text-vault-red' : 'text-vault-text' }}">
                {{ $netWorth < 0 ? '-' : '' }}${{ format_cents(abs($netWorth)) }}
            </div>
        </div>
        @if ($sumAbs > 0)
            <div class="flex h-[6px] rounded-[3px] overflow-hidden gap-px mb-3.5">
                @foreach (\App\Enums\AccountCategory::cases() as $c)
                    @php $val = abs($cats[$c->value]); @endphp
                    @if ($val > 0)
                        <div style="width: {{ ($val / $sumAbs) * 100 }}%; background: {{ $c->vaultColor() }}; opacity: 0.8;"></div>
                    @endif
                @endforeach
            </div>
        @endif
        <div class="flex flex-wrap gap-x-5 gap-y-1.5">
            @foreach (\App\Enums\AccountCategory::cases() as $c)
                <div class="flex items-center gap-1.5">
                    <div class="size-1.5 rounded-full flex-shrink-0" style="background: {{ $c->vaultColor() }};"></div>
                    <span class="text-[11px] text-vault-muted">{{ __($c->label()) }}</span>
                    <span class="text-[11px] {{ $c === \App\Enums\AccountCategory::Debt ? 'text-vault-red' : 'text-vault-text' }}">${{ format_cents($cats[$c->value]) }}</span>
                </div>
            @endforeach
            @if ($this->uncategorizedExpenseCount > 0)
                <a href="{{ route('expenses.index') }}" wire:navigate
                   class="ml-auto flex items-center gap-1.5 text-[11px] text-vault-warm hover:text-vault-accent">
                    <span class="size-1.5 rounded-full bg-vault-warm"></span>
                    {{ trans_choice(':count expense needs categorizing|:count expenses need categorizing', $this->uncategorizedExpenseCount, ['count' => $this->uncategorizedExpenseCount]) }} →
                </a>
            @endif
        </div>
    </div>

    {{-- Body grid --}}
    <div class="px-10 py-6 pb-10 grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
        {{-- LEFT COLUMN --}}
        <div class="flex flex-col gap-5">
            {{-- Spending Plan --}}
            @if ($plan)
                @php
                    $gfPercent = $plan->categoryPercent(\App\Enums\SpendingCategory::GuiltFree);
                    $gfHealthy = \App\Enums\SpendingCategory::GuiltFree->isWithinIdeal($gfPercent);
                @endphp
                <div class="rounded-xl border border-vault-card-bd bg-vault-card px-[26px] py-[22px]">
                    <div class="flex justify-between items-start mb-5">
                        <div>
                            <div class="eyebrow mb-3">{{ __('Spending Plan — :month', ['month' => now()->format('F')]) }}</div>
                            <div class="font-display text-[20px] text-vault-text">{{ $plan->name }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-display text-[22px] italic text-vault-text">${{ format_cents($plan->monthly_income) }}<span class="text-[12px] not-italic font-sans text-vault-muted">/mo</span></div>
                            <a href="{{ route('spending-plans.edit', $plan) }}" wire:navigate
                               class="inline-block mt-1.5 px-3 py-1 rounded-lg border border-vault-card-bd text-[11px] font-semibold text-vault-textsub hover:bg-vault-card-hov transition-colors">
                                {{ __('Edit plan') }}
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        @foreach (\App\Enums\SpendingCategory::spendingCases() as $category)
                            @php
                                $total = $plan->categoryTotal($category);
                                $planPercent = $plan->categoryPercent($category);
                                $withinIdeal = $category->isWithinIdeal($planPercent, $gfHealthy);
                                $actualSpent = $this->monthlyExpenseTotals[$category->value] ?? 0;
                                $remaining = $total - $actualSpent;
                                $over = $total > 0 && $actualSpent > $total;
                                $spentPercent = $total > 0 ? min(($actualSpent / $total) * 100, 100) : 0;
                                $color = $category->vaultColor();
                            @endphp
                            <div>
                                <div class="flex justify-between items-center mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="size-[7px] rounded-full" style="background: {{ $color }};"></div>
                                        <span class="text-[13px] text-vault-text">{{ __($category->label()) }}</span>
                                        <span class="text-[10px] font-semibold px-[7px] py-[2px] rounded-full"
                                            style="background: {{ $over || $planPercent < 0 ? 'color-mix(in srgb, var(--color-vault-red) 15%, transparent)' : 'color-mix(in srgb, var(--color-vault-textsub) 15%, transparent)' }};
                                                   color: {{ $over || $planPercent < 0 ? 'var(--color-vault-red)' : 'var(--color-vault-textsub)' }};
                                                   letter-spacing: 0.03em;">
                                            {{ round($planPercent) }}%
                                        </span>
                                    </div>
                                    <div class="flex gap-3 items-center">
                                        @if ($total > 0)
                                            <span class="text-[11px] text-vault-muted">${{ format_cents($actualSpent) }} / ${{ format_cents($total) }}</span>
                                            <span class="text-[11px]" style="color: {{ $over ? 'var(--color-vault-red)' : 'var(--color-vault-accent)' }};">
                                                {{ $over ? '−' : '' }}${{ format_cents(abs($remaining)) }} {{ $over ? __('over') : __('left') }}
                                            </span>
                                        @else
                                            <span class="text-[11px] {{ $total < 0 ? 'text-vault-red' : 'text-vault-text' }}">
                                                {{ $total < 0 ? '−' : '' }}${{ format_cents(abs($total)) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="h-[5px] rounded-[2.5px] bg-vault-card-bd overflow-hidden">
                                    <div class="h-full rounded-[2.5px] transition-[width] duration-500"
                                         style="width: {{ $total > 0 ? $spentPercent : min(max($planPercent, 0), 100) }}%;
                                                background: {{ $over ? 'var(--color-vault-red)' : $color }};
                                                opacity: 0.85;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-vault-card-bd p-7 text-center">
                    <div class="eyebrow mb-3">{{ __('Spending Plan') }}</div>
                    @if (\App\Models\SpendingPlan::exists())
                        <div class="font-display text-[18px] text-vault-textsub mb-3">{{ __('No current spending plan') }}</div>
                        <a href="{{ route('spending-plans.dashboard') }}" wire:navigate
                           class="inline-block px-4 py-2 rounded-lg border border-vault-card-bd text-[12px] font-semibold text-vault-textsub hover:bg-vault-card-hov transition-colors">
                            {{ __('Choose a Plan') }}
                        </a>
                    @else
                        <div class="font-display text-[18px] text-vault-textsub mb-3">{{ __('Create your spending plan') }}</div>
                        <a href="{{ route('spending-plans.create') }}" wire:navigate
                           class="inline-block px-4 py-2 rounded-lg bg-vault-accent text-vault-bg text-[12px] font-semibold hover:bg-vault-accent-hov transition-colors">
                            {{ __('Get Started') }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Rich Life Vision --}}
            <div class="rounded-xl border border-vault-card-bd bg-vault-card px-[26px] py-[22px]">
                <div class="flex items-start justify-between mb-1">
                    <div class="eyebrow">{{ __('Rich Life Vision') }}</div>
                    <flux:button
                        size="xs"
                        variant="ghost"
                        :icon="$visionEditing ? 'lock-open' : 'lock-closed'"
                        wire:click="$toggle('visionEditing')"
                        aria-label="{{ $visionEditing ? __('Lock list') : __('Unlock list') }}"
                        class="!text-vault-muted hover:!text-vault-textsub"
                    />
                </div>
                <div class="font-serif italic text-[14px] font-light text-vault-textsub mb-[18px]">
                    {{ __("What you're building toward") }}
                </div>

                @if (! $visionEditing)
                    {{-- Read mode: 2-column Vault style --}}
                    @if ($this->visionCategories->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                            @foreach ($this->visionCategories as $cat)
                                <div>
                                    <div class="text-[10px] font-semibold tracking-[0.1em] mb-2.5 uppercase" style="color: var(--color-vault-accent);">{{ $cat->name }}</div>
                                    @foreach ($cat->visions as $vision)
                                        <div class="flex gap-2 mb-1.5 items-start">
                                            <span class="text-[12px] mt-px flex-shrink-0" style="color: var(--color-vault-card-bd);">—</span>
                                            <span class="text-[12px] text-vault-textsub leading-[1.5]">{{ $vision->text }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($this->uncategorizedVisions->isNotEmpty())
                        <div class="{{ $this->visionCategories->isNotEmpty() ? 'mt-5' : '' }}">
                            @if ($this->visionCategories->isNotEmpty())
                                <div class="text-[10px] font-semibold tracking-[0.14em] uppercase text-vault-muted mb-2.5">{{ __('Uncategorized') }}</div>
                            @endif
                            @foreach ($this->uncategorizedVisions as $vision)
                                <div class="flex gap-2 mb-1.5 items-start">
                                    <span class="text-[12px] mt-px flex-shrink-0" style="color: var(--color-vault-card-bd);">—</span>
                                    <span class="text-[12px] text-vault-textsub leading-[1.5]">{{ $vision->text }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($this->visionCategories->isEmpty() && $this->uncategorizedVisions->isEmpty())
                        <div class="text-[12px] text-vault-muted italic">{{ __('Unlock to add categories and visions.') }}</div>
                    @endif
                @else
                    {{-- Edit mode: full editing UI --}}
                    <div class="space-y-3" data-sortable-categories>
                        @foreach ($this->visionCategories as $cat)
                            <div class="category-item" data-category-id="{{ $cat->id }}" wire:key="category-{{ $cat->id }}">
                                <div class="flex items-center gap-2 mb-1.5">
                                    @if ($editingCategoryId === $cat->id)
                                        <div class="flex-1 flex items-center gap-2">
                                            <flux:input wire:model="editingCategoryName" size="sm" wire:keydown.enter="updateCategory" />
                                            <flux:button size="xs" variant="primary" wire:click="updateCategory">{{ __('Save') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" wire:click="cancelEditCategory">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    @else
                                        <div class="category-drag-handle cursor-grab active:cursor-grabbing text-vault-muted hover:text-vault-textsub touch-none">
                                            <flux:icon.bars-3 variant="micro" />
                                        </div>
                                        <span class="flex-1 text-[10px] font-semibold uppercase tracking-[0.1em]" style="color: var(--color-vault-accent);">{{ $cat->name }}</span>
                                        <div class="flex items-center gap-0.5">
                                            <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editCategory({{ $cat->id }})" aria-label="{{ __('Edit category') }}" />
                                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmRemoveCategory({{ $cat->id }})" aria-label="{{ __('Remove category') }}" />
                                        </div>
                                    @endif
                                </div>
                                <ul class="space-y-1" data-sortable-visions data-category-id="{{ $cat->id }}">
                                    @foreach ($cat->visions as $vision)
                                        <li class="flex items-center gap-2 group" data-vision-id="{{ $vision->id }}" wire:key="vision-{{ $vision->id }}">
                                            @if ($editingVisionId === $vision->id)
                                                <div class="flex-1 flex items-center gap-2">
                                                    <flux:input wire:model="editingVisionText" size="sm" wire:keydown.enter="updateVision" />
                                                    <flux:button size="xs" variant="primary" wire:click="updateVision">{{ __('Save') }}</flux:button>
                                                    <flux:button size="xs" variant="ghost" wire:click="cancelEditVision">{{ __('Cancel') }}</flux:button>
                                                </div>
                                            @else
                                                <div class="drag-handle cursor-grab active:cursor-grabbing text-vault-muted hover:text-vault-textsub touch-none">
                                                    <flux:icon.bars-3 variant="micro" />
                                                </div>
                                                <span class="flex-1 text-[12px] text-vault-textsub">{{ $vision->text }}</span>
                                                <div class="flex items-center gap-0.5">
                                                    <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editVision({{ $vision->id }})" aria-label="{{ __('Edit vision') }}" />
                                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmRemoveVision({{ $vision->id }})" aria-label="{{ __('Remove vision') }}" />
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($addVisionToCategoryId === $cat->id)
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <flux:input class="flex-1" wire:model="newVisionText" size="sm" :placeholder="__('Add a vision...')" wire:keydown.enter="addVision({{ $cat->id }})" wire:keydown.escape="\$set('addVisionToCategoryId', null)" />
                                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="addVision({{ $cat->id }})" aria-label="{{ __('Add vision') }}" />
                                    </div>
                                @else
                                    <button wire:click="$set('addVisionToCategoryId', {{ $cat->id }})" class="mt-1 w-full text-left text-[12px] text-vault-muted hover:text-vault-textsub py-1">+ {{ __('Add a vision...') }}</button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Uncategorized visions --}}
                    @if ($this->uncategorizedVisions->isNotEmpty() || $visionEditing)
                        <div class="{{ $this->visionCategories->isNotEmpty() ? 'mt-3' : '' }}">
                            @if ($this->uncategorizedVisions->isNotEmpty() && $this->visionCategories->isNotEmpty())
                                <div class="text-[10px] font-semibold uppercase tracking-[0.14em] text-vault-muted mb-1.5">{{ __('Uncategorized') }}</div>
                            @endif
                            <ul class="space-y-1" data-sortable-visions data-category-id="uncategorized">
                                @foreach ($this->uncategorizedVisions as $vision)
                                    <li class="flex items-center gap-2 group" data-vision-id="{{ $vision->id }}" wire:key="vision-{{ $vision->id }}">
                                        @if ($editingVisionId === $vision->id)
                                            <div class="flex-1 flex items-center gap-2">
                                                <flux:input wire:model="editingVisionText" size="sm" wire:keydown.enter="updateVision" />
                                                <flux:button size="xs" variant="primary" wire:click="updateVision">{{ __('Save') }}</flux:button>
                                                <flux:button size="xs" variant="ghost" wire:click="cancelEditVision">{{ __('Cancel') }}</flux:button>
                                            </div>
                                        @else
                                            <div class="drag-handle cursor-grab active:cursor-grabbing text-vault-muted hover:text-vault-textsub touch-none">
                                                <flux:icon.bars-3 variant="micro" />
                                            </div>
                                            <span class="flex-1 text-[12px] text-vault-textsub">{{ $vision->text }}</span>
                                            <div class="flex items-center gap-0.5">
                                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editVision({{ $vision->id }})" aria-label="{{ __('Edit vision') }}" />
                                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmRemoveVision({{ $vision->id }})" aria-label="{{ __('Remove vision') }}" />
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($addVisionToCategoryId === 0)
                                <div class="flex items-center gap-2 mt-1.5">
                                    <flux:input class="flex-1" wire:model="newVisionText" size="sm" :placeholder="__('Add a vision...')" wire:keydown.enter="addVision(null)" wire:keydown.escape="\$set('addVisionToCategoryId', null)" />
                                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addVision(null)" aria-label="{{ __('Add vision') }}" />
                                </div>
                            @else
                                <button wire:click="$set('addVisionToCategoryId', 0)" class="mt-1 w-full text-left text-[12px] text-vault-muted hover:text-vault-textsub py-1">+ {{ __('Add a vision...') }}</button>
                            @endif
                        </div>
                    @endif

                    {{-- Add category input --}}
                    <div class="flex items-center gap-2 mt-4 pt-3 border-t border-vault-card-bd">
                        <flux:input class="flex-1" wire:model="newCategoryName" size="sm" :placeholder="__('Add a category...')" wire:keydown.enter="addCategory" />
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="addCategory" aria-label="{{ __('Add category') }}" />
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="flex flex-col gap-5 self-start">
            {{-- Critical Numbers --}}
            <div class="rounded-xl border border-vault-card-bd bg-vault-card px-[26px] py-[22px]">
                <div class="eyebrow">{{ __('Critical Numbers') }}</div>
                <div class="flex flex-col">
                    {{-- Emergency Fund --}}
                    @php
                        $ef = $this->emergencyFund;
                        $efBal = $ef?->balance ?? 0;
                        $monthlyFixedCosts = $plan ? $plan->categoryTotal(\App\Enums\SpendingCategory::FixedCosts) : 0;
                        $monthsRunway = $monthlyFixedCosts > 0 ? $efBal / $monthlyFixedCosts : null;
                        $efMonths = $this->emergencyFundMonths;
                    @endphp
                    <div class="py-3.5">
                        <div class="flex justify-between items-baseline mb-1.5">
                            <div class="flex items-center gap-2">
                                <div class="size-1.5 rounded-full bg-vault-accent"></div>
                                <span class="text-[11px] tracking-[0.1em] text-vault-muted uppercase">{{ __('Emergency Fund') }}</span>
                            </div>
                            @if ($monthsRunway !== null)
                                <span class="text-[11px] {{ $monthsRunway < ($efMonths / 2) ? 'text-vault-red' : 'text-vault-accent' }}">
                                    {{ number_format($monthsRunway, 1) }} months fixed costs
                                </span>
                            @endif
                        </div>
                        <div class="flex justify-between items-baseline mb-2">
                            <span class="font-display text-[24px] text-vault-text">${{ format_cents($efBal) }}</span>
                            <span class="text-[10px] text-vault-muted" x-data="{ editing: false }">
                                {{ __('Goal:') }}
                                <span x-show="!editing" @click="editing = true; $nextTick(() => $refs.efMonthsInput.select())"
                                      class="cursor-pointer hover:text-vault-textsub border-b border-dotted border-vault-card-bd"
                                      role="button" tabindex="0" @keydown.enter.prevent="editing = true; $nextTick(() => $refs.efMonthsInput.select())"
                                      aria-label="{{ __('Edit emergency fund target in months') }}">{{ $efMonths }}</span>
                                <input x-show="editing" x-cloak x-ref="efMonthsInput" type="number" min="3" max="24"
                                       wire:model.live="emergencyFundMonths"
                                       @blur="editing = false" @keydown.enter.prevent="$el.blur()" @keydown.escape="editing = false"
                                       class="w-10 bg-transparent border-b border-vault-card-bd text-vault-text text-[10px] focus:outline-none focus:border-vault-accent" />
                                {{ __('months') }}
                            </span>
                        </div>
                        @if ($monthlyFixedCosts > 0)
                            <div class="h-[4px] rounded-[2px] bg-vault-card-bd overflow-hidden">
                                <div class="h-full bg-vault-accent rounded-[2px]"
                                     style="width: {{ min(100, ($efBal / ($monthlyFixedCosts * $efMonths)) * 100) }}%; opacity: 0.85;"></div>
                            </div>
                        @endif
                    </div>

                    {{-- Debt Free --}}
                    @if ($this->debtPayoff && ! ($this->debtPayoff['needs_plan_item'] ?? false))
                        <div class="h-px bg-vault-card-bd"></div>
                        <a href="{{ route('debt-payoff.index') }}" wire:navigate class="block py-3.5 hover:bg-vault-card-hov -mx-2 px-2 rounded-md transition-colors">
                            <div class="flex justify-between items-baseline mb-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="size-1.5 rounded-full bg-vault-warm"></div>
                                    <span class="text-[11px] tracking-[0.1em] text-vault-muted uppercase">{{ __('Debt Free') }}</span>
                                </div>
                                <span class="text-[11px] text-vault-red">${{ format_cents($this->debtPayoff['total_debt']) }} {{ __('remaining') }}</span>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <span class="font-display text-[24px] text-vault-warm">{{ $this->debtPayoff['payoff_date']->format('M Y') }}</span>
                                <span class="text-[10px] text-vault-muted">{{ __('on current plan') }}</span>
                            </div>
                        </a>
                    @elseif ($this->debtPayoff && ($this->debtPayoff['needs_plan_item'] ?? false))
                        <div class="h-px bg-vault-card-bd"></div>
                        <a href="{{ $plan ? route('spending-plans.edit', $plan) : route('spending-plans.dashboard') }}" wire:navigate class="block py-3.5 hover:bg-vault-card-hov -mx-2 px-2 rounded-md transition-colors">
                            <div class="flex items-center gap-2 mb-1.5">
                                <div class="size-1.5 rounded-full bg-vault-warm"></div>
                                <span class="text-[11px] tracking-[0.1em] text-vault-muted uppercase">{{ __('Debt Free') }}</span>
                            </div>
                            <div class="text-[12px] text-vault-muted">{{ __('Add a "Debt Payments" item to your spending plan to see your payoff date.') }}</div>
                        </a>
                    @endif

                    {{-- Retirement --}}
                    @php
                        $investmentBalance = $cats['investments'] ?? 0;
                        $monthlyContribution = $plan
                            ? $plan->categoryTotal(\App\Enums\SpendingCategory::Investments) + ($plan->pre_tax_investments ?? 0)
                            : 0;
                        $currentAge = $dateOfBirth ? \Carbon\Carbon::parse($dateOfBirth)->age : null;
                        $canProject = $currentAge && $retirementAge && $retirementAge > $currentAge;
                        $projectedCents = null;
                        if ($canProject) {
                            $monthsToRetirement = ($retirementAge - $currentAge) * 12;
                            $monthlyRate = pow(1 + $expectedReturn / 100, 1 / 12) - 1;
                            if ($monthlyRate > 0) {
                                $growthFactor = pow(1 + $monthlyRate, $monthsToRetirement);
                                $projectedCents = (int) round(
                                    ($investmentBalance * $growthFactor) + ($monthlyContribution * ($growthFactor - 1) / $monthlyRate)
                                );
                            } else {
                                $projectedCents = $investmentBalance + ($monthlyContribution * $monthsToRetirement);
                            }
                        }
                        $monthlyWithdrawal = $projectedCents && $withdrawalRate ? (int) round($projectedCents * ($withdrawalRate / 100) / 12) : null;
                    @endphp
                    <div class="h-px bg-vault-card-bd"></div>
                    @if ($canProject)
                        <a href="{{ route('net-worth.index') }}" wire:navigate class="block py-3.5 hover:bg-vault-card-hov -mx-2 px-2 rounded-md transition-colors">
                            <div class="flex justify-between items-baseline mb-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="size-1.5 rounded-full bg-vault-blue"></div>
                                    <span class="text-[11px] tracking-[0.1em] text-vault-muted uppercase">{{ __('Est. at Retirement') }}</span>
                                </div>
                                @if ($monthlyWithdrawal)
                                    <span class="text-[11px] text-vault-textsub">~${{ format_cents($monthlyWithdrawal) }}/mo {{ __('safe') }}</span>
                                @endif
                            </div>
                            <div class="flex justify-between items-baseline">
                                <span class="font-display text-[24px] text-vault-accent">${{ format_cents($projectedCents) }}</span>
                                <span class="text-[10px] text-vault-muted">{{ __('at age :age', ['age' => $retirementAge]) }}</span>
                            </div>
                        </a>
                    @else
                        <div class="py-3.5">
                            <div class="flex items-center gap-2 mb-1.5">
                                <div class="size-1.5 rounded-full bg-vault-blue"></div>
                                <span class="text-[11px] tracking-[0.1em] text-vault-muted uppercase">{{ __('Est. at Retirement') }}</span>
                            </div>
                            <div class="text-[12px] text-vault-muted">{{ __('Set your birthday and retirement age below.') }}</div>
                        </div>
                    @endif
                </div>

                {{-- Inline retirement editor --}}
                @if ($retirementEditing || ! $dateOfBirth)
                    <div class="mt-4 pt-4 border-t border-vault-card-bd space-y-3">
                        <flux:input type="date" size="sm" wire:model="dateOfBirth" wire:change="saveRetirementSettings" max="{{ now()->format('Y-m-d') }}" label="{{ __('Birthday') }}" />
                        <div class="grid grid-cols-3 gap-2">
                            <flux:input type="number" size="sm" wire:model="retirementAge" wire:change="saveRetirementSettings" min="1" max="120" label="{{ __('Retire Age') }}" />
                            <flux:input type="number" size="sm" wire:model="expectedReturn" wire:change="saveRetirementSettings" min="0" max="30" step="0.1" label="{{ __('Return %') }}" />
                            <flux:input type="number" size="sm" wire:model="withdrawalRate" wire:change="saveRetirementSettings" min="0" max="30" step="0.1" label="{{ __('Withdrawal %') }}" />
                        </div>
                    </div>
                @endif
            </div>

            {{-- Recent Expenses --}}
            <div class="rounded-xl border border-vault-card-bd bg-vault-card px-6 py-5">
                <div class="flex justify-between items-center mb-4">
                    <div class="eyebrow">{{ __('Recent Expenses') }}</div>
                    <a href="{{ route('expenses.index') }}" wire:navigate class="text-[11px] text-vault-accent hover:text-vault-accent-hov">{{ __('All →') }}</a>
                </div>
                @php
                    $recentExpenses = \App\Models\Expense::query()->orderByDesc('date')->orderByDesc('id')->limit(6)->get();
                @endphp
                @if ($recentExpenses->isEmpty())
                    <div class="text-[12px] text-vault-muted">{{ __('No expenses yet.') }}</div>
                @else
                    <div class="flex flex-col">
                        @foreach ($recentExpenses as $i => $e)
                            @php $cat = $e->category; @endphp
                            @if ($i > 0)
                                <div class="h-px bg-vault-card-bd"></div>
                            @endif
                            <div class="flex justify-between items-center py-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="size-7 rounded-md flex items-center justify-center flex-shrink-0"
                                         style="background: {{ $cat ? 'color-mix(in srgb, ' . $cat->vaultColor() . ' 16%, transparent)' : 'var(--color-vault-card-bd)' }};">
                                        <span class="text-[11px] font-semibold"
                                              style="color: {{ $cat?->vaultColor() ?? 'var(--color-vault-textsub)' }};">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($e->merchant, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[12px] text-vault-text truncate">{{ $e->merchant }}</div>
                                        <div class="text-[10px] text-vault-muted">{{ $e->date->format('M j') }}</div>
                                    </div>
                                </div>
                                <span class="text-[12px] font-medium text-vault-text flex-shrink-0 ml-2">${{ format_cents($e->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Delete Category Confirmation --}}
    <flux:modal wire:model.self="confirmingDeleteCategoryId" class="min-w-[22rem]">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Remove category') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Remove this category?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">{{ __('Visions in this category will become uncategorized.') }}</div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelRemoveCategory">{{ __('Cancel') }}</flux:button>
                @if ($confirmingDeleteCategoryId)
                    <flux:button variant="danger" wire:click="removeCategory({{ $confirmingDeleteCategoryId }})">{{ __('Remove') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    {{-- Delete Vision Confirmation --}}
    <flux:modal wire:model.self="confirmingDeleteVisionId" class="min-w-[22rem]">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Remove item') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Remove this item?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">{{ __('This action cannot be undone.') }}</div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelRemoveVision">{{ __('Cancel') }}</flux:button>
                @if ($confirmingDeleteVisionId)
                    <flux:button variant="danger" wire:click="removeVision({{ $confirmingDeleteVisionId }})">{{ __('Remove') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>

@assets
<script src="/vendor/sortable.min.js"></script>
@endassets

@script
<script>
    function initVisionSortables() {
        // Category sortable
        const catContainer = $wire.$el.querySelector('[data-sortable-categories]');
        if (catContainer && !catContainer._sortable) {
            catContainer._sortable = Sortable.create(catContainer, {
                handle: '.category-drag-handle',
                animation: 150,
                ghostClass: 'opacity-30',
                onEnd() {
                    $wire.reorderCategories(
                        Array.from(catContainer.children)
                            .filter(child => child.dataset.categoryId)
                            .map(child => child.dataset.categoryId)
                    );
                }
            });
        }

        // Per-list vision sortables
        $wire.$el.querySelectorAll('[data-sortable-visions]').forEach(el => {
            if (el._sortable) return;
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
        });
    }

    initVisionSortables();

    new MutationObserver(() => initVisionSortables())
        .observe($wire.$el, { childList: true, subtree: true });
</script>
@endscript

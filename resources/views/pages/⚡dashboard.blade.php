<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\NetWorthAccount;
use App\Models\Profile;
use App\Models\RichLifeVision;
use App\Models\RichLifeVisionCategory;
use App\Models\SpendingPlan;
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

    public function mount(): void
    {
        $profile = Profile::instance();
        $this->dateOfBirth = $profile->date_of_birth?->format('Y-m-d');
        $this->retirementAge = $profile->retirement_age;
        $this->expectedReturn = (float) $profile->expected_return;
        $this->withdrawalRate = (float) $profile->withdrawal_rate;
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

    public function removeCategory(int $categoryId): void
    {
        $category = RichLifeVisionCategory::findOrFail($categoryId);

        $category->delete();
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

    public function removeVision(int $visionId): void
    {
        $vision = RichLifeVision::findOrFail($visionId);

        $vision->delete();
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
}; ?>

<div class="w-full space-y-6">
    @if ($this->uncategorizedExpenseCount > 0)
        <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 dark:border-amber-800 dark:bg-amber-950">
            <flux:text class="text-sm text-amber-700 dark:text-amber-300">
                {{ trans_choice(':count expense needs categorizing|:count expenses need categorizing', $this->uncategorizedExpenseCount, ['count' => $this->uncategorizedExpenseCount]) }}
            </flux:text>
            <a
                href="{{ route('expenses.index') }}"
                wire:navigate
                class="text-sm font-medium text-amber-700 underline hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100"
            >{{ __('Review') }}</a>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
    {{-- Left column: block on desktop, flattened on mobile via contents --}}
    <div class="contents lg:block lg:space-y-6">
        {{-- Rich Life Vision --}}
        <div class="order-0 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-2">
                <flux:heading>{{ __('Rich Life Vision') }}</flux:heading>
                <flux:button
                    size="sm"
                    variant="subtle"
                    :icon="$visionEditing ? 'lock-open' : 'lock-closed'"
                    wire:click="$toggle('visionEditing')"
                    aria-label="{{ $visionEditing ? __('Lock list') : __('Unlock list') }}"
                />
            </div>

            <div class="space-y-2" data-sortable-categories>
                @foreach ($this->visionCategories as $cat)
                    <div class="category-item" data-category-id="{{ $cat->id }}" wire:key="category-{{ $cat->id }}">
                        {{-- Category heading --}}
                        <div class="flex items-center gap-2 mb-1">
                            @if ($visionEditing && $editingCategoryId === $cat->id)
                                <div class="flex-1 flex items-center gap-2">
                                    <flux:input wire:model="editingCategoryName" size="sm" wire:keydown.enter="updateCategory" />
                                    <flux:button size="xs" variant="primary" wire:click="updateCategory">{{ __('Save') }}</flux:button>
                                    <flux:button size="xs" variant="ghost" wire:click="cancelEditCategory">{{ __('Cancel') }}</flux:button>
                                </div>
                            @elseif ($visionEditing)
                                <div class="category-drag-handle cursor-grab active:cursor-grabbing text-zinc-300 dark:text-zinc-600 hover:text-zinc-500 dark:hover:text-zinc-400 touch-none">
                                    <flux:icon.bars-3 variant="micro" />
                                </div>
                                <span class="flex-1 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $cat->name }}</span>
                                <div class="flex items-center gap-0.5">
                                    <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editCategory({{ $cat->id }})" aria-label="{{ __('Edit category') }}" />
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeCategory({{ $cat->id }})" wire:confirm="{{ __('Remove this category? Visions will become uncategorized.') }}" aria-label="{{ __('Remove category') }}" />
                                </div>
                            @else
                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $cat->name }}</span>
                            @endif
                        </div>

                        {{-- Visions in this category --}}
                        @if ($cat->visions->isNotEmpty() || $visionEditing)
                            <ul class="space-y-1" data-sortable-visions data-category-id="{{ $cat->id }}">
                                @foreach ($cat->visions as $vision)
                                    <li class="flex items-center gap-2 py-0.5 group" data-vision-id="{{ $vision->id }}" wire:key="vision-{{ $vision->id }}">
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

                        {{-- Per-category add vision input --}}
                        @if ($visionEditing)
                            @if ($addVisionToCategoryId === $cat->id)
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="flex-1">
                                        <flux:input
                                            wire:model="newVisionText"
                                            size="sm"
                                            :placeholder="__('Add a vision...')"
                                            wire:keydown.enter="addVision({{ $cat->id }})"
                                            wire:keydown.escape="$set('addVisionToCategoryId', null)"
                                        />
                                    </div>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="plus"
                                        wire:click="addVision({{ $cat->id }})"
                                        aria-label="{{ __('Add vision') }}"
                                    />
                                </div>
                            @else
                                <button
                                    wire:click="$set('addVisionToCategoryId', {{ $cat->id }})"
                                    class="mt-1 w-full text-left text-sm text-zinc-400 hover:text-zinc-500 dark:text-zinc-500 dark:hover:text-zinc-400 py-1"
                                >+ {{ __('Add a vision...') }}</button>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Uncategorized visions --}}
            @if ($this->uncategorizedVisions->isNotEmpty() || $visionEditing)
                <div class="{{ $this->visionCategories->isNotEmpty() ? 'mt-2' : '' }}">
                    @if ($this->uncategorizedVisions->isNotEmpty())
                        @if ($this->visionCategories->isNotEmpty())
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Uncategorized') }}</span>
                            </div>
                        @endif
                        <ul class="space-y-1" data-sortable-visions data-category-id="uncategorized">
                            @foreach ($this->uncategorizedVisions as $vision)
                                <li class="flex items-center gap-2 py-0.5 group" data-vision-id="{{ $vision->id }}" wire:key="vision-{{ $vision->id }}">
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

                    {{-- Add uncategorized vision input --}}
                    @if ($visionEditing)
                        @if ($addVisionToCategoryId === 0)
                            <div class="flex items-center gap-2 mt-1">
                                <div class="flex-1">
                                    <flux:input
                                        wire:model="newVisionText"
                                        size="sm"
                                        :placeholder="__('Add a vision...')"
                                        wire:keydown.enter="addVision(null)"
                                        wire:keydown.escape="$set('addVisionToCategoryId', null)"
                                    />
                                </div>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="plus"
                                    wire:click="addVision(null)"
                                    aria-label="{{ __('Add vision') }}"
                                />
                            </div>
                        @else
                            <button
                                wire:click="$set('addVisionToCategoryId', 0)"
                                class="mt-1 w-full text-left text-sm text-zinc-400 hover:text-zinc-500 dark:text-zinc-500 dark:hover:text-zinc-400 py-1"
                            >+ {{ __('Add a vision...') }}</button>
                        @endif
                    @endif
                </div>
            @endif

            {{-- Add category input --}}
            @if ($visionEditing)
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                    <div class="flex-1">
                        <flux:input
                            wire:model="newCategoryName"
                            size="sm"
                            :placeholder="__('Add a category...')"
                            wire:keydown.enter="addCategory"
                        />
                    </div>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        wire:click="addCategory"
                        aria-label="{{ __('Add category') }}"
                    />
                </div>
            @endif
        </div>

        {{-- Net Worth --}}
        <div class="order-1 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:subheading>{{ __('Net Worth') }}</flux:subheading>
                    <div class="mt-1 text-3xl font-bold {{ $this->netWorthSummary['net_worth'] < 0 ? 'text-red-600 dark:text-red-300' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $this->netWorthSummary['net_worth'] < 0 ? '-' : '' }}${{ format_cents(abs($this->netWorthSummary['net_worth'])) }}
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
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($total) }}</span>
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
                            ${{ format_cents($ef->balance) }}
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

        {{-- Debt Payoff --}}
        @if ($this->debtPayoff)
            <div class="order-5 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:subheading>{{ __('Debt Payoff') }}</flux:subheading>
                        @if (! ($this->debtPayoff['needs_plan_item'] ?? false))
                            <div class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $this->debtPayoff['payoff_date']->format('M Y') }}
                            </div>
                        @endif
                    </div>
                    <flux:button variant="subtle" size="sm" icon="cog-6-tooth" :href="route('net-worth.index')" wire:navigate aria-label="{{ __('Manage debt accounts') }}" />
                </div>

                @if ($this->debtPayoff['needs_plan_item'] ?? false)
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Add a "Debt Payments" item to your spending plan\'s Fixed Costs to see your payoff timeline.') }}
                    </flux:text>
                @else
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Total debt') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($this->debtPayoff['total_debt']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Monthly payment') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($this->debtPayoff['monthly_payment']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Months remaining') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $this->debtPayoff['months_to_payoff'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Total interest') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($this->debtPayoff['total_interest_paid']) }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Right column --}}
    <div class="contents lg:block lg:space-y-6">
        {{-- Current Spending Plan --}}
        @if ($this->currentPlan)
            @php $plan = $this->currentPlan; @endphp
            <div class="order-2 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <flux:subheading>{{ __('Current Spending Plan') }}</flux:subheading>
                        <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                            ${{ format_cents($plan->monthly_income) }}/mo
                        </div>
                        @if ($plan->gross_monthly_income)
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                ${{ format_cents($plan->gross_monthly_income * 12) }}/yr {{ __('gross') }}
                            </div>
                        @endif
                    </div>
                    <flux:button variant="subtle" size="sm" icon="pencil-square" :href="route('spending-plans.edit', $plan)" wire:navigate aria-label="{{ __('Edit plan') }}" />
                </div>

                @php
                    $gfPercent = $plan->categoryPercent(SpendingCategory::GuiltFree);
                    $gfHealthy = SpendingCategory::GuiltFree->isWithinIdeal($gfPercent);
                @endphp
                <div class="space-y-5">
                    @foreach (SpendingCategory::spendingCases() as $category)
                        @php
                            $total = $plan->categoryTotal($category);
                            $percent = $plan->categoryPercent($category);
                            [$min, $max] = $category->idealRange();
                            $withinIdeal = $category->isWithinIdeal($percent, $gfHealthy);
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
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($total) }}</span>
                                    @else
                                        <span class="text-sm font-medium {{ $total < 0 ? 'text-red-600 dark:text-red-300' : 'text-zinc-900 dark:text-zinc-100' }}">
                                            {{ $total < 0 ? '-' : '' }}${{ format_cents(abs($total)) }}
                                        </span>
                                    @endif
                                    <flux:badge size="sm" color="{{ $percent < 0 ? 'red' : ($withinIdeal ? 'green' : 'amber') }}" class="w-10 justify-center">
                                        {{ round($percent) }}%
                                    </flux:badge>
                                </div>
                            </div>

                            @php
                                $actualSpent = $this->monthlyExpenseTotals[$category->value] ?? 0;
                                $remaining = $total - $actualSpent;
                                $spentPercent = $total > 0 ? min(($actualSpent / $total) * 100, 100) : 0;
                            @endphp
                            <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-700 overflow-hidden">
                                <div class="h-full rounded-full {{ $total > 0 && $actualSpent > $total ? 'bg-red-500' : $category->color() }}" style="width: {{ $total > 0 ? $spentPercent : min(max($percent, 0), 100) }}%"></div>
                            </div>
                            @if ($total > 0)
                                <div class="mt-1.5 flex items-center justify-between text-xs">
                                    <span class="text-zinc-500 dark:text-zinc-400">
                                        {{ __('Spent') }}: ${{ format_cents($actualSpent) }}
                                    </span>
                                    <span class="{{ $remaining < 0 ? 'text-red-600 dark:text-red-300' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        ${{ format_cents(abs($remaining)) }}
                                        {{ $remaining >= 0 ? __('left') : __('over') }}
                                    </span>
                                </div>
                            @endif

                            @if ($items->isNotEmpty() || ($category === SpendingCategory::FixedCosts && $plan->fixed_costs_misc_percent > 0))
                                <div class="mt-2 space-y-0.5">
                                    @foreach ($items as $item)
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ $item->name }}</span>
                                            <span class="text-zinc-600 dark:text-zinc-300">${{ format_cents($item->amount) }}</span>
                                        </div>
                                    @endforeach
                                    @if ($category === SpendingCategory::FixedCosts && $plan->fixed_costs_misc_percent > 0)
                                        <div class="flex items-center justify-between text-sm italic text-zinc-500 dark:text-zinc-400">
                                            <span>{{ __('Miscellaneous') }} ({{ $plan->fixed_costs_misc_percent }}%)</span>
                                            <span class="text-zinc-600 dark:text-zinc-300">${{ format_cents($plan->fixedCostsMiscellaneous()) }}</span>
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
                @if (\App\Models\SpendingPlan::exists())
                    <flux:subheading class="mb-2">{{ __('No current spending plan') }}</flux:subheading>
                    <flux:button variant="subtle" size="sm" :href="route('spending-plans.dashboard')" wire:navigate>
                        {{ __('Choose a Plan') }}
                    </flux:button>
                @else
                    <flux:subheading class="mb-2">{{ __('Create your spending plan') }}</flux:subheading>
                    <flux:button variant="primary" size="sm" :href="route('spending-plans.create')" wire:navigate>
                        {{ __('Get Started') }}
                    </flux:button>
                @endif
            </div>
        @endif

        {{-- Investments at Retirement --}}
        <div class="order-4 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            @php
                $investmentBalance = $this->netWorthSummary['categories'][AccountCategory::Investments->value];
                $plan = $this->currentPlan;
                $monthlyContribution = $plan
                    ? $plan->categoryTotal(SpendingCategory::Investments) + ($plan->pre_tax_investments ?? 0)
                    : 0;
                $currentAge = $dateOfBirth ? \Carbon\Carbon::parse($dateOfBirth)->age : null;
                $canProject = $currentAge && $retirementAge && $retirementAge > $currentAge;
                $projectedCents = null;
                $yearsToRetirement = null;

                if ($canProject) {
                    $yearsToRetirement = $retirementAge - $currentAge;
                    $monthsToRetirement = $yearsToRetirement * 12;
                    $monthlyRate = pow(1 + $expectedReturn / 100, 1 / 12) - 1;

                    if ($monthlyRate > 0) {
                        $growthFactor = pow(1 + $monthlyRate, $monthsToRetirement);
                        $projectedCents = (int) round(
                            ($investmentBalance * $growthFactor)
                            + ($monthlyContribution * ($growthFactor - 1) / $monthlyRate)
                        );
                    } else {
                        $projectedCents = $investmentBalance + ($monthlyContribution * $monthsToRetirement);
                    }
                }
            @endphp

            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:subheading>{{ __('Est. Investments at Retirement') }}</flux:subheading>
                    @if ($canProject)
                        <div class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                            ${{ format_cents($projectedCents) }}
                        </div>
                    @endif
                </div>
                @if ($dateOfBirth)
                    <flux:button
                        size="sm"
                        variant="subtle"
                        icon="cog-6-tooth"
                        wire:click="$toggle('retirementEditing')"
                        aria-label="{{ __('Edit retirement settings') }}"
                    />
                @endif
            </div>

            <div class="space-y-3">
                @if ($retirementEditing || ! $dateOfBirth)
                    <div class="space-y-3">
                        <flux:input type="date" size="sm" wire:model="dateOfBirth" wire:change="saveRetirementSettings" max="{{ now()->format('Y-m-d') }}" label="{{ __('Birthday') }}" />
                        <div class="grid grid-cols-3 gap-3">
                            <flux:input type="number" size="sm" wire:model="retirementAge" wire:change="saveRetirementSettings" min="1" max="120" label="{{ __('Retire Age') }}" />
                            <flux:input type="number" size="sm" wire:model="expectedReturn" wire:change="saveRetirementSettings" min="0" max="30" step="0.1" label="{{ __('Return %') }}" />
                            <flux:input type="number" size="sm" wire:model="withdrawalRate" wire:change="saveRetirementSettings" min="0" max="30" step="0.1" label="{{ __('Withdrawal %') }}" />
                        </div>
                    </div>
                @endif

                @if ($canProject)
                    <div class="space-y-2 pt-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Current investments') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($investmentBalance) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Monthly contributions') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($monthlyContribution) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Years until retirement') }}</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $yearsToRetirement }}</span>
                        </div>
                        @if ($withdrawalRate > 0)
                            @php $monthlyWithdrawal = (int) round($projectedCents * ($withdrawalRate / 100) / 12); @endphp
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Safe monthly withdrawal') }}</span>
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($monthlyWithdrawal) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    </div>
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

<?php

use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Services\CsvExpenseImporter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    // Tab selection
    public string $selectedAccountId = 'all';

    // Infinite scroll
    public int $perPage = 25;

    // Add expense form
    public string $newMerchant = '';
    public string $newAmount = '';
    public string $newCategory = '';
    public string $newDate = '';
    public string $newAccountId = '';
    // First account setup
    public string $firstAccountName = '';

    // Account rename
    public string $renamingAccountName = '';
    public bool $isRenamingAccount = false;

    // Inline editing
    public ?int $editingExpenseId = null;
    public string $editingMerchant = '';
    public string $editingAmount = '';
    public string $editingCategory = '';
    public string $editingDate = '';
    public string $editingAccountId = '';
    // CSV import
    public mixed $csvFile = null;
    public array $parsedRows = [];
    public array $selectedRows = [];
    public bool $showImportModal = false;
    public ?int $importAccountId = null;
    public string $importFeedback = '';
    public array $matchedExpenses = [];
    public array $selectedMatches = [];

    // Monthly history
    public bool $showMonthlyHistory = false;

    // Bulk categorize prompt
    public bool $showBulkCategorizeModal = false;
    public string $bulkCategorizeMerchant = '';
    public string $bulkCategorizeCategory = '';
    public int $bulkCategorizeCount = 0;

    // Delete confirmations
    public ?int $confirmingDeleteExpenseId = null;
    public bool $confirmingDeleteAccount = false;

    #[Computed]
    public function accounts()
    {
        return ExpenseAccount::query()->orderBy('name')->get();
    }

    #[Computed]
    public function uncategorizedCount(): int
    {
        return Expense::query()->whereNull('category')->count();
    }

    #[Computed]
    public function expenses()
    {
        $query = Expense::query()->with('expenseAccount')
            ->latest('date')
            ->latest('id');

        $this->applyTabFilter($query);

        return $query->take($this->perPage)->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        $query = Expense::query();

        $this->applyTabFilter($query);

        return $query->count() > $this->perPage;
    }

    #[Computed]
    public function monthlyTotal(): int
    {
        $query = Expense::query()
            ->where('category', '!=', SpendingCategory::Ignored)
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]);

        $this->applyTabFilter($query);

        return (int) $query->sum('amount');
    }

    #[Computed]
    public function categoryTotals(): array
    {
        $query = Expense::query()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]);

        $this->applyTabFilter($query);

        $totals = [];
        foreach (SpendingCategory::spendingCases() as $category) {
            $totals[$category->value] = (int) (clone $query)->where('category', $category->value)->sum('amount');
        }

        return $totals;
    }

    #[Computed]
    public function monthlyHistory(): array
    {
        $query = Expense::query()
            ->where('date', '<', now()->startOfMonth())
            ->where('category', '!=', SpendingCategory::Ignored);

        $this->applyTabFilter($query);

        $expenses = (clone $query)
            ->selectRaw("strftime('%Y-%m', date) as month, sum(amount) as total")
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderByDesc('month')
            ->get();

        $months = [];
        foreach ($expenses as $row) {
            $categoryTotals = [];
            foreach (SpendingCategory::spendingCases() as $category) {
                $catQuery = Expense::query()
                    ->where('category', $category->value)
                    ->whereRaw("strftime('%Y-%m', date) = ?", [$row->month]);

                $this->applyTabFilter($catQuery);

                $catTotal = (int) $catQuery->sum('amount');
                if ($catTotal > 0) {
                    $categoryTotals[$category->value] = $catTotal;
                }
            }

            $months[] = [
                'month' => $row->month,
                'label' => \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('F Y'),
                'total' => (int) $row->total,
                'categories' => $categoryTotals,
            ];
        }

        return $months;
    }

    private function applyTabFilter($query): void
    {
        if ($this->selectedAccountId === 'uncategorized') {
            $query->whereNull('category');
        } elseif (str_starts_with($this->selectedAccountId, 'category:')) {
            $query->where('category', substr($this->selectedAccountId, 9));
        } elseif ($this->selectedAccountId !== 'all') {
            $query->where('expense_account_id', $this->selectedAccountId);
        }
    }

    public function updatedSelectedAccountId(): void
    {
        $this->perPage = 25;
        $this->cancelEdit();
        $this->cancelRename();
        $this->resetExpensesCaches();
    }

    public function loadMore(): void
    {
        $this->perPage += 25;
        unset($this->expenses, $this->hasMore);
    }

    public function updatedNewMerchant(): void
    {
        if (empty($this->newMerchant) || ! empty($this->newCategory)) {
            return;
        }

        $recentExpense = Expense::query()
            ->where('merchant', $this->newMerchant)
            ->latest('date')
            ->first();

        if ($recentExpense) {
            $this->newCategory = $recentExpense->category->value;
        }
    }

    public function addExpense(): void
    {
        // Auto-set account from selected tab
        if (is_numeric($this->selectedAccountId)) {
            $this->newAccountId = $this->selectedAccountId;
        }

        $this->newAmount = sanitize_money_input($this->newAmount);

        $this->validate([
            'newAccountId' => ['required', 'integer'],
            'newMerchant' => ['required', 'string', 'max:255'],
            'newAmount' => ['required', 'numeric', 'min:0.01'],
            'newCategory' => ['required', Rule::enum(SpendingCategory::class)],
            'newDate' => ['required', 'date'],
        ], [], [
            'newAccountId' => 'account',
            'newMerchant' => 'merchant',
            'newAmount' => 'amount',
            'newCategory' => 'category',
            'newDate' => 'date',
        ]);

        Expense::create([
            'expense_account_id' => $this->newAccountId,
            'merchant' => $this->newMerchant,
            'amount' => (int) round($this->newAmount * 100),
            'category' => $this->newCategory,
            'date' => $this->newDate,
        ]);

        $this->newMerchant = '';
        $this->newAmount = '';
        $this->newCategory = '';
        $this->resetExpensesCaches();
    }

    public function editExpense(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);

        $this->editingExpenseId = $expenseId;
        $this->editingMerchant = $expense->merchant;
        $this->editingAmount = number_format($expense->amount / 100, 2, '.', '');
        $this->editingCategory = $expense->category?->value ?? '';
        $this->editingDate = $expense->date->format('Y-m-d');
        $this->editingAccountId = (string) $expense->expense_account_id;
    }

    public function updateExpense(): void
    {
        $this->editingAmount = sanitize_money_input($this->editingAmount);

        $validated = $this->validate([
            'editingMerchant' => ['required', 'string', 'max:255'],
            'editingAmount' => ['required', 'numeric', 'min:0.01'],
            'editingCategory' => ['required', Rule::enum(SpendingCategory::class)],
            'editingDate' => ['required', 'date'],
            'editingAccountId' => ['required', 'integer'],
        ]);

        $expense = Expense::findOrFail($this->editingExpenseId);

        $expense->update([
            'merchant' => $validated['editingMerchant'],
            'amount' => (int) round($validated['editingAmount'] * 100),
            'category' => $validated['editingCategory'],
            'date' => $validated['editingDate'],
            'expense_account_id' => $validated['editingAccountId'],
        ]);

        $this->cancelEdit();
        $this->resetExpensesCaches();
    }

    public function cancelEdit(): void
    {
        $this->editingExpenseId = null;
        $this->editingMerchant = '';
        $this->editingAmount = '';
        $this->editingCategory = '';
        $this->editingDate = '';
        $this->editingAccountId = '';
    }

    public function categorizeExpense(int $expenseId, string $category): void
    {
        $expense = Expense::findOrFail($expenseId);

        if (! SpendingCategory::tryFrom($category)) {
            return;
        }

        $expense->update(['category' => $category]);

        $uncategorizedCount = Expense::query()
            ->where('merchant', $expense->merchant)
            ->whereNull('category')
            ->count();

        if ($uncategorizedCount > 0) {
            $this->bulkCategorizeMerchant = $expense->merchant;
            $this->bulkCategorizeCategory = $category;
            $this->bulkCategorizeCount = $uncategorizedCount;
            $this->showBulkCategorizeModal = true;
        }

        $this->resetExpensesCaches();
    }

    public function changeCategory(int $expenseId, string $category): void
    {
        if (! SpendingCategory::tryFrom($category)) {
            return;
        }

        Expense::findOrFail($expenseId)->update(['category' => $category]);

        $this->resetExpensesCaches();
    }

    public function bulkCategorize(): void
    {
        if (! SpendingCategory::tryFrom($this->bulkCategorizeCategory)) {
            return;
        }

        Expense::query()
            ->where('merchant', $this->bulkCategorizeMerchant)
            ->whereNull('category')
            ->update(['category' => $this->bulkCategorizeCategory]);

        $this->cancelBulkCategorize();
        $this->resetExpensesCaches();
    }

    public function cancelBulkCategorize(): void
    {
        $this->showBulkCategorizeModal = false;
        $this->bulkCategorizeMerchant = '';
        $this->bulkCategorizeCategory = '';
        $this->bulkCategorizeCount = 0;
    }

    public function confirmRemoveExpense(int $expenseId): void
    {
        $this->confirmingDeleteExpenseId = $expenseId;
    }

    public function cancelRemoveExpense(): void
    {
        $this->confirmingDeleteExpenseId = null;
    }

    public function removeExpense(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);

        $expense->delete();
        $this->confirmingDeleteExpenseId = null;
        $this->resetExpensesCaches();
    }

    // Account management

    public function createFirstAccount(): void
    {
        $this->validate([
            'firstAccountName' => ['required', 'string', 'max:255'],
        ]);

        $account = ExpenseAccount::create([
            'name' => $this->firstAccountName,
        ]);

        $this->firstAccountName = '';
        $this->selectedAccountId = (string) $account->id;
        $this->newAccountId = (string) $account->id;
        unset($this->accounts);
    }

    public function addAccount(): void
    {
        $account = ExpenseAccount::create([
            'name' => 'New',
        ]);

        $this->selectedAccountId = (string) $account->id;
        $this->newAccountId = (string) $account->id;
        $this->isRenamingAccount = true;
        $this->renamingAccountName = $account->name;
        unset($this->accounts);
        $this->resetExpensesCaches();
        $this->selectRenameInput();
    }

    public function startRenamingAccount(): void
    {
        if ($this->selectedAccountId === 'all') {
            return;
        }

        $account = ExpenseAccount::findOrFail($this->selectedAccountId);

        $this->isRenamingAccount = true;
        $this->renamingAccountName = $account->name;
        $this->selectRenameInput();
    }

    private function selectRenameInput(): void
    {
        $this->js("setTimeout(() => { const input = document.querySelector('input[wire\\\\:model=renamingAccountName]'); if (input) input.select(); }, 50)");
    }

    public function renameAccount(): void
    {
        $this->validate([
            'renamingAccountName' => ['required', 'string', 'max:255'],
        ]);

        $account = ExpenseAccount::findOrFail($this->selectedAccountId);

        $account->update(['name' => $this->renamingAccountName]);

        $this->isRenamingAccount = false;
        $this->renamingAccountName = '';
        unset($this->accounts);
    }

    public function cancelRename(): void
    {
        $this->isRenamingAccount = false;
        $this->renamingAccountName = '';
    }

    public function confirmRemoveAccount(): void
    {
        if ($this->selectedAccountId === 'all') {
            return;
        }

        $this->confirmingDeleteAccount = true;
    }

    public function cancelRemoveAccount(): void
    {
        $this->confirmingDeleteAccount = false;
    }

    public function removeAccount(): void
    {
        if ($this->selectedAccountId === 'all') {
            return;
        }

        $account = ExpenseAccount::findOrFail($this->selectedAccountId);

        $account->delete();
        $this->selectedAccountId = 'all';
        $this->confirmingDeleteAccount = false;
        unset($this->accounts);
        $this->resetExpensesCaches();
    }

    // CSV Import

    public function openImportModal(): void
    {
        $this->importAccountId = is_numeric($this->selectedAccountId)
            ? (int) $this->selectedAccountId
            : null;
        $this->csvFile = null;
        $this->parsedRows = [];
        $this->selectedRows = [];
        $this->matchedExpenses = [];
        $this->selectedMatches = [];
        $this->importFeedback = '';
        $this->showImportModal = true;
    }

    public function updatedCsvFile(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $this->parseCSV();
    }

    public function parseCSV(): void
    {
        $this->importFeedback = '';
        $this->matchedExpenses = [];

        if (! $this->csvFile || ! $this->importAccountId) {
            return;
        }

        $importer = app(CsvExpenseImporter::class);
        $result = $importer->parse($this->csvFile->getRealPath(), $this->importAccountId);

        $this->parsedRows = $result['parsedRows'];
        $this->matchedExpenses = $result['matchedExpenses'];
        $this->selectedRows = array_keys($this->parsedRows);
        $this->selectedMatches = array_keys($this->matchedExpenses);
        $this->importFeedback = $result['feedback'];
    }

    public function importExpenses(): void
    {
        if (! $this->importAccountId || (empty($this->selectedRows) && empty($this->selectedMatches))) {
            return;
        }

        $importer = app(CsvExpenseImporter::class);
        $importer->import(
            $this->selectedRows,
            $this->parsedRows,
            $this->selectedMatches,
            $this->matchedExpenses,
            $this->importAccountId,
        );

        $this->showImportModal = false;
        $this->csvFile = null;
        $this->parsedRows = [];
        $this->selectedRows = [];
        $this->matchedExpenses = [];
        $this->selectedMatches = [];
        $this->resetExpensesCaches();
    }

    public function cancelImport(): void
    {
        $this->showImportModal = false;
        $this->csvFile = null;
        $this->parsedRows = [];
        $this->selectedRows = [];
        $this->matchedExpenses = [];
        $this->selectedMatches = [];
        $this->importFeedback = '';
    }

    // Helpers


    private function resetExpensesCaches(): void
    {
        unset($this->expenses, $this->hasMore, $this->monthlyTotal, $this->categoryTotals, $this->monthlyHistory, $this->uncategorizedCount);
    }

}; ?>

<section class="w-full px-10 py-9 max-w-[1320px] mx-auto">
    @php
        $monthLabel = now()->format('F Y');
        $activeCategory = str_starts_with($selectedAccountId, 'category:')
            ? \App\Enums\SpendingCategory::tryFrom(substr($selectedAccountId, 9))
            : null;
    @endphp

    <div class="flex items-start justify-between mb-6">
        <x-page-heading eyebrow="Expenses" title="Track & categorize" />

        @if (! $this->accounts->isEmpty())
            <div class="flex items-center gap-2 pt-5" wire:key="account-actions-{{ $isRenamingAccount ? 'renaming' : 'idle' }}">
                @if ($isRenamingAccount)
                    <flux:input
                        wire:model="renamingAccountName"
                        size="sm"
                        wire:keydown.enter="renameAccount"
                        wire:keydown.escape="cancelRename"
                        class="max-w-xs"
                    />
                    <flux:button size="sm" variant="primary" wire:click="renameAccount">{{ __('Save') }}</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="cancelRename">{{ __('Cancel') }}</flux:button>
                @elseif (is_numeric($selectedAccountId))
                    <flux:button size="sm" variant="ghost" icon="arrow-up-tray" wire:click="openImportModal">
                        {{ __('Import CSV') }}
                    </flux:button>
                    <flux:dropdown>
                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Account actions') }}" />
                        <flux:menu>
                            <flux:menu.item icon="pencil" wire:click="startRenamingAccount">{{ __('Rename account') }}</flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmRemoveAccount">{{ __('Delete account') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        @endif
    </div>

    @if ($this->accounts->isEmpty())
        {{-- First account setup --}}
        <div class="rounded-2xl border border-vault-card-bd bg-vault-card p-10 text-center">
            <div class="font-display text-vault-text mb-2" style="font-size: 22px; font-weight: 300;">{{ __('Get started') }}</div>
            <div class="text-vault-textsub mb-6" style="font-size: 13px;">{{ __('Create your first expense account to start tracking spending.') }}</div>

            <div class="flex items-end justify-center gap-2 max-w-sm mx-auto">
                <div class="flex-1">
                    <flux:input
                        wire:model="firstAccountName"
                        size="sm"
                        :placeholder="__('e.g. Chase Checking')"
                        wire:keydown.enter="createFirstAccount"
                    />
                </div>
                <flux:button variant="primary" size="sm" wire:click="createFirstAccount">{{ __('Create') }}</flux:button>
            </div>
            @error('firstAccountName')
                <div class="text-vault-red mt-2" style="font-size: 12px;">{{ $message }}</div>
            @enderror
        </div>
    @else

    {{-- Monthly summary card --}}
    <div class="rounded-2xl border border-vault-card-bd bg-vault-card mb-5" style="padding: 22px 26px;">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="eyebrow text-vault-muted">{{ $monthLabel }}</div>
                <div class="font-display text-vault-text mt-1.5" style="font-size: 32px; font-weight: 300; line-height: 1;">
                    ${{ format_cents($this->monthlyTotal, 2) }}
                </div>
            </div>

            @if ($this->monthlyTotal > 0)
                <div class="flex flex-wrap gap-2.5 justify-end">
                    @foreach (SpendingCategory::spendingCases() as $cat)
                        @php $catTotal = $this->categoryTotals[$cat->value] ?? 0; @endphp
                        @if ($catTotal > 0)
                            @php $catColor = $cat->vaultColor(); @endphp
                            <button
                                wire:click="$set('selectedAccountId', 'category:{{ $cat->value }}')"
                                class="text-center transition hover:brightness-110"
                                style="background: color-mix(in srgb, {{ $catColor }} 12%, transparent); border: 1px solid color-mix(in srgb, {{ $catColor }} 28%, transparent); border-radius: 8px; padding: 8px 14px; min-width: 110px;"
                            >
                                <div style="font-size: 10px; font-weight: 600; color: {{ $catColor }}; letter-spacing: 0.04em;">{{ $cat->label() }}</div>
                                <div class="font-display text-vault-text mt-0.5" style="font-size: 16px; font-weight: 300;">${{ format_cents($catTotal) }}</div>
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Previous months toggle --}}
    @if (count($this->monthlyHistory) > 0)
        <div class="mb-5">
            <button
                wire:click="$toggle('showMonthlyHistory')"
                class="flex items-center gap-1.5 text-vault-textsub hover:text-vault-text transition-colors"
                style="font-size: 12px;"
            >
                <flux:icon.chevron-right class="size-3.5 transition-transform {{ $showMonthlyHistory ? 'rotate-90' : '' }}" />
                {{ __('Previous months') }}
            </button>

            @if ($showMonthlyHistory)
                <div class="mt-3 flex flex-col gap-2.5">
                    @foreach ($this->monthlyHistory as $month)
                        <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 14px 22px;">
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <span class="text-vault-textsub" style="font-size: 13px;">{{ $month['label'] }}</span>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($month['categories'] as $catValue => $catTotal)
                                            @php
                                                $cat = SpendingCategory::from($catValue);
                                                $cc = $cat->vaultColor();
                                            @endphp
                                            <span style="font-size: 10px; padding: 3px 8px; border-radius: 5px; background: color-mix(in srgb, {{ $cc }} 12%, transparent); color: {{ $cc }};">{{ $cat->label() }}: ${{ format_cents($catTotal) }}</span>
                                        @endforeach
                                    </div>
                                    <span class="font-display text-vault-text" style="font-size: 16px; font-weight: 300;">${{ format_cents($month['total']) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Account tabs --}}
    <div class="flex flex-wrap items-center mb-4 border-b border-vault-card-bd">
        <button
            wire:click="$set('selectedAccountId', 'all')"
            class="transition-all"
            style="padding: 8px 18px; font-size: 12px; font-weight: 500; border-bottom: 2px solid {{ $selectedAccountId === 'all' ? 'var(--color-vault-accent)' : 'transparent' }}; color: {{ $selectedAccountId === 'all' ? 'var(--color-vault-text)' : 'var(--color-vault-muted)' }};"
        >{{ __('All') }}</button>

        @foreach ($this->accounts as $account)
            @php $isActive = $selectedAccountId === (string) $account->id; @endphp
            <button
                wire:click="$set('selectedAccountId', '{{ $account->id }}')"
                wire:key="tab-{{ $account->id }}"
                class="transition-all"
                style="padding: 8px 18px; font-size: 12px; font-weight: 500; border-bottom: 2px solid {{ $isActive ? 'var(--color-vault-accent)' : 'transparent' }}; color: {{ $isActive ? 'var(--color-vault-text)' : 'var(--color-vault-muted)' }};"
            >{{ $account->name }}</button>
        @endforeach

        @if ($this->uncategorizedCount > 0)
            @php $uncatActive = $selectedAccountId === 'uncategorized'; @endphp
            <button
                wire:click="$set('selectedAccountId', 'uncategorized')"
                class="transition-all"
                style="padding: 8px 18px; font-size: 12px; font-weight: 500; border-bottom: 2px solid {{ $uncatActive ? 'var(--color-vault-warm)' : 'transparent' }}; color: {{ $uncatActive ? 'var(--color-vault-warm)' : 'var(--color-vault-warm)' }};"
            >{{ __('Uncategorized') }} ({{ $this->uncategorizedCount }})</button>
        @endif

        @if ($activeCategory)
            <button
                class="transition-all"
                style="padding: 8px 18px; font-size: 12px; font-weight: 500; border-bottom: 2px solid {{ $activeCategory->vaultColor() }}; color: {{ $activeCategory->vaultColor() }};"
            >{{ $activeCategory->label() }}</button>
        @endif

        <button
            wire:click="addAccount"
            class="ml-auto transition-colors hover:!text-vault-textsub"
            style="padding: 8px 14px; font-size: 11px; color: var(--color-vault-muted);"
            aria-label="{{ __('Add account') }}"
        >+ {{ __('New account') }}</button>
    </div>

    {{-- Uncategorized warning --}}
    @if ($this->uncategorizedCount > 0 && $selectedAccountId !== 'uncategorized')
        <div class="mb-4 flex items-center gap-2 rounded-lg" style="padding: 8px 16px; background: color-mix(in srgb, var(--color-vault-warm) 8%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-warm) 25%, transparent);">
            <span class="text-vault-warm" style="font-size: 12px;">
                {{ trans_choice(':count expense needs categorizing|:count expenses need categorizing', $this->uncategorizedCount, ['count' => $this->uncategorizedCount]) }}
            </span>
            <button
                wire:click="$set('selectedAccountId', 'uncategorized')"
                class="text-vault-warm underline hover:brightness-110"
                style="font-size: 12px; font-weight: 500;"
            >{{ __('Review') }}</button>
        </div>
    @endif

    {{-- Add expense form --}}
    @if ($selectedAccountId !== 'uncategorized')
    <div class="rounded-2xl border border-vault-card-bd bg-vault-card mb-3" style="padding: 14px 18px;">
        <div class="eyebrow text-vault-muted mb-2.5">{{ __('Add expense') }}</div>
        <form wire:submit="addExpense" class="grid grid-cols-2 sm:grid-cols-3 lg:flex lg:items-end gap-2" x-init="if (! $wire.newDate) { const d = new Date(); $wire.newDate = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }">
            @if (! is_numeric($selectedAccountId))
                <div class="min-w-0 lg:flex-1">
                    <flux:select wire:model="newAccountId" size="sm">
                        <option value="">{{ __('Account') }}</option>
                        @foreach ($this->accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <div class="min-w-0 lg:flex-1">
                <flux:input wire:model="newDate" type="date" size="sm" />
            </div>

            <div class="min-w-0 lg:flex-[2]">
                <flux:input wire:model.blur="newMerchant" :placeholder="__('Merchant')" size="sm" />
            </div>

            <div class="min-w-0 lg:flex-1">
                <flux:select wire:model="newCategory" size="sm">
                    <option value="">{{ __('Category') }}</option>
                    @foreach (SpendingCategory::cases() as $cat)
                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="min-w-0 lg:flex-1">
                <flux:input wire:model="newAmount" type="text" inputmode="decimal" :placeholder="__('0.00')" size="sm">
                    <x-slot:prefix>$</x-slot:prefix>
                </flux:input>
            </div>

            <flux:button variant="primary" size="sm" type="submit" class="shrink-0">
                {{ __('Add') }}
            </flux:button>
        </form>
    </div>
    @endif

    {{-- Expense list card --}}
    <div class="rounded-2xl border border-vault-card-bd bg-vault-card overflow-hidden">
        @if ($this->expenses->isNotEmpty())
            {{-- Header row --}}
            <div class="hidden lg:grid border-b border-vault-card-bd" style="grid-template-columns: 1fr 120px 130px 100px 70px; gap: 12px; padding: 12px 26px;">
                <span class="eyebrow text-vault-muted">{{ __('Merchant') }}</span>
                <span class="eyebrow text-vault-muted">{{ __('Account') }}</span>
                <span class="eyebrow text-vault-muted">{{ __('Category') }}</span>
                <span class="eyebrow text-vault-muted text-right">{{ __('Amount') }}</span>
                <span></span>
            </div>
        @endif

        @forelse ($this->expenses as $i => $expense)
            @php
                $catColor = $expense->category?->vaultColor() ?? '#9aad9e';
                $firstLetter = mb_strtoupper(mb_substr($expense->merchant ?: '?', 0, 1));
                $hasCategory = (bool) $expense->category;
            @endphp
            <div
                wire:key="expense-{{ $expense->id }}-{{ $selectedAccountId === 'uncategorized' ? 'uncat' : 'display' }}"
                x-data="{ removing: false }"
                :class="removing && 'opacity-0 max-h-0 !py-0 overflow-hidden !border-transparent'"
                class="group transition-all duration-300 {{ $i > 0 ? 'border-t border-vault-card-bd' : '' }}"
            >
                @if ($editingExpenseId === $expense->id)
                    {{-- Inline edit mode --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:flex lg:items-end gap-2" style="padding: 12px 26px;">
                        <div class="min-w-0 lg:flex-1">
                            <flux:select wire:model="editingAccountId" size="sm">
                                @foreach ($this->accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="min-w-0 lg:flex-1">
                            <flux:input wire:model="editingDate" type="date" size="sm" wire:keydown.enter="updateExpense" />
                        </div>
                        <div class="min-w-0 lg:flex-[2]">
                            <flux:input wire:model="editingMerchant" size="sm" :placeholder="__('Merchant')" wire:keydown.enter="updateExpense" />
                        </div>
                        <div class="min-w-0 lg:flex-1">
                            <flux:select wire:model="editingCategory" size="sm">
                                @foreach (SpendingCategory::cases() as $cat)
                                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="min-w-0 lg:flex-1">
                            <flux:input wire:model="editingAmount" type="text" inputmode="decimal" size="sm" :placeholder="__('0.00')" wire:keydown.enter="updateExpense">
                                <x-slot:prefix>$</x-slot:prefix>
                            </flux:input>
                        </div>
                        <flux:button size="sm" variant="primary" wire:click="updateExpense" class="shrink-0">{{ __('Save') }}</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="cancelEdit" class="shrink-0">{{ __('Cancel') }}</flux:button>
                    </div>
                @elseif ($hasCategory)
                    {{-- Categorized: grid layout matching header --}}
                    <div class="grid items-center" style="grid-template-columns: 1fr 120px 130px 100px 70px; gap: 12px; padding: 11px 26px;">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="flex items-center justify-center shrink-0" style="width: 32px; height: 32px; border-radius: 7px; background: color-mix(in srgb, {{ $catColor }} 14%, transparent);">
                                <span style="font-size: 12px; font-weight: 600; color: {{ $catColor }};">{{ $firstLetter }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-vault-text truncate" style="font-size: 13px;">{{ $expense->merchant }}</div>
                                <div class="text-vault-muted" style="font-size: 10px;">{{ $expense->date->format('M j, Y') }}</div>
                            </div>
                        </div>

                        <span class="text-vault-textsub truncate" style="font-size: 11px;">{{ $expense->expenseAccount->name }}</span>

                        <div class="min-w-0">
                            <flux:dropdown>
                                <button
                                    type="button"
                                    class="hover:brightness-110 transition cursor-pointer"
                                    aria-label="{{ __('Change category') }}"
                                    style="font-size: 10px; padding: 4px 9px; border-radius: 5px; background: color-mix(in srgb, {{ $catColor }} 14%, transparent); color: {{ $catColor }}; border: 1px solid color-mix(in srgb, {{ $catColor }} 28%, transparent);"
                                >{{ $expense->category->label() }}</button>
                                <flux:menu>
                                    @foreach (SpendingCategory::cases() as $cat)
                                        @if ($cat !== $expense->category)
                                            <flux:menu.item wire:click="changeCategory({{ $expense->id }}, '{{ $cat->value }}')">
                                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 2px; background: {{ $cat->vaultColor() }}; margin-right: 8px;"></span>
                                                {{ $cat->label() }}
                                            </flux:menu.item>
                                        @endif
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        <span class="text-vault-text text-right" style="font-size: 13px; font-weight: 500;">${{ format_cents($expense->amount, 2) }}</span>

                        <div class="flex items-center justify-end gap-0.5">
                            <div class="opacity-0 group-hover:opacity-100 transition">
                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editExpense({{ $expense->id }})" aria-label="{{ __('Edit expense') }}" />
                            </div>
                            <div class="opacity-0 group-hover:opacity-100 transition">
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmRemoveExpense({{ $expense->id }})" aria-label="{{ __('Remove expense') }}" />
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Uncategorized: flex layout with categorize buttons --}}
                    <div class="flex items-center gap-3 flex-wrap" style="padding: 11px 26px;">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <div class="flex items-center justify-center shrink-0" style="width: 32px; height: 32px; border-radius: 7px; background: color-mix(in srgb, var(--color-vault-warm) 14%, transparent);">
                                <span class="text-vault-warm" style="font-size: 12px; font-weight: 600;">{{ $firstLetter }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-vault-text truncate" style="font-size: 13px;">{{ $expense->merchant }}</span>
                                    <span class="text-vault-muted hidden sm:inline truncate" style="font-size: 10px;">{{ $expense->expenseAccount->name }}</span>
                                </div>
                                <div class="text-vault-muted" style="font-size: 10px;">{{ $expense->date->format('M j, Y') }}</div>
                            </div>
                        </div>

                        <span class="text-vault-text shrink-0" style="font-size: 13px; font-weight: 500;">${{ format_cents($expense->amount, 2) }}</span>

                        <div class="flex flex-wrap gap-1 shrink-0">
                            @foreach (SpendingCategory::cases() as $cat)
                                @php $cc = $cat->vaultColor(); @endphp
                                <button
                                    @if ($selectedAccountId === 'uncategorized')
                                        x-on:click="removing = true; setTimeout(() => $wire.categorizeExpense({{ $expense->id }}, '{{ $cat->value }}'), 300)"
                                    @else
                                        wire:click="categorizeExpense({{ $expense->id }}, '{{ $cat->value }}')"
                                    @endif
                                    aria-label="{{ __('Categorize as :category', ['category' => $cat->label()]) }}"
                                    class="hover:brightness-110 transition"
                                    style="font-size: 10px; padding: 4px 9px; border-radius: 5px; background: color-mix(in srgb, {{ $cc }} 14%, transparent); color: {{ $cc }}; border: 1px solid color-mix(in srgb, {{ $cc }} 28%, transparent);"
                                >{{ $cat->label() }}</button>
                            @endforeach
                        </div>

                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmRemoveExpense({{ $expense->id }})" aria-label="{{ __('Remove expense') }}" />
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center text-center" style="padding: 56px 26px; gap: 12px;">
                <div class="flex items-center justify-center" style="width: 44px; height: 44px; border-radius: 10px; background: color-mix(in srgb, var(--color-vault-muted) 12%, transparent);">
                    <flux:icon.banknotes class="size-5 text-vault-muted" />
                </div>
                <div>
                    @if ($selectedAccountId === 'uncategorized')
                        <div class="font-display text-vault-text" style="font-size: 18px; font-weight: 300;">{{ __('All caught up') }}</div>
                        <div class="text-vault-textsub mt-1" style="font-size: 12px;">{{ __('No uncategorized expenses.') }}</div>
                    @else
                        <div class="font-display text-vault-text" style="font-size: 18px; font-weight: 300;">{{ __('No expenses yet') }}</div>
                        <div class="text-vault-textsub mt-1" style="font-size: 12px;">{{ __('Add one above to start tracking.') }}</div>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Infinite scroll sentinel --}}
    @if ($this->hasMore)
        <div wire:intersect="loadMore" class="flex items-center justify-center gap-2 py-5">
            <span class="inline-block size-2 rounded-full animate-pulse" style="background: var(--color-vault-accent); animation-delay: 0ms;"></span>
            <span class="inline-block size-2 rounded-full animate-pulse" style="background: var(--color-vault-accent); animation-delay: 150ms;"></span>
            <span class="inline-block size-2 rounded-full animate-pulse" style="background: var(--color-vault-accent); animation-delay: 300ms;"></span>
            <span class="text-vault-muted ml-2" style="font-size: 11px; letter-spacing: 0.06em;">{{ __('Loading more') }}</span>
        </div>
    @endif

    {{-- CSV Import Modal --}}
    <flux:modal wire:model="showImportModal" class="max-w-2xl">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('CSV import') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Import expenses') }}</div>
            </div>

            @if (empty($parsedRows) && empty($matchedExpenses))
                {{-- Phase 1: Upload --}}
                <div class="flex flex-col gap-4">
                    @if (! $importAccountId)
                        <flux:select wire:model="importAccountId" :label="__('Import into account')" size="sm">
                            <option value="">{{ __('Select account...') }}</option>
                            @foreach ($this->accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </flux:select>
                    @else
                        @php $importAccount = $this->accounts->firstWhere('id', $importAccountId); @endphp
                        <div class="text-vault-textsub" style="font-size: 13px;">
                            {{ __('Importing into') }} <span class="text-vault-text font-medium">{{ $importAccount?->name }}</span>
                        </div>
                    @endif

                    <div>
                        <div class="text-vault-textsub mb-2" style="font-size: 12px;">{{ __('Upload a CSV file with columns for Date, Description, and Amount.') }}</div>
                        <label class="block rounded-xl cursor-pointer transition" style="border: 1px dashed var(--color-vault-input-bd); background: var(--color-vault-input); padding: 18px;">
                            <input type="file" wire:model="csvFile" accept=".csv,.txt" class="hidden" />
                            <div class="flex items-center gap-3">
                                <flux:icon.arrow-up-tray class="size-5 text-vault-textsub" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-vault-text" style="font-size: 13px;">{{ __('Choose a CSV file') }}</div>
                                    <div class="text-vault-muted truncate" style="font-size: 11px;">
                                        {{ $csvFile?->getClientOriginalName() ?? __('Drag and drop or click to browse') }}
                                    </div>
                                </div>
                                <span class="text-vault-accent" style="font-size: 11px; font-weight: 600; letter-spacing: 0.06em;">{{ __('BROWSE') }}</span>
                            </div>
                        </label>
                        @error('csvFile') <div class="text-vault-red mt-2" style="font-size: 12px;">{{ $message }}</div> @enderror
                    </div>

                    <div wire:loading wire:target="csvFile" class="text-vault-textsub" style="font-size: 12px;">
                        {{ __('Uploading and parsing...') }}
                    </div>

                    @if ($importFeedback)
                        <div class="flex items-center gap-2 rounded-lg" style="padding: 10px 14px; background: color-mix(in srgb, var(--color-vault-warm) 8%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-warm) 25%, transparent);">
                            <flux:icon.exclamation-triangle class="size-4 text-vault-warm shrink-0" />
                            <span class="text-vault-warm" style="font-size: 12px;">{{ $importFeedback }}</span>
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <flux:button variant="ghost" wire:click="cancelImport">{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            @else
                {{-- Phase 2: Preview & select --}}
                @php $totalSelected = count($selectedRows) + count($selectedMatches); @endphp

                <div class="text-vault-textsub" style="font-size: 12px;">
                    @if (count($matchedExpenses) > 0 && count($parsedRows) > 0)
                        {{ __(':matchCount matched, :newCount new transactions found.', ['matchCount' => count($matchedExpenses), 'newCount' => count($parsedRows)]) }}
                    @elseif (count($matchedExpenses) > 0)
                        {{ __(':matchCount matched transactions found.', ['matchCount' => count($matchedExpenses)]) }}
                    @else
                        {{ __(':count new transactions found.', ['count' => count($parsedRows)]) }}
                    @endif
                </div>

                {{-- Matched transactions section --}}
                @if (count($matchedExpenses) > 0)
                    <div class="flex flex-col gap-2">
                        <div class="eyebrow text-vault-muted">{{ __('Matched to your entries') }}</div>

                        <div class="max-h-48 overflow-y-auto rounded-xl border border-vault-card-bd">
                            <table class="w-full">
                                <thead class="sticky top-0" style="background: var(--color-vault-card-hov);">
                                    <tr>
                                        <th class="w-8" style="padding: 8px;"></th>
                                        <th class="text-left eyebrow text-vault-muted" style="padding: 8px;">{{ __('Your entry') }}</th>
                                        <th class="w-6" style="padding: 8px;"></th>
                                        <th class="text-left eyebrow text-vault-muted" style="padding: 8px;">{{ __('CSV transaction') }}</th>
                                        <th class="text-right eyebrow text-vault-muted" style="padding: 8px;">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($matchedExpenses as $index => $match)
                                        <tr class="border-t border-vault-card-bd {{ in_array($index, $selectedMatches) ? '' : 'opacity-50' }}">
                                            <td style="padding: 8px;">
                                                <input type="checkbox"
                                                    value="{{ $index }}"
                                                    wire:model.live="selectedMatches"
                                                    class="rounded"
                                                    style="border-color: var(--color-vault-input-bd); background: var(--color-vault-input); accent-color: var(--color-vault-accent);"
                                                />
                                            </td>
                                            <td class="truncate max-w-32" style="padding: 8px;">
                                                <span class="text-vault-text" style="font-size: 12px;">{{ $match['expense_merchant'] }}</span>
                                                <span class="text-vault-muted ml-1" style="font-size: 10px;">{{ $match['expense_date'] }}</span>
                                            </td>
                                            <td class="text-center" style="padding: 8px;"><flux:icon.arrow-right class="size-3 text-vault-muted inline" /></td>
                                            <td class="truncate max-w-32" style="padding: 8px;">
                                                <span class="text-vault-text" style="font-size: 12px;">{{ $match['csv_merchant'] }}</span>
                                                <span class="text-vault-muted ml-1" style="font-size: 10px;">{{ $match['csv_date'] }}</span>
                                            </td>
                                            <td class="text-right text-vault-text" style="padding: 8px; font-size: 12px; font-weight: 500;">${{ format_cents($match['amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- New transactions table --}}
                @if (count($parsedRows) > 0)
                    <div class="flex flex-col gap-2">
                        @if (count($matchedExpenses) > 0)
                            <div class="eyebrow text-vault-muted">{{ __('New transactions') }}</div>
                        @endif

                        <div class="max-h-96 overflow-y-auto rounded-xl border border-vault-card-bd">
                            <table class="w-full">
                                <thead class="sticky top-0" style="background: var(--color-vault-card-hov);">
                                    <tr>
                                        <th class="w-8 text-left" style="padding: 8px;">
                                            <input type="checkbox"
                                                {{ count($selectedRows) === count($parsedRows) ? 'checked' : '' }}
                                                wire:click="$set('selectedRows', {{ count($selectedRows) === count($parsedRows) ? '[]' : json_encode(array_keys($parsedRows)) }})"
                                                class="rounded"
                                                style="border-color: var(--color-vault-input-bd); background: var(--color-vault-input); accent-color: var(--color-vault-accent);"
                                            />
                                        </th>
                                        <th class="text-left eyebrow text-vault-muted" style="padding: 8px;">{{ __('Date') }}</th>
                                        <th class="text-left eyebrow text-vault-muted" style="padding: 8px;">{{ __('Merchant') }}</th>
                                        <th class="text-right eyebrow text-vault-muted" style="padding: 8px;">{{ __('Amount') }}</th>
                                        <th class="text-left eyebrow text-vault-muted" style="padding: 8px;">{{ __('Category') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($parsedRows as $index => $row)
                                        <tr class="border-t border-vault-card-bd {{ in_array($index, $selectedRows) ? '' : 'opacity-50' }}">
                                            <td style="padding: 8px;">
                                                <input type="checkbox"
                                                    value="{{ $index }}"
                                                    wire:model.live="selectedRows"
                                                    class="rounded"
                                                    style="border-color: var(--color-vault-input-bd); background: var(--color-vault-input); accent-color: var(--color-vault-accent);"
                                                />
                                            </td>
                                            <td class="text-vault-textsub" style="padding: 8px; font-size: 12px;">{{ $row['date'] }}</td>
                                            <td class="text-vault-text truncate max-w-48" style="padding: 8px; font-size: 12px;">{{ $row['merchant'] }}</td>
                                            <td class="text-right text-vault-text" style="padding: 8px; font-size: 12px; font-weight: 500;">${{ format_cents($row['amount'], 2) }}</td>
                                            <td style="padding: 8px;">
                                                @if ($row['category'])
                                                    @php
                                                        $catEnum = SpendingCategory::from($row['category']);
                                                        $cc = $catEnum->vaultColor();
                                                    @endphp
                                                    <span style="font-size: 10px; padding: 3px 8px; border-radius: 5px; background: color-mix(in srgb, {{ $cc }} 14%, transparent); color: {{ $cc }};">{{ $catEnum->label() }}</span>
                                                @else
                                                    <span class="text-vault-muted" style="font-size: 10px; padding: 3px 8px; border-radius: 5px; background: color-mix(in srgb, var(--color-vault-muted) 14%, transparent);">{{ __('Uncategorized') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="cancelImport">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" wire:click="importExpenses" :disabled="$totalSelected === 0">
                        {{ __('Import :count expenses', ['count' => $totalSelected]) }}
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
    @endif

    {{-- Bulk Categorize Confirmation --}}
    <flux:modal wire:model.self="showBulkCategorizeModal" class="min-w-[22rem]">
        @php
            $bulkCat = $bulkCategorizeCategory ? \App\Enums\SpendingCategory::tryFrom($bulkCategorizeCategory) : null;
            $bulkColor = $bulkCat?->vaultColor() ?? '#9aad9e';
        @endphp
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Bulk categorize') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Categorize similar expenses?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">
                    {{ trans_choice(
                        ':count other expense from :merchant is also uncategorized. Categorize it as :category too?|:count other expenses from :merchant are also uncategorized. Categorize them all as :category?',
                        $bulkCategorizeCount,
                        [
                            'count' => $bulkCategorizeCount,
                            'merchant' => $bulkCategorizeMerchant,
                            'category' => $bulkCat?->label() ?? '',
                        ]
                    ) }}
                </div>
                @if ($bulkCat)
                    <div class="mt-3 inline-flex items-center" style="gap: 6px; padding: 4px 10px; border-radius: 6px; background: color-mix(in srgb, {{ $bulkColor }} 14%, transparent); border: 1px solid color-mix(in srgb, {{ $bulkColor }} 28%, transparent);">
                        <span class="rounded-sm" style="width: 8px; height: 8px; background: {{ $bulkColor }};"></span>
                        <span style="font-size: 11px; color: {{ $bulkColor }}; font-weight: 500;">{{ $bulkCat->label() }}</span>
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelBulkCategorize">{{ __('No thanks') }}</flux:button>
                <flux:button variant="primary" wire:click="bulkCategorize">{{ __('Categorize all') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete Expense Confirmation --}}
    <flux:modal wire:model.self="confirmingDeleteExpenseId" class="min-w-[22rem]">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Remove expense') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Remove this expense?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">{{ __('This action cannot be undone.') }}</div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelRemoveExpense">{{ __('Cancel') }}</flux:button>
                @if ($confirmingDeleteExpenseId)
                    <flux:button variant="danger" wire:click="removeExpense({{ $confirmingDeleteExpenseId }})">{{ __('Remove') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    {{-- Delete Account Confirmation --}}
    <flux:modal wire:model.self="confirmingDeleteAccount" class="min-w-[22rem]">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Delete account') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Delete this account and all its expenses?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">{{ __('This action cannot be undone.') }}</div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelRemoveAccount">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="removeAccount">{{ __('Delete account') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>

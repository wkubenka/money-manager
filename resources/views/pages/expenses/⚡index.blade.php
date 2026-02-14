<?php

use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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
    public $csvFile;
    public array $parsedRows = [];
    public array $selectedRows = [];
    public bool $showImportModal = false;
    public ?int $importAccountId = null;
    public string $importFeedback = '';

    // Bulk categorize prompt
    public bool $showBulkCategorizeModal = false;
    public string $bulkCategorizeMerchant = '';
    public string $bulkCategorizeCategory = '';
    public int $bulkCategorizeCount = 0;

    public function mount(): void
    {
        //
    }

    #[Computed]
    public function accounts()
    {
        return Auth::user()->expenseAccounts()->orderBy('name')->get();
    }

    #[Computed]
    public function uncategorizedCount(): int
    {
        return Auth::user()->expenses()->whereNull('category')->count();
    }

    #[Computed]
    public function expenses()
    {
        $query = Auth::user()->expenses()->with('expenseAccount')
            ->latest('date')
            ->latest('id');

        $this->applyTabFilter($query);

        return $query->take($this->perPage)->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        $query = Auth::user()->expenses();

        $this->applyTabFilter($query);

        return $query->count() > $this->perPage;
    }

    #[Computed]
    public function monthlyTotal(): int
    {
        $query = Auth::user()->expenses()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]);

        $this->applyTabFilter($query);

        return (int) $query->sum('amount');
    }

    #[Computed]
    public function categoryTotals(): array
    {
        $query = Auth::user()->expenses()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]);

        $this->applyTabFilter($query);

        $totals = [];
        foreach (SpendingCategory::cases() as $category) {
            $totals[$category->value] = (int) (clone $query)->where('category', $category->value)->sum('amount');
        }

        return $totals;
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

        $recentExpense = Auth::user()->expenses()
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
        if ($this->selectedAccountId !== 'all') {
            $this->newAccountId = $this->selectedAccountId;
        }

        $this->newAmount = $this->sanitizeAmount($this->newAmount);

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

        $account = ExpenseAccount::findOrFail($this->newAccountId);
        abort_unless($account->user_id === Auth::id(), 403);

        Auth::user()->expenses()->create([
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
        abort_unless($expense->user_id === Auth::id(), 403);

        $this->editingExpenseId = $expenseId;
        $this->editingMerchant = $expense->merchant;
        $this->editingAmount = number_format($expense->amount / 100, 2, '.', '');
        $this->editingCategory = $expense->category?->value ?? '';
        $this->editingDate = $expense->date->format('Y-m-d');
        $this->editingAccountId = (string) $expense->expense_account_id;
    }

    public function updateExpense(): void
    {
        $this->editingAmount = $this->sanitizeAmount($this->editingAmount);

        $validated = $this->validate([
            'editingMerchant' => ['required', 'string', 'max:255'],
            'editingAmount' => ['required', 'numeric', 'min:0.01'],
            'editingCategory' => ['required', Rule::enum(SpendingCategory::class)],
            'editingDate' => ['required', 'date'],
            'editingAccountId' => ['required', 'integer'],
        ]);

        $expense = Expense::findOrFail($this->editingExpenseId);
        abort_unless($expense->user_id === Auth::id(), 403);

        $account = ExpenseAccount::findOrFail($validated['editingAccountId']);
        abort_unless($account->user_id === Auth::id(), 403);

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
        abort_unless($expense->user_id === Auth::id(), 403);

        if (! SpendingCategory::tryFrom($category)) {
            return;
        }

        $expense->update(['category' => $category]);

        $uncategorizedCount = Auth::user()->expenses()
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

    public function bulkCategorize(): void
    {
        if (! SpendingCategory::tryFrom($this->bulkCategorizeCategory)) {
            return;
        }

        Auth::user()->expenses()
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

    public function removeExpense(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);
        abort_unless($expense->user_id === Auth::id(), 403);

        $expense->delete();
        $this->resetExpensesCaches();
    }

    // Account management

    public function createFirstAccount(): void
    {
        $this->validate([
            'firstAccountName' => ['required', 'string', 'max:255'],
        ]);

        $account = Auth::user()->expenseAccounts()->create([
            'name' => $this->firstAccountName,
        ]);

        $this->firstAccountName = '';
        $this->selectedAccountId = (string) $account->id;
        $this->newAccountId = (string) $account->id;
        unset($this->accounts);
    }

    public function addAccount(): void
    {
        $account = Auth::user()->expenseAccounts()->create([
            'name' => 'New',
        ]);

        $this->selectedAccountId = (string) $account->id;
        $this->newAccountId = (string) $account->id;
        $this->isRenamingAccount = true;
        $this->renamingAccountName = $account->name;
        unset($this->accounts);
        $this->resetExpensesCaches();
    }

    public function startRenamingAccount(): void
    {
        if ($this->selectedAccountId === 'all') {
            return;
        }

        $account = ExpenseAccount::findOrFail($this->selectedAccountId);
        abort_unless($account->user_id === Auth::id(), 403);

        $this->isRenamingAccount = true;
        $this->renamingAccountName = $account->name;
    }

    public function renameAccount(): void
    {
        $this->validate([
            'renamingAccountName' => ['required', 'string', 'max:255'],
        ]);

        $account = ExpenseAccount::findOrFail($this->selectedAccountId);
        abort_unless($account->user_id === Auth::id(), 403);

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

    public function removeAccount(): void
    {
        if ($this->selectedAccountId === 'all') {
            return;
        }

        $account = ExpenseAccount::findOrFail($this->selectedAccountId);
        abort_unless($account->user_id === Auth::id(), 403);

        $account->delete();
        $this->selectedAccountId = 'all';
        unset($this->accounts);
        $this->resetExpensesCaches();
    }

    // CSV Import

    public function openImportModal(): void
    {
        $this->importAccountId = $this->selectedAccountId !== 'all'
            ? (int) $this->selectedAccountId
            : null;
        $this->csvFile = null;
        $this->parsedRows = [];
        $this->selectedRows = [];
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

        if (! $this->csvFile || ! $this->importAccountId) {
            return;
        }

        $path = $this->csvFile->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            $this->importFeedback = __('Could not read the file.');

            return;
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            $this->importFeedback = __('The file appears to be empty.');

            return;
        }

        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $dateCol = $this->detectColumn($headers, ['date', 'transaction date', 'posted date', 'posting date', 'post date']);
        $merchantCol = $this->detectColumn($headers, ['description', 'merchant', 'name', 'memo', 'payee', 'transaction']);
        $amountCol = $this->detectColumn($headers, ['amount', 'debit', 'total', 'charge']);

        if ($dateCol === null || $merchantCol === null || $amountCol === null) {
            fclose($handle);
            $this->importFeedback = __('Could not detect Date, Description, or Amount columns in this file.');

            return;
        }

        $existingExpenses = Auth::user()->expenses()
            ->where('expense_account_id', $this->importAccountId)
            ->get(['date', 'amount'])
            ->map(fn ($e) => $e->date->format('Y-m-d') . '|' . $e->amount)
            ->toArray();

        // First pass: collect all rows with raw signed amounts
        $rawRows = [];
        $hasNegativeAmounts = false;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) <= max($dateCol, $merchantCol, $amountCol)) {
                continue;
            }

            $rawAmount = (float) str_replace([',', '$', ' '], '', $row[$amountCol]);

            if ($rawAmount < 0) {
                $hasNegativeAmounts = true;
            }

            $rawRows[] = [
                'rawAmount' => $rawAmount,
                'merchant' => trim($row[$merchantCol]),
                'dateStr' => trim($row[$dateCol]),
            ];
        }

        // Second pass: filter and build parsed rows
        $rows = [];
        foreach ($rawRows as $raw) {
            // In signed-amount CSVs, positive values are credits/income — skip them
            if ($hasNegativeAmounts && $raw['rawAmount'] > 0) {
                continue;
            }

            $amount = abs($raw['rawAmount']);

            if ($amount <= 0) {
                continue;
            }

            $amountCents = (int) round($amount * 100);

            try {
                $date = Carbon::parse($raw['dateStr'])->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }

            $key = $date . '|' . $amountCents;
            if (in_array($key, $existingExpenses)) {
                continue;
            }

            $category = $this->lookupMerchantCategory($raw['merchant']);

            $rows[] = [
                'date' => $date,
                'merchant' => $raw['merchant'],
                'amount' => $amountCents,
                'category' => $category,
            ];
        }

        fclose($handle);

        $this->parsedRows = $rows;
        $this->selectedRows = array_keys($rows);

        if (empty($rows) && ! empty($rawRows)) {
            $this->importFeedback = __('All transactions in this file have already been imported.');
        }
    }

    public function importExpenses(): void
    {
        if (! $this->importAccountId || empty($this->selectedRows)) {
            return;
        }

        $account = ExpenseAccount::findOrFail($this->importAccountId);
        abort_unless($account->user_id === Auth::id(), 403);

        foreach ($this->selectedRows as $index) {
            if (! isset($this->parsedRows[$index])) {
                continue;
            }

            $row = $this->parsedRows[$index];

            Auth::user()->expenses()->create([
                'expense_account_id' => $this->importAccountId,
                'merchant' => $row['merchant'],
                'amount' => $row['amount'],
                'category' => $row['category'],
                'date' => $row['date'],
            ]);
        }

        $this->showImportModal = false;
        $this->csvFile = null;
        $this->parsedRows = [];
        $this->selectedRows = [];
        $this->resetExpensesCaches();
    }

    public function cancelImport(): void
    {
        $this->showImportModal = false;
        $this->csvFile = null;
        $this->parsedRows = [];
        $this->selectedRows = [];
        $this->importFeedback = '';
    }

    // Helpers

    private function sanitizeAmount(string $value): string
    {
        return str_replace([',', '$', ' '], '', $value);
    }

    private function resetExpensesCaches(): void
    {
        unset($this->expenses, $this->hasMore, $this->monthlyTotal, $this->categoryTotals, $this->uncategorizedCount);
    }

    private function detectColumn(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function lookupMerchantCategory(string $merchant): ?string
    {
        $expense = Auth::user()->expenses()
            ->where('merchant', $merchant)
            ->whereNotNull('category')
            ->latest('date')
            ->first();

        return $expense?->category->value;
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Expenses') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Track where your money went') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    @if ($this->accounts->isEmpty())
        {{-- First account setup --}}
        <div class="rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
            <flux:heading size="lg" class="mb-2">{{ __('Get started') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mb-6">{{ __('Create your first expense account to start tracking spending.') }}</flux:text>

            <div class="flex items-end justify-center gap-2 max-w-sm mx-auto">
                <div class="flex-1">
                    <flux:input
                        wire:model="firstAccountName"
                        size="sm"
                        :label="__('Account name')"
                        :placeholder="__('e.g. Chase Checking')"
                        wire:keydown.enter="createFirstAccount"
                    />
                </div>
                <flux:button variant="primary" size="sm" wire:click="createFirstAccount">{{ __('Create') }}</flux:button>
            </div>
            @error('firstAccountName')
                <flux:text class="text-sm text-red-600 mt-2">{{ $message }}</flux:text>
            @enderror
        </div>
    @else
    {{-- Monthly summary --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 mb-6">
        <div class="flex items-center justify-between">
            <flux:subheading>{{ __('This Month') }}</flux:subheading>
            <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">${{ number_format($this->monthlyTotal / 100, 2) }}</span>
        </div>
        @if ($this->monthlyTotal > 0)
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach (SpendingCategory::cases() as $cat)
                    @php $catTotal = $this->categoryTotals[$cat->value] ?? 0; @endphp
                    @if ($catTotal > 0)
                        <flux:badge as="button" size="sm" color="{{ $cat->badgeColor() }}" variant="solid" wire:click="$set('selectedAccountId', 'category:{{ $cat->value }}')" class="cursor-pointer">
                            {{ $cat->label() }}: ${{ number_format($catTotal / 100) }}
                        </flux:badge>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- Account tabs --}}
    <div class="mb-4 flex flex-wrap items-center gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('selectedAccountId', 'all')"
            class="px-3 py-2 text-sm font-medium border-b-2 transition-colors {{ $selectedAccountId === 'all' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
        >{{ __('All') }}</button>
        @if ($this->uncategorizedCount > 0)
            <button
                wire:click="$set('selectedAccountId', 'uncategorized')"
                class="px-3 py-2 text-sm font-medium border-b-2 transition-colors {{ $selectedAccountId === 'uncategorized' ? 'border-amber-600 text-amber-700 dark:border-amber-400 dark:text-amber-300' : 'border-transparent text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300' }}"
            >{{ __('Uncategorized') }} ({{ $this->uncategorizedCount }})</button>
        @endif
        @if (str_starts_with($selectedAccountId, 'category:'))
            @php $activeCategory = \App\Enums\SpendingCategory::tryFrom(substr($selectedAccountId, 9)); @endphp
            @if ($activeCategory)
                <button
                    class="px-3 py-2 text-sm font-medium border-b-2 border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100 transition-colors"
                >{{ $activeCategory->label() }}</button>
            @endif
        @endif
        @foreach ($this->accounts as $account)
            <button
                wire:click="$set('selectedAccountId', '{{ $account->id }}')"
                wire:key="tab-{{ $account->id }}"
                class="px-3 py-2 text-sm font-medium border-b-2 transition-colors {{ $selectedAccountId === (string) $account->id ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
            >{{ $account->name }}</button>
        @endforeach
        <button
            wire:click="addAccount"
            class="px-3 py-2 text-sm font-medium border-b-2 border-transparent text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition-colors"
            aria-label="{{ __('Add account') }}"
        >+ {{ __('New') }}</button>
    </div>

    {{-- Uncategorized warning --}}
    @if ($this->uncategorizedCount > 0 && $selectedAccountId !== 'uncategorized')
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 dark:border-amber-800 dark:bg-amber-950">
            <flux:text class="text-sm text-amber-700 dark:text-amber-300">
                {{ trans_choice(':count expense needs categorizing|:count expenses need categorizing', $this->uncategorizedCount, ['count' => $this->uncategorizedCount]) }}
            </flux:text>
            <button
                wire:click="$set('selectedAccountId', 'uncategorized')"
                class="text-sm font-medium text-amber-700 underline hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100"
            >{{ __('Review') }}</button>
        </div>
    @endif

    {{-- Account rename/delete bar --}}
    @if ($selectedAccountId !== 'all' && $selectedAccountId !== 'uncategorized')
        <div class="mb-4 flex items-center gap-2">
            @if ($isRenamingAccount)
                <flux:input
                    wire:model="renamingAccountName"
                    size="sm"
                    wire:keydown.enter="renameAccount"
                    wire:keydown.escape="cancelRename"
                    class="max-w-xs"
                />
                <flux:button size="xs" variant="primary" wire:click="renameAccount">{{ __('Save') }}</flux:button>
                <flux:button size="xs" variant="ghost" wire:click="cancelRename">{{ __('Cancel') }}</flux:button>
            @else
                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="startRenamingAccount" aria-label="{{ __('Rename account') }}" />
                <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeAccount" wire:confirm="{{ __('Delete this account and all its expenses?') }}" aria-label="{{ __('Delete account') }}" />
                <div class="flex-1"></div>
                <flux:button size="xs" variant="ghost" icon="arrow-up-tray" wire:click="openImportModal" aria-label="{{ __('Import CSV') }}">
                    {{ __('Import CSV') }}
                </flux:button>
            @endif
        </div>
    @endif

    {{-- Add expense form --}}
    @if ($selectedAccountId !== 'uncategorized')
    <form wire:submit="addExpense" class="mb-6 grid grid-cols-2 sm:grid-cols-3 lg:flex lg:items-end gap-2" x-init="if (! $wire.newDate) { const d = new Date(); $wire.newDate = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }">
        @if ($selectedAccountId === 'all')
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
    @endif

    {{-- Expense list --}}
    <div class="space-y-1">
        @forelse ($this->expenses as $expense)
            <div class="flex items-center gap-2 py-2 group border-b border-zinc-100 dark:border-zinc-800" wire:key="expense-{{ $expense->id }}">
                @if ($editingExpenseId === $expense->id)
                    {{-- Inline edit mode --}}
                    <div class="flex-1 grid grid-cols-2 sm:grid-cols-3 lg:flex lg:items-end gap-2">
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
                @elseif ($selectedAccountId === 'uncategorized')
                    {{-- Categorization mode --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate">{{ $expense->merchant }}</span>
                            <span class="hidden sm:inline text-xs text-zinc-400 dark:text-zinc-500 truncate max-w-32">{{ $expense->expenseAccount->name }}</span>
                        </div>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $expense->date->format('M j, Y') }}</span>
                    </div>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 shrink-0">${{ number_format($expense->amount / 100, 2) }}</span>
                    <div class="flex gap-1 shrink-0">
                        @foreach (SpendingCategory::cases() as $cat)
                            <flux:button size="xs" variant="primary" color="{{ $cat->badgeColor() }}" wire:click="categorizeExpense({{ $expense->id }}, '{{ $cat->value }}')" aria-label="{{ __('Categorize as :category', ['category' => $cat->label()]) }}">{{ $cat->label() }}</flux:button>
                        @endforeach
                    </div>
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeExpense({{ $expense->id }})" wire:confirm="{{ __('Remove this expense?') }}" aria-label="{{ __('Remove expense') }}" />
                @else
                    {{-- Display mode --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate">{{ $expense->merchant }}</span>
                            @if ($selectedAccountId === 'all')
                                <span class="hidden sm:inline text-xs text-zinc-400 dark:text-zinc-500 truncate max-w-32">{{ $expense->expenseAccount->name }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $expense->date->format('M j, Y') }}</span>
                            @if ($expense->category)
                                <flux:badge size="sm" color="{{ $expense->category->badgeColor() }}" variant="solid">
                                    {{ $expense->category->label() }}
                                </flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">
                                    {{ __('Uncategorized') }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 shrink-0">${{ number_format($expense->amount / 100, 2) }}</span>
                    <div class="flex items-center gap-0.5 shrink-0">
                        <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editExpense({{ $expense->id }})" aria-label="{{ __('Edit expense') }}" />
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeExpense({{ $expense->id }})" wire:confirm="{{ __('Remove this expense?') }}" aria-label="{{ __('Remove expense') }}" />
                    </div>
                @endif
            </div>
        @empty
            <div class="py-8 text-center">
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No expenses yet. Add one above!') }}</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Infinite scroll sentinel --}}
    @if ($this->hasMore)
        <div wire:intersect="loadMore" class="py-4 text-center">
            <flux:text class="text-sm text-zinc-400">{{ __('Loading more...') }}</flux:text>
        </div>
    @endif

    {{-- CSV Import Modal --}}
    <flux:modal wire:model="showImportModal" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Import Expenses from CSV') }}</flux:heading>

            @if (empty($parsedRows))
                {{-- Phase 1: Upload --}}
                <div class="space-y-4">
                    @if (! $importAccountId)
                        <flux:select wire:model="importAccountId" :label="__('Import into account')" size="sm">
                            <option value="">{{ __('Select account...') }}</option>
                            @foreach ($this->accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </flux:select>
                    @else
                        @php $importAccount = $this->accounts->firstWhere('id', $importAccountId); @endphp
                        <flux:text>
                            {{ __('Importing into:') }} <strong>{{ $importAccount?->name }}</strong>
                        </flux:text>
                    @endif

                    <div>
                        <flux:text class="text-sm text-zinc-500 mb-2">{{ __('Upload a CSV file with columns for Date, Description, and Amount.') }}</flux:text>
                        <input type="file" wire:model="csvFile" accept=".csv,.txt" class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-300" />
                        @error('csvFile') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div wire:loading wire:target="csvFile" class="text-sm text-zinc-500">
                        {{ __('Uploading and parsing...') }}
                    </div>

                    @if ($importFeedback)
                        <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 dark:border-amber-800 dark:bg-amber-950">
                            <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
                            <flux:text class="text-sm text-amber-700 dark:text-amber-300">{{ $importFeedback }}</flux:text>
                        </div>
                    @endif
                </div>
            @else
                {{-- Phase 2: Preview & select --}}
                <flux:text class="text-sm">
                    {{ __(':count transactions found (duplicates already filtered out).', ['count' => count($parsedRows)]) }}
                </flux:text>

                <div class="max-h-96 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 sticky top-0">
                            <tr>
                                <th class="p-2 text-left w-8">
                                    <input type="checkbox"
                                        {{ count($selectedRows) === count($parsedRows) ? 'checked' : '' }}
                                        wire:click="$set('selectedRows', {{ count($selectedRows) === count($parsedRows) ? '[]' : json_encode(array_keys($parsedRows)) }})"
                                        class="rounded border-zinc-300 dark:border-zinc-600"
                                    />
                                </th>
                                <th class="p-2 text-left">{{ __('Date') }}</th>
                                <th class="p-2 text-left">{{ __('Merchant') }}</th>
                                <th class="p-2 text-right">{{ __('Amount') }}</th>
                                <th class="p-2 text-left">{{ __('Category') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($parsedRows as $index => $row)
                                <tr class="border-t border-zinc-100 dark:border-zinc-800 {{ in_array($index, $selectedRows) ? '' : 'opacity-50' }}">
                                    <td class="p-2">
                                        <input type="checkbox"
                                            value="{{ $index }}"
                                            wire:model.live="selectedRows"
                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                        />
                                    </td>
                                    <td class="p-2">{{ $row['date'] }}</td>
                                    <td class="p-2 truncate max-w-48">{{ $row['merchant'] }}</td>
                                    <td class="p-2 text-right">${{ number_format($row['amount'] / 100, 2) }}</td>
                                    <td class="p-2">
                                        @if ($row['category'])
                                            @php $catEnum = SpendingCategory::from($row['category']); @endphp
                                            <flux:badge size="sm" color="{{ $catEnum->badgeColor() }}" variant="solid">
                                                {{ $catEnum->label() }}
                                            </flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">
                                                {{ __('Uncategorized') }}
                                            </flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="cancelImport">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" wire:click="importExpenses" :disabled="empty($selectedRows)">
                        {{ __('Import :count expenses', ['count' => count($selectedRows)]) }}
                    </flux:button>
                </div>
            @endif

            @if (empty($parsedRows))
                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="cancelImport">{{ __('Cancel') }}</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
    @endif

    {{-- Bulk Categorize Confirmation --}}
    <flux:modal wire:model.self="showBulkCategorizeModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Categorize similar expenses?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ trans_choice(
                        ':count other expense from :merchant is also uncategorized. Would you like to categorize it as :category too?|:count other expenses from :merchant are also uncategorized. Would you like to categorize them all as :category?',
                        $bulkCategorizeCount,
                        [
                            'count' => $bulkCategorizeCount,
                            'merchant' => $bulkCategorizeMerchant,
                            'category' => $bulkCategorizeCategory ? \App\Enums\SpendingCategory::tryFrom($bulkCategorizeCategory)?->label() : '',
                        ]
                    ) }}
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="cancelBulkCategorize">{{ __('No thanks') }}</flux:button>
                <flux:button variant="primary" wire:click="bulkCategorize">{{ __('Categorize all') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>

<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    // Per-category new account form
    public array $newAccountNames = [];
    public array $newAccountBalances = [];

    // Inline editing
    public ?int $editingAccountId = null;
    public string $editingAccountName = '';
    public string $editingAccountBalance = '';

    #[Computed]
    public function accounts()
    {
        return Auth::user()->netWorthAccounts()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function netWorth(): int
    {
        $total = 0;

        foreach (AccountCategory::cases() as $category) {
            $categoryTotal = (int) $this->accounts
                ->where('category', $category)
                ->sum('balance');

            $total += $category->isDeducted() ? -$categoryTotal : $categoryTotal;
        }

        return $total;
    }

    public function categoryTotal(AccountCategory $category): int
    {
        return (int) $this->accounts
            ->where('category', $category)
            ->sum('balance');
    }

    public function addAccount(string $category): void
    {
        $this->newAccountBalances[$category] = $this->sanitizeBalance($this->newAccountBalances[$category] ?? '');

        $this->validate([
            "newAccountNames.{$category}" => ['required', 'string', 'max:255'],
            "newAccountBalances.{$category}" => ['required', 'numeric', 'min:0.01'],
        ], [], [
            "newAccountNames.{$category}" => 'account name',
            "newAccountBalances.{$category}" => 'balance',
        ]);

        abort_unless(
            in_array($category, array_column(AccountCategory::cases(), 'value')),
            422
        );

        Auth::user()->netWorthAccounts()->create([
            'category' => $category,
            'name' => $this->newAccountNames[$category],
            'balance' => (int) round($this->newAccountBalances[$category] * 100),
        ]);

        $this->newAccountNames[$category] = '';
        $this->newAccountBalances[$category] = '';
        unset($this->accounts, $this->netWorth);

        $this->js("document.getElementById('new-account-name-{$category}')?.focus()");
    }

    public function editAccount(int $accountId): void
    {
        $account = NetWorthAccount::findOrFail($accountId);
        abort_unless($account->user_id === Auth::id(), 403);

        $this->editingAccountId = $accountId;
        $this->editingAccountName = $account->name;
        $this->editingAccountBalance = number_format($account->balance / 100, 2, '.', '');
    }

    public function updateAccount(): void
    {
        $this->editingAccountBalance = $this->sanitizeBalance($this->editingAccountBalance);

        $validated = $this->validate([
            'editingAccountName' => ['required', 'string', 'max:255'],
            'editingAccountBalance' => ['required', 'numeric', 'min:0.01'],
        ]);

        $account = NetWorthAccount::findOrFail($this->editingAccountId);
        abort_unless($account->user_id === Auth::id(), 403);

        $data = ['balance' => (int) round($validated['editingAccountBalance'] * 100)];

        if (! $account->is_emergency_fund) {
            $data['name'] = $validated['editingAccountName'];
        }

        $account->update($data);

        $this->cancelEdit();
        unset($this->accounts, $this->netWorth);
    }

    public function cancelEdit(): void
    {
        $this->editingAccountId = null;
        $this->editingAccountName = '';
        $this->editingAccountBalance = '';
    }

    private function sanitizeBalance(string $value): string
    {
        return str_replace([',', '$', ' '], '', $value);
    }

    public function removeAccount(int $accountId): void
    {
        $account = NetWorthAccount::findOrFail($accountId);
        abort_unless($account->user_id === Auth::id(), 403);
        abort_if($account->is_emergency_fund, 422);

        $account->delete();
        unset($this->accounts, $this->netWorth);
    }
}; ?>

<section class="w-full">
    @include('partials.net-worth-heading')

    {{-- Net worth summary --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 mb-8">
        <div class="flex items-center justify-between">
            <div>
                <flux:subheading>{{ __('Net Worth') }}</flux:subheading>
                <div class="mt-1 text-3xl font-bold {{ $this->netWorth < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ $this->netWorth < 0 ? '-' : '' }}${{ number_format(abs($this->netWorth) / 100) }}
                </div>
            </div>
            <flux:link :href="route('dashboard')" wire:navigate class="text-sm">
                {{ __('Back to dashboard') }}
            </flux:link>
        </div>
    </div>

    {{-- Account categories --}}
    <div class="space-y-6">
        @foreach (AccountCategory::cases() as $category)
            @php
                $catKey = $category->value;
                $items = $this->accounts->where('category', $category);
                $total = $this->categoryTotal($category);
            @endphp
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="size-3 rounded-full {{ $category->color() }}"></div>
                        <flux:heading>{{ $category->label() }}</flux:heading>
                    </div>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        ${{ number_format($total / 100) }}
                    </span>
                </div>

                {{-- Existing accounts --}}
                @if ($items->isNotEmpty())
                    <div class="space-y-1 mb-4">
                        @foreach ($items as $account)
                            <div class="flex items-center gap-2 py-1.5 group">
                                @if ($editingAccountId === $account->id)
                                    {{-- Inline edit mode --}}
                                    <div class="flex-1 space-y-2">
                                        @if ($account->is_emergency_fund)
                                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $account->name }}</span>
                                        @else
                                            <flux:input wire:model="editingAccountName" size="sm" wire:keydown.enter="updateAccount" />
                                        @endif
                                        <div class="flex items-center gap-2">
                                            <flux:input wire:model="editingAccountBalance" type="text" inputmode="decimal" size="sm" class="w-28" wire:keydown.enter="updateAccount">
                                                <x-slot:prefix>$</x-slot:prefix>
                                            </flux:input>
                                            <flux:button size="xs" variant="primary" wire:click="updateAccount">{{ __('Save') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    </div>
                                @else
                                    {{-- Display mode --}}
                                    <span class="flex-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $account->name }}</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($account->balance / 100) }}</span>
                                    <div class="flex items-center gap-0.5">
                                        <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editAccount({{ $account->id }})" aria-label="{{ __('Edit account') }}" />
                                        @if ($account->is_emergency_fund)
                                            <div class="size-8"></div>
                                        @else
                                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeAccount({{ $account->id }})" wire:confirm="{{ __('Remove this account?') }}" aria-label="{{ __('Remove account') }}" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Add new account --}}
                <div class="flex items-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                    <div class="flex-1">
                        <flux:input
                            id="new-account-name-{{ $catKey }}"
                            wire:model="newAccountNames.{{ $catKey }}"
                            size="sm"
                            :placeholder="__('Name')"
                            wire:keydown.enter="addAccount('{{ $catKey }}')"
                        />
                    </div>
                    <div class="w-32">
                        <flux:input
                            wire:model="newAccountBalances.{{ $catKey }}"
                            type="text"
                            inputmode="decimal"
                            size="sm"
                            :placeholder="__('0.00')"
                            wire:keydown.enter="addAccount('{{ $catKey }}')"
                        >
                            <x-slot:prefix>$</x-slot:prefix>
                        </flux:input>
                    </div>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        wire:click="addAccount('{{ $catKey }}')"
                        aria-label="{{ __('Add account') }}"
                    />
                </div>
            </div>
        @endforeach
    </div>
</section>

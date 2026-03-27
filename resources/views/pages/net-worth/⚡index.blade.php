<?php

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    // Per-category new account form
    public array $newAccountNames = [];
    public array $newAccountBalances = [];
    public array $newAccountMinPayments = [];
    public array $newAccountInterestRates = [];

    // Inline editing
    public ?int $editingAccountId = null;
    public string $editingAccountName = '';
    public string $editingAccountBalance = '';
    public string $editingMinPayment = '';
    public string $editingInterestRate = '';

    #[Computed]
    public function accounts()
    {
        return NetWorthAccount::query()
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
        $this->newAccountBalances[$category] = sanitize_money_input($this->newAccountBalances[$category] ?? '');

        $rules = [
            "newAccountNames.{$category}" => ['required', 'string', 'max:255'],
            "newAccountBalances.{$category}" => ['required', 'numeric', 'min:0.01'],
        ];
        $attributes = [
            "newAccountNames.{$category}" => 'account name',
            "newAccountBalances.{$category}" => 'balance',
        ];

        if ($category === AccountCategory::Debt->value) {
            $this->newAccountMinPayments[$category] = sanitize_money_input($this->newAccountMinPayments[$category] ?? '');

            $rules["newAccountMinPayments.{$category}"] = ['nullable', 'numeric', 'min:0'];
            $rules["newAccountInterestRates.{$category}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $attributes["newAccountMinPayments.{$category}"] = 'minimum payment';
            $attributes["newAccountInterestRates.{$category}"] = 'interest rate';
        }

        $this->validate($rules, [], $attributes);

        abort_unless(
            in_array($category, array_column(AccountCategory::cases(), 'value')),
            422
        );

        $data = [
            'category' => $category,
            'name' => $this->newAccountNames[$category],
            'balance' => (int) round($this->newAccountBalances[$category] * 100),
        ];

        if ($category === AccountCategory::Debt->value) {
            $minPayment = $this->newAccountMinPayments[$category] ?? '';
            $interestRate = $this->newAccountInterestRates[$category] ?? '';

            $data['minimum_payment'] = $minPayment !== '' ? (int) round($minPayment * 100) : null;
            $data['interest_rate'] = $interestRate !== '' ? $interestRate : null;
        }

        NetWorthAccount::create($data);

        $this->newAccountNames[$category] = '';
        $this->newAccountBalances[$category] = '';
        $this->newAccountMinPayments[$category] = '';
        $this->newAccountInterestRates[$category] = '';
        unset($this->accounts, $this->netWorth);

        $this->js("document.getElementById('new-account-name-{$category}')?.focus()");
    }

    public function editAccount(int $accountId): void
    {
        $account = NetWorthAccount::findOrFail($accountId);

        $this->editingAccountId = $accountId;
        $this->editingAccountName = $account->name;
        $this->editingAccountBalance = number_format($account->balance / 100, 2, '.', '');

        if ($account->category === AccountCategory::Debt) {
            $this->editingMinPayment = $account->minimum_payment !== null
                ? number_format($account->minimum_payment / 100, 2, '.', '')
                : '';
            $this->editingInterestRate = $account->interest_rate ?? '';
        }
    }

    public function updateAccount(): void
    {
        $this->editingAccountBalance = sanitize_money_input($this->editingAccountBalance);

        $account = NetWorthAccount::findOrFail($this->editingAccountId);

        $rules = [
            'editingAccountName' => ['required', 'string', 'max:255'],
            'editingAccountBalance' => ['required', 'numeric', 'min:0.01'],
        ];

        if ($account->category === AccountCategory::Debt) {
            $this->editingMinPayment = sanitize_money_input($this->editingMinPayment);

            $rules['editingMinPayment'] = ['nullable', 'numeric', 'min:0'];
            $rules['editingInterestRate'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        $validated = $this->validate($rules);

        $data = ['balance' => (int) round($validated['editingAccountBalance'] * 100)];

        if (! $account->is_emergency_fund) {
            $data['name'] = $validated['editingAccountName'];
        }

        if ($account->category === AccountCategory::Debt) {
            $data['minimum_payment'] = $validated['editingMinPayment'] !== null && $validated['editingMinPayment'] !== ''
                ? (int) round($validated['editingMinPayment'] * 100)
                : null;
            $data['interest_rate'] = $validated['editingInterestRate'] !== null && $validated['editingInterestRate'] !== ''
                ? $validated['editingInterestRate']
                : null;
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
        $this->editingMinPayment = '';
        $this->editingInterestRate = '';
    }


    public function removeAccount(int $accountId): void
    {
        $account = NetWorthAccount::findOrFail($accountId);
        abort_if($account->is_emergency_fund, 422);

        $account->delete();
        unset($this->accounts, $this->netWorth);
    }
}; ?>

<section class="w-full">
    <x-page-heading title="Net Worth" subtitle="Track what you own and what you owe" />

    {{-- Net worth summary --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 mb-8">
        <div class="flex items-center justify-between">
            <div>
                <flux:subheading>{{ __('Net Worth') }}</flux:subheading>
                <div class="mt-1 text-3xl font-bold {{ $this->netWorth < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ $this->netWorth < 0 ? '-' : '' }}${{ format_cents(abs($this->netWorth)) }}
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
                        ${{ format_cents($total) }}
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
                                            @if ($account->category === AccountCategory::Debt)
                                                <flux:input wire:model="editingMinPayment" type="text" inputmode="decimal" size="sm" class="w-28" :placeholder="__('Min. payment')" wire:keydown.enter="updateAccount">
                                                    <x-slot:prefix>$</x-slot:prefix>
                                                </flux:input>
                                                <flux:input wire:model="editingInterestRate" type="text" inputmode="decimal" size="sm" class="w-20" :placeholder="__('APR')" wire:keydown.enter="updateAccount">
                                                    <x-slot:suffix>%</x-slot:suffix>
                                                </flux:input>
                                            @endif
                                            <flux:button size="xs" variant="primary" wire:click="updateAccount">{{ __('Save') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    </div>
                                @else
                                    {{-- Display mode --}}
                                    <div class="flex-1">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $account->name }}</span>
                                        @if ($account->category === AccountCategory::Debt && ($account->minimum_payment || $account->interest_rate))
                                            <div class="flex gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                                @if ($account->minimum_payment)
                                                    <span>${{ format_cents($account->minimum_payment) }}/mo min</span>
                                                @endif
                                                @if ($account->interest_rate)
                                                    <span>{{ $account->interest_rate }}% APR</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ format_cents($account->balance) }}</span>
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
                <div class="flex items-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-700 {{ $category === AccountCategory::Debt ? 'flex-wrap' : '' }}">
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
                            :placeholder="__('Balance')"
                            wire:keydown.enter="addAccount('{{ $catKey }}')"
                        >
                            <x-slot:prefix>$</x-slot:prefix>
                        </flux:input>
                    </div>
                    @if ($category === AccountCategory::Debt)
                        <div class="w-32">
                            <flux:input
                                wire:model="newAccountMinPayments.{{ $catKey }}"
                                type="text"
                                inputmode="decimal"
                                size="sm"
                                :placeholder="__('Min. payment')"
                                wire:keydown.enter="addAccount('{{ $catKey }}')"
                            >
                                <x-slot:prefix>$</x-slot:prefix>
                            </flux:input>
                        </div>
                        <div class="w-20">
                            <flux:input
                                wire:model="newAccountInterestRates.{{ $catKey }}"
                                type="text"
                                inputmode="decimal"
                                size="sm"
                                :placeholder="__('APR')"
                                wire:keydown.enter="addAccount('{{ $catKey }}')"
                            >
                                <x-slot:suffix>%</x-slot:suffix>
                            </flux:input>
                        </div>
                    @endif
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

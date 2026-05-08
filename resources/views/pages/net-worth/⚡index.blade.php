<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\NetWorthAccount;
use App\Models\Profile;
use App\Models\SpendingPlan;
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

    // Retirement assumptions
    public ?string $dateOfBirth = null;
    public ?int $retirementAge = null;
    public ?float $expectedReturn = null;
    public ?float $withdrawalRate = null;
    public bool $retirementEditing = false;

    // Delete confirmation
    public ?int $confirmingDeleteAccountId = null;

    public function mount(): void
    {
        $profile = Profile::instance();
        $this->dateOfBirth = $profile->date_of_birth?->format('Y-m-d');
        $this->retirementAge = $profile->retirement_age;
        $this->expectedReturn = (float) $profile->expected_return;
        $this->withdrawalRate = (float) $profile->withdrawal_rate;
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

    #[Computed]
    public function retirementProjection(): ?array
    {
        if (! $this->dateOfBirth || ! $this->retirementAge) {
            return null;
        }

        $currentAge = \Carbon\Carbon::parse($this->dateOfBirth)->age;
        if ($this->retirementAge <= $currentAge) {
            return null;
        }

        $investmentBalance = (int) $this->accounts
            ->where('category', AccountCategory::Investments)
            ->sum('balance');

        $plan = SpendingPlan::where('is_current', true)->first()?->load('items');
        $monthlyContribution = $plan
            ? $plan->categoryTotal(SpendingCategory::Investments) + ($plan->pre_tax_investments ?? 0)
            : 0;

        $yearsToRetirement = $this->retirementAge - $currentAge;
        $monthsToRetirement = $yearsToRetirement * 12;
        $monthlyRate = pow(1 + ($this->expectedReturn ?? 0) / 100, 1 / 12) - 1;

        if ($monthlyRate > 0) {
            $growthFactor = pow(1 + $monthlyRate, $monthsToRetirement);
            $projectedCents = (int) round(
                ($investmentBalance * $growthFactor) + ($monthlyContribution * ($growthFactor - 1) / $monthlyRate)
            );
        } else {
            $projectedCents = $investmentBalance + ($monthlyContribution * $monthsToRetirement);
        }

        $safeMonthlyCents = $this->withdrawalRate
            ? (int) round($projectedCents * ($this->withdrawalRate / 100) / 12)
            : 0;

        return [
            'current_age' => $currentAge,
            'years_to_retirement' => $yearsToRetirement,
            'invested_today' => $investmentBalance,
            'monthly_contribution' => $monthlyContribution,
            'projected_cents' => $projectedCents,
            'safe_monthly_cents' => $safeMonthlyCents,
        ];
    }

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


    public function confirmRemoveAccount(int $accountId): void
    {
        $account = NetWorthAccount::findOrFail($accountId);

        if ($account->is_emergency_fund) {
            return;
        }

        $this->confirmingDeleteAccountId = $accountId;
    }

    public function cancelRemoveAccount(): void
    {
        $this->confirmingDeleteAccountId = null;
    }

    public function removeAccount(int $accountId): void
    {
        $account = NetWorthAccount::findOrFail($accountId);
        abort_if($account->is_emergency_fund, 422);

        $account->delete();
        $this->confirmingDeleteAccountId = null;
        unset($this->accounts, $this->netWorth);
    }
}; ?>

@php
    $netWorthCategories = collect(AccountCategory::cases())->map(fn ($c) => [
        'category' => $c,
        'total'    => $this->categoryTotal($c),
    ]);

    $absSumForBar = $netWorthCategories->sum(fn ($r) => abs($r['total']));
    $proj = $this->retirementProjection;
@endphp

<section class="w-full px-10 py-9 max-w-[1320px] mx-auto">
    <x-page-heading eyebrow="Net Worth" title="What you own & owe" />

    {{-- Summary card --}}
    <div class="rounded-2xl border border-vault-card-bd bg-vault-card mb-6" style="padding: 24px 28px;">
        <div class="flex items-baseline gap-4">
            <div class="font-display leading-none {{ $this->netWorth < 0 ? 'text-vault-red' : 'text-vault-text' }}" style="font-size: 42px; font-weight: 300;">
                {{ $this->netWorth < 0 ? '-' : '' }}${{ format_cents(abs($this->netWorth)) }}
            </div>
        </div>

        {{-- Stacked bar with 2px gaps --}}
        <div class="flex w-full mt-4" style="height: 8px; gap: 2px;">
            @foreach ($netWorthCategories as $row)
                @php
                    $width = $absSumForBar > 0 ? (abs($row['total']) / $absSumForBar) * 100 : 0;
                @endphp
                @if ($width > 0)
                    <div style="width: {{ $width }}%; background: {{ $row['category']->vaultColor() }}; opacity: 0.85; border-radius: 3px;"></div>
                @endif
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap mt-3" style="gap: 24px;">
            @foreach ($netWorthCategories as $row)
                <div class="flex items-center" style="gap: 6px;">
                    <span class="rounded-full" style="width: 6px; height: 6px; background: {{ $row['category']->vaultColor() }};"></span>
                    <span class="text-vault-muted" style="font-size: 11px;">{{ $row['category']->label() }}</span>
                    <span style="font-size: 11px; font-weight: 500; color: {{ $row['category'] === AccountCategory::Debt && $row['total'] > 0 ? 'var(--color-vault-red)' : 'var(--color-vault-text)' }};">
                        ${{ format_cents($row['total']) }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 2-column: categories left, retirement right --}}
    <div class="grid gap-5" style="grid-template-columns: 1.7fr 1fr;">

        {{-- LEFT: Category cards --}}
        <div class="flex flex-col gap-4">
            @foreach (AccountCategory::cases() as $category)
                @php
                    $catKey = $category->value;
                    $items = $this->accounts->where('category', $category)->values();
                    $total = $this->categoryTotal($category);
                    $catColor = $category->vaultColor();
                    $isDebt = $category === AccountCategory::Debt;
                @endphp
                <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 20px 24px;">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center" style="gap: 10px;">
                            <span style="width: 8px; height: 8px; border-radius: 2px; background: {{ $catColor }}; flex-shrink: 0;"></span>
                            <div>
                                <div class="font-display text-vault-text" style="font-size: 16px; font-weight: 400;">{{ $category->label() }}</div>
                                <div class="text-vault-muted" style="font-size: 10px;">{{ $category->description() }}</div>
                            </div>
                        </div>
                        <span class="font-display" style="font-size: 18px; font-weight: 300; color: {{ $isDebt && $total > 0 ? 'var(--color-vault-red)' : 'var(--color-vault-text)' }};">
                            {{ $isDebt && $total > 0 ? '−' : '' }}${{ format_cents($total) }}
                        </span>
                    </div>

                    {{-- Account list --}}
                    @if ($items->isNotEmpty())
                        <div class="flex flex-col">
                            @foreach ($items as $i => $account)
                                @if ($i > 0)
                                    <div class="border-t border-vault-card-bd"></div>
                                @endif
                                <div class="flex items-center justify-between py-2.5 group">
                                    @if ($editingAccountId === $account->id)
                                        <div class="flex-1 flex items-center gap-2 flex-wrap">
                                            @if ($account->is_emergency_fund)
                                                <span class="text-vault-text flex-1 min-w-0" style="font-size: 13px;">{{ $account->name }}</span>
                                            @else
                                                <flux:input wire:model="editingAccountName" size="sm" class="flex-1 min-w-0" wire:keydown.enter="updateAccount" />
                                            @endif
                                            <flux:input wire:model="editingAccountBalance" type="text" inputmode="decimal" size="sm" class="w-28" wire:keydown.enter="updateAccount">
                                                <x-slot:prefix>$</x-slot:prefix>
                                            </flux:input>
                                            @if ($isDebt)
                                                <flux:input wire:model="editingInterestRate" type="text" inputmode="decimal" size="sm" class="w-20" :placeholder="__('APR')" wire:keydown.enter="updateAccount">
                                                    <x-slot:suffix>%</x-slot:suffix>
                                                </flux:input>
                                                <flux:input wire:model="editingMinPayment" type="text" inputmode="decimal" size="sm" class="w-24" :placeholder="__('Min')" wire:keydown.enter="updateAccount">
                                                    <x-slot:prefix>$</x-slot:prefix>
                                                </flux:input>
                                            @endif
                                            <flux:button size="xs" variant="primary" wire:click="updateAccount">{{ __('Save') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    @else
                                        <div class="min-w-0">
                                            <div class="flex items-center" style="gap: 6px;">
                                                <span class="text-vault-text truncate" style="font-size: 13px;">{{ $account->name }}</span>
                                                @if ($account->is_emergency_fund)
                                                    <span class="rounded text-vault-accent" style="background: color-mix(in srgb, var(--color-vault-accent) 15%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-accent) 35%, transparent); font-size: 9px; padding: 2px 6px; letter-spacing: 0.04em;">{{ __('Emergency Fund') }}</span>
                                                @endif
                                            </div>
                                            @if ($isDebt && ($account->interest_rate || $account->minimum_payment))
                                                @php
                                                    $debtMeta = [];
                                                    if ($account->interest_rate) {
                                                        $debtMeta[] = $account->interest_rate.'% APR';
                                                    }
                                                    if ($account->minimum_payment) {
                                                        $debtMeta[] = '$'.format_cents($account->minimum_payment).'/mo min';
                                                    }
                                                @endphp
                                                <div class="text-vault-muted mt-0.5" style="font-size: 10px;">
                                                    {{ implode(' · ', $debtMeta) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex items-center" style="gap: 10px;">
                                            <span style="font-size: 13px; font-weight: 500; color: {{ $isDebt ? 'var(--color-vault-red)' : 'var(--color-vault-text)' }};">
                                                ${{ format_cents($account->balance) }}
                                            </span>
                                            <div class="flex items-center gap-0.5">
                                                <div class="opacity-0 group-hover:opacity-100 transition">
                                                    <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editAccount({{ $account->id }})" aria-label="{{ __('Edit account') }}" />
                                                </div>
                                                @if ($account->is_emergency_fund)
                                                    <div class="size-7" aria-hidden="true"></div>
                                                @else
                                                    <div class="opacity-0 group-hover:opacity-100 transition">
                                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmRemoveAccount({{ $account->id }})" aria-label="{{ __('Remove account') }}" />
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Add new account form (inline, dashed) --}}
                    <div class="mt-3 pt-3 border-t border-vault-card-bd flex items-end gap-2 {{ $isDebt ? 'flex-wrap' : '' }}">
                        <div class="flex-1 min-w-0">
                            <flux:input
                                id="new-account-name-{{ $catKey }}"
                                wire:model="newAccountNames.{{ $catKey }}"
                                size="sm"
                                :placeholder="__('Add :label account', ['label' => strtolower($category->label())])"
                                wire:keydown.enter="addAccount('{{ $catKey }}')"
                            />
                        </div>
                        <div class="w-28">
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
                        @if ($isDebt)
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
                            <div class="w-24">
                                <flux:input
                                    wire:model="newAccountMinPayments.{{ $catKey }}"
                                    type="text"
                                    inputmode="decimal"
                                    size="sm"
                                    :placeholder="__('Min')"
                                    wire:keydown.enter="addAccount('{{ $catKey }}')"
                                >
                                    <x-slot:prefix>$</x-slot:prefix>
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

        {{-- RIGHT: Retirement Estimator sidebar --}}
        <div class="flex flex-col gap-4">
            <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 24px 26px;">
                <div class="flex items-center justify-between">
                    <div class="eyebrow text-vault-textsub" style="letter-spacing: 0.13em;">{{ __('Retirement Estimator') }}</div>
                    @if ($proj)
                        <flux:button
                            size="xs"
                            variant="ghost"
                            icon="cog-6-tooth"
                            wire:click="$toggle('retirementEditing')"
                            aria-label="{{ __('Adjust assumptions') }}"
                            class="!text-vault-muted hover:!text-vault-textsub"
                        />
                    @endif
                </div>

                @if ($proj)
                    <div class="font-serif italic font-light text-vault-textsub mt-2 mb-[18px]" style="font-size: 13px;">
                        {{ __("Where you're headed at :age", ['age' => $retirementAge]) }}
                    </div>
                    <div class="font-display text-vault-accent" style="font-size: 36px; font-weight: 300; line-height: 1;">
                        ${{ format_cents($proj['projected_cents']) }}
                    </div>
                    <div class="text-vault-muted mt-1 mb-[22px]" style="font-size: 11px;">
                        {{ __('projected balance at retirement') }}
                    </div>

                    <div class="flex flex-col gap-3.5 pt-4 border-t border-vault-card-bd">
                        <div class="flex justify-between items-baseline">
                            <span class="text-vault-muted" style="font-size: 11px; letter-spacing: 0.06em;">{{ __('Age now') }}</span>
                            <span class="font-display text-vault-text" style="font-size: 16px; font-weight: 300;">{{ $proj['current_age'] }}</span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-vault-muted" style="font-size: 11px; letter-spacing: 0.06em;">{{ __('Years to retirement') }}</span>
                            <span class="font-display text-vault-text" style="font-size: 16px; font-weight: 300;">{{ $proj['years_to_retirement'] }}</span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-vault-muted" style="font-size: 11px; letter-spacing: 0.06em;">{{ __('Invested today') }}</span>
                            <span class="font-display text-vault-text" style="font-size: 16px; font-weight: 300;">${{ format_cents($proj['invested_today']) }}</span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-vault-muted" style="font-size: 11px; letter-spacing: 0.06em;">{{ __('Adding monthly') }}</span>
                            <span class="font-display text-vault-text" style="font-size: 16px; font-weight: 300;">${{ format_cents($proj['monthly_contribution']) }}</span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-vault-muted" style="font-size: 11px; letter-spacing: 0.06em;">{{ __('Return assumed') }}</span>
                            <span class="font-display text-vault-textsub" style="font-size: 16px; font-weight: 300;">{{ rtrim(rtrim(number_format($expectedReturn, 1), '0'), '.') }}%/yr</span>
                        </div>
                    </div>

                    {{-- Safe withdrawal callout --}}
                    <div class="mt-[22px] rounded-[10px]" style="padding: 16px 18px; background: color-mix(in srgb, var(--color-vault-accent) 8%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-accent) 25%, transparent);">
                        <div class="text-vault-accent" style="font-size: 10px; font-weight: 600; letter-spacing: 0.13em;">{{ __('SAFE WITHDRAWAL') }}</div>
                        <div class="font-display text-vault-text mt-1.5" style="font-size: 22px; font-weight: 300;">
                            ${{ format_cents($proj['safe_monthly_cents']) }}<span class="text-vault-muted" style="font-size: 12px;">/mo</span>
                        </div>
                        <div class="text-vault-muted mt-1" style="font-size: 10px;">
                            {{ __('at :rate% withdrawal rate', ['rate' => rtrim(rtrim(number_format($withdrawalRate, 1), '0'), '.')]) }}
                        </div>
                    </div>
                @else
                    <div class="text-vault-textsub mt-3" style="font-size: 13px;">
                        {{ __('Set your birthday and retirement age to see a projection.') }}
                    </div>
                @endif

                {{-- Inline editor --}}
                @if ($retirementEditing || ! $proj)
                    <div class="mt-5 pt-4 border-t border-vault-card-bd flex flex-col gap-2.5">
                        <flux:input
                            type="date"
                            size="sm"
                            wire:model="dateOfBirth"
                            wire:change="saveRetirementSettings"
                            max="{{ now()->format('Y-m-d') }}"
                            label="{{ __('Birthday') }}"
                        />
                        <div class="grid grid-cols-3 gap-2">
                            <flux:input type="number" size="sm" wire:model="retirementAge" wire:change="saveRetirementSettings" min="1" max="120" label="{{ __('Retire age') }}" />
                            <flux:input type="number" size="sm" wire:model="expectedReturn" wire:change="saveRetirementSettings" min="0" max="30" step="0.1" label="{{ __('Return %') }}" />
                            <flux:input type="number" size="sm" wire:model="withdrawalRate" wire:change="saveRetirementSettings" min="0" max="30" step="0.1" label="{{ __('Withdraw %') }}" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Delete Account Confirmation --}}
    <flux:modal wire:model.self="confirmingDeleteAccountId" class="min-w-[22rem]">
        <div class="flex flex-col gap-5">
            <div>
                <div class="eyebrow text-vault-muted mb-2">{{ __('Remove account') }}</div>
                <div class="font-display text-vault-text" style="font-size: 22px; font-weight: 300; line-height: 1.2;">{{ __('Remove this account?') }}</div>
                <div class="text-vault-textsub mt-3" style="font-size: 13px; line-height: 1.5;">{{ __('This action cannot be undone.') }}</div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelRemoveAccount">{{ __('Cancel') }}</flux:button>
                @if ($confirmingDeleteAccountId)
                    <flux:button variant="danger" wire:click="removeAccount({{ $confirmingDeleteAccountId }})">{{ __('Remove') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</section>

<?php

use App\Enums\AccountCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function accounts()
    {
        return Auth::user()->netWorthAccounts()->get();
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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    {{-- Net Worth Hero --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <flux:subheading>{{ __('Net Worth') }}</flux:subheading>
        <div class="mt-1 text-3xl font-bold {{ $this->netWorthSummary['net_worth'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
            {{ $this->netWorthSummary['net_worth'] < 0 ? '-' : '' }}${{ number_format(abs($this->netWorthSummary['net_worth']) / 100) }}
        </div>
    </div>

    {{-- Category Cards --}}
    <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
        @foreach (AccountCategory::cases() as $category)
            @php $total = $this->netWorthSummary['categories'][$category->value]; @endphp
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                <div class="flex items-center gap-2 mb-2">
                    <div class="size-3 rounded-full {{ $category->color() }}"></div>
                    <flux:subheading>{{ $category->label() }}</flux:subheading>
                </div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                    ${{ number_format($total / 100) }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Manage link --}}
    <div>
        <flux:button variant="subtle" :href="route('net-worth.index')" wire:navigate>
            {{ __('Manage Accounts') }}
        </flux:button>
    </div>
</div>

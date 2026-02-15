<?php

use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\NetWorthAccount;
use App\Models\SpendingPlan;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function userMetrics(): array
    {
        return [
            'total' => User::count(),
            'last_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'last_30_days' => User::where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    #[Computed]
    public function expenseMetrics(): array
    {
        return [
            'total' => Expense::count(),
            'imported' => Expense::where('is_imported', true)->count(),
            'manual' => Expense::where('is_imported', false)->count(),
            'uncategorized' => Expense::whereNull('category')->count(),
        ];
    }

    #[Computed]
    public function planMetrics(): array
    {
        return [
            'total' => SpendingPlan::count(),
            'current' => SpendingPlan::where('is_current', true)->count(),
        ];
    }

    #[Computed]
    public function accountMetrics(): array
    {
        return [
            'net_worth_accounts' => NetWorthAccount::count(),
            'expense_accounts' => ExpenseAccount::count(),
        ];
    }

    #[Computed]
    public function recentUsers()
    {
        return User::latest()->take(10)->get(['id', 'name', 'email', 'created_at']);
    }

    #[Computed]
    public function recentErrors(): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            return [];
        }

        $content = File::get($logPath);

        preg_match_all(
            '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+\w+\.(ERROR|CRITICAL):\s+(.+?)(?=\n\[\d{4}-|\z)/sm',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        $errors = array_slice($matches, -10);
        $errors = array_reverse($errors);

        return array_map(fn ($match) => [
            'timestamp' => $match[1],
            'level' => $match[2],
            'message' => Str::limit(trim($match[3]), 200),
        ], $errors);
    }
}; ?>

<section class="w-full">
    <x-page-heading title="Admin Dashboard" subtitle="Site-wide usage metrics" />

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Users --}}
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:subheading>{{ __('Users') }}</flux:subheading>
            <div class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ number_format($this->userMetrics['total']) }}
            </div>
            <div class="mt-2 space-y-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                <div>{{ $this->userMetrics['last_7_days'] }} {{ __('in last 7 days') }}</div>
                <div>{{ $this->userMetrics['last_30_days'] }} {{ __('in last 30 days') }}</div>
            </div>
        </div>

        {{-- Expenses --}}
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:subheading>{{ __('Expenses') }}</flux:subheading>
            <div class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ number_format($this->expenseMetrics['total']) }}
            </div>
            <div class="mt-2 space-y-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                <div>{{ $this->expenseMetrics['imported'] }} {{ __('imported') }}</div>
                <div>{{ $this->expenseMetrics['manual'] }} {{ __('manual') }}</div>
                <div>{{ $this->expenseMetrics['uncategorized'] }} {{ __('uncategorized') }}</div>
            </div>
        </div>

        {{-- Spending Plans --}}
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:subheading>{{ __('Spending Plans') }}</flux:subheading>
            <div class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ number_format($this->planMetrics['total']) }}
            </div>
            <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $this->planMetrics['current'] }} {{ __('marked current') }}
            </div>
        </div>

        {{-- Accounts --}}
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:subheading>{{ __('Accounts') }}</flux:subheading>
            <div class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ number_format($this->accountMetrics['net_worth_accounts'] + $this->accountMetrics['expense_accounts']) }}
            </div>
            <div class="mt-2 space-y-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                <div>{{ number_format($this->accountMetrics['net_worth_accounts']) }} {{ __('net worth') }}</div>
                <div>{{ number_format($this->accountMetrics['expense_accounts']) }} {{ __('expense') }}</div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Recent Registrations --}}
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading class="mb-4">{{ __('Recent Registrations') }}</flux:heading>
            @if ($this->recentUsers->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($this->recentUsers as $user)
                        <div wire:key="user-{{ $user->id }}" class="flex items-center justify-between text-sm">
                            <div>
                                <span class="text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400">({{ $user->email }})</span>
                            </div>
                            <span class="text-zinc-500 dark:text-zinc-400">
                                {{ $user->created_at->diffForHumans() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="text-sm">{{ __('No users yet.') }}</flux:text>
            @endif
        </div>

        {{-- Recent Errors --}}
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading class="mb-4">{{ __('Recent Errors') }}</flux:heading>
            @if (count($this->recentErrors) > 0)
                <div class="space-y-3">
                    @foreach ($this->recentErrors as $index => $error)
                        <div wire:key="error-{{ $index }}" class="text-sm">
                            <div class="mb-0.5 flex items-center gap-2">
                                <flux:badge size="sm" color="{{ $error['level'] === 'CRITICAL' ? 'red' : 'amber' }}">
                                    {{ $error['level'] }}
                                </flux:badge>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $error['timestamp'] }}
                                </span>
                            </div>
                            <p class="break-all text-zinc-700 dark:text-zinc-300">
                                {{ $error['message'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="text-sm">{{ __('No recent errors.') }}</flux:text>
            @endif
        </div>
    </div>
</section>

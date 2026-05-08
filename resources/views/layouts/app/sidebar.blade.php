<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-vault-bg text-vault-text font-sans">
        @php
            $hasDebt = \App\Models\NetWorthAccount::where('category', 'debt')->exists();

            $navGroups = [
                [
                    'items' => [
                        ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'squares-2x2', 'href' => route('dashboard'), 'current' => request()->routeIs('dashboard')],
                    ],
                ],
                [
                    'heading' => 'Plan',
                    'items' => array_filter([
                        ['id' => 'spending', 'label' => 'Spending Plans', 'icon' => 'queue-list', 'href' => route('spending-plans.dashboard'), 'current' => request()->routeIs('spending-plans.*')],
                        $hasDebt
                            ? ['id' => 'debt', 'label' => 'Debt Payoff', 'icon' => 'clock', 'href' => route('debt-payoff.index'), 'current' => request()->routeIs('debt-payoff.*')]
                            : null,
                    ]),
                ],
                [
                    'heading' => 'Track',
                    'items' => [
                        ['id' => 'networth', 'label' => 'Net Worth', 'icon' => 'arrow-trending-up', 'href' => route('net-worth.index'), 'current' => request()->routeIs('net-worth.*')],
                        ['id' => 'expenses', 'label' => 'Expenses', 'icon' => 'credit-card', 'href' => route('expenses.index'), 'current' => request()->routeIs('expenses.*')],
                    ],
                ],
            ];
        @endphp

        <flux:sidebar
            sticky
            collapsible="mobile"
            class="!border-r !border-vault-sidebar-bd !bg-vault-sidebar !w-[232px]"
        >
            {{-- Brand --}}
            <div class="flex items-center justify-between px-6 pt-7 pb-5 border-b border-vault-sidebar-bd">
                <a href="{{ route('dashboard') }}" wire:navigate class="block leading-none">
                    <div class="font-serif italic text-[22px] font-normal text-vault-accent leading-none">Astute</div>
                    <div class="mt-[3px] text-[9px] tracking-[0.2em] text-vault-muted">MONEY</div>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </div>

            {{-- Nav --}}
            <nav class="flex-1 overflow-y-auto py-4">
                @foreach ($navGroups as $group)
                    <div class="mb-1">
                        @if (!empty($group['heading']))
                            <div class="px-6 pt-2.5 pb-1 text-[9px] font-semibold uppercase tracking-[0.14em] text-vault-muted">
                                {{ __($group['heading']) }}
                            </div>
                        @endif
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ $item['href'] }}"
                                wire:navigate
                                class="group flex w-full items-center gap-[11px] px-6 py-2.5 transition-all border-l-2 {{ $item['current'] ? 'bg-[color-mix(in_srgb,var(--color-vault-accent)_15%,transparent)] border-vault-accent' : 'border-transparent hover:bg-vault-card-hov' }}"
                            >
                                <flux:icon :name="$item['icon']" variant="micro" class="!size-4 {{ $item['current'] ? 'text-vault-accent' : 'text-vault-muted' }}" />
                                <span class="text-[13px] {{ $item['current'] ? 'font-semibold text-vault-text' : 'font-normal text-vault-textsub' }}">
                                    {{ __($item['label']) }}
                                </span>
                                @if ($item['current'])
                                    <span class="ml-auto block size-[5px] rounded-full bg-vault-accent"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            {{-- User pill / settings --}}
            <a
                href="{{ route('appearance.edit') }}"
                wire:navigate
                class="flex items-center gap-2.5 px-6 py-4 border-t border-vault-sidebar-bd hover:bg-vault-card-hov transition-colors"
            >
                <span class="flex size-8 flex-shrink-0 items-center justify-center rounded-full border border-vault-card-bd"
                    style="background: color-mix(in srgb, var(--color-vault-accent) 15%, transparent);">
                    <span class="text-[11px] font-semibold text-vault-accent">{{ \Illuminate\Support\Str::of(config('app.name'))->substr(0, 2)->upper() }}</span>
                </span>
                <span class="leading-tight">
                    <span class="block text-[12px] font-medium text-vault-text">{{ config('app.name', 'Astute') }}</span>
                    <span class="block text-[10px] text-vault-muted">{{ __('Settings') }}</span>
                </span>
            </a>
        </flux:sidebar>

        {{-- Mobile Header --}}
        <flux:header class="lg:hidden !bg-vault-sidebar !border-b !border-vault-sidebar-bd">
            <flux:sidebar.toggle class="lg:hidden text-vault-textsub" icon="bars-2" inset="left" />

            <flux:spacer />

            <a href="{{ route('appearance.edit') }}" wire:navigate aria-label="{{ __('Settings') }}" class="text-vault-textsub p-2">
                <flux:icon name="cog-6-tooth" variant="micro" />
            </a>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>

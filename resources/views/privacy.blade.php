<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="mx-auto max-w-2xl px-6 py-12">
            <div class="mb-8">
                <flux:heading size="xl">{{ __('Privacy Policy') }}</flux:heading>
                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Last updated: :date', ['date' => 'March 8, 2026']) }}
                </flux:text>
            </div>

            <div class="space-y-6 text-sm text-zinc-700 dark:text-zinc-300">
                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('Your Data Stays on Your Device') }}</flux:heading>
                    <p>{{ config('app.name') }} {{ __('is a desktop application. All of your financial data — spending plans, account balances, expenses, and personal details — is stored locally on your computer in a SQLite database. Nothing is sent to external servers.') }}</p>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('What We Don\'t Do') }}</flux:heading>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>{{ __('We do not collect or transmit your personal data.') }}</li>
                        <li>{{ __('We do not use analytics, tracking, or telemetry.') }}</li>
                        <li>{{ __('We do not share your data with third parties.') }}</li>
                        <li>{{ __('We do not require an account or login.') }}</li>
                    </ul>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('Removing Your Data') }}</flux:heading>
                    <p>{{ __('Since all data is stored locally, uninstalling the application removes everything. You can also delete the application\'s data directory to remove all stored information.') }}</p>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('Updates') }}</flux:heading>
                    <p>{{ __('The application checks for updates via GitHub Releases. No personal data is sent during this process.') }}</p>
                </section>
            </div>

            <div class="mt-10">
                <flux:button variant="subtle" size="sm" href="{{ url()->previous(route('dashboard')) }}">
                    &larr; {{ __('Back') }}
                </flux:button>
            </div>
        </div>
        @fluxScripts
    </body>
</html>

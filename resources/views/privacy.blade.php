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
                    {{ __('Last updated: :date', ['date' => 'February 10, 2026']) }}
                </flux:text>
            </div>

            <div class="space-y-6 text-sm text-zinc-700 dark:text-zinc-300">
                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('What We Collect') }}</flux:heading>
                    <p>{{ config('app.name') }} {{ __('collects only the information you provide directly: your name, email address, and the financial data you enter into the application (spending plans, account balances, etc.).') }}</p>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('How We Use Your Data') }}</flux:heading>
                    <p>{{ __('Your personal data is used solely to provide the application\'s functionality to you. We may also use it for debugging and resolving technical issues.') }}</p>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('What We Don\'t Do') }}</flux:heading>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>{{ __('We do not sell your personal data. Ever.') }}</li>
                        <li>{{ __('We do not share your data with third parties.') }}</li>
                        <li>{{ __('We do not use your data for advertising or marketing purposes.') }}</li>
                        <li>{{ __('We do not use analytics or tracking tools.') }}</li>
                    </ul>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('Data Storage') }}</flux:heading>
                    <p>{{ __('Your data is stored securely and is only accessible to you through your authenticated account.') }}</p>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('Account Deletion') }}</flux:heading>
                    <p>{{ __('You may delete your account at any time from your account settings. This will permanently remove all of your data.') }}</p>
                </section>

                <section>
                    <flux:heading size="lg" class="mb-2">{{ __('Contact') }}</flux:heading>
                    <p>{{ __('If you have any questions about this privacy policy, please reach out to the application administrator.') }}</p>
                </section>
            </div>

            <div class="mt-10">
                <flux:button variant="subtle" size="sm" href="{{ url()->previous(route('home')) }}">
                    &larr; {{ __('Back') }}
                </flux:button>
            </div>
        </div>
        @fluxScripts
    </body>
</html>

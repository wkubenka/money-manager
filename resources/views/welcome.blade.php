<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        {{-- Nav --}}
        <nav class="flex items-center justify-between px-6 py-5 sm:px-10">
            <div class="flex items-center gap-2.5">
                <x-app-logo-icon class="size-8 fill-current text-emerald-500" />
                <span class="text-lg font-semibold text-white">{{ config('app.name') }}</span>
            </div>
            <div class="flex items-center gap-3">
                <flux:button :href="route('login')" variant="subtle" size="sm">{{ __('Log in') }}</flux:button>
                <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Sign up') }}</flux:button>
            </div>
        </nav>

        {{-- Hero --}}
        <section class="mx-auto flex max-w-3xl flex-col items-center px-6 pb-28 pt-24 text-center sm:pt-32">
            <flux:badge color="emerald" size="sm" class="mb-6">Free &amp; open source</flux:badge>

            <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                Your money, on your terms
            </h1>

            <p class="mt-5 max-w-xl text-lg leading-relaxed text-zinc-400">
                A simple, friendly place to plan your spending, track your progress, and actually enjoy the money you earn &mdash; without anyone watching over your shoulder.
            </p>

            <div class="mt-8 flex items-center gap-3">
                <flux:button :href="route('register')" variant="primary" class="!text-base !px-6 !py-2.5">{{ __('Get Started for Free') }}</flux:button>
                <flux:button :href="route('login')" variant="filled" class="!text-base !px-6 !py-2.5">{{ __('Log in') }}</flux:button>
            </div>
        </section>

        {{-- Philosophy --}}
        <section class="mx-auto max-w-4xl px-6 pb-28">
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900/50 px-8 py-12 text-center sm:px-16">
                <h2 class="text-2xl font-semibold text-white">Money doesn&rsquo;t have to be stressful</h2>

                <p class="mx-auto mt-4 max-w-2xl text-zinc-400 leading-relaxed">
                    You don&rsquo;t need a complicated spreadsheet or a guilt trip every time you buy a coffee. You just need a plan that lets you <span class="text-white font-medium">spend freely on what you love</span> because you&rsquo;ve already taken care of the rest.
                </p>
            </div>
        </section>

        {{-- Features --}}
        <section class="mx-auto max-w-5xl px-6 pb-28">
            <div class="mb-12 text-center">
                <h2 class="text-2xl font-semibold text-white">Everything you need, nothing you don&rsquo;t</h2>
                <p class="mt-2 text-zinc-400">Three tools that work together to give you clarity and confidence.</p>
            </div>

            <div class="grid gap-6" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-8 text-center">
                    <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-500/10">
                        <flux:icon.banknotes class="size-6 text-emerald-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">Conscious Spending</h3>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-400">
                        Split your income into four simple buckets &mdash; fixed costs, savings, investments, and guilt-free spending. No guesswork, no surprises.
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-8 text-center">
                    <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-500/10">
                        <flux:icon.arrow-trending-up class="size-6 text-emerald-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">Net Worth Tracking</h3>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-400">
                        See your full financial picture at a glance. Watching your net worth grow &mdash; even slowly &mdash; is one of the most motivating things you can do.
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-8 text-center">
                    <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-500/10">
                        <flux:icon.heart class="size-6 text-emerald-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">Guilt-Free Spending</h3>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-400">
                        Once your essentials, savings, and investments are handled, the rest is yours. Spend it on whatever makes you happy.
                    </p>
                </div>
            </div>
        </section>

        {{-- Privacy --}}
        <section class="mx-auto max-w-5xl px-6 pb-28">
            <div class="mb-12 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-500/10">
                    <flux:icon.shield-check class="size-7 text-emerald-500" />
                </div>
                <h2 class="text-2xl font-semibold text-white">Your data stays yours</h2>
                <p class="mx-auto mt-2 max-w-xl text-zinc-400">
                    {{ config('app.name') }} is built for you, not advertisers. Here&rsquo;s what that means:
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-start gap-4 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
                    <flux:icon.x-circle class="mt-0.5 size-5 shrink-0 text-red-400" />
                    <div>
                        <p class="font-medium text-white">No data selling &mdash; ever</p>
                        <p class="mt-1 text-sm text-zinc-400">Your financial data is never sold or shared with third parties.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
                    <flux:icon.x-circle class="mt-0.5 size-5 shrink-0 text-red-400" />
                    <div>
                        <p class="font-medium text-white">No tracking or analytics</p>
                        <p class="mt-1 text-sm text-zinc-400">No cookies, no pixels, no third-party scripts watching what you do.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
                    <flux:icon.x-circle class="mt-0.5 size-5 shrink-0 text-red-400" />
                    <div>
                        <p class="font-medium text-white">No ads or marketing</p>
                        <p class="mt-1 text-sm text-zinc-400">Your data is used to power your experience &mdash; that&rsquo;s it.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
                    <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                    <div>
                        <p class="font-medium text-white">Full account deletion</p>
                        <p class="mt-1 text-sm text-zinc-400">Delete your account anytime and every byte of your data goes with it.</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <flux:link :href="route('privacy')" class="text-sm">{{ __('Read the full privacy policy') }} &rarr;</flux:link>
            </div>
        </section>

        {{-- Footer CTA --}}
        <section class="border-t border-zinc-800 px-6 py-20 text-center">
            <h2 class="text-2xl font-semibold text-white">Ready to take control of your money?</h2>
            <p class="mt-3 text-zinc-400">It&rsquo;s free, it&rsquo;s private, and it takes about a minute to get started.</p>

            <div class="mt-8">
                <flux:button :href="route('register')" variant="primary" class="!text-base !px-6 !py-2.5">{{ __('Create Your Free Account') }}</flux:button>
            </div>

            <div class="mt-10 flex items-center justify-center gap-2.5 text-zinc-500">
                <x-app-logo-icon class="size-5 fill-current" />
                <span class="text-sm">{{ config('app.name') }}</span>
            </div>
        </section>

    </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6">
            <div class="flex flex-col items-center gap-4">
                <span class="flex h-12 w-12 items-center justify-center rounded-md">
                    <x-app-logo-icon class="size-12 fill-current text-emerald-500" />
                </span>
                <flux:heading size="xl">{{ config('app.name') }}</flux:heading>
            </div>

            <div class="flex items-center gap-3">
                <flux:button :href="route('login')" variant="primary">{{ __('Log in') }}</flux:button>
                <flux:button :href="route('register')" variant="filled">{{ __('Register') }}</flux:button>
            </div>

            <flux:link :href="route('privacy')" class="text-sm">{{ __('Privacy Policy') }}</flux:link>
        </div>
        @fluxScripts
    </body>
</html>

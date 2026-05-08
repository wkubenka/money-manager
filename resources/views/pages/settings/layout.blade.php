@php
    $navItems = [
        ['label' => __('Appearance'), 'href' => route('appearance.edit'), 'current' => request()->routeIs('appearance.*')],
        ['label' => __('Data'), 'href' => route('data.edit'), 'current' => request()->routeIs('data.*')],
        ['label' => __('Privacy Policy'), 'href' => route('privacy'), 'current' => request()->routeIs('privacy'), 'navigate' => false],
    ];
@endphp

<div class="flex items-start gap-10 max-md:flex-col">
    <aside class="w-full md:w-[220px]">
        <div class="eyebrow mb-3">{{ __('Settings') }}</div>
        <nav class="flex flex-col gap-0.5">
            @foreach ($navItems as $item)
                <a
                    href="{{ $item['href'] }}"
                    @if ($item['navigate'] ?? true) wire:navigate @endif
                    class="rounded-lg transition-colors {{ $item['current'] ? 'bg-vault-card-hov text-vault-text' : 'text-vault-textsub hover:bg-vault-card-hov hover:text-vault-text' }}"
                    style="padding: 9px 12px; font-size: 13px;"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="flex-1 self-stretch min-w-0 max-md:pt-6">
        @if (! empty($heading))
            <div class="mb-1 font-display text-vault-text" style="font-size: 24px;">{{ $heading }}</div>
        @endif
        @if (! empty($subheading))
            <p class="text-[13px] text-vault-textsub mb-6">{{ $subheading }}</p>
        @endif

        <div class="w-full">
            {{ $slot }}
        </div>
    </div>
</div>

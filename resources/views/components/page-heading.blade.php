@props(['title', 'subtitle' => null, 'eyebrow' => null])

<div class="relative mb-7 w-full">
    @if($eyebrow)
        <div class="eyebrow mb-1.5" style="letter-spacing: 0.15em;">{{ __($eyebrow) }}</div>
    @endif
    <h1 class="font-display text-[30px] leading-[1.1] text-vault-text">{{ __($title) }}</h1>
    @if($subtitle)
        <p class="mt-1.5 text-[13px] text-vault-textsub">{{ __($subtitle) }}</p>
    @endif
</div>

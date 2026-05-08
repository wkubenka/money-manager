@php
    $windfallBuckets = [
        ['key' => 'Savings',     'wire' => 'windfallSavings',     'color' => '#c8a96e', 'pct' => $windfallSavings],
        ['key' => 'Investments', 'wire' => 'windfallInvestments', 'color' => '#4ebb78', 'pct' => $windfallInvestments],
        ['key' => 'Guilt-Free',  'wire' => 'windfallGuiltFree',   'color' => '#c084fc', 'pct' => $windfallGuiltFree],
        ['key' => 'Debt',        'wire' => 'windfallDebt',        'color' => '#6da6d8', 'pct' => $windfallDebt],
    ];

    $windfallTotal = $windfallSavings + $windfallInvestments + $windfallGuiltFree + $windfallDebt;
    $windfallValid = $windfallTotal === 100;
@endphp

<div class="order-6 rounded-xl border border-vault-card-bd bg-vault-card" style="padding: 20px 26px;">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-3.5">
        <div>
            <div class="eyebrow text-vault-textsub" style="letter-spacing: 0.15em;">{{ __('Windfall Plan') }}</div>
            <div class="font-display text-vault-text" style="font-size: 16px; font-weight: 300;">
                {{ __('How to split unexpected income') }}
            </div>
        </div>
        @if (! $windfallEditing)
            <flux:button
                variant="subtle"
                size="sm"
                icon="pencil-square"
                wire:click="$set('windfallEditing', true)"
                aria-label="{{ __('Edit windfall plan') }}"
            />
        @else
            <div class="flex items-center gap-1">
                <flux:button
                    variant="primary"
                    size="sm"
                    wire:click="saveWindfallPlan"
                    :disabled="! $windfallValid"
                >
                    {{ __('Save') }}
                </flux:button>
                <flux:button
                    variant="ghost"
                    size="sm"
                    wire:click="cancelWindfall"
                >
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        @endif
    </div>

    {{-- 4 horizontal tiles --}}
    <div class="flex gap-3">
        @foreach ($windfallBuckets as $bucket)
            <div
                class="flex-1 rounded-[10px] text-center"
                style="
                    background: {{ $bucket['color'] }}1f;
                    border: 1px solid {{ $bucket['color'] }}4d;
                    padding: 14px 16px;
                "
            >
                @if ($windfallEditing)
                    <div class="flex items-center justify-center gap-1">
                        <input
                            type="number"
                            min="0"
                            max="100"
                            wire:model.live="{{ $bucket['wire'] }}"
                            class="w-12 bg-transparent text-center font-display font-light outline-none"
                            style="font-size: 24px; color: {{ $bucket['color'] }};"
                        />
                        <span class="font-display font-light" style="font-size: 24px; color: {{ $bucket['color'] }};">%</span>
                    </div>
                @else
                    <div class="font-display font-light" style="font-size: 24px; color: {{ $bucket['color'] }};">
                        {{ $bucket['pct'] }}%
                    </div>
                @endif
                <div class="font-sans text-vault-muted mt-1" style="font-size: 11px;">
                    {{ __($bucket['key']) }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Validation notice (editing only) --}}
    @if ($windfallEditing)
        <div class="mt-3 text-xs text-center {{ $windfallValid ? 'text-vault-accent' : 'text-vault-warm' }}">
            @if ($windfallValid)
                {{ __('✓ Splits add up to 100%') }}
            @else
                {{ __('Total: ') }}{{ $windfallTotal }}{{ __('% — must equal 100%') }}
            @endif
        </div>
    @endif

    {{-- Footer note --}}
    @if (! $windfallEditing)
        <p class="mt-3 text-center italic text-vault-muted" style="font-size: 11px;">
            {{ __('Applied to bonuses, tax refunds, gifts & other windfalls') }}
        </p>
    @endif

</div>

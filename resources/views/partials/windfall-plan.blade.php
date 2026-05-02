<?php
/**
 * Windfall Plan dashboard card.
 *
 * Drop this file at:
 *   resources/views/partials/windfall-plan.blade.php
 *
 * Then include it in ⚡dashboard.blade.php inside the right column,
 * after the "Investments at Retirement" card:
 *
 *   @include('partials.windfall-plan')
 *
 * The Livewire component (⚡dashboard.blade.php) needs two additions:
 *
 *   1. At the top, import the model:
 *        use App\Models\WindfallPlan;
 *
 *   2. Add these public properties + methods to the inline class:
 *
 *        public bool  $windfallEditing          = false;
 *        public int   $windfallSavings          = 0;
 *        public int   $windfallInvestments      = 0;
 *        public int   $windfallGuiltFree        = 0;
 *        public int   $windfallDebt             = 0;
 *
 *        public function mountWindfall(): void
 *        {
 *            // Call from mount():
 *            $plan = WindfallPlan::instance();
 *            $this->windfallSavings     = $plan->savings_percent;
 *            $this->windfallInvestments = $plan->investments_percent;
 *            $this->windfallGuiltFree   = $plan->guilt_free_percent;
 *            $this->windfallDebt        = $plan->debt_percent;
 *        }
 *
 *        public function saveWindfallPlan(): void
 *        {
 *            $this->validate([
 *                'windfallSavings'     => ['required', 'integer', 'min:0', 'max:100'],
 *                'windfallInvestments' => ['required', 'integer', 'min:0', 'max:100'],
 *                'windfallGuiltFree'   => ['required', 'integer', 'min:0', 'max:100'],
 *                'windfallDebt'        => ['required', 'integer', 'min:0', 'max:100'],
 *            ]);
 *
 *            $total = $this->windfallSavings
 *                   + $this->windfallInvestments
 *                   + $this->windfallGuiltFree
 *                   + $this->windfallDebt;
 *
 *            $this->addError('windfallSavings', 'Splits must add up to 100%.');
 *
 *            if ($total !== 100) {
 *                $this->addError('windfallSavings', 'Splits must add up to 100%.');
 *                return;
 *            }
 *
 *            WindfallPlan::instance()->update([
 *                'savings_percent'     => $this->windfallSavings,
 *                'investments_percent' => $this->windfallInvestments,
 *                'guilt_free_percent'  => $this->windfallGuiltFree,
 *                'debt_percent'        => $this->windfallDebt,
 *            ]);
 *
 *            $this->windfallEditing = false;
 *            $this->dispatch('windfall-saved');
 *        }
 *
 *        public function cancelWindfall(): void
 *        {
 *            $plan = WindfallPlan::instance();
 *            $this->windfallSavings     = $plan->savings_percent;
 *            $this->windfallInvestments = $plan->investments_percent;
 *            $this->windfallGuiltFree   = $plan->guilt_free_percent;
 *            $this->windfallDebt        = $plan->debt_percent;
 *            $this->windfallEditing     = false;
 *        }
 *
 *   3. Inside mount(), call: $this->mountWindfall();
 */
?>

@php
    $windfallBuckets = [
        ['key' => 'Savings',     'wire' => 'windfallSavings',     'color' => 'bg-cyan-500',    'badge' => 'cyan',    'pct' => $windfallSavings],
        ['key' => 'Investments', 'wire' => 'windfallInvestments', 'color' => 'bg-emerald-500', 'badge' => 'emerald', 'pct' => $windfallInvestments],
        ['key' => 'Guilt-Free',  'wire' => 'windfallGuiltFree',   'color' => 'bg-purple-500',  'badge' => 'purple',  'pct' => $windfallGuiltFree],
        ['key' => 'Debt',        'wire' => 'windfallDebt',        'color' => 'bg-blue-500',    'badge' => 'blue',    'pct' => $windfallDebt],
    ];

    $windfallTotal = $windfallSavings + $windfallInvestments + $windfallGuiltFree + $windfallDebt;
    $windfallValid = $windfallTotal === 100;
@endphp

<div class="order-6 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-4">
        <div>
            <flux:subheading>{{ __('Windfall Plan') }}</flux:subheading>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 leading-snug">
                {{ __('How to split unexpected income') }}
            </p>
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

    {{-- Donut + rows --}}
    <div class="flex items-center gap-5">

        {{-- SVG donut --}}
        @php
            $donutR      = 36;
            $donutStroke = 14;
            $donutCirc   = 2 * M_PI * $donutR;
            $donutOffset = 0;

            $donutSegments = collect($windfallBuckets)->map(function ($b) use (&$donutOffset, $donutCirc, $windfallTotal) {
                $pct  = $windfallTotal > 0 ? $b['pct'] / $windfallTotal : 0;
                $dash = $pct * $donutCirc;
                $gap  = $donutCirc - $dash;
                $off  = $donutOffset * $donutCirc / ($windfallTotal ?: 1);
                $donutOffset += $b['pct'];
                return array_merge($b, ['dash' => $dash, 'gap' => $gap, 'svgOffset' => $off]);
            })->filter(fn ($s) => $s['dash'] > 0);

            $colorMap = [
                'bg-cyan-500'    => '#06b6d4',
                'bg-emerald-500' => '#10b981',
                'bg-purple-500'  => '#a855f7',
                'bg-blue-500'    => '#3b82f6',
            ];
        @endphp

        <div class="relative shrink-0" style="width:96px;height:96px;">
            <svg width="96" height="96" style="transform:rotate(-90deg)">
                {{-- track --}}
                <circle
                    cx="48" cy="48" r="{{ $donutR }}"
                    fill="none"
                    stroke="currentColor"
                    class="text-zinc-100 dark:text-zinc-700"
                    stroke-width="{{ $donutStroke }}"
                />
                {{-- segments --}}
                @foreach ($donutSegments as $seg)
                    <circle
                        cx="48" cy="48" r="{{ $donutR }}"
                        fill="none"
                        stroke="{{ $colorMap[$seg['color']] }}"
                        stroke-width="{{ $donutStroke }}"
                        stroke-dasharray="{{ round($seg['dash'], 3) }} {{ round($seg['gap'], 3) }}"
                        stroke-dashoffset="{{ round(-$seg['svgOffset'], 3) }}"
                        stroke-linecap="butt"
                    />
                @endforeach
            </svg>
            {{-- centre label --}}
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span class="text-[10px] font-semibold tracking-wider uppercase text-zinc-400 dark:text-zinc-500 leading-none">
                    {{ __('split') }}
                </span>
                <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100 leading-none mt-0.5">
                    {{ count($windfallBuckets) }}
                </span>
            </div>
        </div>

        {{-- Bucket rows --}}
        <div class="flex-1 space-y-2.5">
            @foreach ($windfallBuckets as $bucket)
                <div class="flex items-center gap-2">
                    <div class="size-2 rounded-full {{ $bucket['color'] }} shrink-0"></div>
                    <span class="flex-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $bucket['key'] }}</span>
                    @if ($windfallEditing)
                        <div class="flex items-center gap-1">
                            <flux:input
                                wire:model.live="{{ $bucket['wire'] }}"
                                type="number"
                                min="0"
                                max="100"
                                size="sm"
                                class="w-14 text-right"
                            />
                            <span class="text-xs text-zinc-500">%</span>
                        </div>
                    @else
                        <flux:badge size="sm" color="{{ $bucket['badge'] }}" class="w-10 justify-center">
                            {{ $bucket['pct'] }}%
                        </flux:badge>
                    @endif
                </div>
            @endforeach
        </div>

    </div>

    {{-- Validation notice (editing only) --}}
    @if ($windfallEditing)
        <div class="mt-3 text-xs text-center {{ $windfallValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
            @if ($windfallValid)
                {{ __('✓ Splits add up to 100%') }}
            @else
                {{ __('Total: ') }}{{ $windfallTotal }}{{ __('% — must equal 100%') }}
            @endif
        </div>
    @endif

    {{-- Footer note --}}
    @if (! $windfallEditing)
        <flux:separator variant="subtle" class="mt-4 mb-3" />
        <p class="text-xs text-center italic text-zinc-400 dark:text-zinc-500">
            {{ __('Applied to bonuses, tax refunds, gifts & other windfalls') }}
        </p>
    @endif

</div>

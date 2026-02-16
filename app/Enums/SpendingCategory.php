<?php

namespace App\Enums;

enum SpendingCategory: string
{
    case FixedCosts = 'fixed_costs';
    case Investments = 'investments';
    case Savings = 'savings';
    case GuiltFree = 'guilt_free';
    case Ignored = 'ignored';

    /**
     * Get only the 4 spending plan categories (excludes Ignored).
     *
     * @return array<self>
     */
    public static function spendingCases(): array
    {
        return [
            self::FixedCosts,
            self::Investments,
            self::Savings,
            self::GuiltFree,
        ];
    }

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::FixedCosts => 'Fixed Costs',
            self::Investments => 'Investments',
            self::Savings => 'Savings',
            self::GuiltFree => 'Guilt-Free',
            self::Ignored => 'Ignored',
        };
    }

    /**
     * Get the ideal percentage range as [min, max].
     *
     * @return array{float, float}|null
     */
    public function idealRange(): ?array
    {
        return match ($this) {
            self::FixedCosts => [50, 60],
            self::Investments => [10, 10],
            self::Savings => [5, 10],
            self::GuiltFree => [20, 35],
            self::Ignored => null,
        };
    }

    /**
     * Get the Tailwind color class for this category's bar.
     */
    public function color(): string
    {
        return match ($this) {
            self::FixedCosts => 'bg-blue-500',
            self::Investments => 'bg-emerald-500',
            self::Savings => 'bg-cyan-500',
            self::GuiltFree => 'bg-purple-500',
            self::Ignored => 'bg-zinc-400',
        };
    }

    /**
     * Get the Flux-compatible color name for badges.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::FixedCosts => 'blue',
            self::Investments => 'emerald',
            self::Savings => 'cyan',
            self::GuiltFree => 'purple',
            self::Ignored => 'zinc',
        };
    }

    /**
     * Get whether the actual percentage is acceptable.
     * For Fixed Costs, under the max is good (lower is better).
     * For Investments/Savings, exceeding the ideal is good when guilt-free is healthy.
     * For other categories, must be within the ideal range.
     */
    public function isWithinIdeal(float $actualPercent, bool $guiltFreeIsHealthy = false): bool
    {
        $range = $this->idealRange();

        if ($range === null) {
            return false;
        }

        [$min, $max] = $range;

        if ($this === self::FixedCosts) {
            return $actualPercent <= $max;
        }

        if ($guiltFreeIsHealthy && ($this === self::Investments || $this === self::Savings)) {
            return $actualPercent >= $min;
        }

        return $actualPercent >= $min && $actualPercent <= $max;
    }
}

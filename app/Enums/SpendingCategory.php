<?php

namespace App\Enums;

enum SpendingCategory: string
{
    case FixedCosts = 'fixed_costs';
    case Investments = 'investments';
    case Savings = 'savings';
    case GuiltFree = 'guilt_free';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::FixedCosts => 'Fixed Costs',
            self::Investments => 'Investments',
            self::Savings => 'Savings',
            self::GuiltFree => 'Guilt-Free Spending',
        };
    }

    /**
     * Get the ideal percentage range as [min, max].
     *
     * @return array{float, float}
     */
    public function idealRange(): array
    {
        return match ($this) {
            self::FixedCosts => [50, 60],
            self::Investments => [10, 10],
            self::Savings => [5, 10],
            self::GuiltFree => [20, 35],
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
            self::Savings => 'bg-amber-500',
            self::GuiltFree => 'bg-purple-500',
        };
    }

    /**
     * Get whether the actual percentage is acceptable.
     * For Fixed Costs, under the max is good (lower is better).
     * For other categories, must be within the ideal range.
     */
    public function isWithinIdeal(float $actualPercent): bool
    {
        [$min, $max] = $this->idealRange();

        if ($this === self::FixedCosts) {
            return $actualPercent <= $max;
        }

        return $actualPercent >= $min && $actualPercent <= $max;
    }
}

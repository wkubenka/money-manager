<?php

namespace App\Enums;

enum AccountCategory: string
{
    case Assets = 'assets';
    case Investments = 'investments';
    case Savings = 'savings';
    case Debt = 'debt';

    public function label(): string
    {
        return match ($this) {
            self::Assets => 'Assets',
            self::Investments => 'Investments',
            self::Savings => 'Savings',
            self::Debt => 'Debt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Assets => 'bg-blue-500',
            self::Investments => 'bg-emerald-500',
            self::Savings => 'bg-purple-500',
            self::Debt => 'bg-red-500',
        };
    }

    /**
     * Hex color for the Vault design palette.
     */
    public function vaultColor(): string
    {
        return match ($this) {
            self::Assets => '#6da6d8',      // blue
            self::Investments => '#4ebb78', // sage
            self::Savings => '#c8a96e',     // warm
            self::Debt => '#e07070',        // red
        };
    }

    /**
     * Brief description shown under the label in Net Worth cards.
     */
    public function description(): string
    {
        return match ($this) {
            self::Assets => 'Things you own',
            self::Investments => 'Money working for you',
            self::Savings => 'Cash reserves',
            self::Debt => 'What you owe',
        };
    }

    /**
     * Whether this category is subtracted from the net worth total.
     */
    public function isDeducted(): bool
    {
        return $this === self::Debt;
    }
}

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
     * Whether this category is subtracted from the net worth total.
     */
    public function isDeducted(): bool
    {
        return $this === self::Debt;
    }
}

<?php

namespace App\Models;

use App\Enums\SpendingCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpendingPlan extends Model
{
    /** @use HasFactory<\Database\Factories\SpendingPlanFactory> */
    use HasFactory;

    public const MAX_PER_USER = 6;

    public const MAX_ITEMS_PER_CATEGORY = 15;

    /** @var array<string, list<string>> */
    public const DEFAULT_ITEMS = [
        'fixed_costs' => [
            'Rent / Mortgage',
            'Utilities (gas, water, electric, internet, cable, etc.)',
            'Insurance (medical, auto, home / renters, etc.)',
            'Car Payment / Transportation',
            'Debt Payments',
            'Groceries',
            'Clothes',
            'Phone',
            'Subscriptions (Netflix, gym membership, meal services, Amazon, etc.)',
        ],
        'investments' => [
            'Post-Tax Retirement Savings',
            'Stocks',
        ],
        'savings' => [
            'Vacations',
            'Gifts',
        ],
    ];

    protected $fillable = [
        'user_id',
        'name',
        'monthly_income',
        'gross_monthly_income',
        'pre_tax_investments',
        'is_current',
        'fixed_costs_misc_percent',
    ];

    protected function casts(): array
    {
        return [
            'monthly_income' => 'integer',
            'gross_monthly_income' => 'integer',
            'pre_tax_investments' => 'integer',
            'is_current' => 'boolean',
            'fixed_costs_misc_percent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SpendingPlanItem::class)->orderBy('sort_order');
    }

    /**
     * Get the miscellaneous buffer for Fixed Costs (15% of fixed cost items).
     */
    public function fixedCostsMiscellaneous(): int
    {
        if ($this->fixed_costs_misc_percent === 0) {
            return 0;
        }

        $itemsTotal = (int) $this->items->where('category', SpendingCategory::FixedCosts)->sum('amount');

        return (int) round($itemsTotal * $this->fixed_costs_misc_percent / 100);
    }

    /**
     * Get the total amount for a given category in cents.
     * Fixed Costs includes a miscellaneous buffer.
     * Guilt-Free is auto-calculated as the remainder after the other three categories.
     */
    public function categoryTotal(SpendingCategory $category): int
    {
        if ($category === SpendingCategory::GuiltFree) {
            return $this->monthly_income - $this->plannedTotal();
        }

        $itemsTotal = (int) $this->items->where('category', $category)->sum('amount');

        if ($category === SpendingCategory::FixedCosts) {
            return $itemsTotal + $this->fixedCostsMiscellaneous();
        }

        return $itemsTotal;
    }

    /**
     * Get the percentage of income for a given category.
     */
    public function categoryPercent(SpendingCategory $category): float
    {
        if ($this->monthly_income === 0) {
            return 0;
        }

        return round(($this->categoryTotal($category) / $this->monthly_income) * 100, 1);
    }

    /**
     * Get the total of all planned items (Fixed Costs + Investments + Savings) in cents.
     */
    public function plannedTotal(): int
    {
        return (int) $this->items->sum('amount') + $this->fixedCostsMiscellaneous();
    }
}

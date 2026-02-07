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

    protected $fillable = [
        'user_id',
        'name',
        'monthly_income',
        'gross_monthly_income',
        'pre_tax_investments',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'monthly_income' => 'integer',
            'gross_monthly_income' => 'integer',
            'pre_tax_investments' => 'integer',
            'is_current' => 'boolean',
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
     * Get the total amount for a given category in cents.
     * Guilt-Free is auto-calculated as the remainder after the other three categories.
     */
    public function categoryTotal(SpendingCategory $category): int
    {
        if ($category === SpendingCategory::GuiltFree) {
            return $this->monthly_income - $this->plannedTotal();
        }

        return (int) $this->items
            ->where('category', $category)
            ->sum('amount');
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
        return (int) $this->items->sum('amount');
    }
}

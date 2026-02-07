<?php

namespace App\Models;

use App\Enums\SpendingCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpendingPlanItem extends Model
{
    /** @use HasFactory<\Database\Factories\SpendingPlanItemFactory> */
    use HasFactory;

    protected $fillable = [
        'spending_plan_id',
        'category',
        'name',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => SpendingCategory::class,
            'amount' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function spendingPlan(): BelongsTo
    {
        return $this->belongsTo(SpendingPlan::class);
    }
}

<?php

namespace App\Models;

use App\Enums\SpendingCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'expense_account_id',
        'merchant',
        'amount',
        'category',
        'date',
        'is_imported',
        'reference_number',
    ];

    protected function casts(): array
    {
        return [
            'category' => SpendingCategory::class,
            'amount' => 'integer',
            'date' => 'date',
            'is_imported' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ExpenseAccount::class);
    }
}

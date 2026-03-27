<?php

namespace App\Models;

use App\Enums\AccountCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetWorthAccount extends Model
{
    /** @use HasFactory<\Database\Factories\NetWorthAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'category',
        'name',
        'balance',
        'sort_order',
        'is_emergency_fund',
        'minimum_payment',
        'interest_rate',
    ];

    protected function casts(): array
    {
        return [
            'category' => AccountCategory::class,
            'balance' => 'integer',
            'sort_order' => 'integer',
            'is_emergency_fund' => 'boolean',
            'minimum_payment' => 'integer',
            'interest_rate' => 'decimal:2',
        ];
    }
}

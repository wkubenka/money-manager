<?php

namespace App\Models;

use App\Enums\AccountCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetWorthAccount extends Model
{
    /** @use HasFactory<\Database\Factories\NetWorthAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'name',
        'balance',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => AccountCategory::class,
            'balance' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

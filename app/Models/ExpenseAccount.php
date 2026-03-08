<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseAccount extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}

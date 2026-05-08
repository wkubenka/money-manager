<?php

namespace App\Models;

use Database\Factories\DebtScenarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtScenario extends Model
{
    /** @use HasFactory<DebtScenarioFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extra_payment_cents' => 'integer',
            'lump_sum_cents' => 'integer',
            'lump_sum_month' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}

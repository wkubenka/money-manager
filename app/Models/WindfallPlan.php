<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WindfallPlan extends Model
{
    protected $table = 'windfall_plan';

    protected $fillable = [
        'savings_percent',
        'investments_percent',
        'guilt_free_percent',
        'debt_percent',
    ];

    protected function casts(): array
    {
        return [
            'savings_percent' => 'integer',
            'investments_percent' => 'integer',
            'guilt_free_percent' => 'integer',
            'debt_percent' => 'integer',
        ];
    }

    /**
     * Return the single windfall plan row, creating it with defaults if absent.
     */
    public static function instance(): static
    {
        return static::firstOrCreate([], [
            'savings_percent' => 30,
            'investments_percent' => 50,
            'guilt_free_percent' => 10,
            'debt_percent' => 10,
        ]);
    }

    /**
     * Return the four split buckets as a labelled array for the view.
     *
     * @return array<int, array{key: string, label: string, color: string, percent: int}>
     */
    public function buckets(): array
    {
        return [
            ['key' => 'savings',     'label' => 'Savings',     'color' => 'cyan',    'percent' => $this->savings_percent],
            ['key' => 'investments', 'label' => 'Investments', 'color' => 'emerald', 'percent' => $this->investments_percent],
            ['key' => 'guilt_free',  'label' => 'Guilt-Free',  'color' => 'purple',  'percent' => $this->guilt_free_percent],
            ['key' => 'debt',        'label' => 'Debt',        'color' => 'blue',    'percent' => $this->debt_percent],
        ];
    }

    /**
     * Validate that the four splits sum to 100.
     */
    public function splitsAreValid(): bool
    {
        return ($this->savings_percent
            + $this->investments_percent
            + $this->guilt_free_percent
            + $this->debt_percent) === 100;
    }
}

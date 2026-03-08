<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'date_of_birth',
        'retirement_age',
        'expected_return',
        'withdrawal_rate',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'retirement_age' => 'integer',
            'expected_return' => 'decimal:1',
            'withdrawal_rate' => 'decimal:1',
        ];
    }

    public static function instance(): static
    {
        return static::firstOrCreate([]);
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }
}

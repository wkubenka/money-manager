<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'date_of_birth',
        'retirement_age',
        'expected_return',
        'withdrawal_rate',
        'emergency_fund_months',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'retirement_age' => 'integer',
            'expected_return' => 'decimal:1',
            'withdrawal_rate' => 'decimal:1',
            'emergency_fund_months' => 'integer',
        ];
    }

    public static function instance(): static
    {
        $profile = static::firstOrCreate([]);

        if ($profile->wasRecentlyCreated) {
            $profile->refresh();
        }

        return $profile;
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }
}

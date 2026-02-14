<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'date_of_birth',
        'retirement_age',
        'expected_return',
        'withdrawal_rate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'retirement_age' => 'integer',
            'expected_return' => 'decimal:1',
            'withdrawal_rate' => 'decimal:1',
        ];
    }

    public function spendingPlans(): HasMany
    {
        return $this->hasMany(SpendingPlan::class);
    }

    public function currentSpendingPlan(): ?SpendingPlan
    {
        return $this->spendingPlans()->where('is_current', true)->first();
    }

    public function netWorthAccounts(): HasMany
    {
        return $this->hasMany(NetWorthAccount::class);
    }

    public function richLifeVisions(): HasMany
    {
        return $this->hasMany(RichLifeVision::class);
    }

    public function expenseAccounts(): HasMany
    {
        return $this->hasMany(ExpenseAccount::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function emergencyFund(): ?NetWorthAccount
    {
        return $this->netWorthAccounts()->where('is_emergency_fund', true)->first();
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}

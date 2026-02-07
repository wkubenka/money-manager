<?php

namespace App\Observers;

use App\Enums\AccountCategory;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        $user->netWorthAccounts()->create([
            'category' => AccountCategory::Savings,
            'name' => 'Emergency Fund',
            'balance' => 0,
            'sort_order' => 0,
            'is_emergency_fund' => true,
        ]);
    }
}

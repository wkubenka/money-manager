<?php

namespace App\Actions;

use App\Models\SpendingPlan;
use App\Models\User;

class CopySpendingPlan
{
    public function __invoke(SpendingPlan $plan, User $user): SpendingPlan
    {
        abort_unless($plan->user_id === $user->id, 403);
        abort_if($user->spendingPlans()->count() >= SpendingPlan::MAX_PER_USER, 422);

        $copy = SpendingPlan::create([
            'user_id' => $user->id,
            'name' => "Copy of {$plan->name}",
            'monthly_income' => $plan->monthly_income,
            'gross_monthly_income' => $plan->gross_monthly_income,
            'pre_tax_investments' => $plan->pre_tax_investments,
            'fixed_costs_misc_percent' => $plan->fixed_costs_misc_percent,
            'is_current' => false,
        ]);

        foreach ($plan->items as $item) {
            $copy->items()->create([
                'category' => $item->category,
                'name' => $item->name,
                'amount' => $item->amount,
                'sort_order' => $item->sort_order,
            ]);
        }

        return $copy;
    }
}

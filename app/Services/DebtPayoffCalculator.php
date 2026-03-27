<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class DebtPayoffCalculator
{
    public const MAX_MONTHS = 360;

    /**
     * Calculate debt payoff timeline using the avalanche method.
     *
     * @param  Collection<int, array{balance: int, interest_rate: float, minimum_payment: int}>  $debts
     * @param  int  $totalMonthlyPaymentCents  Total monthly debt budget from spending plan (cents)
     * @return array{payoff_date: Carbon, months_to_payoff: int, total_interest_paid: int}|null
     */
    public function calculate(Collection $debts, int $totalMonthlyPaymentCents): ?array
    {
        if ($debts->isEmpty() || $totalMonthlyPaymentCents <= 0) {
            return null;
        }

        // Build working array sorted by interest rate descending (avalanche order)
        $working = $debts
            ->map(fn (array $debt) => [
                'balance' => (float) $debt['balance'],
                'interest_rate' => (float) $debt['interest_rate'],
                'minimum_payment' => (int) $debt['minimum_payment'],
                'monthly_rate' => ((float) $debt['interest_rate']) / 100 / 12,
            ])
            ->sortByDesc('interest_rate')
            ->values()
            ->all();

        $totalInterestPaid = 0;
        $months = 0;

        while ($months < self::MAX_MONTHS) {
            // Check if all debts are paid off
            $totalRemaining = array_sum(array_column($working, 'balance'));
            if ($totalRemaining <= 0) {
                break;
            }

            $months++;

            // Step 1: Accrue interest on each debt
            foreach ($working as &$debt) {
                if ($debt['balance'] <= 0) {
                    continue;
                }

                $interest = $debt['balance'] * $debt['monthly_rate'];
                $debt['balance'] += $interest;
                $totalInterestPaid += $interest;
            }
            unset($debt);

            // Step 2: Calculate surplus (total budget minus sum of active minimums)
            $activeMinimums = 0;
            foreach ($working as &$debt) {
                if ($debt['balance'] <= 0) {
                    continue;
                }

                $activeMinimums += $debt['minimum_payment'];
            }
            unset($debt);

            $surplus = max(0, $totalMonthlyPaymentCents - $activeMinimums);

            // Step 3: Apply minimum payments
            foreach ($working as &$debt) {
                if ($debt['balance'] <= 0) {
                    continue;
                }

                $payment = min($debt['minimum_payment'], $debt['balance']);
                $debt['balance'] -= $payment;
            }
            unset($debt);

            // Step 4: Apply surplus to highest-rate debt (already sorted)
            foreach ($working as &$debt) {
                if ($debt['balance'] <= 0 || $surplus <= 0) {
                    continue;
                }

                $extraPayment = min($surplus, $debt['balance']);
                $debt['balance'] -= $extraPayment;
                $surplus -= $extraPayment;
            }
            unset($debt);
        }

        return [
            'payoff_date' => Carbon::now()->addMonthsNoOverflow($months),
            'months_to_payoff' => $months,
            'total_interest_paid' => (int) round($totalInterestPaid),
        ];
    }
}

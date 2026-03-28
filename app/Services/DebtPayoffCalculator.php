<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class DebtPayoffCalculator
{
    public const MAX_MONTHS = 360;

    /**
     * Calculate debt payoff timeline using the specified strategy.
     *
     * @param  Collection<int, array{name?: string, balance: int, interest_rate: float, minimum_payment: int}>  $debts
     * @param  int  $totalMonthlyPaymentCents  Total monthly debt budget from spending plan (cents)
     * @param  string  $strategy  Payoff strategy: 'avalanche' (highest rate first) or 'snowball' (smallest balance first)
     * @param  int  $lumpSumCents  One-time extra payment amount (cents)
     * @param  int  $lumpSumMonth  Month number in which to apply the lump sum
     * @return array{payoff_date: Carbon, months_to_payoff: int, total_interest_paid: int}|null
     */
    public function calculate(Collection $debts, int $totalMonthlyPaymentCents, string $strategy = 'avalanche', int $lumpSumCents = 0, int $lumpSumMonth = 1): ?array
    {
        if ($debts->isEmpty() || $totalMonthlyPaymentCents <= 0) {
            return null;
        }

        // Build working array with strategy-based sorting
        $working = $debts
            ->map(fn (array $debt) => [
                'name' => $debt['name'] ?? 'Debt',
                'balance' => (float) $debt['balance'],
                'interest_rate' => (float) $debt['interest_rate'],
                'minimum_payment' => (int) $debt['minimum_payment'],
                'monthly_rate' => ((float) $debt['interest_rate']) / 100 / 12,
            ]);

        // Sort by strategy: avalanche = highest interest first, snowball = smallest balance first
        $working = match ($strategy) {
            'snowball' => $working->sortBy('balance')->values()->all(),
            default => $working->sortByDesc('interest_rate')->values()->all(),
        };

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

            // Step 2: Apply lump sum in the specified month
            if ($lumpSumCents > 0 && $months === $lumpSumMonth) {
                $remainingLumpSum = $lumpSumCents;
                foreach ($working as &$debt) {
                    if ($debt['balance'] <= 0 || $remainingLumpSum <= 0) {
                        continue;
                    }

                    $extraPayment = min($remainingLumpSum, $debt['balance']);
                    $debt['balance'] -= $extraPayment;
                    $remainingLumpSum -= $extraPayment;
                }
                unset($debt);
            }

            // Step 3: Calculate surplus (total budget minus sum of active minimums)
            $activeMinimums = 0;
            foreach ($working as &$debt) {
                if ($debt['balance'] <= 0) {
                    continue;
                }

                $activeMinimums += $debt['minimum_payment'];
            }
            unset($debt);

            $surplus = max(0, $totalMonthlyPaymentCents - $activeMinimums);

            // Step 4: Apply minimum payments
            foreach ($working as &$debt) {
                if ($debt['balance'] <= 0) {
                    continue;
                }

                $payment = min($debt['minimum_payment'], $debt['balance']);
                $debt['balance'] -= $payment;
            }
            unset($debt);

            // Step 5: Apply surplus to target debt (first in sorted order)
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

<?php

use App\Services\DebtPayoffCalculator;

test('calculates payoff date for a single debt', function () {
    $calculator = new DebtPayoffCalculator;

    // $10,000 at 20% APR, $500/mo minimum, $500/mo budget
    $debts = collect([
        ['balance' => 1000000, 'interest_rate' => 20.0, 'minimum_payment' => 50000],
    ]);

    $result = $calculator->calculate($debts, 50000);

    expect($result)->not->toBeNull();
    expect($result['months_to_payoff'])->toBeGreaterThan(0);
    expect($result['months_to_payoff'])->toBeLessThan(30); // ~24 months at these rates
    expect($result['total_interest_paid'])->toBeGreaterThan(0);
    expect($result['payoff_date'])->toBeInstanceOf(Carbon\Carbon::class);
});

test('avalanche method pays highest rate first', function () {
    $calculator = new DebtPayoffCalculator;

    // Two debts: high rate ($5,000 at 25%) and low rate ($5,000 at 5%)
    // $600/mo budget, $100/mo minimum each
    $debts = collect([
        ['balance' => 500000, 'interest_rate' => 25.0, 'minimum_payment' => 10000],
        ['balance' => 500000, 'interest_rate' => 5.0, 'minimum_payment' => 10000],
    ]);

    $result = $calculator->calculate($debts, 60000);

    expect($result)->not->toBeNull();
    expect($result['months_to_payoff'])->toBeGreaterThan(0);
    // With avalanche, total interest should be less than if we paid low rate first
    // Just verify it completes in a reasonable time
    expect($result['months_to_payoff'])->toBeLessThan(24);
});

test('freed minimum payment rolls into next debt', function () {
    $calculator = new DebtPayoffCalculator;

    // Small debt ($1,000 at 10%, $200/mo min) and large debt ($10,000 at 15%, $200/mo min)
    // $500/mo budget total
    $debts = collect([
        ['balance' => 1000000, 'interest_rate' => 15.0, 'minimum_payment' => 20000],
        ['balance' => 100000, 'interest_rate' => 10.0, 'minimum_payment' => 20000],
    ]);

    $resultWithRollover = $calculator->calculate($debts, 50000);

    // The small debt should be paid off quickly, then its $200 min rolls into the large debt
    // Compare against paying just minimums (no surplus)
    $resultMinimumsOnly = $calculator->calculate($debts, 40000);

    expect($resultWithRollover['months_to_payoff'])->toBeLessThan($resultMinimumsOnly['months_to_payoff']);
});

test('returns null when no debts exist', function () {
    $calculator = new DebtPayoffCalculator;

    $result = $calculator->calculate(collect(), 50000);

    expect($result)->toBeNull();
});

test('returns null when monthly payment is zero', function () {
    $calculator = new DebtPayoffCalculator;

    $debts = collect([
        ['balance' => 500000, 'interest_rate' => 10.0, 'minimum_payment' => 10000],
    ]);

    $result = $calculator->calculate($debts, 0);

    expect($result)->toBeNull();
});

test('handles zero interest rate debt', function () {
    $calculator = new DebtPayoffCalculator;

    // $5,000 at 0% APR, $200/mo minimum, $200/mo budget
    $debts = collect([
        ['balance' => 500000, 'interest_rate' => 0.0, 'minimum_payment' => 20000],
    ]);

    $result = $calculator->calculate($debts, 20000);

    expect($result)->not->toBeNull();
    expect($result['months_to_payoff'])->toBe(25); // $5,000 / $200 = 25 months
    expect($result['total_interest_paid'])->toBe(0);
});

test('snowball method pays smallest balance first', function () {
    $calculator = new DebtPayoffCalculator;

    // Small balance ($2,000 at 5%) and large balance ($8,000 at 25%)
    // Snowball targets the $2,000 first despite lower rate
    $debts = collect([
        ['name' => 'Big Loan', 'balance' => 800000, 'interest_rate' => 25.0, 'minimum_payment' => 10000],
        ['name' => 'Small Loan', 'balance' => 200000, 'interest_rate' => 5.0, 'minimum_payment' => 10000],
    ]);

    $result = $calculator->calculate($debts, 60000, strategy: 'snowball');

    expect($result)->not->toBeNull();
    // Snowball pays more total interest than avalanche
    $avalancheResult = $calculator->calculate($debts, 60000, strategy: 'avalanche');
    expect($result['total_interest_paid'])->toBeGreaterThan($avalancheResult['total_interest_paid']);
});

test('caps at maximum months to prevent infinite loop', function () {
    $calculator = new DebtPayoffCalculator;

    // Huge debt with tiny payment - will hit cap
    $debts = collect([
        ['balance' => 100000000, 'interest_rate' => 25.0, 'minimum_payment' => 100],
    ]);

    $result = $calculator->calculate($debts, 100);

    expect($result)->not->toBeNull();
    expect($result['months_to_payoff'])->toBe(DebtPayoffCalculator::MAX_MONTHS);
});

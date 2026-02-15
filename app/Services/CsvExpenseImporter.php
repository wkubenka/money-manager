<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseAccount;
use Carbon\Carbon;

class CsvExpenseImporter
{
    /**
     * Parse a CSV file and return rows ready for import.
     *
     * @return array{parsedRows: array, matchedExpenses: array, feedback: string}
     */
    public function parse(string $filePath, int $accountId, int $userId): array
    {
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            return ['parsedRows' => [], 'matchedExpenses' => [], 'feedback' => __('Could not read the file.')];
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return ['parsedRows' => [], 'matchedExpenses' => [], 'feedback' => __('The file appears to be empty.')];
        }

        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $dateCol = $this->detectColumn($headers, ['date', 'transaction date', 'posted date', 'posting date', 'post date']);
        $merchantCol = $this->detectColumn($headers, ['description', 'merchant', 'name', 'memo', 'payee', 'transaction']);
        $amountCol = $this->detectColumn($headers, ['amount', 'debit', 'total', 'charge']);
        $refCol = $this->detectColumn($headers, ['reference number', 'transaction id', 'reference', 'ref']);
        $statusCol = $this->detectColumn($headers, ['status']);

        if ($dateCol === null || $merchantCol === null || $amountCol === null) {
            fclose($handle);

            return ['parsedRows' => [], 'matchedExpenses' => [], 'feedback' => __('Could not detect Date, Description, or Amount columns in this file.')];
        }

        $requiredColCount = max($dateCol, $merchantCol, $amountCol);

        // Load existing reference numbers for this account (for re-import detection)
        $existingRefNumbers = Expense::where('user_id', $userId)
            ->where('expense_account_id', $accountId)
            ->whereNotNull('reference_number')
            ->pluck('reference_number')
            ->toArray();

        // Load unimported expenses as a consumable amount pool (for manual entry matching)
        $unimportedExpenses = Expense::where('user_id', $userId)
            ->where('expense_account_id', $accountId)
            ->where('is_imported', false)
            ->get(['id', 'amount', 'merchant', 'date']);

        $unimportedPool = [];
        foreach ($unimportedExpenses as $expense) {
            $unimportedPool[] = [
                'id' => $expense->id,
                'amount' => $expense->amount,
                'merchant' => $expense->merchant,
                'date' => $expense->date->format('Y-m-d'),
            ];
        }

        // First pass: collect all rows with raw signed amounts
        $rawRows = [];
        $hasNegativeAmounts = false;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) <= $requiredColCount) {
                continue;
            }

            // Filter out non-cleared transactions when status column exists
            if ($statusCol !== null && isset($row[$statusCol])) {
                if (strtolower(trim($row[$statusCol])) !== 'cleared') {
                    continue;
                }
            }

            $rawAmount = (float) str_replace([',', '$', ' '], '', $row[$amountCol]);

            if ($rawAmount < 0) {
                $hasNegativeAmounts = true;
            }

            $rawRows[] = [
                'rawAmount' => $rawAmount,
                'merchant' => trim($row[$merchantCol]),
                'dateStr' => trim($row[$dateCol]),
                'bankRef' => ($refCol !== null && isset($row[$refCol])) ? trim($row[$refCol]) : null,
                'csvRow' => $row,
            ];
        }

        fclose($handle);

        // Track occurrence counts for hash-based reference numbers
        $lineOccurrences = [];

        // Second pass: filter and build parsed rows
        $rows = [];
        $matchedExpenses = [];

        foreach ($rawRows as $raw) {
            // In signed-amount CSVs, positive values are credits/income — skip them
            if ($hasNegativeAmounts && $raw['rawAmount'] > 0) {
                continue;
            }

            $amount = abs($raw['rawAmount']);

            if ($amount <= 0) {
                continue;
            }

            $amountCents = (int) round($amount * 100);

            try {
                $date = Carbon::parse($raw['dateStr'])->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }

            // Build reference number: bank-provided or hash-generated with occurrence index
            $bankRef = $raw['bankRef'];
            if ($bankRef) {
                $referenceNumber = $bankRef;
            } else {
                $lineKey = implode('|', $raw['csvRow']);
                $lineOccurrences[$lineKey] = ($lineOccurrences[$lineKey] ?? 0) + 1;
                $referenceNumber = hash('xxh128', $lineKey.'|'.$lineOccurrences[$lineKey]);
            }

            // Step 1: Check reference number match (prevents re-importing)
            if (in_array($referenceNumber, $existingRefNumbers)) {
                continue;
            }

            // Step 2: Check amount match against unimported expenses (catches manual entries)
            $matchedIndex = null;
            foreach ($unimportedPool as $index => $unimported) {
                if ($unimported['amount'] === $amountCents) {
                    $matchedIndex = $index;

                    break;
                }
            }

            if ($matchedIndex !== null) {
                $matched = $unimportedPool[$matchedIndex];
                $matchedExpenses[] = [
                    'expense_id' => $matched['id'],
                    'expense_merchant' => $matched['merchant'],
                    'expense_date' => $matched['date'],
                    'csv_merchant' => $raw['merchant'],
                    'csv_date' => $date,
                    'amount' => $amountCents,
                    'reference_number' => $referenceNumber,
                ];
                unset($unimportedPool[$matchedIndex]);

                continue;
            }

            $category = $this->lookupMerchantCategory($raw['merchant'], $userId);

            $rows[] = [
                'date' => $date,
                'merchant' => $raw['merchant'],
                'amount' => $amountCents,
                'category' => $category,
                'reference_number' => $referenceNumber,
            ];
        }

        $feedback = '';
        if (empty($rows) && empty($matchedExpenses) && ! empty($rawRows)) {
            $feedback = __('All transactions in this file have already been imported.');
        }

        return ['parsedRows' => $rows, 'matchedExpenses' => $matchedExpenses, 'feedback' => $feedback];
    }

    /**
     * Import selected expenses and update matched manual entries.
     */
    public function import(array $selectedRows, array $parsedRows, array $selectedMatches, array $matchedExpenses, int $accountId, int $userId): void
    {
        $account = ExpenseAccount::findOrFail($accountId);
        abort_unless($account->user_id === $userId, 403);

        foreach ($selectedRows as $index) {
            if (! isset($parsedRows[$index])) {
                continue;
            }

            $row = $parsedRows[$index];

            Expense::create([
                'user_id' => $userId,
                'expense_account_id' => $accountId,
                'merchant' => $row['merchant'],
                'amount' => $row['amount'],
                'category' => $row['category'],
                'date' => $row['date'],
                'is_imported' => true,
                'reference_number' => $row['reference_number'],
            ]);
        }

        // Update approved matched manual entries so future re-imports detect them
        foreach ($selectedMatches as $index) {
            if (! isset($matchedExpenses[$index])) {
                continue;
            }

            $match = $matchedExpenses[$index];

            Expense::where('id', $match['expense_id'])
                ->where('user_id', $userId)
                ->update([
                    'is_imported' => true,
                    'reference_number' => $match['reference_number'],
                ]);
        }
    }

    private function detectColumn(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function lookupMerchantCategory(string $merchant, int $userId): ?string
    {
        $expense = Expense::where('user_id', $userId)
            ->where('merchant', $merchant)
            ->whereNotNull('category')
            ->latest('date')
            ->first();

        return $expense?->category->value;
    }
}

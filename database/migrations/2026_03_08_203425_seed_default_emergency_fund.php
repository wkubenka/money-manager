<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure a default Emergency Fund account exists for the single-user desktop app.
     * The old per-user migration only created one when users existed in the users table,
     * which is empty on a fresh install (and later dropped entirely).
     */
    public function up(): void
    {
        $exists = DB::table('net_worth_accounts')
            ->where('is_emergency_fund', true)
            ->exists();

        if (! $exists) {
            DB::table('net_worth_accounts')->insert([
                'category' => 'savings',
                'name' => 'Emergency Fund',
                'balance' => 0,
                'sort_order' => 0,
                'is_emergency_fund' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Only delete if balance is still 0 (untouched default)
        DB::table('net_worth_accounts')
            ->where('is_emergency_fund', true)
            ->where('balance', 0)
            ->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('net_worth_accounts', function (Blueprint $table) {
            $table->boolean('is_emergency_fund')->default(false);
        });

        $users = DB::table('users')->pluck('id');

        foreach ($users as $userId) {
            DB::table('net_worth_accounts')->insert([
                'user_id' => $userId,
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('net_worth_accounts')->where('is_emergency_fund', true)->delete();

        Schema::table('net_worth_accounts', function (Blueprint $table) {
            $table->dropColumn('is_emergency_fund');
        });
    }
};

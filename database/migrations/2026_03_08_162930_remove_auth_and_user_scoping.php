<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');

        // Drop foreign keys first (separate call for SQLite compatibility)
        Schema::table('spending_plans', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('net_worth_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('rich_life_visions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('expense_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Now drop indexes and columns
        Schema::table('spending_plans', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
        Schema::table('net_worth_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
        Schema::table('rich_life_visions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
        Schema::table('expense_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
            $table->dropColumn('user_id');
        });

        Schema::dropIfExists('users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

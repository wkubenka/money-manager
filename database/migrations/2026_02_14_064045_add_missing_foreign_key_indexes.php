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
        Schema::table('expense_accounts', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('expense_account_id');
        });

        Schema::table('net_worth_accounts', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('rich_life_visions', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['expense_account_id']);
        });

        Schema::table('net_worth_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('rich_life_visions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};

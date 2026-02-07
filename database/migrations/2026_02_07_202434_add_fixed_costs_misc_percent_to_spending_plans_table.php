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
        Schema::table('spending_plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('fixed_costs_misc_percent')->default(15);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spending_plans', function (Blueprint $table) {
            $table->dropColumn('fixed_costs_misc_percent');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spending_plans', function (Blueprint $table) {
            $table->unsignedInteger('gross_monthly_income')->default(0)->after('monthly_income');
            $table->unsignedInteger('pre_tax_investments')->default(0)->after('gross_monthly_income');
        });
    }

    public function down(): void
    {
        Schema::table('spending_plans', function (Blueprint $table) {
            $table->dropColumn(['gross_monthly_income', 'pre_tax_investments']);
        });
    }
};

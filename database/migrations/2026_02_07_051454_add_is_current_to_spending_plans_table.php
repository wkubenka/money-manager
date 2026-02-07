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
            $table->boolean('is_current')->default(false)->after('pre_tax_investments');
        });
    }

    public function down(): void
    {
        Schema::table('spending_plans', function (Blueprint $table) {
            $table->dropColumn('is_current');
        });
    }
};

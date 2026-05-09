<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('emergency_fund_months')->default(6)->after('withdrawal_rate');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('emergency_fund_months');
        });
    }
};

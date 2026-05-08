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
        Schema::create('debt_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('strategy')->default('avalanche');
            $table->unsignedInteger('extra_payment_cents')->default(0);
            $table->unsignedInteger('lump_sum_cents')->default(0);
            $table->unsignedInteger('lump_sum_month')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_scenarios');
    }
};

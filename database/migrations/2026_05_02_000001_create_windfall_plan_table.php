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
        Schema::create('windfall_plan', function (Blueprint $table) {
            $table->id();
            // Splits stored as integer percentages (0–100).
            // They are validated to sum to 100 at the application layer.
            $table->unsignedTinyInteger('savings_percent')->default(30);
            $table->unsignedTinyInteger('investments_percent')->default(50);
            $table->unsignedTinyInteger('guilt_free_percent')->default(10);
            $table->unsignedTinyInteger('debt_percent')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('windfall_plan');
    }
};

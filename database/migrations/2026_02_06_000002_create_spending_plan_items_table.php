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
        Schema::create('spending_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spending_plan_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('name');
            $table->unsignedInteger('amount');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('spending_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spending_plan_items');
    }
};

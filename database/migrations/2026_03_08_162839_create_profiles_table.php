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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('retirement_age')->nullable()->default(65);
            $table->decimal('expected_return', 4, 1)->nullable()->default(7.0);
            $table->decimal('withdrawal_rate', 4, 1)->nullable()->default(4.0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_age')->nullable();
            $table->unsignedSmallInteger('retirement_age')->nullable()->default(65);
            $table->decimal('expected_return', 4, 1)->nullable()->default(7.0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['current_age', 'retirement_age', 'expected_return']);
        });
    }
};

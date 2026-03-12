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
        Schema::table('rich_life_visions', function (Blueprint $table) {
            $table->foreignId('rich_life_vision_category_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rich_life_visions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rich_life_vision_category_id');
        });
    }
};

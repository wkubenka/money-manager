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
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('is_imported')->default(false)->after('date');
            $table->string('reference_number')->nullable()->after('is_imported');

            $table->index(['expense_account_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['expense_account_id', 'reference_number']);
            $table->dropColumn(['is_imported', 'reference_number']);
        });
    }
};

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
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->boolean('is_inhabited')->default(false)->after('is_hidden');
            $table->index('is_inhabited'); // For fast filtering of inhabited/uninhabited systems
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropIndex(['is_inhabited']);
            $table->dropColumn('is_inhabited');
        });
    }
};

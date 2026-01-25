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
            // Region: core (civilized center) or outer (frontier wilderness)
            $table->string('region', 20)->default('outer')->after('is_inhabited');

            // Fortified systems have pre-built defenses
            $table->boolean('is_fortified')->default(false)->after('region');

            // Owner for mining restrictions (core systems may be owned by factions)
            $table->foreignId('owner_id')->nullable()->after('is_fortified')
                ->constrained('players')->onDelete('set null');

            // Index for region-based queries
            $table->index(['galaxy_id', 'region']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropIndex(['galaxy_id', 'region']);
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['region', 'is_fortified', 'owner_id']);
        });
    }
};

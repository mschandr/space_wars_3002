<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add region, is_fortified, and owner_id columns and a composite index to points_of_interest.
     *
     * Adds 'region' (string, 20) with default 'outer' after 'is_inhabited'; 'is_fortified' (boolean) with default false after 'region'; a nullable 'owner_id' foreign key referencing 'players' with on delete set null after 'is_fortified'; and a composite index on ['galaxy_id', 'region'].
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
     * Revert the schema changes applied to the points_of_interest table by this migration.
     *
     * Drops the composite index on `galaxy_id` and `region`, removes the foreign key on `owner_id`,
     * and deletes the `region`, `is_fortified`, and `owner_id` columns.
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
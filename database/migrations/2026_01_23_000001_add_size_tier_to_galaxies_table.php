<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add administrative generation and tracking columns to the `galaxies` table.
     *
     * Adds the following nullable columns:
     * - `size_tier` (string, length 20): size tier such as "small", "medium", or "large".
     * - `core_bounds` (JSON): object with `x_min`, `x_max`, `y_min`, `y_max` defining the civilized center.
     * - `progress_status` (JSON): array of step objects with `step`, `name`, `percentage`, `status`, and `timestamp`.
     * - `generation_started_at` (timestamp): generation start time.
     * - `generation_completed_at` (timestamp): generation completion time.
     */
    public function up(): void
    {
        Schema::table('galaxies', function (Blueprint $table) {
            // Size tier: small (500x500), medium (1500x1500), large (2500x2500)
            $table->string('size_tier', 20)->nullable()->after('config');

            // Core bounds define the civilized center of the galaxy
            // JSON: {x_min, x_max, y_min, y_max}
            $table->json('core_bounds')->nullable()->after('size_tier');

            // Real-time progress tracking for galaxy creation
            // JSON array of step objects: [{step, name, percentage, status, timestamp}]
            $table->json('progress_status')->nullable()->after('core_bounds');

            // Timestamps for tracking generation duration
            $table->timestamp('generation_started_at')->nullable()->after('progress_status');
            $table->timestamp('generation_completed_at')->nullable()->after('generation_started_at');
        });
    }

    /**
     * Remove galaxy generation and tracking columns from the galaxies table.
     *
     * Drops the following columns: `size_tier`, `core_bounds`, `progress_status`, `generation_started_at`, and `generation_completed_at`.
     */
    public function down(): void
    {
        Schema::table('galaxies', function (Blueprint $table) {
            $table->dropColumn([
                'size_tier',
                'core_bounds',
                'progress_status',
                'generation_started_at',
                'generation_completed_at',
            ]);
        });
    }
};
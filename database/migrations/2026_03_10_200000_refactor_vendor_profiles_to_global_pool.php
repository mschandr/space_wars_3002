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
        // Step 0: Clear old data (fresh start)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('vendor_dialogue')->truncate();
        DB::table('vendor_profiles')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Step 1: Create new galaxy_vendor_profiles table before modifying vendor_profiles
        Schema::create('galaxy_vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // References
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poi_id')->constrained('points_of_interest')->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('trading_post_id')->nullable()->constrained('trading_posts')->cascadeOnDelete();

            // Service type and criminality (from template but can be modified per galaxy)
            $table->enum('service_type', ['trading_hub', 'salvage_yard', 'shipyard', 'market']);
            $table->decimal('criminality', 3, 2)->default(0.0);

            // Dialogue generation state (per galaxy instance)
            $table->enum('dialogue_generation_status', ['pending', 'generating', 'complete', 'failed'])->default('pending');
            $table->unsignedInteger('dialogue_generation_version')->default(1);
            $table->timestamp('dialogue_generated_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('galaxy_id');
            $table->index('poi_id');
            $table->index('vendor_profile_id');
            $table->index('service_type');
            $table->index('dialogue_generation_status');

            // Unique constraint: one vendor instance per POI per galaxy
            $table->unique(['galaxy_id', 'poi_id']);
        });

        // Step 2: Modify vendor_profiles table to be global templates
        // Handle both cases: whether the columns exist or not (migration may have been partially run)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('vendor_profiles', function (Blueprint $table) {
            // Conditionally drop foreign keys if they exist
            $columns = DB::getSchemaBuilder()->getColumnListing('vendor_profiles');

            if (in_array('galaxy_id', $columns)) {
                $table->dropForeign(['galaxy_id']);
                $table->dropColumn('galaxy_id');
            }

            if (in_array('poi_id', $columns)) {
                try {
                    $table->dropUnique('vendor_profiles_poi_id_unique');
                } catch (\Exception $e) {
                    // Unique constraint may not exist
                }
                $table->dropForeign(['poi_id']);
                $table->dropColumn('poi_id');
            }

            if (in_array('trading_post_id', $columns)) {
                $table->dropForeign(['trading_post_id']);
                $table->dropColumn('trading_post_id');
            }

            // Drop dialogue columns if they exist
            if (in_array('dialogue_generation_status', $columns)) {
                $table->dropColumn(['dialogue_generation_status', 'dialogue_generation_version', 'dialogue_generated_at']);
            }
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Step 3: Add template fields (name and archetype)
        Schema::table('vendor_profiles', function (Blueprint $table) {
            // Only add if they don't exist
            $columns = DB::getSchemaBuilder()->getColumnListing('vendor_profiles');

            if (!in_array('name', $columns)) {
                $table->string('name')->nullable()->after('uuid');
            }

            if (!in_array('archetype', $columns)) {
                $table->string('archetype')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            // Restore columns
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poi_id')->constrained('points_of_interest')->cascadeOnDelete();
            $table->foreignId('trading_post_id')->constrained()->cascadeOnDelete();

            $table->enum('dialogue_generation_status', ['pending', 'generating', 'complete', 'failed'])->default('pending');
            $table->unsignedInteger('dialogue_generation_version')->default(1);
            $table->timestamp('dialogue_generated_at')->nullable();

            // Restore indexes
            $table->unique('poi_id');
            $table->index('service_type');
            $table->index('criminality');

            // Remove template fields
            $table->dropColumn(['name', 'archetype']);
        });

        Schema::dropIfExists('galaxy_vendor_profiles');
    }
};

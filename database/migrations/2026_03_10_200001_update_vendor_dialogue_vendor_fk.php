<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                // Drop the old foreign key constraint
                $table->dropForeign(['vendor_profile_id']);
            });

            // Step 2: Rename the column
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                $table->renameColumn('vendor_profile_id', 'galaxy_vendor_profile_id');
            });

            // Step 3: Add the new foreign key
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                $table->unsignedBigInteger('galaxy_vendor_profile_id')->change();
                $table->foreign('galaxy_vendor_profile_id')
                    ->references('id')
                    ->on('galaxy_vendor_profiles')
                    ->cascadeOnDelete();
            });

            // Step 4: Update indexes
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                // Drop old indexes that reference vendor_profile_id
                $table->dropIndex('idx_vendor_line_type');
                $table->dropIndex('idx_vendor_lookup');
                $table->dropIndex('idx_inventory_context');

                // Add new indexes with galaxy_vendor_profile_id
                $table->index(['galaxy_vendor_profile_id', 'line_type'], 'idx_vendor_line_type');
                $table->index(['galaxy_vendor_profile_id', 'line_type', 'interaction_bucket'], 'idx_vendor_lookup');
                $table->index(['galaxy_vendor_profile_id', 'inventory_context'], 'idx_inventory_context');
            });
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                // Drop the new foreign key
                $table->dropForeign(['galaxy_vendor_profile_id']);
            });

            // Rename back
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                $table->renameColumn('galaxy_vendor_profile_id', 'vendor_profile_id');
            });

            // Restore old foreign key
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                $table->unsignedBigInteger('vendor_profile_id')->change();
                $table->foreign('vendor_profile_id')
                    ->references('id')
                    ->on('vendor_profiles')
                    ->cascadeOnDelete();
            });

            // Restore indexes
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                $table->dropIndex('idx_vendor_line_type');
                $table->dropIndex('idx_vendor_lookup');
                $table->dropIndex('idx_inventory_context');

                $table->index(['vendor_profile_id', 'line_type'], 'idx_vendor_line_type');
                $table->index(['vendor_profile_id', 'line_type', 'interaction_bucket'], 'idx_vendor_lookup');
                $table->index(['vendor_profile_id', 'inventory_context'], 'idx_inventory_context');
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table exists with potentially wrong schema
        if (Schema::hasTable('crew_members')) {
            // Safety check: only drop if table is empty (no production data loss)
            $rowCount = DB::table('crew_members')->count();

            if ($rowCount === 0) {
                // Safe to drop and recreate: table is empty
                Schema::drop('crew_members');
                $this->createCrewMembersTable();
            } else {
                // Data exists: perform safe schema migration instead
                $this->safeUpdateCrewMembersSchema();
            }
        } else {
            // Table doesn't exist: create it fresh
            $this->createCrewMembersTable();
        }
    }

    /**
     * Create the crew_members table with correct schema
     */
    private function createCrewMembersTable(): void
    {
        Schema::create('crew_members', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('galaxy_id');
            $table->string('name', 100);
            $table->enum('role', ['science_officer', 'tactical_officer', 'chief_engineer', 'logistics_officer', 'helms_officer']);
            $table->enum('alignment', ['lawful', 'neutral', 'shady']);
            $table->unsignedBigInteger('player_ship_id')->nullable();
            $table->unsignedBigInteger('current_poi_id');
            $table->integer('shady_actions')->default(0);
            $table->integer('reputation')->default(0);
            $table->json('traits')->nullable();
            $table->string('backstory', 500)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('galaxy_id')->references('id')->on('galaxies')->onDelete('cascade');
            $table->foreign('player_ship_id')->references('id')->on('player_ships')->onDelete('set null');
            $table->foreign('current_poi_id')->references('id')->on('points_of_interest')->onDelete('cascade');
        });
    }

    /**
     * Safely update existing crew_members table schema without data loss
     * Adds missing columns if they don't exist
     */
    private function safeUpdateCrewMembersSchema(): void
    {
        Schema::table('crew_members', function (Blueprint $table) {
            // Add missing columns if they don't already exist
            if (!Schema::hasColumn('crew_members', 'uuid')) {
                $table->uuid()->unique()->after('id');
            }
            if (!Schema::hasColumn('crew_members', 'galaxy_id')) {
                $table->unsignedBigInteger('galaxy_id')->after('uuid');
            }
            if (!Schema::hasColumn('crew_members', 'alignment')) {
                $table->enum('alignment', ['lawful', 'neutral', 'shady'])->default('neutral')->after('role');
            }
            if (!Schema::hasColumn('crew_members', 'player_ship_id')) {
                $table->unsignedBigInteger('player_ship_id')->nullable()->after('alignment');
            }
            if (!Schema::hasColumn('crew_members', 'current_poi_id')) {
                $table->unsignedBigInteger('current_poi_id')->after('player_ship_id');
            }
            if (!Schema::hasColumn('crew_members', 'shady_actions')) {
                $table->integer('shady_actions')->default(0)->after('current_poi_id');
            }
            if (!Schema::hasColumn('crew_members', 'reputation')) {
                $table->integer('reputation')->default(0)->after('shady_actions');
            }
            if (!Schema::hasColumn('crew_members', 'traits')) {
                $table->json('traits')->nullable()->after('reputation');
            }
            if (!Schema::hasColumn('crew_members', 'backstory')) {
                $table->string('backstory', 500)->nullable()->after('traits');
            }

            // Add foreign keys if they don't exist
            try {
                if (!Schema::hasColumn('crew_members', 'galaxy_id')) {
                    $table->foreign('galaxy_id')->references('id')->on('galaxies')->onDelete('cascade');
                }
            } catch (\Exception $e) {
                // FK may already exist
            }

            try {
                if (!Schema::hasColumn('crew_members', 'player_ship_id')) {
                    $table->foreign('player_ship_id')->references('id')->on('player_ships')->onDelete('set null');
                }
            } catch (\Exception $e) {
                // FK may already exist
            }

            try {
                if (!Schema::hasColumn('crew_members', 'current_poi_id')) {
                    $table->foreign('current_poi_id')->references('id')->on('points_of_interest')->onDelete('cascade');
                }
            } catch (\Exception $e) {
                // FK may already exist
            }
        });
    }

    public function down(): void
    {
        // Only drop the table if we created it fresh (not if we just updated schema)
        // Since we can't reliably detect which path was taken, we provide a safe down:
        // Drop only the columns we added, not the entire table (preserves data)
        Schema::table('crew_members', function (Blueprint $table) {
            // Remove new columns in reverse order
            $columnsToRemove = [
                'backstory',
                'traits',
                'reputation',
                'shady_actions',
                'current_poi_id',
                'player_ship_id',
                'alignment',
                'galaxy_id',
                'uuid',
            ];

            $existingColumns = DB::select("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'crew_members'
                AND TABLE_SCHEMA = DATABASE()
            ");

            $existingColumnNames = array_map(fn ($col) => $col->COLUMN_NAME, $existingColumns);

            // Only drop columns that exist
            $columnsToActuallyRemove = array_intersect($columnsToRemove, $existingColumnNames);

            if (!empty($columnsToActuallyRemove)) {
                $table->dropColumn($columnsToActuallyRemove);
            }
        });
    }
};

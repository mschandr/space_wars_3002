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
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('galaxy_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Alpha-7", "Sector B-3"
            $table->integer('grid_x'); // Grid position X (0-9 for 10x10)
            $table->integer('grid_y'); // Grid position Y (0-9 for 10x10)
            $table->float('x_min'); // Bounding box min X
            $table->float('x_max'); // Bounding box max X
            $table->float('y_min'); // Bounding box min Y
            $table->float('y_max'); // Bounding box max Y
            $table->json('attributes')->nullable(); // POI count, danger level, etc.
            $table->timestamps();

            // Indexes
            $table->index(['galaxy_id', 'grid_x', 'grid_y']);
            $table->unique(['galaxy_id', 'grid_x', 'grid_y']);
        });

        // Add sector_id to points_of_interest
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->after('galaxy_id')->constrained()->onDelete('set null');
            $table->index('sector_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove sector_id from points_of_interest
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropIndex(['sector_id']);
            $table->dropColumn('sector_id');
        });

        Schema::dropIfExists('sectors');
    }
};

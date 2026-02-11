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
        Schema::create('warp_gates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('galaxy_id')->constrained('galaxies')->onDelete('cascade');
            $table->foreignId('source_poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->foreignId('destination_poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->boolean('is_hidden')->default(false);
            $table->float('distance')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['galaxy_id', 'source_poi_id']);
            $table->index(['galaxy_id', 'destination_poi_id']);
            // TODO: (Design Clarification) This unique constraint allows both A->B and B->A as separate
            // records. If warp gates are bidirectional, this permits duplicate logical connections.
            // Clarify: Are gates one-way (current design is correct) or two-way (need LEAST/GREATEST
            // constraint or application-level enforcement to prevent duplicates)?
            $table->unique(['source_poi_id', 'destination_poi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warp_gates');
    }
};

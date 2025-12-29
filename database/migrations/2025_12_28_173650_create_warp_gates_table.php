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

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
        Schema::create('orbital_structures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('structure_type'); // orbital_defense, magnetic_mine, mining_platform, orbital_base
            $table->tinyInteger('level')->default(1);
            $table->string('status')->default('constructing'); // constructing, operational, damaged, destroyed
            $table->string('name');
            $table->integer('construction_progress')->default(0); // 0-100
            $table->timestamp('construction_started_at')->nullable();
            $table->timestamp('construction_completed_at')->nullable();
            $table->unsignedInteger('health')->default(0);
            $table->unsignedInteger('max_health')->default(0);
            $table->json('attributes')->nullable();
            $table->integer('credits_per_cycle')->default(0);
            $table->integer('minerals_per_cycle')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('player_id');
            $table->index(['poi_id', 'structure_type']);
            $table->index(['player_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orbital_structures');
    }
};

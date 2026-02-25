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
        Schema::create('player_system_knowledge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poi_id')->constrained('points_of_interest')->cascadeOnDelete();
            $table->tinyInteger('knowledge_level')->default(0);
            $table->string('source', 20); // sensor, warp_lane, chart, rumor, visit, scan, spawn
            $table->foreignId('source_poi_id')->nullable()->constrained('points_of_interest')->nullOnDelete();
            $table->timestamp('acquired_at');
            $table->boolean('has_pirate_warning')->default(false);
            $table->json('pirate_warning_data')->nullable();
            $table->json('services_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'poi_id']);
            $table->index(['player_id', 'knowledge_level']);
            $table->index('poi_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_system_knowledge');
    }
};

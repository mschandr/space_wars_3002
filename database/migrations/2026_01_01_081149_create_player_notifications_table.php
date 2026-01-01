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
        Schema::create('player_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('type'); // low_resource, gate_shutdown, pirate_detected, building_complete, etc.
            $table->string('severity')->default('info'); // info, warning, critical
            $table->string('title');
            $table->text('message');

            // Optional references
            $table->foreignId('colony_id')->nullable()->constrained('colonies')->onDelete('cascade');
            $table->foreignId('poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null');

            // Metadata
            $table->json('data')->nullable(); // Additional context
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('player_id');
            $table->index(['player_id', 'is_read']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_notifications');
    }
};

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
        Schema::create('flotillas', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->foreignId('flagship_ship_id')->constrained('player_ships')->onDelete('cascade');
            $table->timestamps();

            // Indexes for common queries
            $table->index(['player_id']);
            $table->index(['flagship_ship_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flotillas');
    }
};

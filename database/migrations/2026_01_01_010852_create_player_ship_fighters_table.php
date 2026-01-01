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
        Schema::create('player_ship_fighters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('player_ship_id')->constrained()->onDelete('cascade'); // The carrier ship
            $table->foreignId('ship_id')->constrained()->onDelete('cascade'); // The fighter ship type
            $table->string('fighter_name')->nullable(); // Custom name for this fighter
            $table->integer('hull')->default(0); // Current hull strength
            $table->integer('max_hull')->default(0); // Maximum hull strength
            $table->integer('weapons')->default(0); // Weapons level
            $table->boolean('is_deployed')->default(false); // Currently deployed in combat
            $table->json('attributes')->nullable(); // Additional fighter-specific data
            $table->timestamps();

            $table->index('player_ship_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_ship_fighters');
    }
};

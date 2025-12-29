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
        Schema::create('ships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('class'); // fighter, transport, hauler, battleship, etc.
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->integer('cargo_capacity')->default(0);
            $table->integer('speed')->default(0);
            $table->integer('hull_strength')->default(0);
            $table->integer('shield_strength')->default(0);
            $table->integer('weapon_slots')->default(0);
            $table->integer('utility_slots')->default(0);
            $table->string('rarity')->default('common');
            $table->json('requirements')->nullable(); // Level, credits, etc.
            $table->json('attributes')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index('class');
            $table->index('rarity');
            $table->index('is_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ships');
    }
};

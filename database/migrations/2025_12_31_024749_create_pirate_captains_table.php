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
        Schema::create('pirate_captains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('faction_id')->constrained('pirate_factions')->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('title')->default('Captain');
            $table->integer('combat_skill')->default(50);
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index('faction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pirate_captains');
    }
};

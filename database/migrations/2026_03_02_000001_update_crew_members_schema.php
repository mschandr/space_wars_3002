<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old crew_members table if it exists with the wrong schema
        if (Schema::hasTable('crew_members')) {
            Schema::drop('crew_members');
        }

        // Create the new crew_members table with the correct schema
        Schema::create('crew_members', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('galaxy_id');
            $table->string('name', 100);
            $table->enum('role', ['science_officer', 'tactical_officer', 'chief_engineer', 'logistics_officer', 'helms_officer']);
            $table->enum('alignment', ['lawful', 'neutral', 'shady']);
            $table->unsignedBigInteger('player_ship_id')->nullable();
            $table->unsignedBigInteger('current_poi_id');
            $table->integer('shady_actions')->default(0);
            $table->integer('reputation')->default(0);
            $table->json('traits')->nullable();
            $table->string('backstory', 500)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('galaxy_id')->references('id')->on('galaxies')->onDelete('cascade');
            $table->foreign('player_ship_id')->references('id')->on('player_ships')->onDelete('set null');
            $table->foreign('current_poi_id')->references('id')->on('points_of_interest')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_members');
    }
};

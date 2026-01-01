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
        Schema::create('pirate_fleets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('captain_id')->constrained('pirate_captains')->onDelete('cascade');
            $table->foreignId('ship_id')->constrained('ships')->onDelete('restrict');
            $table->string('ship_name')->nullable();

            // Ship stats (snapshot from Ship template + modifications)
            $table->integer('hull')->default(100);
            $table->integer('max_hull')->default(100);
            $table->integer('weapons')->default(10);
            $table->integer('speed')->default(100);
            $table->integer('warp_drive')->default(1);
            $table->integer('cargo_capacity')->default(50);

            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['captain_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pirate_fleets');
    }
};

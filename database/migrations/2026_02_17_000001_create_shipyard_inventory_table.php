<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipyard_inventory', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->foreignId('ship_id')->constrained('ships')->onDelete('cascade');
            $table->string('name');
            $table->string('rarity')->default('common');
            $table->decimal('price', 14, 2);
            $table->integer('hull_strength');
            $table->integer('shield_strength')->default(0);
            $table->integer('cargo_capacity');
            $table->integer('speed');
            $table->integer('weapon_slots')->default(2);
            $table->integer('utility_slots')->default(2);
            $table->integer('max_fuel')->default(100);
            $table->integer('sensors')->default(1);
            $table->integer('warp_drive')->default(1);
            $table->integer('weapons')->default(10);
            $table->json('variation_traits')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_sold')->default(false);
            $table->timestamps();

            $table->index('poi_id');
            $table->index('rarity');
            $table->index('is_sold');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipyard_inventory');
    }
};

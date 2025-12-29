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
        Schema::create('player_cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_ship_id')->constrained()->onDelete('cascade');
            $table->foreignId('mineral_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['player_ship_id', 'mineral_id']);
            $table->index('player_ship_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_cargos');
    }
};

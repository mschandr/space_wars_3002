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
        Schema::create('player_star_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('revealed_poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->foreignId('purchased_from_poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null');
            $table->decimal('price_paid', 10, 2)->default(0);
            $table->timestamp('purchased_at');
            $table->timestamps();

            $table->unique(['player_id', 'revealed_poi_id']); // Can't buy same chart twice
            $table->index('player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_star_charts');
    }
};

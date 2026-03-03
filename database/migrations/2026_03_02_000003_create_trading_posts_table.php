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
        Schema::create('trading_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Template info
            $table->string('name', 100);
            $table->enum('service_type', ['trading_hub', 'salvage_yard', 'shipyard', 'market']);

            // Base traits for all vendors created from this template
            $table->decimal('base_criminality', 3, 2)->default(0.0);  // 0.0-1.0, high = black market
            $table->json('personality')->nullable();                   // {honesty, greed, charm, etc.}
            $table->json('dialogue_pool')->nullable();                 // Context-based dialogue lines
            $table->decimal('markup_base', 5, 4)->default(0.0);       // Base markup multiplier

            $table->timestamps();

            // Indexes
            $table->index('service_type');
            $table->index('base_criminality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_posts');
    }
};

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

        // Add DB-level CHECK constraint to enforce criminality range
        \DB::statement('ALTER TABLE trading_posts ADD CONSTRAINT base_criminality_range CHECK (base_criminality >= 0 AND base_criminality <= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop constraint before dropping table (for cleaner rollback)
        \DB::statement('ALTER TABLE trading_posts DROP CONSTRAINT IF EXISTS base_criminality_range');

        Schema::dropIfExists('trading_posts');
    }
};

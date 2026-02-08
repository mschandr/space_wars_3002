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
        Schema::create('poi_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary(); // Matches enum value
            $table->string('code', 50)->unique();         // Enum name: STAR, PLANET, etc.
            $table->string('label', 100);                 // Display name: "Gas Giant"
            $table->text('description')->nullable();      // What this type represents
            $table->string('domain', 20);                 // "Universe" or "System"

            // Capabilities
            $table->boolean('is_habitable')->default(false);    // Can players colonize
            $table->boolean('is_mineable')->default(false);     // Can extract resources
            $table->boolean('is_orbital')->default(false);      // Orbits a parent body
            $table->boolean('is_dockable')->default(false);     // Ships can dock here
            $table->boolean('can_have_trading_hub')->default(false); // Can host trading
            $table->boolean('can_have_warp_gate')->default(false);   // Can have gates

            // Danger level (0-10 scale)
            $table->unsignedTinyInteger('base_danger_level')->default(0);

            // UI/Display
            $table->string('icon', 50)->nullable();       // Icon name for frontend
            $table->string('color', 7)->nullable();       // Hex color for map (#RRGGBB)
            $table->string('category', 30);               // star, planet, anomaly, debris

            // Mineral production (JSON array of mineral symbols this type can produce)
            $table->json('produces_minerals')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poi_types');
    }
};

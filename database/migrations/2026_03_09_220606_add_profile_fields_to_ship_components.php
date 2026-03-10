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
        Schema::table('ship_components', function (Blueprint $table) {
            // Component category (Engines, Shields, Weapons, Hull, Sensors, etc.)
            $table->string('category')->after('name')->default('engines');

            // Condition tier (Pristine, Like New, Good, Fair, Poor)
            $table->string('condition_tier')->after('category')->default('pristine');

            // Quality multiplier (1.0 for pristine, 0.8 for like-new, etc.)
            $table->decimal('condition_multiplier', 3, 2)->after('condition_tier')->default(1.0);

            // System/civilization origin (e.g., "Calaxarian Empire", "Terran Alliance")
            $table->string('origin')->after('condition_multiplier')->nullable();

            // Component variant description
            $table->text('variant_description')->after('origin')->nullable();

            // Index for faster category lookups
            $table->index('category');
            $table->index('condition_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ship_components', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['condition_tier']);
            $table->dropColumn(['category', 'condition_tier', 'condition_multiplier', 'origin', 'variant_description']);
        });
    }
};

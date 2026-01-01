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
        Schema::table('points_of_interest', function (Blueprint $table) {
            // Habitability fields
            $table->string('planet_class')->nullable(); // Class M (habitable), Class J (gas giant), Class D (barren), etc.
            $table->decimal('temperature', 5, 2)->nullable(); // In Celsius (-273 to 5000+)
            $table->decimal('gravity', 3, 2)->nullable(); // In Earth Gs (0.1 to 5.0)
            $table->string('atmosphere_type')->nullable(); // breathable, thin, toxic, none
            $table->decimal('atmosphere_density', 3, 2)->nullable(); // 0.0 to 2.0 (Earth = 1.0)
            $table->decimal('water_coverage', 3, 2)->nullable(); // 0.0 to 1.0 (percentage)
            $table->boolean('has_magnetic_field')->default(false);
            $table->decimal('radiation_level', 5, 2)->nullable(); // In rem/year

            // Resource richness (for mining)
            $table->json('mineral_deposits')->nullable(); // {"iron": "abundant", "platinum": "trace", etc.}
            $table->boolean('has_asteroid_field')->default(false);
            $table->integer('moon_count')->default(0);

            // Calculated habitability score (0.0 to 1.0)
            $table->decimal('habitability_score', 3, 2)->nullable();

            // Colonization
            $table->boolean('is_colonizable')->default(false);
            $table->boolean('is_colonized')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropColumn([
                'planet_class',
                'temperature',
                'gravity',
                'atmosphere_type',
                'atmosphere_density',
                'water_coverage',
                'has_magnetic_field',
                'radiation_level',
                'mineral_deposits',
                'has_asteroid_field',
                'moon_count',
                'habitability_score',
                'is_colonizable',
                'is_colonized',
            ]);
        });
    }
};

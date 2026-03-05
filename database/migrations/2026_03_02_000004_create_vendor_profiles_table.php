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
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // References
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poi_id')->constrained('points_of_interest')->cascadeOnDelete();
            $table->foreignId('trading_post_id')->constrained()->cascadeOnDelete();

            // Service type (inherited from trading post, but stored for query convenience)
            $table->enum('service_type', ['trading_hub', 'salvage_yard', 'shipyard', 'market']);

            // Criminality (inherited from trading post, but can be modified per vendor instance)
            $table->decimal('criminality', 3, 2)->default(0.0);

            // Instance-specific traits (inherits from trading post, can be customized)
            $table->json('personality')->nullable();
            $table->json('dialogue_pool')->nullable();
            $table->decimal('markup_base', 5, 4)->default(0.0);

            $table->timestamps();

            // Indexes
            $table->index('galaxy_id');
            $table->index('poi_id');
            $table->index('trading_post_id');
            $table->index('service_type');
            $table->index('criminality');

            // Unique constraint: one vendor per POI
            $table->unique('poi_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};

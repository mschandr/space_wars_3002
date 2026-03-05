<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Galaxy-specific vendor state: tracks per-galaxy vendor modifications
     * Vendor instances (vendor_profiles) are permanent templates
     * This table tracks how vendors have changed per galaxy due to player interactions
     */
    public function up(): void
    {
        Schema::create('galaxy_vendor_states', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->cascadeOnDelete();

            // State modifications (relative to vendor_profile template)
            $table->decimal('markup_modifier', 5, 4)->default(0);  // Additional markup above base
            $table->integer('interaction_count')->default(0);      // Number of trades with this vendor
            $table->decimal('average_satisfaction', 3, 2)->nullable(); // Player satisfaction rating

            // Market state per galaxy (could diverge from other galaxies)
            $table->integer('price_multiplier_base')->default(100); // Percentage relative to base price

            $table->timestamps();

            // Unique constraint: one state per vendor per galaxy
            $table->unique(['galaxy_id', 'vendor_profile_id']);

            // Index for queries
            $table->index('galaxy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('galaxy_vendor_states');
    }
};

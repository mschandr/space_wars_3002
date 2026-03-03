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
        Schema::create('player_vendor_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->constrained()->cascadeOnDelete();

            // Relationship tracking
            $table->integer('goodwill')->default(0);         // Increases with successful trades
            $table->integer('shady_dealings')->default(0);   // Increases with illegal sales
            $table->integer('visit_count')->default(0);      // How many times player visited

            // Pricing modifier (personal discount/markup)
            $table->decimal('markup_modifier', 5, 4)->default(0.0000);

            // Lockout state
            $table->boolean('is_locked_out')->default(false);

            // Last interaction timestamp
            $table->timestamp('last_interaction_at')->nullable();

            $table->timestamps();

            // Composite unique key
            $table->unique(['player_id', 'vendor_profile_id']);

            // Indexes
            $table->index('goodwill');
            $table->index('is_locked_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_vendor_relationships');
    }
};

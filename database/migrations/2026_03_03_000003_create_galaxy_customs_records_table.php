<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Galaxy-specific customs interaction records: tracks player history with customs officials
     * Customs officials (customs_officials) are permanent templates
     * This table tracks player interactions per galaxy
     */
    public function up(): void
    {
        Schema::create('galaxy_customs_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customs_official_id')->constrained('customs_officials')->cascadeOnDelete();

            // Interaction history per galaxy
            $table->integer('total_checks')->default(0);           // Number of arrival checks
            $table->integer('times_fined')->default(0);            // Number of times fined
            $table->integer('times_bribed')->default(0);           // Number of bribes accepted
            $table->integer('total_bribes_paid')->default(0);      // Total credits paid in bribes

            // Corruption tracking per galaxy (officials can become more/less corrupt based on bribes)
            $table->decimal('actual_honesty', 3, 2)->nullable();   // Can diverge from base template

            // Reputation with this official
            $table->integer('relationship_score')->default(0);     // Positive: trusting, Negative: hostile

            $table->timestamps();

            // Unique constraint: one record per official per galaxy
            $table->unique(['galaxy_id', 'customs_official_id']);

            // Index for queries
            $table->index('galaxy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('galaxy_customs_records');
    }
};

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
        Schema::create('pvp_challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Challenger and target
            $table->foreignId('challenger_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('target_id')->constrained('players')->onDelete('cascade');

            // Challenge details
            $table->string('status')->default('pending'); // pending, accepted, declined, expired, completed
            $table->text('message')->nullable(); // Optional challenge message
            $table->decimal('wager_credits', 15, 2)->default(0); // Optional credits bet

            // Location requirement (players must be at same POI)
            $table->foreignId('challenge_poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null');

            // Timestamps
            $table->timestamp('challenged_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Challenge expires after 5 minutes
            $table->timestamps();

            // Indexes
            $table->index('challenger_id');
            $table->index('target_id');
            $table->index('status');
            $table->index(['challenger_id', 'target_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pvp_challenges');
    }
};

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
        Schema::create('contract_events', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();

            // Event tracking
            $table->enum('event_type', ['POSTED', 'ACCEPTED', 'COMPLETED', 'FAILED', 'EXPIRED', 'CANCELLED']);
            $table->enum('actor_type', ['SYSTEM', 'PLAYER', 'ADMIN'])->default('SYSTEM');
            $table->unsignedBigInteger('actor_id')->nullable();

            // Payload for rich logging
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index('contract_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_events');
    }
};

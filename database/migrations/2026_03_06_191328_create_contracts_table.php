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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Contract identity
            $table->enum('type', ['TRANSPORT', 'SUPPLY'])->default('TRANSPORT');
            $table->enum('status', ['POSTED', 'ACCEPTED', 'COMPLETED', 'FAILED', 'EXPIRED', 'CANCELLED'])->default('POSTED');
            $table->enum('scope', ['LOCAL', 'REGIONAL', 'GALACTIC'])->default('LOCAL');

            // Issuance
            $table->foreignId('bar_location_id')->constrained('points_of_interest')->cascadeOnDelete();
            $table->enum('issuer_type', ['SYSTEM', 'COLONY', 'FACTION', 'PLAYER'])->default('SYSTEM');
            $table->unsignedBigInteger('issuer_id')->nullable();

            // Contract details
            $table->string('title');
            $table->text('description')->nullable();

            // Routing
            $table->foreignId('origin_location_id')->constrained('points_of_interest')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('points_of_interest')->restrictOnDelete();

            // Cargo
            $table->json('cargo_manifest');

            // Compensation & risk
            $table->integer('reward_credits');
            $table->enum('risk_rating', ['LOW', 'MEDIUM', 'HIGH'])->default('LOW');

            // Requirements
            $table->integer('reputation_min')->default(0);
            $table->integer('active_contract_limit')->default(5);

            // Lifecycle
            $table->dateTime('posted_at');
            $table->dateTime('expires_at');
            $table->dateTime('deadline_at');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Player acceptance
            $table->foreignId('accepted_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->dateTime('accepted_at')->nullable();

            // Determinism
            $table->unsignedInteger('seed')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['bar_location_id', 'status']);
            $table->index(['accepted_by_player_id', 'status']);
            $table->index(['origin_location_id', 'destination_location_id']);
            $table->index('expires_at');
            $table->index('deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

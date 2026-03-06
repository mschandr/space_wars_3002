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
        Schema::create('player_contract_reputation', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_id')->unique()->constrained('players')->cascadeOnDelete();

            // Scoring
            $table->integer('reliability_score')->default(50);
            $table->integer('completed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('abandoned_count')->default(0);
            $table->integer('expired_count')->default(0);

            // Penalties
            $table->integer('failure_penalty')->default(0);
            $table->integer('abandonment_penalty')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_contract_reputation');
    }
};

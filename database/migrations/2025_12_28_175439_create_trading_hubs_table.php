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
        Schema::create('trading_hubs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('standard'); // standard, major, premium
            $table->boolean('has_salvage_yard')->default(false);
            $table->integer('gate_count')->default(0); // Number of gates at this location
            $table->decimal('tax_rate', 5, 2)->default(5.00); // Percentage tax on trades
            $table->json('services')->nullable(); // Additional services offered
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('poi_id');
            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_hubs');
    }
};

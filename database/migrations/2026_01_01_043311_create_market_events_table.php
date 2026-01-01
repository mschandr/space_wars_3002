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
        Schema::create('market_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mineral_id')->nullable()->constrained('minerals')->onDelete('cascade');
            $table->foreignId('trading_hub_id')->nullable()->constrained('trading_hubs')->onDelete('cascade');
            $table->string('event_type'); // SHORTAGE, BOOM, EMBARGO, DISCOVERY, etc.
            $table->decimal('price_multiplier', 5, 2)->default(1.00); // 1.5 = 150%, 0.5 = 50%
            $table->text('description'); // "Mining accident cuts titanium supplies!"
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index('mineral_id');
            $table->index('trading_hub_id');
            $table->index(['is_active', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_events');
    }
};

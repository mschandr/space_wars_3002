<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commodities', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('code', 50)->unique(); // IRON, TITANIUM, RARE_EARTH, etc.
            $table->string('name', 100);
            $table->enum('category', ['MINERAL', 'EXOTIC', 'SOFT'])->default('MINERAL');
            $table->text('description')->nullable();

            // Pricing
            $table->decimal('base_price', 12, 2); // Credits per unit
            $table->boolean('is_conserved')->default(true); // Ledger-enforced

            // Per-commodity price clamps
            $table->decimal('price_min_multiplier', 3, 2)->default(0.5);
            $table->decimal('price_max_multiplier', 3, 2)->default(3.0);

            $table->timestamps();

            $table->index('code');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commodities');
    }
};

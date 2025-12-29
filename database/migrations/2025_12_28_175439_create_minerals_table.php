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
        Schema::create('minerals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('symbol', 10)->unique();
            $table->text('description')->nullable();
            $table->decimal('base_value', 10, 2)->default(0);
            $table->string('rarity')->default('common'); // abundant, common, uncommon, rare, very_rare, epic, legendary, mythic
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index('rarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minerals');
    }
};

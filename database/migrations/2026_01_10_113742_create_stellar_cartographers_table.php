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
        Schema::create('stellar_cartographers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->decimal('chart_base_price', 10, 2)->default(1000.00);
            $table->decimal('markup_multiplier', 4, 2)->default(1.0);
            $table->timestamps();

            $table->index('poi_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stellar_cartographers');
    }
};

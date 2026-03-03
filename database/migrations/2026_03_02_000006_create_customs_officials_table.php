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
        Schema::create('customs_officials', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('poi_id')->unique()->constrained('points_of_interest')->cascadeOnDelete();

            // Officer personality
            $table->string('name', 100);
            $table->decimal('honesty', 3, 2);        // 0.0=corrupt, 1.0=incorruptible
            $table->decimal('severity', 3, 2);       // 0.0=lenient, 1.0=maximum enforcement
            $table->integer('bribe_threshold');      // Minimum credits to attempt bribe

            // Search capability
            $table->decimal('detection_skill', 3, 2); // How well they find hidden cargo

            $table->timestamps();

            // Index
            $table->index('poi_id');
            $table->index('honesty');
            $table->index('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customs_officials');
    }
};

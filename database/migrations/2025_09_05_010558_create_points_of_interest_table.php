<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_of_interest', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Belongs to a galaxy
            $table->foreignId('galaxy_id')
                ->constrained()
                ->cascadeOnDelete();

            // Enum-backed fields
            $table->unsignedTinyInteger('type');   // App\Enums\PointOfInterestType
            $table->unsignedTinyInteger('status')->default(0); // App\Enums\PointOfInterestStatus

            // Coordinates inside the galaxy
            $table->unsignedInteger('x');
            $table->unsignedInteger('y');

            // Metadata
            $table->string('name')->nullable();
            $table->json('attributes')->nullable(); // flexible config (resources, defenses, etc.)
            $table->boolean('is_hidden')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_of_interest');
    }
};

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
        /**
         *
         *  Schema::create('star_systems', function (Blueprint $table) {
         *      $table->id();
         *      $table->uuid('uuid')->index();
         *
         *      $table->foreignId('star_type_id')->constrained()->cascadeOnDelete();
         *      $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
         *
         *      $table->bigInteger('x_coordinate')->nullable(false);
         *      $table->bigInteger('y_coordinate')->nullable(false);
         *
         *      $table->boolean('has_planets')->nullable(false)->default(null);
         *      $table->boolean('has_asteroid_belt')->nullable(true)->default(null);
         *      $table->timestamps();
         *  });
         */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * Schema::dropIfExists('star_systems');
         */
    }
};

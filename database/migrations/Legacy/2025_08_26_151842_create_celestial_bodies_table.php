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
         * Schema::create('celestial_bodies', function (Blueprint $table) {
         * $table->id();
         * $table->uuid('uuid')->unique();
         *
         * // Required: all bodies are in a galaxy
         * $table->foreignId('galaxy_id')
         * ->constrained()
         * ->cascadeOnDelete();
         *
         * // Optional: most bodies are in a system,
         * //           rogues leave it null
         * //           nebula can occur in warp areas
         * $table->foreignId('star_system_id')
         * ->nullable()
         * ->constrained('star_systems')
         * ->nullOnDelete();
         *
         * $table->foreignId('celestial_body_type_id')
         * ->constrained('celestial_body_types')
         * ->cascadeOnDelete();
         *
         * // Optional: Because not everything is associated
         * //           with a star
         * $table->foreignId('star_type_id')
         * ->nullable()
         * ->constrained('star_types')
         * ->nullOnDelete();
         *
         * $table->unsignedSmallInteger('orbit_index')->nullable(); // NULL for rogues
         * $table->unsignedTinyInteger('sub_index')->default(0);
         *
         * $table->foreignId('parent_body_id')
         * ->nullable()
         * ->constrained('celestial_bodies')
         * ->nullOnDelete();
         *
         * $table->unsignedSmallInteger('name_ordinal')->default(1);
         * $table->string('name');
         * $table->timestamps();
         *
         * $table->index(['star_system_id', 'orbit_index']);
         * $table->unique(['star_system_id','name','name_ordinal']);
         * $table->unique(['star_system_id','orbit_index','sub_index'], 'uniq_orbit_slot');
         * });
         *
         **/
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * Schema::dropIfExists('celestial_bodies');
         */
    }
};

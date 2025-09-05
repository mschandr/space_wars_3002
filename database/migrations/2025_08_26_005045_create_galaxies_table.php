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
        Schema::create('galaxies', function (Blueprint $table) {
            $table->id();
            $table->uuid('galaxy_uuid')->unique();

            $table->string('name')->nullable();
            $table->text('description')->nullable();

            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->bigInteger('seed');

            $table->tinyInteger('distribution_method')->default(0); // 0=scatter, 1=poisson, 2=halton
            $table->float('spacing_factor')->default(0.75);
            $table->tinyInteger('engine')->default(0); // 0=mt19937, 1=pcg, 2=xoshiro, etc.

            $table->unsignedInteger('turn_limit')->default(200);
            $table->tinyInteger('status')->default(0); // 0=draft, 1=active, 2=archived
            $table->string('version', 20)->default('1.0.0');

            $table->boolean('is_public')->default(true);

            $table->json('config')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('galaxies');
    }
};

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

            $table->string('name');
            $table->unsignedBigInteger('seed');

            $table->unsignedSmallInteger('height')->default(300);
            $table->unsignedSmallInteger('width')->default(300);

            $table->integer('stars');

            $table->json('config');
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

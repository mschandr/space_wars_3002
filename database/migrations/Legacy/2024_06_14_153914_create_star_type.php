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
         *  Schema::create('star_types', function (Blueprint $table) {
         *      $table->id();
         *      $table->enum('classification', ['O','B','A','F','G','K','M', 'N'])->nullable(false);
         *      $table->string('name')->nullable(true);
         *
         *      $table->unsignedMediumInteger('age_min')->unsigned()->nullable(false);
         *      $table->unsignedMediumInteger('age_max')->unsigned()->nullable(true);
         *      $table->unsignedMediumInteger('temperature_min');
         *      $table->unsignedMediumInteger('temperature_max');
         *
         *      $table->unsignedTinyInteger('magnetic_field')->nullable(false);
         *      $table->timestamps();
         *  });
         **/
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Schema::dropIfExists('star_types');
         */
    }
};

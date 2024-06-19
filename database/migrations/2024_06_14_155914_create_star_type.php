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
        Schema::table('star_type', function (Blueprint $table) {
            $table->char('id', 36)->unique();
            $table->enum('classification', ['O','B','A','F','G','K','M'])->nullable(false);
            $table->string('name')->nullable(false);
            $table->mediumInteger('age_min')->unsigned()->nullable(false);
            $table->mediumInteger('age_max')->unsigned()->nullable(false);
            $table->float('temperature_min', 2)->unsigned()->nullable(false);
            $table->float('temperature_max', 2)->unsigned()->nullable(false);
            $table->tinyInteger('magnetic_field')->nullable(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('star_type', function (Blueprint $table) {
            //
        });
    }
};

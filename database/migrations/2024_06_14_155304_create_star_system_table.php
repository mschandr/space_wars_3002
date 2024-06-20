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
        Schema::create('star_system', function (Blueprint $table) {
            $table->char('id', 36)->unique();
            $table->char('star_type_id', 36)->nullable(false);
            $table->char('celestial_body_id', 36)->nullable(false);
            $table->bigInteger('x_coordinate')->nullable(false);
            $table->bigInteger('y_coordinate')->nullable(false);
            $table->foreign('celestial_body_id', 'celestial_body_fk_2')
                  ->references('id')
                  ->on('celestial_body')
                  ->onDelete('cascade');
            $table->foreign('star_type_id', 'star_type_fk')
                  ->references('id')
                  ->on('star_type')
                  ->onDelete('cascade');
            $table->boolean('has_planets')->nullable(false)->default(null);
            $table->boolean('has_asteroid_belt')->nullable(true)->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('StarSystem', function (Blueprint $table) {
            //
        });
    }
};

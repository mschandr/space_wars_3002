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
        Schema::create('celestial_body', function (Blueprint $table) {
            $table->char('id', 36)->unique();
            $table->char('celestial_body_type_id', 36)->nullable(false);
            $table->string('name')->nullable()->default(null);
            $table->bigInteger('x_coordinate')->nullable(false);
            $table->bigInteger('y_coordinate')->nullable(false);
            $table->foreign('celestial_body_type_id', 'celestial_body_fk')
                  ->references('id', 'celestial_body_type')
                  ->on('celestial_body_type')
                  ->onDelete('cascade');
            $table->unique(['x_coordinate', 'y_coordinate'], 'unique_celestial_body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('celestial_body');
    }
};

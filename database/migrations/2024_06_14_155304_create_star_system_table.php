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
        Schema::table('star_system', function (Blueprint $table) {
            $table->char('id', 36)->unique();
            $table->char('star_type_id', 36)->nullable(false);
            $table->char('celestial_body_id', 36)->nullable(false);
            $table->foreign('celestial_body_id', 'celestial_body_fk')
                  ->references('id', 'celestial_body')
                  ->on('celestial_body')
                  ->onDelete('cascade');
            $table->foreign('star_type_id', 'star_type_fk')
                  ->references('id', 'star_type')
                  ->on('star_type')
                  ->onDelete('cascade');
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

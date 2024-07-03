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
        Schema::create('star_system_template', function (Blueprint $table) {
            $table->char('id', 36)->unique();
            $table->string('name')->nullable(false);
            $table->text('description')->nullable(false);
            $table->enum('for_system_star_type', ['O','B','A','F','G','K','M', 'N'])->nullable(false);
            $table->smallInteger('planet_count')->nullable(false);
            $table->json('star_system_configuration')->nullable(false);
            $table->float('weight')->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('star_system_template', function (Blueprint $table) {
            //
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('code', 100)->unique(); // FRIGATE_MK1, STATION_BASIC, etc.
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Classification
            $table->enum('type', ['SHIP', 'HABITAT', 'MODULE', 'FACILITY']);
            $table->string('output_item_code', 100)->nullable(); // What gets created

            // Build time
            $table->integer('build_time_ticks')->default(10);

            $table->timestamps();

            $table->index('type');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pirate_cargo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pirate_fleet_id')->constrained('pirate_fleets')->onDelete('cascade');
            $table->foreignId('mineral_id')->nullable()->constrained('minerals')->onDelete('cascade');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->index('pirate_fleet_id');
        });

        // Add check constraint: either mineral_id OR plan_id, not both
        // SQLite doesn't support ALTER TABLE ADD CONSTRAINT
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pirate_cargo ADD CONSTRAINT pirate_cargo_check CHECK ((mineral_id IS NOT NULL AND plan_id IS NULL) OR (mineral_id IS NULL AND plan_id IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pirate_cargo');
    }
};

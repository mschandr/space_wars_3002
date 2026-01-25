<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `npc_cargos` table with its columns, foreign keys, and constraints.
     *
     * The table includes:
     * - `id` primary key.
     * - `npc_ship_id` and `mineral_id` foreign keys with cascade on delete.
     * - `quantity` integer defaulting to 0.
     * - `created_at` and `updated_at` timestamps.
     * - a unique constraint on the combination of `npc_ship_id` and `mineral_id`.
     */
    public function up(): void
    {
        Schema::create('npc_cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_ship_id')->constrained()->onDelete('cascade');
            $table->foreignId('mineral_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['npc_ship_id', 'mineral_id']);
        });
    }

    /**
     * Drop the npc_cargos table if it exists.
     *
     * Removes the npc_cargos table created by this migration to allow rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_cargos');
    }
};
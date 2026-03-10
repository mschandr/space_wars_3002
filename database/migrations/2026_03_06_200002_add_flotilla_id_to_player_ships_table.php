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
        Schema::table('player_ships', function (Blueprint $table) {
            $table->foreignId('flotilla_id')
                ->nullable()
                ->constrained('flotillas')
                ->onDelete('set null');

            $table->index(['flotilla_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_ships', function (Blueprint $table) {
            $table->dropForeignKey(['flotilla_id']);
            $table->dropIndex(['flotilla_id']);
            $table->dropColumn('flotilla_id');
        });
    }
};

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
        Schema::table('pirate_factions', function (Blueprint $table) {
            $table->foreignId('galaxy_id')->after('id')->constrained('galaxies')->onDelete('cascade');
            $table->index('galaxy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pirate_factions', function (Blueprint $table) {
            $table->dropForeign(['galaxy_id']);
            $table->dropColumn('galaxy_id');
        });
    }
};

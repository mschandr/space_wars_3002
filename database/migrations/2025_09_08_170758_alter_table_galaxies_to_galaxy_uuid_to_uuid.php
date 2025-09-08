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
        Schema::table('galaxies', function (Blueprint $table) {
            if (Schema::hasColumn('galaxies', 'galaxy_uuid')) {
                $table->renameColumn('galaxy_uuid', 'uuid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galaxies', function (Blueprint $table) {
            if (Schema::hasColumn('galaxies', 'uuid')) {
                $table->renameColumn('uuid', 'galaxy_uuid');
            }
        });
    }
};

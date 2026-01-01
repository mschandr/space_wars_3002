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
        Schema::table('colonies', function (Blueprint $table) {
            $table->integer('quantium_storage')->default(0)->after('mineral_storage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colonies', function (Blueprint $table) {
            $table->dropColumn('quantium_storage');
        });
    }
};

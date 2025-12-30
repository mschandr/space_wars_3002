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
        Schema::table('trading_hubs', function (Blueprint $table) {
            $table->boolean('has_plans')->default(false)->after('has_salvage_yard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_hubs', function (Blueprint $table) {
            $table->dropColumn('has_plans');
        });
    }
};

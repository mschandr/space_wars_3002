<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salvage_yard_inventory', function (Blueprint $table) {
            $table->foreignId('poi_id')
                ->nullable()
                ->after('trading_hub_id')
                ->constrained('points_of_interest')
                ->onDelete('cascade');
        });

        // Make trading_hub_id nullable for POI-based inventory
        Schema::table('salvage_yard_inventory', function (Blueprint $table) {
            $table->unsignedBigInteger('trading_hub_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('salvage_yard_inventory', function (Blueprint $table) {
            $table->dropForeign(['poi_id']);
            $table->dropColumn('poi_id');
        });

        Schema::table('salvage_yard_inventory', function (Blueprint $table) {
            $table->unsignedBigInteger('trading_hub_id')->nullable(false)->change();
        });
    }
};

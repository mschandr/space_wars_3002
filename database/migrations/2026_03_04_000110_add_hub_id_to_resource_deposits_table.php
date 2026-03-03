<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_deposits', function (Blueprint $table) {
            if (!Schema::hasColumn('resource_deposits', 'trading_hub_id')) {
                $table->foreignId('trading_hub_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete()
                    ->after('commodity_id')
                    ->comment('Optional: if set, extraction routes to this hub. If null, uses galaxy fallback hub');

                // Index for faster lookups
                $table->index(['galaxy_id', 'trading_hub_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('resource_deposits', function (Blueprint $table) {
            $table->dropIndex(['galaxy_id', 'trading_hub_id']);
            $table->dropForeignKeyIfExists(['trading_hub_id']);
            $table->dropColumn('trading_hub_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_hub_inventories', function (Blueprint $table) {
            // New ledger-backed inventory fields
            $table->decimal('on_hand_qty', 14, 4)->default(0)->after('mineral_id')->comment('Actual physical quantity (units)');
            $table->decimal('reserved_qty', 14, 4)->default(0)->after('on_hand_qty')->comment('Reserved for pending orders');
            $table->dateTime('last_snapshot_at')->nullable()->after('reserved_qty')->comment('Last reconciliation with ledger');

            // Index for performance
            $table->index(['trading_hub_id', 'mineral_id']);
        });

        // Backfill on_hand_qty from existing quantity column
        \DB::statement('UPDATE trading_hub_inventories SET on_hand_qty = COALESCE(quantity, 0)');
    }

    public function down(): void
    {
        Schema::table('trading_hub_inventories', function (Blueprint $table) {
            $table->dropColumn(['on_hand_qty', 'reserved_qty', 'last_snapshot_at']);
            $table->dropIndex(['trading_hub_id', 'mineral_id']);
        });
    }
};

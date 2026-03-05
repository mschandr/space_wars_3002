<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_hubs', function (Blueprint $table) {
            $table->decimal('spread_buy', 5, 4)->default(0.08)->after('name')->comment('Buy side spread (0.08 = 8%)');
            $table->decimal('spread_sell', 5, 4)->default(0.08)->after('spread_buy')->comment('Sell side spread (0.08 = 8%)');
            $table->foreignId('reserve_policy_id')->nullable()->after('spread_sell')->comment('Optional minimum inventory policy');
        });
    }

    public function down(): void
    {
        Schema::table('trading_hubs', function (Blueprint $table) {
            $table->dropColumn(['spread_buy', 'spread_sell', 'reserve_policy_id']);
        });
    }
};

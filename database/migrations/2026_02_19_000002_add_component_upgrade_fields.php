<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ship_components', function (Blueprint $table) {
            $table->unsignedInteger('max_upgrade_level')->default(0)->after('is_available');
            $table->decimal('upgrade_cost_base', 12, 2)->default(0)->after('max_upgrade_level');
            $table->string('size_class', 20)->default('any')->after('upgrade_cost_base');
        });

        Schema::table('player_ship_components', function (Blueprint $table) {
            $table->unsignedInteger('upgrade_level')->default(0)->after('is_active');
            $table->decimal('mechanic_bonus', 5, 4)->default(0.0)->after('upgrade_level');
        });
    }

    public function down(): void
    {
        Schema::table('ship_components', function (Blueprint $table) {
            $table->dropColumn(['max_upgrade_level', 'upgrade_cost_base', 'size_class']);
        });

        Schema::table('player_ship_components', function (Blueprint $table) {
            $table->dropColumn(['upgrade_level', 'mechanic_bonus']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ships', function (Blueprint $table) {
            $table->unsignedInteger('engine_slots')->default(1)->after('utility_slots');
            $table->unsignedInteger('reactor_slots')->default(1)->after('engine_slots');
            $table->unsignedInteger('hull_plating_slots')->default(1)->after('reactor_slots');
            $table->unsignedInteger('shield_slots')->default(1)->after('hull_plating_slots');
            $table->unsignedInteger('sensor_slots')->default(1)->after('shield_slots');
            $table->unsignedInteger('cargo_module_slots')->default(1)->after('sensor_slots');
            $table->string('size_class', 20)->default('medium')->after('cargo_module_slots');
        });

        Schema::table('player_ships', function (Blueprint $table) {
            $table->unsignedInteger('engine_slots')->default(1)->after('utility_slots');
            $table->unsignedInteger('reactor_slots')->default(1)->after('engine_slots');
            $table->unsignedInteger('hull_plating_slots')->default(1)->after('reactor_slots');
            $table->unsignedInteger('shield_slots')->default(1)->after('hull_plating_slots');
            $table->unsignedInteger('sensor_slots')->default(1)->after('shield_slots');
            $table->unsignedInteger('cargo_module_slots')->default(1)->after('sensor_slots');
            $table->string('size_class', 20)->default('medium')->after('cargo_module_slots');
        });
    }

    public function down(): void
    {
        Schema::table('ships', function (Blueprint $table) {
            $table->dropColumn([
                'engine_slots', 'reactor_slots', 'hull_plating_slots',
                'shield_slots', 'sensor_slots', 'cargo_module_slots', 'size_class',
            ]);
        });

        Schema::table('player_ships', function (Blueprint $table) {
            $table->dropColumn([
                'engine_slots', 'reactor_slots', 'hull_plating_slots',
                'shield_slots', 'sensor_slots', 'cargo_module_slots', 'size_class',
            ]);
        });
    }
};

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
        Schema::table('colony_buildings', function (Blueprint $table) {
            // Stage requirements
            $table->integer('required_stage')->default(1)->after('building_type');

            // Operating costs (per hour/cycle)
            $table->integer('credits_per_cycle')->default(0)->after('effects');
            $table->integer('quantium_per_cycle')->default(0)->after('credits_per_cycle');
            $table->integer('food_per_cycle')->default(0)->after('quantium_per_cycle');
            $table->integer('minerals_per_cycle')->default(0)->after('food_per_cycle');

            // Production/income (per hour/cycle)
            $table->integer('credits_generated_per_cycle')->default(0)->after('minerals_per_cycle');

            // Last processed
            $table->timestamp('last_cycle_at')->nullable()->after('construction_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colony_buildings', function (Blueprint $table) {
            $table->dropColumn([
                'required_stage',
                'credits_per_cycle',
                'quantium_per_cycle',
                'food_per_cycle',
                'minerals_per_cycle',
                'credits_generated_per_cycle',
                'last_cycle_at',
            ]);
        });
    }
};

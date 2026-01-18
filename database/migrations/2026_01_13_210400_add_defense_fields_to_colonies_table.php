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
            $table->integer('defense_rating')->default(0)->after('development_level');
            $table->integer('garrison_strength')->default(0)->after('defense_rating');
            $table->timestamp('last_attacked_at')->nullable()->after('last_growth_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colonies', function (Blueprint $table) {
            $table->dropColumn(['defense_rating', 'garrison_strength', 'last_attacked_at']);
        });
    }
};

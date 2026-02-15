<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->boolean('is_charted')->default(false)->after('is_inhabited');
        });

        // Backfill: all currently inhabited systems are also charted
        DB::table('points_of_interest')
            ->where('is_inhabited', true)
            ->update(['is_charted' => true]);
    }

    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropColumn('is_charted');
        });
    }
};

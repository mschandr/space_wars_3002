<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ship_components')
            ->where('rarity', 'very_rare')
            ->update(['rarity' => 'epic']);

        DB::table('ship_components')
            ->where('rarity', 'legendary')
            ->update(['rarity' => 'exotic']);
    }

    public function down(): void
    {
        DB::table('ship_components')
            ->where('rarity', 'epic')
            ->update(['rarity' => 'very_rare']);

        DB::table('ship_components')
            ->where('rarity', 'exotic')
            ->update(['rarity' => 'legendary']);
    }
};

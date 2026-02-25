<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Core game data (galaxy-independent reference tables)
        // Note: PirateFactionSeeder, PirateCaptainSeeder, and WarpLanePirateSeeder
        // are galaxy-dependent and run during galaxy:initialize / galaxy:distribute-pirates
        $this->call([
            MineralSeeder::class,
            QuantiumMineralSeeder::class,
            PoiTypeSeeder::class,
            ShipTypesSeeder::class,
            ShipComponentsSeeder::class,
            PlansSeeder::class,
            PrecursorShipSeeder::class,
        ]);
    }
}

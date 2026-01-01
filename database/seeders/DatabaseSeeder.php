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
        // Core game data
        $this->call([
            ShipTypesSeeder::class,
            PirateFactionSeeder::class,
            PirateCaptainSeeder::class,
            WarpLanePirateSeeder::class,
        ]);
    }
}

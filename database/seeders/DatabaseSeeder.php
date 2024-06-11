<?php

namespace Database\Seeders;

use App\Models\CelestialBody;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(10)->create();
        (new CelestialBodyTypeSeeder())->run();
        CelestialBody::factory(1000)->create();
    }
}

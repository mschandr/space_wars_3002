<?php

namespace Database\Seeders;

use App\Models\CelestialBody;
use App\Models\CelestialBodyType;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(10)->create();
        (CelestialBodyType::all()->count() < 5) ?? CelestialBodyType::factory(10)->create();
        CelestialBody::factory(20)->create();
    }
}

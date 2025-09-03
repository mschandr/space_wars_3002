<?php

namespace Database\Seeders;

use App\Models\CelestialBody;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Galaxy

        $attempts  = 0;
        $row_count = 2500;
        User::factory(10)->create();
        // Running the following script will cause a cascading delete of CelestialBody
        (new CelestialBodyTypeSeeder())->run();
        // I'm doing it this way because when you insert by defining factory(2500) you can't do checks
        // against rows that are going to be inserted.
        while ($attempts < 10 || CelestialBody::count() < $row_count) {
            try {
                CelestialBody::factory()->create();
                $attempts++;
            } catch (QueryException $e) {
                // we're going to ignore this exception should it occur...
                if ($e->getCode() === '23000') {
                    Log::info($e->getMessage());
                }
            }
        }
    }
}

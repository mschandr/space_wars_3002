<?php

namespace Database\Seeders;

use App\Models\CelestialBody;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StarSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        //CelestialBody::getId
        $star_systems = [
            'id'                => (string) Str::uuid(),
            'star_type_id'      => 'star_type_id', //get real random star_type_id
            'celestial_body_id' => 'celestial_body_id', // the reference for which/where the star is, as well as
                                                        // the stars name
            'created_at'        => $now,
            'updated_at'        => $now,

        ];
    }
}

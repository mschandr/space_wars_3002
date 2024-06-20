<?php

namespace Database\Seeders;

use App\Models\StarType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StarTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /***
         *
         *            Name                      Age (Years)               Temperature (Kelvin)    Magnetic Field
         *    O        Hot Stars                10 - 90 Million           30,000 - 50,000            Weak
         *    B        Very Hot Stars           10 - 100 Million          10,000 - 30,000            Moderate
         *    A        Hot Stars                100 Million - 1 Billion    7,500 - 10,000            Strong
         *    F        Yellow-White Stars       1 - 5 Billion               6,000 - 7,500            Moderate
         *    G        Yellow Stars (Sun-like)  5 - 10 Billion              5,000 - 6,000            Weak
         *    K        Orange Stars             10 - 20 Billion             3,500 - 5,000            Very Weak
         *    M        Cool Stars (Red Dwarfs)  Trillions                   2,000 - 3,500            Very Weak
         *    N        Neutron stars            10 billion - Trillions    100,000 - 1,000,000        Very strong
         ***/
        $now        = date('Y-m-d H:i:s');
        $star_types = [
            [   // O type star
                'id'              => (string) Str::uuid(),
                'classification'  => 'O',
                'name'            => 'Hot Stars',
                'age_min'         => 10,
                'age_max'         => 90,
                'temperature_min' => 30.00,
                'temperature_max' => 50.00,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_WEAK,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [   // B type Stars
                'id'              => (string) Str::uuid(),
                'classification'  => 'B',
                'name'            => 'Very Hot Stars',
                'age_min'         => 10,
                'age_max'         => 100,
                'temperature_min' => 10.00,
                'temperature_max' => 30.00,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_MODERATE,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [   // A type stars
                'id'              => (string) Str::uuid(),
                'classification'  => 'A',
                'name'            => 'Hot Stars',
                'age_min'         => 100,
                'age_max'         => 1000,
                'temperature_min' => 7.50,
                'temperature_max' => 10.00,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_STRONG,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [   // F type stars
                'id'              => (string) Str::uuid(),
                'classification'  => 'F',
                'name'            => 'Yellow-White Stars',
                'age_min'         => 1000,
                'age_max'         => 5000,
                'temperature_min' => 6.00,
                'temperature_max' => 7.50,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_MODERATE,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::uuid(),
                'classification'  => 'G',
                'name'            => 'Yellow Stars',  // the proper name for these types of stars
                'age_min'         => 5000,
                'age_max'         => 10000,
                'temperature_min' => 5,
                'temperature_max' => 6,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_WEAK,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::uuid(),
                'classification'  => 'K',
                'name'            => 'Orange Stars',  // the proper name for these types of stars
                'age_min'         => 1000000,
                'age_max'         => null,
                'temperature_min' => 3.5,
                'temperature_max' => 5.0,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_VERY_WEAK,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::uuid(),
                'classification'  => 'M',
                'name'            => 'Cool Stars',  // the proper name for these types of stars
                'age_min'         => 10000,
                'age_max'         => 20000,
                'temperature_min' => 3.5,
                'temperature_max' => 5.0,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_VERY_WEAK,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::uuid(),
                'classification'  => 'N',
                'name'            => 'Neutron stars',
                'age_min'         => 10000,
                'age_max'         => 20000,
                'temperature_min' => 3.5,
                'temperature_max' => 5.0,
                'magnetic_field'  => StarType::STAR_MAGNETIC_LEVEL_VERY_STRONG,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];
        
        StarType::insert($star_types);

    }
}

<?php

namespace Database\Seeders;

use App\Models\Ship;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShipTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ships = [
            [
                'name' => 'Sparrow-class Light Freighter',
                'class' => 'Light Freighter',
                'description' => 'A reliable entry-level vessel. Not exceptional at anything, but capable enough to get started in the galaxy.',
                'base_price' => 5000.00,
                'cargo_capacity' => 50,
                'speed' => 100,
                'hull_strength' => 80,
                'shield_strength' => 40,
                'weapon_slots' => 1,
                'utility_slots' => 1,
                'rarity' => 'common',
                'requirements' => null,
                'attributes' => [
                    'max_fuel' => 100,
                    'starting_weapons' => 15,
                    'starting_sensors' => 1,
                    'starting_warp_drive' => 1,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Viper-class Fighter',
                'class' => 'Fighter',
                'description' => 'A nimble combat vessel built for speed and firepower. Light cargo capacity makes it unsuitable for trading.',
                'base_price' => 15000.00,
                'cargo_capacity' => 20,
                'speed' => 180,
                'hull_strength' => 100,
                'shield_strength' => 80,
                'weapon_slots' => 3,
                'utility_slots' => 1,
                'rarity' => 'common',
                'requirements' => null,
                'attributes' => [
                    'max_fuel' => 80,
                    'starting_weapons' => 35,
                    'starting_sensors' => 1,
                    'starting_warp_drive' => 1,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Mammoth-class Heavy Hauler',
                'class' => 'Heavy Hauler',
                'description' => 'A slow but massive cargo vessel. Perfect for traders who prioritize profit over speed.',
                'base_price' => 25000.00,
                'cargo_capacity' => 250,
                'speed' => 60,
                'hull_strength' => 120,
                'shield_strength' => 60,
                'weapon_slots' => 1,
                'utility_slots' => 2,
                'rarity' => 'common',
                'requirements' => null,
                'attributes' => [
                    'max_fuel' => 150,
                    'starting_weapons' => 10,
                    'starting_sensors' => 1,
                    'starting_warp_drive' => 1,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Phantom-class Scout',
                'class' => 'Scout',
                'description' => 'Built for exploration and reconnaissance. Advanced sensors and efficient warp drive make it ideal for discovering hidden sectors.',
                'base_price' => 18000.00,
                'cargo_capacity' => 40,
                'speed' => 150,
                'hull_strength' => 70,
                'shield_strength' => 50,
                'weapon_slots' => 1,
                'utility_slots' => 3,
                'rarity' => 'uncommon',
                'requirements' => null,
                'attributes' => [
                    'max_fuel' => 120,
                    'starting_weapons' => 12,
                    'starting_sensors' => 3,
                    'starting_warp_drive' => 2,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Corsair-class Gunship',
                'class' => 'Gunship',
                'description' => 'A well-armed and armored warship. Balanced combat capabilities make it a versatile choice for dangerous sectors.',
                'base_price' => 40000.00,
                'cargo_capacity' => 80,
                'speed' => 120,
                'hull_strength' => 150,
                'shield_strength' => 120,
                'weapon_slots' => 4,
                'utility_slots' => 2,
                'rarity' => 'uncommon',
                'requirements' => null,
                'attributes' => [
                    'max_fuel' => 100,
                    'starting_weapons' => 50,
                    'starting_sensors' => 2,
                    'starting_warp_drive' => 1,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Leviathan-class Battleship',
                'class' => 'Battleship',
                'description' => 'A fearsome capital ship bristling with weapons. Expensive to operate and requires significant experience to command.',
                'base_price' => 100000.00,
                'cargo_capacity' => 100,
                'speed' => 80,
                'hull_strength' => 250,
                'shield_strength' => 200,
                'weapon_slots' => 6,
                'utility_slots' => 3,
                'rarity' => 'rare',
                'requirements' => [
                    'level' => 10,
                ],
                'attributes' => [
                    'max_fuel' => 120,
                    'starting_weapons' => 80,
                    'starting_sensors' => 2,
                    'starting_warp_drive' => 1,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Nomad-class Explorer',
                'class' => 'Explorer',
                'description' => 'The ultimate long-range exploration vessel. Superior fuel capacity and warp efficiency allow extended journeys into uncharted space.',
                'base_price' => 35000.00,
                'cargo_capacity' => 100,
                'speed' => 110,
                'hull_strength' => 90,
                'shield_strength' => 70,
                'weapon_slots' => 2,
                'utility_slots' => 4,
                'rarity' => 'uncommon',
                'requirements' => null,
                'attributes' => [
                    'max_fuel' => 200,
                    'starting_weapons' => 18,
                    'starting_sensors' => 4,
                    'starting_warp_drive' => 3,
                ],
                'is_available' => true,
            ],
        ];

        foreach ($ships as $shipData) {
            Ship::create($shipData);
        }
    }
}

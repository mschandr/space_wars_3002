<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\Ship;
use Illuminate\Database\Seeder;

class ShipTypesSeeder extends Seeder
{
    /**
     * Provide predefined ship blueprints for seeding.
     *
     * @return array An array of ship definition associative arrays. Each definition includes the keys:
     *               `name`, `class`, `description`, `base_price`, `cargo_capacity`, `speed`,
     *               `hull_strength`, `shield_strength`, `weapon_slots`, `utility_slots`, `rarity`,
     *               `requirements`, `attributes`, and `is_available`. The `attributes` entry is an
     *               associative array that typically contains `max_fuel`, `starting_weapons`,
     *               `starting_sensors`, and `starting_warp_drive`, and may include optional capability
     *               flags such as `warp_interdiction`, `fighter_capacity`, `is_carrier`,
     *               `colonist_capacity`, and `colony_supplies_capacity`.
     */
    public function getShipDefinitions(): array
    {
        return [
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
            [
                'name' => 'Interdictor-class Corvette',
                'class' => 'Corvette',
                'description' => 'A specialized interdiction vessel favored by pirates and law enforcement. Advanced warp field generators can prevent enemy ships from escaping.',
                'base_price' => 28000.00,
                'cargo_capacity' => 60,
                'speed' => 140,
                'hull_strength' => 110,
                'shield_strength' => 90,
                'weapon_slots' => 3,
                'utility_slots' => 2,
                'rarity' => 'uncommon',
                'requirements' => null,
                'attributes' => [
                    'max_fuel' => 110,
                    'starting_weapons' => 30,
                    'starting_sensors' => 2,
                    'starting_warp_drive' => 2,
                    'warp_interdiction' => true,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Atlas-class Battlecarrier',
                'class' => 'Battlecarrier',
                'description' => 'A massive capital ship designed to deploy fighter squadrons. Its hangar bays can house up to 6 fighters for rapid deployment against pirates and hostiles. Lower combat capability than battleships, but unmatched tactical flexibility.',
                'base_price' => 175000.00,
                'cargo_capacity' => 150,
                'speed' => 70,
                'hull_strength' => 300,
                'shield_strength' => 250,
                'weapon_slots' => 4,
                'utility_slots' => 5,
                'rarity' => 'very_rare',
                'requirements' => [
                    'level' => 15,
                ],
                'attributes' => [
                    'max_fuel' => 180,
                    'starting_weapons' => 40,
                    'starting_sensors' => 3,
                    'starting_warp_drive' => 2,
                    'fighter_capacity' => 6,
                    'is_carrier' => true,
                ],
                'is_available' => true,
            ],
            [
                'name' => 'Colonist-class Transport',
                'class' => 'Colony Ship',
                'description' => 'A specialized vessel designed to transport colonists and supplies to establish new settlements. Equipped with cryogenic pods and modular habitat sections.',
                'base_price' => 45000.00,
                'cargo_capacity' => 150,
                'speed' => 90,
                'hull_strength' => 120,
                'shield_strength' => 60,
                'weapon_slots' => 1,
                'utility_slots' => 4,
                'rarity' => 'uncommon',
                'requirements' => [
                    'level' => 8,
                ],
                'attributes' => [
                    'max_fuel' => 200,
                    'starting_weapons' => 10,
                    'starting_sensors' => 2,
                    'starting_warp_drive' => 2,
                    'colonist_capacity' => 500,
                    'is_colony_ship' => true,
                    'colony_supplies_capacity' => 100,
                ],
                'is_available' => true,
            ],
        ];
    }

    /**
         * No-op seeder entry point.
         *
         * Ship blueprint records are created per-galaxy by calling generateShips(); this method intentionally
         * does not perform seeding itself.
         */
    public function run(): void
    {
        // Ships are created per-galaxy via generateShips()
    }

    /**
     * Creates or updates global ship blueprint records from the predefined ship definitions.
     *
     * Fetches definitions from getShipDefinitions() and ensures each definition exists in the database
     * by creating or updating a Ship record keyed by name and class. The provided Galaxy is only used
     * for contextual invocation and does not scope the created blueprints to that galaxy.
     *
     * @param \App\Models\Galaxy $galaxy Galaxy instance used for contextual seeding (not used to scope records).
     * @param mixed|null $command Optional command/context object (for callers that provide one).
     */
    public function generateShips(Galaxy $galaxy, $command = null): void
    {
        $ships = $this->getShipDefinitions();

        foreach ($ships as $shipData) {
            // Use updateOrCreate to prevent duplicates (ships are global blueprints, not per-galaxy)
            Ship::updateOrCreate(
                [
                    'name' => $shipData['name'],
                    'class' => $shipData['class'],
                ],
                $shipData
            );
        }
    }
}
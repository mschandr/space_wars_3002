<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\Ship;
use Illuminate\Database\Seeder;

class ShipTypesSeeder extends Seeder
{
    /**
     * Get ship definitions.
     *
     * The 6 core human ship classes:
     * 1. Starter - Balanced entry-level ship for new pilots
     * 2. Smuggler - Hidden cargo holds that pirates can't access, but burns more fuel
     * 3. Battleship - Heavily armored warship that dominates in combat
     * 4. Cargo - Massive holds (100x starter), but slow and fuel-hungry
     * 5. Carrier - Deploys fighter squadrons for tactical flexibility
     * 6. Colony Ship - Transports 10,000 colonists with everything needed to start a new world
     */
    public function getShipDefinitions(): array
    {
        return [
            // ============================================================
            // 1. STARTER SHIP - Sparrow-class Light Freighter
            // ============================================================
            [
                'name' => 'Sparrow-class Light Freighter',
                'class' => 'starter',
                'description' => 'A reliable entry-level vessel that every pilot begins their journey with. Not exceptional at anything, but capable enough to get started in the galaxy. Its simplicity makes it easy to maintain and forgiving of rookie mistakes.',
                'base_price' => 0.00, // Free starter ship
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
                    'fuel_regen_rate' => 1.0, // Standard regen (30 seconds per unit)
                    'fuel_consumption_rate' => 1.0, // Standard consumption
                    'starting_weapons' => 15,
                    'starting_sensors' => 1,
                    'starting_warp_drive' => 1,
                    'is_starter' => true,
                ],
                'is_available' => true,
            ],

            // ============================================================
            // 2. SMUGGLER SHIP - Wraith-class Runner
            // ============================================================
            [
                'name' => 'Wraith-class Runner',
                'class' => 'smuggler',
                'description' => 'A sleek vessel favored by those who prefer to keep their cargo... private. Features sophisticated hidden compartments that are virtually undetectable by pirate scans. The stealth systems and reinforced hidden holds require additional power, resulting in higher fuel consumption.',
                'base_price' => 35000.00,
                'cargo_capacity' => 40, // Regular cargo is smaller
                'speed' => 140,
                'hull_strength' => 90,
                'shield_strength' => 70,
                'weapon_slots' => 2,
                'utility_slots' => 3,
                'rarity' => 'uncommon',
                'requirements' => [
                    'level' => 5,
                ],
                'attributes' => [
                    'max_fuel' => 120,
                    'fuel_regen_rate' => 1.0, // Standard regen
                    'fuel_consumption_rate' => 1.4, // 40% more fuel consumption
                    'starting_weapons' => 25,
                    'starting_sensors' => 2,
                    'starting_warp_drive' => 2,
                    'hidden_hold_capacity' => 80, // Hidden cargo pirates can't access
                    'is_smuggler' => true,
                    'stealth_bonus' => 0.3, // 30% harder for pirates to detect cargo
                ],
                'is_available' => true,
            ],

            // ============================================================
            // 3. BATTLESHIP - Leviathan-class Dreadnought
            // ============================================================
            [
                'name' => 'Leviathan-class Dreadnought',
                'class' => 'battleship',
                'description' => 'A fearsome capital warship bristling with weapons and wrapped in heavy armor plating. The Leviathan can take on any starter ship or smuggler without breaking a sweat, even if they have upgrades. Expensive to operate and requires significant combat experience to command effectively.',
                'base_price' => 150000.00,
                'cargo_capacity' => 80,
                'speed' => 70,
                'hull_strength' => 350,
                'shield_strength' => 280,
                'weapon_slots' => 8,
                'utility_slots' => 3,
                'rarity' => 'rare',
                'requirements' => [
                    'level' => 12,
                ],
                'attributes' => [
                    'max_fuel' => 150,
                    'fuel_regen_rate' => 0.8, // 20% slower regen (big ship)
                    'fuel_consumption_rate' => 1.3, // 30% more fuel consumption
                    'starting_weapons' => 100,
                    'starting_sensors' => 3,
                    'starting_warp_drive' => 1,
                    'armor_plating' => 50, // Damage reduction
                    'is_capital_ship' => true,
                    'combat_bonus' => 0.25, // 25% bonus to combat effectiveness
                ],
                'is_available' => true,
            ],

            // ============================================================
            // 4. CARGO SHIP - Titan-class Supertanker
            // ============================================================
            [
                'name' => 'Titan-class Supertanker',
                'class' => 'cargo',
                'description' => 'An absolutely massive cargo vessel with holds that can carry 100 times what a starter ship manages. The Titan is the backbone of galactic commerce, but its immense size means painfully slow fuel regeneration and enormous fuel consumption. Best operated with escort protection.',
                'base_price' => 200000.00,
                'cargo_capacity' => 5000, // 100x the Sparrow's 50
                'speed' => 40, // Very slow
                'hull_strength' => 200,
                'shield_strength' => 100,
                'weapon_slots' => 2,
                'utility_slots' => 4,
                'rarity' => 'rare',
                'requirements' => [
                    'level' => 10,
                ],
                'attributes' => [
                    'max_fuel' => 300, // Large fuel tank
                    'fuel_regen_rate' => 0.3, // 70% slower regen (massive reactor demands)
                    'fuel_consumption_rate' => 2.5, // 150% more fuel consumption
                    'starting_weapons' => 15,
                    'starting_sensors' => 2,
                    'starting_warp_drive' => 1,
                    'is_freighter' => true,
                    'bulk_cargo_bonus' => 0.1, // 10% bonus on bulk trade profits
                ],
                'is_available' => true,
            ],

            // ============================================================
            // 5. CARRIER - Sovereign-class Command Ship
            // ============================================================
            [
                'name' => 'Sovereign-class Command Ship',
                'class' => 'carrier',
                'description' => 'A massive command vessel designed to deploy and coordinate fighter squadrons. The Sovereign\'s hangar bays can house up to 12 fighters for rapid deployment against pirates and hostile forces. While lacking the raw firepower of a battleship, its tactical flexibility is unmatched.',
                'base_price' => 250000.00,
                'cargo_capacity' => 150,
                'speed' => 60,
                'hull_strength' => 400,
                'shield_strength' => 300,
                'weapon_slots' => 4,
                'utility_slots' => 6,
                'rarity' => 'very_rare',
                'requirements' => [
                    'level' => 18,
                ],
                'attributes' => [
                    'max_fuel' => 250,
                    'fuel_regen_rate' => 0.7, // 30% slower regen
                    'fuel_consumption_rate' => 1.5, // 50% more consumption
                    'starting_weapons' => 50,
                    'starting_sensors' => 4,
                    'starting_warp_drive' => 2,
                    'fighter_capacity' => 12,
                    'is_carrier' => true,
                    'is_capital_ship' => true,
                    'command_bonus' => 0.2, // 20% bonus to fighter effectiveness
                ],
                'is_available' => true,
            ],

            // ============================================================
            // 6. COLONY SHIP - Exodus-class Ark
            // ============================================================
            [
                'name' => 'Exodus-class Ark',
                'class' => 'colony_ship',
                'description' => 'A colossal generation ship designed to transport 10,000 colonists in cryogenic stasis along with all the equipment, materials, and supplies needed to establish a thriving colony from scratch. Comes pre-loaded with colonists eager to build a new home, mining equipment, habitat construction modules, and a year\'s worth of supplies.',
                'base_price' => 500000.00,
                'cargo_capacity' => 500, // For additional supplies
                'speed' => 50,
                'hull_strength' => 250,
                'shield_strength' => 150,
                'weapon_slots' => 2,
                'utility_slots' => 8,
                'rarity' => 'legendary',
                'requirements' => [
                    'level' => 15,
                ],
                'attributes' => [
                    'max_fuel' => 400,
                    'fuel_regen_rate' => 0.5, // 50% slower regen
                    'fuel_consumption_rate' => 2.0, // 100% more consumption
                    'starting_weapons' => 20,
                    'starting_sensors' => 3,
                    'starting_warp_drive' => 2,
                    'colonist_capacity' => 10000,
                    'starting_colonists' => 10000, // Pre-loaded!
                    'is_colony_ship' => true,
                    'is_capital_ship' => true,
                    'colony_supplies' => [
                        'mining_equipment' => 100,
                        'habitat_modules' => 50,
                        'food_supplies' => 365, // Days worth
                        'construction_materials' => 200,
                        'medical_supplies' => 100,
                        'seed_bank' => true,
                        'terraforming_basics' => true,
                    ],
                    'colony_bonus' => 0.25, // 25% faster colony establishment
                ],
                'is_available' => true,
            ],

            // ============================================================
            // ADDITIONAL SHIPS (keeping some existing ones)
            // ============================================================

            // Combat Fighter - smaller combat ship
            [
                'name' => 'Viper-class Fighter',
                'class' => 'fighter',
                'description' => 'A nimble combat vessel built for speed and firepower. Light cargo capacity makes it unsuitable for trading, but its agility makes it deadly in combat.',
                'base_price' => 18000.00,
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
                    'fuel_regen_rate' => 1.2, // 20% faster regen (small, efficient)
                    'fuel_consumption_rate' => 0.8, // 20% less consumption
                    'starting_weapons' => 40,
                    'starting_sensors' => 1,
                    'starting_warp_drive' => 1,
                    'evasion_bonus' => 0.2, // 20% harder to hit
                ],
                'is_available' => true,
            ],

            // Explorer - long range exploration
            [
                'name' => 'Nomad-class Explorer',
                'class' => 'explorer',
                'description' => 'The ultimate long-range exploration vessel. Superior fuel capacity and warp efficiency allow extended journeys into uncharted space. Advanced sensors can detect hidden gates and anomalies.',
                'base_price' => 45000.00,
                'cargo_capacity' => 100,
                'speed' => 120,
                'hull_strength' => 90,
                'shield_strength' => 70,
                'weapon_slots' => 2,
                'utility_slots' => 5,
                'rarity' => 'uncommon',
                'requirements' => [
                    'level' => 6,
                ],
                'attributes' => [
                    'max_fuel' => 250,
                    'fuel_regen_rate' => 1.3, // 30% faster regen
                    'fuel_consumption_rate' => 0.7, // 30% less consumption
                    'starting_weapons' => 20,
                    'starting_sensors' => 5,
                    'starting_warp_drive' => 3,
                    'exploration_bonus' => 0.3, // 30% bonus to discovery rewards
                ],
                'is_available' => true,
            ],

            // Mining ship
            [
                'name' => 'Prospector-class Mining Vessel',
                'class' => 'mining',
                'description' => 'A specialized ship equipped with mining lasers and ore processing facilities. Can extract resources directly from asteroid belts and mineral-rich planets.',
                'base_price' => 55000.00,
                'cargo_capacity' => 200, // For ore
                'speed' => 80,
                'hull_strength' => 150,
                'shield_strength' => 80,
                'weapon_slots' => 1,
                'utility_slots' => 4,
                'rarity' => 'uncommon',
                'requirements' => [
                    'level' => 7,
                ],
                'attributes' => [
                    'max_fuel' => 150,
                    'fuel_regen_rate' => 0.9,
                    'fuel_consumption_rate' => 1.2,
                    'starting_weapons' => 15,
                    'starting_sensors' => 2,
                    'starting_warp_drive' => 1,
                    'mining_lasers' => 3,
                    'ore_processing' => true,
                    'mining_bonus' => 0.4, // 40% faster mining
                ],
                'is_available' => true,
            ],

            // ============================================================
            // PRECURSOR SHIP - Cannot be purchased, must be found!
            // ============================================================
            [
                'name' => 'Void Strider (Precursor Vessel)',
                'class' => 'precursor',
                'description' => 'A legendary vessel from 500,000 years ago. The flagship of the Precursor Stellar Engineering Corps, this ship coordinated the repositioning of entire star systems. Its hull bears scars from battles with entities we cannot comprehend. Its databanks contain star charts of galaxies that no longer exist. CANNOT BE PURCHASED - One is hidden in each galaxy, waiting to be found.',
                'base_price' => 0.00, // Cannot be purchased
                'cargo_capacity' => 1000000, // Pocket dimension storage
                'speed' => 10000, // Relativistic
                'hull_strength' => 1000000, // Effectively invincible
                'shield_strength' => 1000000, // Regenerating shields
                'weapon_slots' => 100, // Overwhelming firepower
                'utility_slots' => 100, // Ancient technology
                'rarity' => 'precursor', // Unique rarity
                'requirements' => [
                    'found' => true, // Must be discovered, not bought
                ],
                'attributes' => [
                    'max_fuel' => 999999999, // Infinite fuel
                    'fuel_regen_rate' => 999.0, // Instant regen
                    'fuel_consumption_rate' => 0.0, // No fuel consumption
                    'starting_weapons' => 10000,
                    'starting_sensors' => 100,
                    'starting_warp_drive' => 100,
                    'is_precursor' => true,
                    'jump_drive' => true, // Can jump anywhere without gates
                    'pocket_dimension' => true, // Infinite cargo
                    'shield_harmonics' => 1000, // Regenerating shields
                    'matter_replicator' => true, // Self-repair
                    'neural_interface' => true, // Mind-ship connection
                    'stellar_cartography' => 'complete', // Full ancient star maps
                    'detection_requirement' => [
                        'sensor_level' => 12,
                        'distance' => 10,
                    ],
                ],
                'is_available' => false, // CANNOT BE PURCHASED
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ships = $this->getShipDefinitions();

        foreach ($ships as $shipData) {
            Ship::updateOrCreate(
                [
                    'name' => $shipData['name'],
                ],
                $shipData
            );
        }
    }

    public function generateShips(Galaxy $galaxy, $command = null): void
    {
        $this->run();
    }
}

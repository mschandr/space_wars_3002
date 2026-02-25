<?php

namespace Database\Seeders;

use App\Enums\RarityTier;
use App\Models\ShipComponent;
use Illuminate\Database\Seeder;

/**
 * Seeds ship components for salvage yards.
 *
 * Components are divided into 8 slot types:
 * - Weapons (weapon): Damage-dealing equipment
 * - Shield Generators (shield_generator): Shield regen and capacity
 * - Hull Plating (hull_plating): Armor and hull repair
 * - Engines (engine): Speed and fuel efficiency
 * - Reactors (reactor): Fuel capacity and regeneration
 * - Sensor Arrays (sensor_array): Detection range and pirate detection
 * - Cargo Modules (cargo_module): Cargo capacity expansion
 * - Utilities (utility): Special systems (cloak, emergency jump, etc.)
 */
class ShipComponentsSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // ========================================
            // WEAPONS (weapon)
            // ========================================

            // Lasers - basic, reliable, no ammo
            [
                'name' => 'Mark I Pulse Laser',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'Standard pulse laser. Reliable and requires no ammunition.',
                'slots_required' => 1,
                'base_price' => 5000,
                'rarity' => RarityTier::COMMON,
                'effects' => ['damage' => 25, 'accuracy' => 0.85, 'fire_rate' => 1.0],
                'requirements' => null,
                'max_upgrade_level' => 6,
                'upgrade_cost_base' => 1500,
            ],
            [
                'name' => 'Mark II Pulse Laser',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'Improved pulse laser with better damage output.',
                'slots_required' => 1,
                'base_price' => 12000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['damage' => 40, 'accuracy' => 0.88, 'fire_rate' => 1.1],
                'requirements' => ['level' => 3],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 3600,
            ],
            [
                'name' => 'Heavy Beam Laser',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'High-powered beam laser. Slow to fire but devastating.',
                'slots_required' => 2,
                'base_price' => 35000,
                'rarity' => RarityTier::RARE,
                'effects' => ['damage' => 100, 'accuracy' => 0.92, 'fire_rate' => 0.5],
                'requirements' => ['level' => 5],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 10500,
            ],

            // Missiles - high damage, uses ammo
            [
                'name' => 'Seeker Missile Pod',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'Heat-seeking missiles. High damage but limited ammunition.',
                'slots_required' => 1,
                'base_price' => 15000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['damage' => 60, 'accuracy' => 0.75, 'max_ammo' => 20],
                'requirements' => ['level' => 2],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 4500,
            ],
            [
                'name' => 'Swarm Missile Launcher',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'Launches clusters of small missiles. Overwhelming against small targets.',
                'slots_required' => 2,
                'base_price' => 45000,
                'rarity' => RarityTier::RARE,
                'effects' => ['damage' => 80, 'accuracy' => 0.70, 'max_ammo' => 50, 'fire_rate' => 2.0],
                'requirements' => ['level' => 6],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 13500,
            ],

            // Torpedoes - very high damage, slow, limited ammo
            [
                'name' => 'Plasma Torpedo Tube',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'Launches devastating plasma torpedoes. Slow but extremely powerful.',
                'slots_required' => 2,
                'base_price' => 75000,
                'rarity' => RarityTier::EPIC,
                'effects' => ['damage' => 200, 'accuracy' => 0.65, 'max_ammo' => 8, 'fire_rate' => 0.3],
                'requirements' => ['level' => 8],
                'max_upgrade_level' => 1,
                'upgrade_cost_base' => 22500,
            ],
            [
                'name' => 'Precursor Disruptor',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'Ancient technology that disrupts ship systems. Extremely rare.',
                'slots_required' => 3,
                'base_price' => 250000,
                'rarity' => RarityTier::EXOTIC,
                'effects' => ['damage' => 350, 'accuracy' => 0.95, 'fire_rate' => 0.8, 'system_disruption' => 0.3],
                'requirements' => ['level' => 12],
                'max_upgrade_level' => 0,
                'upgrade_cost_base' => 0,
            ],

            // Point Defense - anti-missile
            [
                'name' => 'Point Defense Turret',
                'type' => 'weapon',
                'slot_type' => 'weapon',
                'description' => 'Automated turret that shoots down incoming missiles and torpedoes.',
                'slots_required' => 1,
                'base_price' => 20000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['damage' => 15, 'accuracy' => 0.90, 'fire_rate' => 3.0, 'anti_missile' => true],
                'requirements' => ['level' => 4],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 6000,
            ],

            // ========================================
            // SHIELD GENERATORS (shield_generator)
            // ========================================
            [
                'name' => 'Basic Shield Regenerator',
                'type' => 'shield',
                'slot_type' => 'shield_generator',
                'description' => 'Slowly regenerates shield capacity over time.',
                'slots_required' => 1,
                'base_price' => 8000,
                'rarity' => RarityTier::COMMON,
                'effects' => ['shield_regen' => 5, 'shield_boost' => 0],
                'requirements' => null,
                'max_upgrade_level' => 6,
                'upgrade_cost_base' => 2400,
            ],
            [
                'name' => 'Enhanced Shield Regenerator',
                'type' => 'shield',
                'slot_type' => 'shield_generator',
                'description' => 'Faster shield regeneration with minor capacity boost.',
                'slots_required' => 1,
                'base_price' => 22000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['shield_regen' => 12, 'shield_boost' => 50],
                'requirements' => ['level' => 3],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 6600,
            ],
            [
                'name' => 'Military Grade Shield Booster',
                'type' => 'shield',
                'slot_type' => 'shield_generator',
                'description' => 'Significantly increases shield capacity and regeneration.',
                'slots_required' => 2,
                'base_price' => 55000,
                'rarity' => RarityTier::RARE,
                'effects' => ['shield_regen' => 20, 'shield_boost' => 150],
                'requirements' => ['level' => 6],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 16500,
            ],
            [
                'name' => 'Adaptive Shield Matrix',
                'type' => 'shield',
                'slot_type' => 'shield_generator',
                'description' => 'Advanced shields that adapt to incoming damage types.',
                'slots_required' => 2,
                'base_price' => 120000,
                'rarity' => RarityTier::EPIC,
                'effects' => ['shield_regen' => 30, 'shield_boost' => 300, 'damage_resistance' => 0.15],
                'requirements' => ['level' => 9],
                'max_upgrade_level' => 1,
                'upgrade_cost_base' => 36000,
            ],

            // ========================================
            // HULL PLATING (hull_plating)
            // ========================================
            [
                'name' => 'Emergency Hull Patch',
                'type' => 'hull',
                'slot_type' => 'hull_plating',
                'description' => 'Temporary patches that slowly repair hull damage.',
                'slots_required' => 1,
                'base_price' => 6000,
                'rarity' => RarityTier::COMMON,
                'effects' => ['hull_repair' => 3],
                'requirements' => null,
                'max_upgrade_level' => 6,
                'upgrade_cost_base' => 1800,
            ],
            [
                'name' => 'Nano-Repair System',
                'type' => 'hull',
                'slot_type' => 'hull_plating',
                'description' => 'Nanobots that continuously repair hull damage.',
                'slots_required' => 1,
                'base_price' => 28000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['hull_repair' => 8, 'hull_boost' => 25],
                'requirements' => ['level' => 4],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 8400,
            ],
            [
                'name' => 'Reinforced Hull Plating',
                'type' => 'hull',
                'slot_type' => 'hull_plating',
                'description' => 'Heavy armor plating that increases maximum hull integrity.',
                'slots_required' => 2,
                'base_price' => 40000,
                'rarity' => RarityTier::RARE,
                'effects' => ['hull_boost' => 100, 'damage_resistance' => 0.10],
                'requirements' => ['level' => 5],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 12000,
            ],
            [
                'name' => 'Ablative Armor Matrix',
                'type' => 'hull',
                'slot_type' => 'hull_plating',
                'description' => 'Self-repairing armor that ablates damage and regenerates.',
                'slots_required' => 2,
                'base_price' => 95000,
                'rarity' => RarityTier::EPIC,
                'effects' => ['hull_repair' => 15, 'hull_boost' => 200, 'damage_resistance' => 0.20],
                'requirements' => ['level' => 8],
                'max_upgrade_level' => 1,
                'upgrade_cost_base' => 28500,
            ],

            // ========================================
            // ENGINES (engine)
            // ========================================
            [
                'name' => 'Standard Ion Drive',
                'type' => 'engine',
                'slot_type' => 'engine',
                'description' => 'A basic but reliable ion propulsion system. Gets the job done.',
                'slots_required' => 1,
                'base_price' => 8000,
                'rarity' => RarityTier::COMMON,
                'effects' => ['speed' => 80, 'fuel_efficiency' => 0],
                'requirements' => null,
                'max_upgrade_level' => 6,
                'upgrade_cost_base' => 2400,
            ],
            [
                'name' => 'Fusion Impulse Engine',
                'type' => 'engine',
                'slot_type' => 'engine',
                'description' => 'Fusion-powered engine with improved speed and fuel efficiency.',
                'slots_required' => 1,
                'base_price' => 20000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['speed' => 120, 'fuel_efficiency' => 0.10],
                'requirements' => ['level' => 3],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 6000,
            ],
            [
                'name' => 'Plasma Thrust Array',
                'type' => 'engine',
                'slot_type' => 'engine',
                'description' => 'High-performance plasma engines for superior speed.',
                'slots_required' => 1,
                'base_price' => 50000,
                'rarity' => RarityTier::RARE,
                'effects' => ['speed' => 180, 'fuel_efficiency' => 0.20],
                'requirements' => ['level' => 6],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 15000,
            ],
            [
                'name' => 'Quantum Phase Drive',
                'type' => 'engine',
                'slot_type' => 'engine',
                'description' => 'Cutting-edge quantum propulsion for extreme speed.',
                'slots_required' => 1,
                'base_price' => 120000,
                'rarity' => RarityTier::EPIC,
                'effects' => ['speed' => 250, 'fuel_efficiency' => 0.30],
                'requirements' => ['level' => 10],
                'max_upgrade_level' => 1,
                'upgrade_cost_base' => 36000,
            ],
            [
                'name' => 'Precursor Fold Engine',
                'type' => 'engine',
                'slot_type' => 'engine',
                'description' => 'Ancient Precursor engine that folds space itself. Unmatched performance.',
                'slots_required' => 1,
                'base_price' => 500000,
                'rarity' => RarityTier::EXOTIC,
                'effects' => ['speed' => 400, 'fuel_efficiency' => 0.50],
                'requirements' => ['level' => 15],
                'max_upgrade_level' => 0,
                'upgrade_cost_base' => 0,
            ],

            // ========================================
            // REACTORS (reactor)
            // ========================================
            [
                'name' => 'Basic Fusion Core',
                'type' => 'fuel_system',
                'slot_type' => 'reactor',
                'description' => 'Standard fusion reactor providing reliable power and fuel generation.',
                'slots_required' => 1,
                'base_price' => 6000,
                'rarity' => RarityTier::COMMON,
                'effects' => ['fuel_capacity' => 50, 'fuel_regen' => 0.05],
                'requirements' => null,
                'max_upgrade_level' => 6,
                'upgrade_cost_base' => 1800,
            ],
            [
                'name' => 'Enhanced Power Cell',
                'type' => 'fuel_system',
                'slot_type' => 'reactor',
                'description' => 'Higher capacity power cell with improved fuel regeneration.',
                'slots_required' => 1,
                'base_price' => 18000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['fuel_capacity' => 100, 'fuel_regen' => 0.15],
                'requirements' => ['level' => 3],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 5400,
            ],
            [
                'name' => 'Antimatter Reactor',
                'type' => 'fuel_system',
                'slot_type' => 'reactor',
                'description' => 'Antimatter annihilation reactor with massive fuel generation.',
                'slots_required' => 1,
                'base_price' => 55000,
                'rarity' => RarityTier::RARE,
                'effects' => ['fuel_capacity' => 200, 'fuel_regen' => 0.30],
                'requirements' => ['level' => 7],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 16500,
            ],
            [
                'name' => 'Zero-Point Generator',
                'type' => 'fuel_system',
                'slot_type' => 'reactor',
                'description' => 'Extracts energy from quantum vacuum fluctuations. Nearly limitless power.',
                'slots_required' => 1,
                'base_price' => 150000,
                'rarity' => RarityTier::EPIC,
                'effects' => ['fuel_capacity' => 400, 'fuel_regen' => 0.50],
                'requirements' => ['level' => 10],
                'max_upgrade_level' => 1,
                'upgrade_cost_base' => 45000,
            ],

            // ========================================
            // SENSOR ARRAYS (sensor_array)
            // ========================================
            [
                'name' => 'Standard Scanner',
                'type' => 'sensor',
                'slot_type' => 'sensor_array',
                'description' => 'Basic scanner providing standard detection range.',
                'slots_required' => 1,
                'base_price' => 10000,
                'rarity' => RarityTier::COMMON,
                'effects' => ['sensor_boost' => 1],
                'requirements' => null,
                'max_upgrade_level' => 5,
                'upgrade_cost_base' => 3000,
            ],
            [
                'name' => 'Enhanced Sensor Array',
                'type' => 'sensor',
                'slot_type' => 'sensor_array',
                'description' => 'Extended detection range with basic pirate detection.',
                'slots_required' => 1,
                'base_price' => 25000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['sensor_boost' => 2, 'pirate_detection' => 0.10],
                'requirements' => ['level' => 3],
                'max_upgrade_level' => 3,
                'upgrade_cost_base' => 7500,
            ],
            [
                'name' => 'Military Scanner Suite',
                'type' => 'sensor',
                'slot_type' => 'sensor_array',
                'description' => 'Military-grade scanning with advanced threat detection.',
                'slots_required' => 2,
                'base_price' => 60000,
                'rarity' => RarityTier::RARE,
                'effects' => ['sensor_boost' => 4, 'pirate_detection' => 0.25],
                'requirements' => ['level' => 6],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 18000,
            ],
            [
                'name' => 'Quantum Sensor Matrix',
                'type' => 'sensor',
                'slot_type' => 'sensor_array',
                'description' => 'Quantum-entangled sensors for near-instantaneous detection across vast distances.',
                'slots_required' => 2,
                'base_price' => 140000,
                'rarity' => RarityTier::EPIC,
                'effects' => ['sensor_boost' => 7, 'pirate_detection' => 0.40],
                'requirements' => ['level' => 10],
                'max_upgrade_level' => 1,
                'upgrade_cost_base' => 42000,
            ],

            // ========================================
            // CARGO MODULES (cargo_module)
            // ========================================
            [
                'name' => 'Standard Cargo Bay',
                'type' => 'cargo',
                'slot_type' => 'cargo_module',
                'description' => 'Standard cargo bay providing additional storage capacity.',
                'slots_required' => 1,
                'base_price' => 5000,
                'rarity' => RarityTier::COMMON,
                'effects' => ['cargo_boost' => 30],
                'requirements' => null,
                'max_upgrade_level' => 6,
                'upgrade_cost_base' => 1500,
            ],
            [
                'name' => 'Expanded Hold',
                'type' => 'cargo',
                'slot_type' => 'cargo_module',
                'description' => 'Expanded cargo hold with improved loading systems.',
                'slots_required' => 1,
                'base_price' => 15000,
                'rarity' => RarityTier::UNCOMMON,
                'effects' => ['cargo_boost' => 80],
                'requirements' => ['level' => 3],
                'max_upgrade_level' => 4,
                'upgrade_cost_base' => 4500,
            ],
            [
                'name' => 'Compression Storage',
                'type' => 'cargo',
                'slot_type' => 'cargo_module',
                'description' => 'Advanced matter compression allows far more cargo per unit volume.',
                'slots_required' => 1,
                'base_price' => 45000,
                'rarity' => RarityTier::RARE,
                'effects' => ['cargo_boost' => 200],
                'requirements' => ['level' => 6],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 13500,
            ],
            [
                'name' => 'Dimensional Pocket',
                'type' => 'cargo',
                'slot_type' => 'cargo_module',
                'description' => 'Creates a small pocket dimension for cargo. Massive capacity in minimal space.',
                'slots_required' => 1,
                'base_price' => 130000,
                'rarity' => RarityTier::EPIC,
                'effects' => ['cargo_boost' => 500],
                'requirements' => ['level' => 10],
                'max_upgrade_level' => 1,
                'upgrade_cost_base' => 39000,
            ],

            // ========================================
            // UTILITIES (utility) - Special systems
            // ========================================

            // Legacy fuel/sensor/cargo components reclassified above.
            // Only true utility items remain here.

            [
                'name' => 'Cloaking Device',
                'type' => 'utility',
                'slot_type' => 'utility',
                'description' => 'Renders ship invisible to standard sensors. Drains power rapidly.',
                'slots_required' => 3,
                'base_price' => 200000,
                'rarity' => RarityTier::EXOTIC,
                'effects' => ['cloak' => true, 'power_drain' => 50],
                'requirements' => ['level' => 10],
                'max_upgrade_level' => 0,
                'upgrade_cost_base' => 0,
            ],
            [
                'name' => 'Emergency Jump Drive',
                'type' => 'utility',
                'slot_type' => 'utility',
                'description' => 'One-use emergency escape system. Jumps to random safe location.',
                'slots_required' => 1,
                'base_price' => 25000,
                'rarity' => RarityTier::RARE,
                'effects' => ['emergency_jump' => true, 'uses' => 1],
                'requirements' => ['level' => 4],
                'max_upgrade_level' => 2,
                'upgrade_cost_base' => 7500,
            ],
        ];

        foreach ($components as $component) {
            ShipComponent::updateOrCreate(
                ['name' => $component['name']],
                $component
            );
        }

        // Remove legacy components that have been replaced by dedicated slot types
        $legacyNames = [
            'Long Range Scanner',           // replaced by Enhanced Sensor Array
            'Deep Space Scanner Array',     // replaced by Military Scanner Suite
            'Cargo Bay Extension',          // replaced by Standard Cargo Bay
            'Compression Storage Module',   // replaced by Compression Storage
            'Auxiliary Fuel Tank',          // replaced by Basic Fusion Core
            'Fuel Recycler',               // replaced by Enhanced Power Cell
            'Efficient Drive Optimizer',    // replaced by Antimatter Reactor
        ];

        ShipComponent::whereIn('name', $legacyNames)->delete();

        $this->command->info('Ship components seeded: '.count($components).' components ('.count($legacyNames).' legacy removed)');
    }
}

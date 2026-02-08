<?php

namespace Database\Seeders;

use App\Models\ShipComponent;
use Illuminate\Database\Seeder;

/**
 * Seeds ship components for salvage yards.
 *
 * Components are divided into:
 * - Weapons (weapon_slot): Damage-dealing equipment
 * - Utilities (utility_slot): Shield regenerators, hull patches, scanners, cargo expanders
 */
class ShipComponentsSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // ========================================
            // WEAPONS (weapon_slot)
            // ========================================

            // Lasers - basic, reliable, no ammo
            [
                'name' => 'Mark I Pulse Laser',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'Standard pulse laser. Reliable and requires no ammunition.',
                'slots_required' => 1,
                'base_price' => 5000,
                'rarity' => 'common',
                'effects' => ['damage' => 25, 'accuracy' => 0.85, 'fire_rate' => 1.0],
                'requirements' => null,
            ],
            [
                'name' => 'Mark II Pulse Laser',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'Improved pulse laser with better damage output.',
                'slots_required' => 1,
                'base_price' => 12000,
                'rarity' => 'uncommon',
                'effects' => ['damage' => 40, 'accuracy' => 0.88, 'fire_rate' => 1.1],
                'requirements' => ['level' => 3],
            ],
            [
                'name' => 'Heavy Beam Laser',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'High-powered beam laser. Slow to fire but devastating.',
                'slots_required' => 2,
                'base_price' => 35000,
                'rarity' => 'rare',
                'effects' => ['damage' => 100, 'accuracy' => 0.92, 'fire_rate' => 0.5],
                'requirements' => ['level' => 5],
            ],

            // Missiles - high damage, uses ammo
            [
                'name' => 'Seeker Missile Pod',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'Heat-seeking missiles. High damage but limited ammunition.',
                'slots_required' => 1,
                'base_price' => 15000,
                'rarity' => 'uncommon',
                'effects' => ['damage' => 60, 'accuracy' => 0.75, 'max_ammo' => 20],
                'requirements' => ['level' => 2],
            ],
            [
                'name' => 'Swarm Missile Launcher',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'Launches clusters of small missiles. Overwhelming against small targets.',
                'slots_required' => 2,
                'base_price' => 45000,
                'rarity' => 'rare',
                'effects' => ['damage' => 80, 'accuracy' => 0.70, 'max_ammo' => 50, 'fire_rate' => 2.0],
                'requirements' => ['level' => 6],
            ],

            // Torpedoes - very high damage, slow, limited ammo
            [
                'name' => 'Plasma Torpedo Tube',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'Launches devastating plasma torpedoes. Slow but extremely powerful.',
                'slots_required' => 2,
                'base_price' => 75000,
                'rarity' => 'very_rare',
                'effects' => ['damage' => 200, 'accuracy' => 0.65, 'max_ammo' => 8, 'fire_rate' => 0.3],
                'requirements' => ['level' => 8],
            ],
            [
                'name' => 'Precursor Disruptor',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'Ancient technology that disrupts ship systems. Extremely rare.',
                'slots_required' => 3,
                'base_price' => 250000,
                'rarity' => 'legendary',
                'effects' => ['damage' => 350, 'accuracy' => 0.95, 'fire_rate' => 0.8, 'system_disruption' => 0.3],
                'requirements' => ['level' => 12],
            ],

            // Point Defense - anti-missile
            [
                'name' => 'Point Defense Turret',
                'type' => 'weapon',
                'slot_type' => 'weapon_slot',
                'description' => 'Automated turret that shoots down incoming missiles and torpedoes.',
                'slots_required' => 1,
                'base_price' => 20000,
                'rarity' => 'uncommon',
                'effects' => ['damage' => 15, 'accuracy' => 0.90, 'fire_rate' => 3.0, 'anti_missile' => true],
                'requirements' => ['level' => 4],
            ],

            // ========================================
            // SHIELDS (utility_slot)
            // ========================================
            [
                'name' => 'Basic Shield Regenerator',
                'type' => 'shield',
                'slot_type' => 'utility_slot',
                'description' => 'Slowly regenerates shield capacity over time.',
                'slots_required' => 1,
                'base_price' => 8000,
                'rarity' => 'common',
                'effects' => ['shield_regen' => 5, 'shield_boost' => 0],
                'requirements' => null,
            ],
            [
                'name' => 'Enhanced Shield Regenerator',
                'type' => 'shield',
                'slot_type' => 'utility_slot',
                'description' => 'Faster shield regeneration with minor capacity boost.',
                'slots_required' => 1,
                'base_price' => 22000,
                'rarity' => 'uncommon',
                'effects' => ['shield_regen' => 12, 'shield_boost' => 50],
                'requirements' => ['level' => 3],
            ],
            [
                'name' => 'Military Grade Shield Booster',
                'type' => 'shield',
                'slot_type' => 'utility_slot',
                'description' => 'Significantly increases shield capacity and regeneration.',
                'slots_required' => 2,
                'base_price' => 55000,
                'rarity' => 'rare',
                'effects' => ['shield_regen' => 20, 'shield_boost' => 150],
                'requirements' => ['level' => 6],
            ],
            [
                'name' => 'Adaptive Shield Matrix',
                'type' => 'shield',
                'slot_type' => 'utility_slot',
                'description' => 'Advanced shields that adapt to incoming damage types.',
                'slots_required' => 2,
                'base_price' => 120000,
                'rarity' => 'very_rare',
                'effects' => ['shield_regen' => 30, 'shield_boost' => 300, 'damage_resistance' => 0.15],
                'requirements' => ['level' => 9],
            ],

            // ========================================
            // HULL (utility_slot)
            // ========================================
            [
                'name' => 'Emergency Hull Patch',
                'type' => 'hull',
                'slot_type' => 'utility_slot',
                'description' => 'Temporary patches that slowly repair hull damage.',
                'slots_required' => 1,
                'base_price' => 6000,
                'rarity' => 'common',
                'effects' => ['hull_repair' => 3],
                'requirements' => null,
            ],
            [
                'name' => 'Nano-Repair System',
                'type' => 'hull',
                'slot_type' => 'utility_slot',
                'description' => 'Nanobots that continuously repair hull damage.',
                'slots_required' => 1,
                'base_price' => 28000,
                'rarity' => 'uncommon',
                'effects' => ['hull_repair' => 8, 'hull_boost' => 25],
                'requirements' => ['level' => 4],
            ],
            [
                'name' => 'Reinforced Hull Plating',
                'type' => 'hull',
                'slot_type' => 'utility_slot',
                'description' => 'Heavy armor plating that increases maximum hull integrity.',
                'slots_required' => 2,
                'base_price' => 40000,
                'rarity' => 'rare',
                'effects' => ['hull_boost' => 100, 'damage_resistance' => 0.10],
                'requirements' => ['level' => 5],
            ],
            [
                'name' => 'Ablative Armor Matrix',
                'type' => 'hull',
                'slot_type' => 'utility_slot',
                'description' => 'Self-repairing armor that ablates damage and regenerates.',
                'slots_required' => 2,
                'base_price' => 95000,
                'rarity' => 'very_rare',
                'effects' => ['hull_repair' => 15, 'hull_boost' => 200, 'damage_resistance' => 0.20],
                'requirements' => ['level' => 8],
            ],

            // ========================================
            // UTILITIES (utility_slot)
            // ========================================

            // Scanners
            [
                'name' => 'Long Range Scanner',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Extends sensor range for detecting ships and anomalies.',
                'slots_required' => 1,
                'base_price' => 15000,
                'rarity' => 'uncommon',
                'effects' => ['sensor_boost' => 2],
                'requirements' => ['level' => 2],
            ],
            [
                'name' => 'Deep Space Scanner Array',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Military-grade scanner array for maximum detection range.',
                'slots_required' => 2,
                'base_price' => 65000,
                'rarity' => 'rare',
                'effects' => ['sensor_boost' => 5, 'pirate_detection' => 0.2],
                'requirements' => ['level' => 6],
            ],

            // Cargo Expanders
            [
                'name' => 'Cargo Bay Extension',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Adds additional cargo capacity to your ship.',
                'slots_required' => 1,
                'base_price' => 10000,
                'rarity' => 'common',
                'effects' => ['cargo_boost' => 50],
                'requirements' => null,
            ],
            [
                'name' => 'Compression Storage Module',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Advanced storage that compresses cargo for more capacity.',
                'slots_required' => 1,
                'base_price' => 35000,
                'rarity' => 'uncommon',
                'effects' => ['cargo_boost' => 150],
                'requirements' => ['level' => 4],
            ],

            // Fuel Systems
            [
                'name' => 'Auxiliary Fuel Tank',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Additional fuel storage for extended range.',
                'slots_required' => 1,
                'base_price' => 12000,
                'rarity' => 'common',
                'effects' => ['fuel_boost' => 500],
                'requirements' => null,
            ],
            [
                'name' => 'Fuel Recycler',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Recycles waste heat to slowly regenerate fuel.',
                'slots_required' => 1,
                'base_price' => 30000,
                'rarity' => 'uncommon',
                'effects' => ['fuel_regen_boost' => 0.2],
                'requirements' => ['level' => 3],
            ],
            [
                'name' => 'Efficient Drive Optimizer',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Reduces fuel consumption for all travel.',
                'slots_required' => 1,
                'base_price' => 45000,
                'rarity' => 'rare',
                'effects' => ['fuel_efficiency' => 0.15],
                'requirements' => ['level' => 5],
            ],

            // Special
            [
                'name' => 'Cloaking Device',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'Renders ship invisible to standard sensors. Drains power rapidly.',
                'slots_required' => 3,
                'base_price' => 200000,
                'rarity' => 'legendary',
                'effects' => ['cloak' => true, 'power_drain' => 50],
                'requirements' => ['level' => 10],
            ],
            [
                'name' => 'Emergency Jump Drive',
                'type' => 'utility',
                'slot_type' => 'utility_slot',
                'description' => 'One-use emergency escape system. Jumps to random safe location.',
                'slots_required' => 1,
                'base_price' => 25000,
                'rarity' => 'rare',
                'effects' => ['emergency_jump' => true, 'uses' => 1],
                'requirements' => ['level' => 4],
            ],
        ];

        foreach ($components as $component) {
            ShipComponent::updateOrCreate(
                ['name' => $component['name']],
                $component
            );
        }

        $this->command->info('Ship components seeded: '.count($components).' components');
    }
}

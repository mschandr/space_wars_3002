<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            // FUEL TANK PLANS
            [
                'name' => 'Basic Fuel Tank Plans',
                'component' => 'max_fuel',
                'description' => 'Engineering blueprints for advanced fuel tank configurations. Extends maximum fuel capacity by 10 upgrade levels beyond standard limits.',
                'additional_levels' => 10,
                'price' => 50000.00,
                'rarity' => 'rare',
                'requirements' => null,
            ],
            [
                'name' => 'Advanced Fuel Tank Plans',
                'component' => 'max_fuel',
                'description' => 'Cutting-edge fuel storage technology. Extends maximum fuel capacity by 20 upgrade levels beyond standard limits.',
                'additional_levels' => 20,
                'price' => 150000.00,
                'rarity' => 'epic',
                'requirements' => ['min_level' => 10],
            ],
            [
                'name' => 'Experimental Fuel Tank Plans',
                'component' => 'max_fuel',
                'description' => 'Experimental quantum-compressed fuel containment systems. Extends maximum fuel capacity by 30 upgrade levels beyond standard limits.',
                'additional_levels' => 30,
                'price' => 500000.00,
                'rarity' => 'legendary',
                'requirements' => ['min_level' => 20],
            ],

            // HULL REINFORCEMENT PLANS
            [
                'name' => 'Basic Hull Reinforcement Plans',
                'component' => 'max_hull',
                'description' => 'Structural engineering schematics for enhanced hull plating. Extends maximum hull strength by 10 upgrade levels beyond standard limits.',
                'additional_levels' => 10,
                'price' => 75000.00,
                'rarity' => 'rare',
                'requirements' => null,
            ],
            [
                'name' => 'Advanced Hull Reinforcement Plans',
                'component' => 'max_hull',
                'description' => 'Military-grade armor composite blueprints. Extends maximum hull strength by 20 upgrade levels beyond standard limits.',
                'additional_levels' => 20,
                'price' => 225000.00,
                'rarity' => 'epic',
                'requirements' => ['min_level' => 10],
            ],
            [
                'name' => 'Experimental Hull Reinforcement Plans',
                'component' => 'max_hull',
                'description' => 'Prototype adaptive nano-armor technology. Extends maximum hull strength by 30 upgrade levels beyond standard limits.',
                'additional_levels' => 30,
                'price' => 750000.00,
                'rarity' => 'legendary',
                'requirements' => ['min_level' => 20],
            ],

            // WEAPONS UPGRADE PLANS
            [
                'name' => 'Basic Weapons Upgrade Plans',
                'component' => 'weapons',
                'description' => 'Tactical weapons system enhancement blueprints. Extends maximum weapons capacity by 10 upgrade levels beyond standard limits.',
                'additional_levels' => 10,
                'price' => 100000.00,
                'rarity' => 'rare',
                'requirements' => null,
            ],
            [
                'name' => 'Advanced Weapons Upgrade Plans',
                'component' => 'weapons',
                'description' => 'Heavy ordnance integration specifications. Extends maximum weapons capacity by 20 upgrade levels beyond standard limits.',
                'additional_levels' => 20,
                'price' => 300000.00,
                'rarity' => 'epic',
                'requirements' => ['min_level' => 10],
            ],
            [
                'name' => 'Experimental Weapons Upgrade Plans',
                'component' => 'weapons',
                'description' => 'Experimental plasma array technology. Extends maximum weapons capacity by 30 upgrade levels beyond standard limits.',
                'additional_levels' => 30,
                'price' => 1000000.00,
                'rarity' => 'legendary',
                'requirements' => ['min_level' => 20],
            ],

            // CARGO HOLD EXPANSION PLANS
            [
                'name' => 'Basic Cargo Hold Expansion Plans',
                'component' => 'cargo_hold',
                'description' => 'Space optimization and modular storage designs. Extends maximum cargo capacity by 10 upgrade levels beyond standard limits.',
                'additional_levels' => 10,
                'price' => 60000.00,
                'rarity' => 'rare',
                'requirements' => null,
            ],
            [
                'name' => 'Advanced Cargo Hold Expansion Plans',
                'component' => 'cargo_hold',
                'description' => 'Dimensional compression storage technology. Extends maximum cargo capacity by 20 upgrade levels beyond standard limits.',
                'additional_levels' => 20,
                'price' => 180000.00,
                'rarity' => 'epic',
                'requirements' => ['min_level' => 10],
            ],
            [
                'name' => 'Experimental Cargo Hold Expansion Plans',
                'component' => 'cargo_hold',
                'description' => 'Prototype subspace cargo bay systems. Extends maximum cargo capacity by 30 upgrade levels beyond standard limits.',
                'additional_levels' => 30,
                'price' => 600000.00,
                'rarity' => 'legendary',
                'requirements' => ['min_level' => 20],
            ],

            // SENSOR ENHANCEMENT PLANS
            [
                'name' => 'Basic Sensor Enhancement Plans',
                'component' => 'sensors',
                'description' => 'Enhanced detection array specifications. Extends maximum sensor range by 10 upgrade levels beyond standard limits.',
                'additional_levels' => 10,
                'price' => 150000.00,
                'rarity' => 'rare',
                'requirements' => null,
            ],
            [
                'name' => 'Advanced Sensor Enhancement Plans',
                'component' => 'sensors',
                'description' => 'Long-range deep-space scanning technology. Extends maximum sensor range by 20 upgrade levels beyond standard limits.',
                'additional_levels' => 20,
                'price' => 450000.00,
                'rarity' => 'epic',
                'requirements' => ['min_level' => 10],
            ],
            [
                'name' => 'Experimental Sensor Enhancement Plans',
                'component' => 'sensors',
                'description' => 'Quantum entanglement sensor grid technology. Extends maximum sensor range by 30 upgrade levels beyond standard limits.',
                'additional_levels' => 30,
                'price' => 1500000.00,
                'rarity' => 'legendary',
                'requirements' => ['min_level' => 20],
            ],

            // WARP DRIVE UPGRADE PLANS
            [
                'name' => 'Basic Warp Drive Upgrade Plans',
                'component' => 'warp_drive',
                'description' => 'Optimized warp field generator designs. Extends maximum warp drive efficiency by 10 upgrade levels beyond standard limits.',
                'additional_levels' => 10,
                'price' => 200000.00,
                'rarity' => 'rare',
                'requirements' => null,
            ],
            [
                'name' => 'Advanced Warp Drive Upgrade Plans',
                'component' => 'warp_drive',
                'description' => 'Enhanced subspace propulsion systems. Extends maximum warp drive efficiency by 20 upgrade levels beyond standard limits.',
                'additional_levels' => 20,
                'price' => 600000.00,
                'rarity' => 'epic',
                'requirements' => ['min_level' => 10],
            ],
            [
                'name' => 'Experimental Warp Drive Upgrade Plans',
                'component' => 'warp_drive',
                'description' => 'Prototype transwarp drive technology. Extends maximum warp drive efficiency by 30 upgrade levels beyond standard limits.',
                'additional_levels' => 30,
                'price' => 2000000.00,
                'rarity' => 'legendary',
                'requirements' => ['min_level' => 20],
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }

        $this->command->info('Successfully created '.count($plans).' upgrade plans.');
    }
}

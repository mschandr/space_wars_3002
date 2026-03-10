<?php

namespace Database\Seeders;

use App\Models\ShipComponent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShipComponentProfileSeeder extends Seeder
{
    private const CIVILIZATIONS = [
        'Terran Alliance',
        'Calaxarian Empire',
        'Voron Collective',
        'Hegemony of Light',
        'Shadow Syndicate',
        'Mineral Consortium',
        'Free Traders Union',
        'Outlander Salvage',
        'Ancient Precursor',
        'Unknown Origin',
    ];

    private const CONDITION_TIERS = [
        ['tier' => 'Pristine', 'multiplier' => 1.0, 'description' => 'Factory fresh, never installed'],
        ['tier' => 'Like New', 'multiplier' => 0.95, 'description' => 'Lightly used, minimal wear'],
        ['tier' => 'Excellent', 'multiplier' => 0.85, 'description' => 'Well-maintained, normal operation'],
        ['tier' => 'Good', 'multiplier' => 0.70, 'description' => 'Moderate wear, still reliable'],
        ['tier' => 'Fair', 'multiplier' => 0.50, 'description' => 'Significant wear, reduced efficiency'],
        ['tier' => 'Poor', 'multiplier' => 0.25, 'description' => 'Heavy damage, marginal functionality'],
        ['tier' => 'Junk', 'multiplier' => 0.10, 'description' => 'Severely damaged, barely functional'],
    ];

    private const COMPONENT_TYPES = [
        'engines' => [
            'type' => 'engine',
            'slot_type' => 'engine',
            'size_class' => 'medium',
            'components' => [
                'Ion Drive System',
                'Fusion Engine',
                'Warp Core',
                'Quantum Thruster',
                'Antimatter Catalyst',
            ]
        ],
        'shields' => [
            'type' => 'shield_generator',
            'slot_type' => 'shield_generator',
            'size_class' => 'medium',
            'components' => [
                'Energy Barrier Generator',
                'Kinetic Shield',
                'Plasma Deflector',
                'Gravitational Lens',
                'Phase Inverter',
            ]
        ],
        'weapons' => [
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'size_class' => 'large',
            'components' => [
                'Plasma Cannon',
                'Railgun Turret',
                'Photon Beam',
                'Missile Array',
                'Laser Battery',
            ]
        ],
        'hull' => [
            'type' => 'hull_plating',
            'slot_type' => 'hull_plating',
            'size_class' => 'large',
            'components' => [
                'Hull Plating (Durasteel)',
                'Reinforced Bulkhead',
                'Ablative Armor',
                'Composite Alloy Sheathing',
                'Reactive Armor Panel',
            ]
        ],
        'sensors' => [
            'type' => 'sensor_array',
            'slot_type' => 'sensor_array',
            'size_class' => 'small',
            'components' => [
                'Long-Range Radar Array',
                'Gravimetric Scanner',
                'Electromagnetic Detector',
                'Quantum Sensor Suite',
                'Multispectrum Analyzer',
            ]
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding ship component profiles with civilization origins and condition tiers...');

        $created = 0;
        $normalCivs = array_filter(self::CIVILIZATIONS, fn($c) => $c !== 'Ancient Precursor');

        foreach (self::COMPONENT_TYPES as $categoryKey => $categoryData) {
            $type = $categoryData['type'];
            $slotType = $categoryData['slot_type'];
            $sizeClass = $categoryData['size_class'];

            foreach ($categoryData['components'] as $componentName) {
                foreach (self::CONDITION_TIERS as $condition) {
                    // Normal civilizations (99.99% of pool)
                    foreach ($normalCivs as $civilization) {
                        $fullName = "{$componentName} ({$condition['tier']}) - {$civilization}";
                        $basePrice = match ($sizeClass) {
                            'small' => 5000,
                            'medium' => 15000,
                            'large' => 35000,
                            default => 10000,
                        };

                        $adjustedPrice = $basePrice * $condition['multiplier'];

                        ShipComponent::updateOrCreate(
                            [
                                'name' => $fullName,
                                'origin' => $civilization,
                                'condition_tier' => $condition['tier'],
                            ],
                            [
                                'category' => $categoryKey,
                                'type' => $type,
                                'slot_type' => $slotType,
                                'size_class' => $sizeClass,
                                'condition_multiplier' => $condition['multiplier'],
                                'variant_description' => "{$condition['description']} - {$civilization} manufacture",
                                'base_price' => $adjustedPrice,
                                'slots_required' => 1,
                                'rarity' => $this->getRarityByCondition($condition['tier']),
                                'effects' => json_encode(['condition' => $condition['tier'], 'origin' => $civilization]),
                                'is_available' => true,
                                'max_upgrade_level' => 10,
                                'upgrade_cost_base' => $adjustedPrice * 0.1,
                                'description' => "{$condition['description']} - {$civilization} manufacture. Condition tier: {$condition['tier']}",
                            ]
                        );

                        $created++;
                    }

                    // Ancient Precursor (extremely rare - only Pristine & Like New tiers)
                    if (in_array($condition['tier'], ['Pristine', 'Like New'])) {
                        $fullName = "{$componentName} ({$condition['tier']}) - Ancient Precursor";
                        $basePrice = match ($sizeClass) {
                            'small' => 50000,
                            'medium' => 150000,
                            'large' => 350000,
                            default => 100000,
                        };

                        $adjustedPrice = $basePrice * $condition['multiplier'];

                        ShipComponent::updateOrCreate(
                            [
                                'name' => $fullName,
                                'origin' => 'Ancient Precursor',
                                'condition_tier' => $condition['tier'],
                            ],
                            [
                                'category' => $categoryKey,
                                'type' => $type,
                                'slot_type' => $slotType,
                                'size_class' => $sizeClass,
                                'condition_multiplier' => $condition['multiplier'],
                                'variant_description' => "{$condition['description']} - Ancient Precursor technology (extremely rare)",
                                'base_price' => $adjustedPrice,
                                'slots_required' => 1,
                                'rarity' => 'exotic',
                                'effects' => json_encode(['condition' => $condition['tier'], 'origin' => 'Ancient Precursor', 'exotic' => true]),
                                'is_available' => true,
                                'max_upgrade_level' => 15,
                                'upgrade_cost_base' => $adjustedPrice * 0.15,
                                'description' => "{$condition['description']} - Ancient Precursor technology (extremely rare). Condition tier: {$condition['tier']}",
                            ]
                        );

                        $created++;
                    }
                }
            }
        }

        $this->command->info("✓ Created/updated {$created} ship component profiles");
        $this->command->info("  • Normal civilizations: 9 × 5 categories × 5 components × 7 conditions = 1,575 components");
        $this->command->info("  • Ancient Precursor (legendary): 5 categories × 5 components × 2 conditions = 50 components");
    }

    private function getRarityByCondition(string $tier): string
    {
        return match ($tier) {
            'Pristine' => 'rare',
            'Like New' => 'uncommon',
            'Excellent' => 'uncommon',
            'Good' => 'common',
            'Fair' => 'common',
            'Poor' => 'common',
            'Junk' => 'common',
            default => 'common',
        };
    }
}

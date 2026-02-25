<?php

namespace Database\Seeders;

use App\Enums\Trading\MineralRarity;
use App\Models\Mineral;
use Illuminate\Database\Seeder;

class MineralSeeder extends Seeder
{
    public function run(): void
    {
        $minerals = [
            // Abundant minerals
            [
                'name' => 'Water Ice',
                'symbol' => 'H2O',
                'description' => 'Frozen water found abundantly in asteroid belts and comets. Essential for life support systems.',
                'base_value' => 10.00,
                'rarity' => MineralRarity::ABUNDANT,
            ],
            [
                'name' => 'Carbon',
                'symbol' => 'C',
                'description' => 'Common element found throughout the galaxy. Used in basic construction and manufacturing.',
                'base_value' => 8.00,
                'rarity' => MineralRarity::ABUNDANT,
            ],

            // Common minerals
            [
                'name' => 'Iron Ore',
                'symbol' => 'Fe',
                'description' => 'Common metallic ore used extensively in ship hull construction and infrastructure.',
                'base_value' => 25.00,
                'rarity' => MineralRarity::COMMON,
            ],
            [
                'name' => 'Silicates',
                'symbol' => 'SiO2',
                'description' => 'Silicon-based compounds used in electronics and construction materials.',
                'base_value' => 30.00,
                'rarity' => MineralRarity::COMMON,
            ],
            [
                'name' => 'Nickel',
                'symbol' => 'Ni',
                'description' => 'Corrosion-resistant metal used in alloys and electronics.',
                'base_value' => 35.00,
                'rarity' => MineralRarity::COMMON,
            ],

            // Uncommon minerals
            [
                'name' => 'Titanium',
                'symbol' => 'Ti',
                'description' => 'Strong, lightweight metal prized for advanced ship components.',
                'base_value' => 75.00,
                'rarity' => MineralRarity::UNCOMMON,
            ],
            [
                'name' => 'Copper',
                'symbol' => 'Cu',
                'description' => 'Excellent conductor used in power systems and electronics.',
                'base_value' => 60.00,
                'rarity' => MineralRarity::UNCOMMON,
            ],
            [
                'name' => 'Aluminum',
                'symbol' => 'Al',
                'description' => 'Lightweight metal used in spacecraft construction.',
                'base_value' => 55.00,
                'rarity' => MineralRarity::UNCOMMON,
            ],
            [
                'name' => 'Lithium',
                'symbol' => 'Li',
                'description' => 'Essential for high-capacity energy storage systems.',
                'base_value' => 80.00,
                'rarity' => MineralRarity::UNCOMMON,
            ],

            // Rare minerals
            [
                'name' => 'Platinum',
                'symbol' => 'Pt',
                'description' => 'Precious metal used in catalytic converters and advanced electronics.',
                'base_value' => 200.00,
                'rarity' => MineralRarity::RARE,
            ],
            [
                'name' => 'Gold',
                'symbol' => 'Au',
                'description' => 'Highly conductive and corrosion-resistant metal valued throughout the galaxy.',
                'base_value' => 250.00,
                'rarity' => MineralRarity::RARE,
            ],
            [
                'name' => 'Palladium',
                'symbol' => 'Pd',
                'description' => 'Rare metal essential for hydrogen fuel cells and advanced electronics.',
                'base_value' => 220.00,
                'rarity' => MineralRarity::RARE,
            ],
            [
                'name' => 'Cobalt',
                'symbol' => 'Co',
                'description' => 'Critical component in high-performance alloys and batteries.',
                'base_value' => 180.00,
                'rarity' => MineralRarity::RARE,
            ],

            // Very Rare minerals
            [
                'name' => 'Rhodium',
                'symbol' => 'Rh',
                'description' => 'Extremely rare and valuable metal used in the most advanced catalytic systems.',
                'base_value' => 500.00,
                'rarity' => MineralRarity::VERY_RARE,
            ],
            [
                'name' => 'Iridium',
                'symbol' => 'Ir',
                'description' => 'One of the densest elements, essential for high-performance spark plugs and crucibles.',
                'base_value' => 450.00,
                'rarity' => MineralRarity::VERY_RARE,
            ],
            [
                'name' => 'Osmium',
                'symbol' => 'Os',
                'description' => 'The densest naturally occurring element, used in specialized alloys.',
                'base_value' => 480.00,
                'rarity' => MineralRarity::VERY_RARE,
            ],
            [
                'name' => 'Tritium',
                'symbol' => 'T',
                'description' => 'Radioactive hydrogen isotope used in fusion reactors.',
                'base_value' => 550.00,
                'rarity' => MineralRarity::VERY_RARE,
            ],

            // Epic minerals
            [
                'name' => 'Antimatter Particles',
                'symbol' => 'AM',
                'description' => 'Exotic matter that annihilates on contact with regular matter, providing enormous energy.',
                'base_value' => 1500.00,
                'rarity' => MineralRarity::EPIC,
            ],
            [
                'name' => 'Neutronium',
                'symbol' => 'Nt',
                'description' => 'Incredibly dense matter from neutron stars, nearly indestructible.',
                'base_value' => 2000.00,
                'rarity' => MineralRarity::EPIC,
            ],
            [
                'name' => 'Dark Matter Crystals',
                'symbol' => 'DMC',
                'description' => 'Crystallized dark matter with unique gravitational properties.',
                'base_value' => 1800.00,
                'rarity' => MineralRarity::EPIC,
            ],

            // Legendary minerals
            [
                'name' => 'Quantum Foam',
                'symbol' => 'QF',
                'description' => 'Exotic spacetime fluctuations harvested and stabilized. Enables FTL travel enhancements.',
                'base_value' => 5000.00,
                'rarity' => MineralRarity::LEGENDARY,
            ],
            [
                'name' => 'Exotic Matter',
                'symbol' => 'EM',
                'description' => 'Matter with negative mass and energy, theoretical basis for warp drives.',
                'base_value' => 6000.00,
                'rarity' => MineralRarity::LEGENDARY,
            ],
            [
                'name' => 'Chronoton Particles',
                'symbol' => 'CP',
                'description' => 'Temporal particles that exist outside normal spacetime. Extremely rare and valuable.',
                'base_value' => 5500.00,
                'rarity' => MineralRarity::LEGENDARY,
            ],

            // Mythic minerals
            [
                'name' => 'Starcore Fragments',
                'symbol' => 'SCF',
                'description' => 'Fragments from the core of collapsed stars. Contains unimaginable energy density.',
                'base_value' => 15000.00,
                'rarity' => MineralRarity::MYTHIC,
            ],
            [
                'name' => 'Zero-Point Energy Crystals',
                'symbol' => 'ZPE',
                'description' => 'Crystals that tap into the quantum vacuum energy. The ultimate power source.',
                'base_value' => 20000.00,
                'rarity' => MineralRarity::MYTHIC,
            ],
            [
                'name' => 'Primordial Elements',
                'symbol' => 'PE',
                'description' => 'Matter dating back to the Big Bang with unique quantum properties. Priceless.',
                'base_value' => 25000.00,
                'rarity' => MineralRarity::MYTHIC,
            ],
        ];

        foreach ($minerals as $mineralData) {
            Mineral::create($mineralData);
        }

        $this->command?->info('Created '.count($minerals).' minerals across all rarity tiers.');
    }
}

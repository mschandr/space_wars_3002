<?php

namespace Database\Seeders;

use App\Models\Mineral;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuantiumMineralSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Mineral::updateOrCreate(
            [
                'name' => 'Quantium',
            ],
            [
                'symbol' => 'Qm',
                'description' => 'Rare crystallized particles found in the upper atmospheres of ice giants. When excited, they create stable micro-wormholes - essential for sustaining warp gates.',
                'rarity' => 'legendary',
                'base_value' => 5000.00, // Very expensive
                'attributes' => [
                    'found_in' => ['ice_giant'],
                    'extraction_difficulty' => 'extreme',
                    'sensor_dependent' => true,
                    'requires_orbital_mining' => true,
                    'use_case' => 'warp_gate_fuel',
                    'market_volatility' => 0.4,
                ],
            ]
        );
    }
}

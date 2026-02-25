<?php

namespace App\Services;

use App\Enums\RarityTier;
use App\Models\PlayerShip;
use App\Models\Ship;

/**
 * Service for generating ship variations.
 *
 * In the real world, no two ships are exactly alike - even those built to the same
 * specifications have slight differences in performance due to manufacturing tolerances,
 * component quality, and countless other factors.
 *
 * This service generates random variations for ships when they are purchased,
 * giving each ship a unique personality and encouraging players to seek out
 * ships with favorable characteristics.
 */
class ShipVariationService
{
    /**
     * Variation traits and their possible values.
     * Each trait has a name, description, and effect on ship stats.
     */
    private const VARIATION_TRAITS = [
        // Fuel system traits
        'efficient_injectors' => [
            'name' => 'Efficient Injectors',
            'description' => 'Fuel injectors tuned for optimal consumption',
            'effect' => ['fuel_consumption_modifier' => -0.10],
            'weight' => 10,
        ],
        'leaky_seals' => [
            'name' => 'Leaky Fuel Seals',
            'description' => 'Minor seal degradation increases fuel consumption',
            'effect' => ['fuel_consumption_modifier' => 0.10],
            'weight' => 8,
        ],
        'overcharged_reactor' => [
            'name' => 'Overcharged Reactor',
            'description' => 'Reactor runs hot, regenerating fuel faster',
            'effect' => ['fuel_regen_modifier' => 0.15],
            'weight' => 8,
        ],
        'sluggish_reactor' => [
            'name' => 'Sluggish Reactor',
            'description' => 'Reactor efficiency below spec',
            'effect' => ['fuel_regen_modifier' => -0.10],
            'weight' => 10,
        ],

        // Speed traits
        'racing_tuned' => [
            'name' => 'Racing Tuned',
            'description' => 'Engine tuned for maximum thrust',
            'effect' => ['speed_modifier' => 0.08],
            'weight' => 6,
        ],
        'sluggish_thrusters' => [
            'name' => 'Sluggish Thrusters',
            'description' => 'Thrusters slightly below specification',
            'effect' => ['speed_modifier' => -0.05],
            'weight' => 10,
        ],

        // Hull traits
        'reinforced_plating' => [
            'name' => 'Reinforced Plating',
            'description' => 'Extra hull reinforcement during construction',
            'effect' => ['hull_bonus' => 10],
            'weight' => 8,
        ],
        'thin_spots' => [
            'name' => 'Thin Spots',
            'description' => 'Minor hull thickness variations',
            'effect' => ['hull_bonus' => -5],
            'weight' => 10,
        ],

        // Cargo traits
        'optimized_storage' => [
            'name' => 'Optimized Storage',
            'description' => 'Clever cargo bay layout provides extra space',
            'effect' => ['cargo_bonus' => 5],
            'weight' => 8,
        ],
        'cramped_layout' => [
            'name' => 'Cramped Layout',
            'description' => 'Awkward cargo bay reduces usable space',
            'effect' => ['cargo_bonus' => -3],
            'weight' => 10,
        ],

        // Sensor traits
        'sensitive_array' => [
            'name' => 'Sensitive Array',
            'description' => 'Sensor calibration exceeds factory specs',
            'effect' => ['sensor_bonus' => 1],
            'weight' => 4,
        ],

        // Special traits (rare)
        'lucky_ship' => [
            'name' => 'Lucky Ship',
            'description' => 'This ship just feels lucky',
            'effect' => ['fuel_consumption_modifier' => -0.05, 'speed_modifier' => 0.05],
            'weight' => 2,
        ],
        'cursed_hull' => [
            'name' => 'Cursed Hull',
            'description' => 'Something about this ship feels off',
            'effect' => ['fuel_consumption_modifier' => 0.08, 'hull_bonus' => -8],
            'weight' => 2,
        ],
    ];

    /**
     * Generate variation traits for a new ship.
     *
     * @param  Ship  $blueprint  The ship blueprint being purchased
     * @param  string  $quality  Quality tier: 'standard', 'premium', 'legendary'
     * @return array{traits: array, modifiers: array}
     */
    public function generateVariation(Ship $blueprint, string $quality = 'standard'): array
    {
        $traitCount = match ($quality) {
            'legendary' => random_int(3, 5),
            'premium' => random_int(2, 3),
            default => random_int(0, 2),
        };

        // Select random traits weighted by rarity
        $selectedTraits = $this->selectTraits($traitCount, $quality);

        // Calculate cumulative modifiers
        $modifiers = $this->calculateModifiers($selectedTraits);

        return [
            'traits' => $selectedTraits,
            'modifiers' => $modifiers,
        ];
    }

    /**
     * Select traits using weighted random selection.
     */
    private function selectTraits(int $count, string $quality): array
    {
        $traits = self::VARIATION_TRAITS;
        $selected = [];
        $positiveTraits = ['efficient_injectors', 'overcharged_reactor', 'racing_tuned', 'reinforced_plating', 'optimized_storage', 'sensitive_array', 'lucky_ship'];

        // Premium/legendary ships have higher chance of positive traits
        $positiveWeight = match ($quality) {
            'legendary' => 3.0,
            'premium' => 1.5,
            default => 1.0,
        };

        for ($i = 0; $i < $count; $i++) {
            // Build weighted pool excluding already selected traits
            $pool = [];
            foreach ($traits as $key => $trait) {
                if (isset($selected[$key])) {
                    continue;
                }

                $weight = $trait['weight'];
                if (in_array($key, $positiveTraits)) {
                    $weight = (int) ($weight * $positiveWeight);
                }

                for ($w = 0; $w < $weight; $w++) {
                    $pool[] = $key;
                }
            }

            if (empty($pool)) {
                break;
            }

            $selectedKey = $pool[array_rand($pool)];
            $selected[$selectedKey] = [
                'name' => $traits[$selectedKey]['name'],
                'description' => $traits[$selectedKey]['description'],
            ];
        }

        return $selected;
    }

    /**
     * Calculate cumulative modifiers from selected traits.
     */
    public function calculateModifiers(array $selectedTraits): array
    {
        $modifiers = [
            'fuel_regen_modifier' => 1.0,
            'fuel_consumption_modifier' => 1.0,
            'speed_modifier' => 1.0,
            'hull_bonus' => 0,
            'cargo_bonus' => 0,
            'sensor_bonus' => 0,
        ];

        foreach (array_keys($selectedTraits) as $traitKey) {
            if (! isset(self::VARIATION_TRAITS[$traitKey])) {
                continue;
            }

            $effects = self::VARIATION_TRAITS[$traitKey]['effect'];

            foreach ($effects as $stat => $value) {
                if (str_ends_with($stat, '_modifier')) {
                    // Modifiers are additive for readability (1.0 + 0.10 = 1.10)
                    $modifiers[$stat] += $value;
                } else {
                    // Bonuses are additive
                    $modifiers[$stat] += $value;
                }
            }
        }

        // Clamp modifiers to reasonable ranges
        $modifiers['fuel_regen_modifier'] = max(0.7, min(1.5, $modifiers['fuel_regen_modifier']));
        $modifiers['fuel_consumption_modifier'] = max(0.7, min(1.5, $modifiers['fuel_consumption_modifier']));
        $modifiers['speed_modifier'] = max(0.8, min(1.3, $modifiers['speed_modifier']));

        return $modifiers;
    }

    /**
     * Apply variation to a PlayerShip instance.
     */
    public function applyVariation(PlayerShip $playerShip, array $variation): PlayerShip
    {
        $modifiers = $variation['modifiers'];

        // Apply modifier fields
        $playerShip->fuel_regen_modifier = round($modifiers['fuel_regen_modifier'], 2);
        $playerShip->fuel_consumption_modifier = round($modifiers['fuel_consumption_modifier'], 2);
        $playerShip->speed_modifier = round($modifiers['speed_modifier'], 2);

        // Apply bonus fields (additive to base stats)
        if (isset($modifiers['hull_bonus']) && $modifiers['hull_bonus'] != 0) {
            $playerShip->max_hull = max(10, $playerShip->max_hull + $modifiers['hull_bonus']);
            $playerShip->hull = min($playerShip->hull, $playerShip->max_hull);
        }

        if (isset($modifiers['cargo_bonus']) && $modifiers['cargo_bonus'] != 0) {
            $playerShip->cargo_hold = max(5, $playerShip->cargo_hold + $modifiers['cargo_bonus']);
        }

        if (isset($modifiers['sensor_bonus']) && $modifiers['sensor_bonus'] != 0) {
            $playerShip->sensors = max(1, $playerShip->sensors + $modifiers['sensor_bonus']);
        }

        // Store traits for display
        $playerShip->variation_traits = $variation['traits'];

        return $playerShip;
    }

    /**
     * Generate variation traits mapped from a rarity tier.
     * Higher rarity = more traits, biased toward positive.
     */
    public function generateVariationForRarity(Ship $blueprint, RarityTier $rarity): array
    {
        $quality = match ($rarity) {
            RarityTier::EXOTIC, RarityTier::UNIQUE => 'legendary',
            RarityTier::EPIC, RarityTier::RARE => 'premium',
            default => 'standard',
        };

        return $this->generateVariation($blueprint, $quality);
    }

    /**
     * Get a human-readable summary of a ship's variations.
     */
    public function getVariationSummary(PlayerShip $playerShip): array
    {
        $summary = [];

        if ($playerShip->fuel_regen_modifier != 1.0) {
            $percent = round(($playerShip->fuel_regen_modifier - 1.0) * 100);
            $summary[] = ($percent > 0 ? '+' : '').$percent.'% fuel regeneration';
        }

        if ($playerShip->fuel_consumption_modifier != 1.0) {
            $percent = round(($playerShip->fuel_consumption_modifier - 1.0) * 100);
            // Invert for display (higher consumption = negative)
            $summary[] = ($percent > 0 ? '+' : '').$percent.'% fuel consumption';
        }

        if ($playerShip->speed_modifier != 1.0) {
            $percent = round(($playerShip->speed_modifier - 1.0) * 100);
            $summary[] = ($percent > 0 ? '+' : '').$percent.'% speed';
        }

        return $summary;
    }
}

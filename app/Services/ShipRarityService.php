<?php

namespace App\Services;

use App\Enums\RarityTier;
use App\Models\Ship;
use Assert\AssertionFailedException;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;

class ShipRarityService
{
    /**
     * Roll a random rarity tier using weighted random selection.
     *
     * @throws AssertionFailedException
     */
    public function rollRarity(): RarityTier
    {
        $weights = config('game_config.rarity.weights', []);

        $generator = new WeightedRandomGenerator;
        $values = [];
        foreach (RarityTier::cases() as $tier) {
            $values[$tier->value] = $weights[$tier->value] ?? $tier->weight();
        }
        $generator->registerValues($values);

        $result = $generator->generate();

        return RarityTier::from($result);
    }

    /**
     * Apply rarity multiplier to a base stat with jitter for uniqueness.
     */
    public function applyRarityToStat(int $base, RarityTier $rarity): int
    {
        $multiplier = $this->getStatMultiplier($rarity);
        $jitter = $this->getJitterPercentage();

        return max(1, (int) round($base * $multiplier * $jitter));
    }

    /**
     * Calculate price based on base price and rarity.
     */
    public function calculatePrice(float $basePrice, RarityTier $rarity): float
    {
        $multiplier = $this->getPriceMultiplier($rarity);

        return round($basePrice * $multiplier, 2);
    }

    /**
     * Apply rarity scaling to all ship stats from a blueprint.
     *
     * @return array<string, int|float>
     */
    public function applyRarityToShipStats(Ship $blueprint, RarityTier $rarity): array
    {
        $attributes = $blueprint->attributes ?? [];

        return [
            'hull_strength' => $this->applyRarityToStat($blueprint->hull_strength, $rarity),
            'shield_strength' => $this->applyRarityToStat($blueprint->shield_strength ?? 50, $rarity),
            'cargo_capacity' => $this->applyRarityToStat($blueprint->cargo_capacity, $rarity),
            'speed' => $this->applyRarityToStat($blueprint->speed, $rarity),
            'weapon_slots' => $this->applyRarityToSlots($blueprint->weapon_slots ?? 2, $rarity),
            'utility_slots' => $this->applyRarityToSlots($blueprint->utility_slots ?? 2, $rarity),
            'max_fuel' => $this->applyRarityToStat($attributes['max_fuel'] ?? 100, $rarity),
            'sensors' => $this->applyRarityToSlots($attributes['starting_sensors'] ?? 1, $rarity),
            'warp_drive' => $this->applyRarityToSlots($attributes['starting_warp_drive'] ?? 1, $rarity),
            'weapons' => $this->applyRarityToStat($attributes['starting_weapons'] ?? 10, $rarity),
        ];
    }

    /**
     * Apply rarity to slot counts (discrete integers, only increase for high rarities).
     */
    private function applyRarityToSlots(int $base, RarityTier $rarity): int
    {
        $bonus = match ($rarity) {
            RarityTier::COMMON, RarityTier::UNCOMMON => 0,
            RarityTier::RARE => random_int(0, 1),
            RarityTier::EPIC => 1,
            RarityTier::UNIQUE => random_int(1, 2),
            RarityTier::EXOTIC => 2,
        };

        return $base + $bonus;
    }

    private function getStatMultiplier(RarityTier $rarity): float
    {
        $multipliers = config('game_config.rarity.stat_multipliers', []);

        return $multipliers[$rarity->value] ?? $rarity->statMultiplier();
    }

    private function getPriceMultiplier(RarityTier $rarity): float
    {
        $multipliers = config('game_config.rarity.price_multipliers', []);

        return $multipliers[$rarity->value] ?? $rarity->priceMultiplier();
    }

    /**
     * Generate a jitter factor within configured percentage bounds.
     */
    private function getJitterPercentage(): float
    {
        $jitterPct = config('game_config.rarity.jitter_percentage', 0.05);
        $jitter = (random_int(-100, 100) / 100) * $jitterPct;

        return 1.0 + $jitter;
    }
}

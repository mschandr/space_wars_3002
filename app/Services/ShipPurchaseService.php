<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use Illuminate\Support\Str;

/**
 * Service for purchasing ships with proper initialization and variations.
 */
class ShipPurchaseService
{
    public function __construct(
        private ShipVariationService $variationService
    ) {}

    /**
     * Purchase a ship for a player.
     *
     * @param  Player  $player  The player purchasing
     * @param  Ship  $blueprint  The ship blueprint
     * @param  string|null  $name  Optional custom name for the ship
     * @param  string  $quality  Quality tier affecting variations: 'standard', 'premium', 'legendary'
     * @return array{success: bool, ship?: PlayerShip, error?: string}
     */
    public function purchaseShip(
        Player $player,
        Ship $blueprint,
        ?string $name = null,
        string $quality = 'standard'
    ): array {
        // Check requirements
        if (! $blueprint->meetsRequirements(['level' => $player->level])) {
            return [
                'success' => false,
                'error' => 'You do not meet the requirements for this ship.',
            ];
        }

        // Check credits
        if ($player->credits < $blueprint->base_price) {
            return [
                'success' => false,
                'error' => 'Insufficient credits. Need '.number_format($blueprint->base_price).' credits.',
            ];
        }

        // Deduct credits
        $player->credits -= $blueprint->base_price;
        $player->save();

        // Create the ship instance
        $playerShip = $this->createShipInstance($player, $blueprint, $name, $quality);

        return [
            'success' => true,
            'ship' => $playerShip,
        ];
    }

    /**
     * Create a ship instance for a player (used for starting ships too).
     */
    public function createShipInstance(
        Player $player,
        Ship $blueprint,
        ?string $name = null,
        string $quality = 'standard'
    ): PlayerShip {
        $attributes = $blueprint->attributes ?? [];

        // Create base ship
        $playerShip = new PlayerShip([
            'uuid' => Str::uuid(),
            'player_id' => $player->id,
            'ship_id' => $blueprint->id,
            'name' => $name ?? $this->generateShipName($blueprint),
            'current_fuel' => $attributes['max_fuel'] ?? 100,
            'max_fuel' => $attributes['max_fuel'] ?? 100,
            'fuel_last_updated_at' => now(),
            'hull' => $blueprint->hull_strength,
            'max_hull' => $blueprint->hull_strength,
            'weapons' => $attributes['starting_weapons'] ?? 10,
            'cargo_hold' => $blueprint->cargo_capacity,
            'sensors' => $attributes['starting_sensors'] ?? 1,
            'warp_drive' => $attributes['starting_warp_drive'] ?? 1,
            'weapon_slots' => $blueprint->weapon_slots ?? 2,
            'utility_slots' => $blueprint->utility_slots ?? 2,
            'shield_strength' => $blueprint->shield_strength ?? 50,
            'current_cargo' => 0,
            'is_active' => false,
            'status' => 'operational',
            // Base modifiers
            'fuel_regen_modifier' => $attributes['fuel_regen_rate'] ?? 1.0,
            'fuel_consumption_modifier' => $attributes['fuel_consumption_rate'] ?? 1.0,
            'speed_modifier' => 1.0,
            // Special capacities
            'hidden_hold_capacity' => $attributes['hidden_hold_capacity'] ?? 0,
            'hidden_cargo' => 0,
            'colonist_capacity' => $attributes['colonist_capacity'] ?? 0,
            'current_colonists' => $attributes['starting_colonists'] ?? 0,
        ]);

        // Generate and apply variations (individual ship uniqueness)
        $variation = $this->variationService->generateVariation($blueprint, $quality);
        $this->variationService->applyVariation($playerShip, $variation);

        $playerShip->save();

        return $playerShip;
    }

    /**
     * Create a starter ship for a new player.
     */
    public function createStarterShip(Player $player, ?string $name = null): PlayerShip
    {
        // Find the starter ship blueprint
        $starterBlueprint = Ship::where('class', 'starter')
            ->orWhere('attributes->is_starter', true)
            ->first();

        if (! $starterBlueprint) {
            // Fallback to first available ship
            $starterBlueprint = Ship::where('is_available', true)
                ->orderBy('base_price', 'asc')
                ->first();
        }

        if (! $starterBlueprint) {
            throw new \RuntimeException('No ship blueprints available in database');
        }

        $ship = $this->createShipInstance($player, $starterBlueprint, $name, 'standard');
        $ship->is_active = true;
        $ship->save();

        return $ship;
    }

    /**
     * Generate a random ship name based on class.
     */
    private function generateShipName(Ship $blueprint): string
    {
        $prefixes = [
            'starter' => ['Lucky', 'Humble', 'Swift', 'Eager', 'Brave'],
            'smuggler' => ['Shadow', 'Ghost', 'Phantom', 'Silent', 'Void'],
            'battleship' => ['Defiant', 'Vengeful', 'Resolute', 'Indomitable', 'Valiant'],
            'cargo' => ['Reliable', 'Steady', 'Bountiful', 'Abundant', 'Prosperous'],
            'carrier' => ['Sovereign', 'Imperial', 'Royal', 'Commanding', 'Regal'],
            'colony_ship' => ['Hope', 'Pioneer', 'Destiny', 'Genesis', 'New Dawn'],
            'fighter' => ['Fierce', 'Razor', 'Thunder', 'Lightning', 'Hawk'],
            'explorer' => ['Wanderer', 'Seeker', 'Pathfinder', 'Discovery', 'Voyager'],
            'mining' => ['Digger', 'Quarry', 'Fortune', 'Goldstrike', 'Bonanza'],
        ];

        $suffixes = [
            'Star', 'Dream', 'Wind', 'Spirit', 'Quest', 'Runner',
            'Dancer', 'Chaser', 'Hunter', 'Seeker', 'Wing', 'Fire',
        ];

        $classPrefixes = $prefixes[$blueprint->class] ?? $prefixes['starter'];
        $prefix = $classPrefixes[array_rand($classPrefixes)];
        $suffix = $suffixes[array_rand($suffixes)];

        return "{$prefix} {$suffix}";
    }

    /**
     * Get available ships for purchase at current location.
     */
    public function getAvailableShips(Player $player): array
    {
        $ships = Ship::where('is_available', true)
            ->orderBy('base_price', 'asc')
            ->get();

        return $ships->map(function ($ship) use ($player) {
            return [
                'uuid' => $ship->uuid,
                'name' => $ship->name,
                'class' => $ship->class,
                'description' => $ship->description,
                'base_price' => (float) $ship->base_price,
                'cargo_capacity' => $ship->cargo_capacity,
                'hull_strength' => $ship->hull_strength,
                'shield_strength' => $ship->shield_strength,
                'weapon_slots' => $ship->weapon_slots,
                'speed' => $ship->speed,
                'rarity' => $ship->rarity,
                'requirements' => $ship->requirements,
                'can_afford' => $player->credits >= $ship->base_price,
                'meets_requirements' => $ship->meetsRequirements(['level' => $player->level]),
                'special_features' => $this->getSpecialFeatures($ship),
            ];
        })->toArray();
    }

    /**
     * Get special features for display.
     */
    private function getSpecialFeatures(Ship $ship): array
    {
        $features = [];
        $attributes = $ship->attributes ?? [];

        if (! empty($attributes['hidden_hold_capacity'])) {
            $features[] = 'Hidden cargo hold ('.$attributes['hidden_hold_capacity'].' units)';
        }

        if (! empty($attributes['colonist_capacity'])) {
            $features[] = 'Colony capacity ('.$attributes['colonist_capacity'].' colonists)';
        }

        if (! empty($attributes['fighter_capacity'])) {
            $features[] = 'Fighter bay ('.$attributes['fighter_capacity'].' fighters)';
        }

        if (! empty($attributes['is_carrier'])) {
            $features[] = 'Carrier vessel';
        }

        if (! empty($attributes['mining_lasers'])) {
            $features[] = 'Mining capability ('.$attributes['mining_lasers'].' lasers)';
        }

        $fuelRegen = $attributes['fuel_regen_rate'] ?? 1.0;
        if ($fuelRegen != 1.0) {
            $percent = round(($fuelRegen - 1.0) * 100);
            $features[] = ($percent > 0 ? '+' : '').$percent.'% fuel regeneration';
        }

        $fuelConsumption = $attributes['fuel_consumption_rate'] ?? 1.0;
        if ($fuelConsumption != 1.0) {
            $percent = round(($fuelConsumption - 1.0) * 100);
            $features[] = ($percent > 0 ? '+' : '').$percent.'% fuel consumption';
        }

        return $features;
    }
}

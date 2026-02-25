<?php

namespace App\Services;

use App\Enums\RarityTier;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\ShipyardInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShipyardInventoryService
{
    public function __construct(
        private ShipRarityService $rarityService,
        private ShipVariationService $variationService,
        private ShipPurchaseService $purchaseService,
    ) {}

    /**
     * Ensure inventory exists, generating lazily if needed.
     */
    public function ensureInventory(PointOfInterest $shipyardPoi): void
    {
        if ($shipyardPoi->isInventoryGenerated()) {
            return;
        }

        $this->generateInventory($shipyardPoi);
    }

    /**
     * Generate shipyard inventory for a POI.
     *
     * Always includes a free Sparrow-class starter ship, plus random rarity-rolled ships.
     *
     * @return int Number of ships generated
     */
    public function generateInventory(PointOfInterest $shipyardPoi): int
    {
        $blueprints = Ship::where('is_available', true)->get();

        if ($blueprints->isEmpty()) {
            $shipyardPoi->markInventoryGenerated();

            return 0;
        }

        $created = 0;

        // Always generate a free Sparrow-class starter ship
        $created += $this->generateFreeStarterShip($shipyardPoi);

        // Generate random rarity-rolled ships
        $count = $this->determineInventoryCount($shipyardPoi);

        for ($i = 0; $i < $count; $i++) {
            $blueprint = $blueprints->random();
            $rarity = $this->rarityService->rollRarity();
            $stats = $this->rarityService->applyRarityToShipStats($blueprint, $rarity);
            $price = $this->rarityService->calculatePrice((float) $blueprint->base_price, $rarity);
            $name = $this->generateShipName($blueprint, $rarity);

            // Generate variation traits based on rarity
            $quality = $this->rarityToQuality($rarity);
            $variation = $this->variationService->generateVariation($blueprint, $quality);

            ShipyardInventory::create([
                'uuid' => (string) Str::uuid(),
                'poi_id' => $shipyardPoi->id,
                'ship_id' => $blueprint->id,
                'name' => $name,
                'rarity' => $rarity->value,
                'price' => $price,
                'hull_strength' => $stats['hull_strength'],
                'shield_strength' => $stats['shield_strength'],
                'cargo_capacity' => $stats['cargo_capacity'],
                'speed' => $stats['speed'],
                'weapon_slots' => $stats['weapon_slots'],
                'utility_slots' => $stats['utility_slots'],
                'max_fuel' => $stats['max_fuel'],
                'sensors' => $stats['sensors'],
                'warp_drive' => $stats['warp_drive'],
                'weapons' => $stats['weapons'],
                'variation_traits' => $variation['traits'],
                'attributes' => $blueprint->attributes,
            ]);

            $created++;
        }

        $shipyardPoi->markInventoryGenerated();

        return $created;
    }

    /**
     * Generate a free Sparrow-class starter ship in the shipyard inventory.
     *
     * @return int 1 if created, 0 if starter blueprint not found
     */
    private function generateFreeStarterShip(PointOfInterest $shipyardPoi): int
    {
        $starterBlueprint = Ship::where('class', 'starter')
            ->orWhere('attributes->is_starter', true)
            ->first();

        if (! $starterBlueprint) {
            Log::warning('No starter ship blueprint found for shipyard inventory generation', [
                'poi_id' => $shipyardPoi->id,
            ]);

            return 0;
        }

        $stats = $this->rarityService->applyRarityToShipStats($starterBlueprint, RarityTier::COMMON);
        $variation = $this->variationService->generateVariation($starterBlueprint, 'standard');

        ShipyardInventory::create([
            'uuid' => (string) Str::uuid(),
            'poi_id' => $shipyardPoi->id,
            'ship_id' => $starterBlueprint->id,
            'name' => $starterBlueprint->name,
            'rarity' => RarityTier::COMMON->value,
            'price' => 0,
            'hull_strength' => $stats['hull_strength'],
            'shield_strength' => $stats['shield_strength'],
            'cargo_capacity' => $stats['cargo_capacity'],
            'speed' => $stats['speed'],
            'weapon_slots' => $stats['weapon_slots'],
            'utility_slots' => $stats['utility_slots'],
            'max_fuel' => $stats['max_fuel'],
            'sensors' => $stats['sensors'],
            'warp_drive' => $stats['warp_drive'],
            'weapons' => $stats['weapons'],
            'variation_traits' => $variation['traits'],
            'attributes' => $starterBlueprint->attributes,
        ]);

        return 1;
    }

    /**
     * Get available (unsold) ships at a shipyard.
     */
    public function getAvailableShips(PointOfInterest $shipyardPoi): Collection
    {
        return ShipyardInventory::where('poi_id', $shipyardPoi->id)
            ->where('is_sold', false)
            ->with('ship')
            ->orderBy('price')
            ->get();
    }

    /**
     * Purchase a ship from shipyard inventory.
     *
     * @return array{success: bool, ship?: PlayerShip, error?: string}
     */
    public function purchaseShip(Player $player, ShipyardInventory $item, ?string $name = null): array
    {
        if ($item->is_sold) {
            return ['success' => false, 'error' => 'This ship has already been sold.'];
        }

        if ($player->credits < $item->price) {
            return [
                'success' => false,
                'error' => 'Insufficient credits. Need '.number_format((float) $item->price).' credits.',
            ];
        }

        $blueprint = $item->ship;
        if ($blueprint && ! $blueprint->meetsRequirements(['level' => $player->level])) {
            return ['success' => false, 'error' => 'You do not meet the requirements for this ship.'];
        }

        return DB::transaction(function () use ($player, $item, $name) {
            $player->deductCredits((float) $item->price);

            $playerShip = $this->purchaseService->createShipFromInventory($player, $item, $name);

            $item->is_sold = true;
            $item->save();

            return ['success' => true, 'ship' => $playerShip];
        });
    }

    /**
     * Determine how many ships to generate based on shipyard class.
     */
    private function determineInventoryCount(PointOfInterest $poi): int
    {
        $shipyardClass = $poi->attributes['shipyard_class'] ?? 'standard';
        $ranges = config('game_config.shipyard.inventory_size', []);
        $range = $ranges[$shipyardClass] ?? $ranges['standard'] ?? [2, 4];

        return random_int($range[0], $range[1]);
    }

    /**
     * Map rarity tier to variation quality.
     */
    private function rarityToQuality(RarityTier $rarity): string
    {
        return match ($rarity) {
            RarityTier::EXOTIC, RarityTier::UNIQUE => 'legendary',
            RarityTier::EPIC, RarityTier::RARE => 'premium',
            default => 'standard',
        };
    }

    /**
     * Generate a unique ship name themed by rarity.
     */
    private function generateShipName(Ship $blueprint, RarityTier $rarity): string
    {
        $prefixes = match ($rarity) {
            RarityTier::EXOTIC => ['Celestial', 'Mythic', 'Primordial', 'Eternal', 'Transcendent'],
            RarityTier::UNIQUE => ['Legendary', 'Fabled', 'Illustrious', 'Exalted', 'Paramount'],
            RarityTier::EPIC => ['Majestic', 'Grand', 'Superior', 'Formidable', 'Imposing'],
            RarityTier::RARE => ['Refined', 'Distinguished', 'Notable', 'Prized', 'Select'],
            RarityTier::UNCOMMON => ['Sturdy', 'Reliable', 'Solid', 'Proven', 'Capable'],
            RarityTier::COMMON => ['Standard', 'Basic', 'Modest', 'Plain', 'Simple'],
        };

        $suffixes = [
            'Star', 'Voyager', 'Runner', 'Spirit', 'Hawk', 'Phoenix',
            'Falcon', 'Viper', 'Tempest', 'Aurora', 'Eclipse', 'Horizon',
        ];

        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = $suffixes[array_rand($suffixes)];
        $serial = strtoupper(substr(md5((string) Str::uuid()), 0, 4));

        return "{$prefix} {$suffix} {$serial}";
    }
}

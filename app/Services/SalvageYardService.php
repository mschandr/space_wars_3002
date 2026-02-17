<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerShipComponent;
use App\Models\PointOfInterest;
use App\Models\SalvageYardInventory;
use App\Models\ShipComponent;
use App\Models\TradingHub;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for handling salvage yard operations.
 *
 * Salvage yards sell ship components:
 * - Weapons for weapon_slots (lasers, missiles, torpedoes)
 * - Utilities for utility_slots (shield regenerators, hull patches, scanners)
 */
class SalvageYardService
{
    /**
     * Get all items available at a salvage yard.
     */
    public function getInventory(TradingHub $hub): Collection
    {
        return SalvageYardInventory::where('trading_hub_id', $hub->id)
            ->where('quantity', '>', 0)
            ->with('component')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'component' => [
                        'id' => $item->component->id,
                        'uuid' => $item->component->uuid,
                        'name' => $item->component->name,
                        'type' => $item->component->type,
                        'slot_type' => $item->component->slot_type,
                        'description' => $item->component->description,
                        'slots_required' => $item->component->slots_required,
                        'rarity' => $item->component->rarity,
                        'rarity_color' => $item->component->getRarityColor(),
                        'effects' => $item->component->effects,
                        'requirements' => $item->component->requirements,
                    ],
                    'quantity' => $item->quantity,
                    'price' => (float) $item->current_price,
                    'condition' => $item->condition,
                    'condition_description' => $item->getConditionDescription(),
                    'source' => $item->source,
                    'source_description' => $item->getSourceDescription(),
                    'is_new' => $item->isNew(),
                ];
            });
    }

    /**
     * Get items grouped by type (weapons, shields, hull, utilities).
     */
    public function getInventoryByType(TradingHub $hub): array
    {
        $inventory = $this->getInventory($hub);

        return [
            'weapons' => $inventory->filter(fn ($item) => $item['component']['slot_type'] === 'weapon_slot')->values(),
            'utilities' => $inventory->filter(fn ($item) => $item['component']['slot_type'] === 'utility_slot')->values(),
        ];
    }

    /**
     * Purchase a component from the salvage yard.
     *
     * @return array{success: bool, component?: PlayerShipComponent, error?: string}
     */
    public function purchaseComponent(
        Player $player,
        TradingHub $hub,
        SalvageYardInventory $item,
        PlayerShip $ship,
        int $slotIndex
    ): array {
        // Verify player is at this hub
        $playerLocation = $player->currentLocation;
        if (! $playerLocation || $hub->poi_id !== $playerLocation->id) {
            return [
                'success' => false,
                'error' => 'You must be at this trading hub to purchase components.',
            ];
        }

        // Verify item is in stock
        if ($item->quantity <= 0) {
            return [
                'success' => false,
                'error' => 'This item is out of stock.',
            ];
        }

        // Verify item belongs to this hub
        if ($item->trading_hub_id !== $hub->id) {
            return [
                'success' => false,
                'error' => 'This item is not available at this salvage yard.',
            ];
        }

        // Check player credits
        if ($player->credits < $item->current_price) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Insufficient credits. You need %s credits but only have %s.',
                    number_format($item->current_price),
                    number_format($player->credits)
                ),
            ];
        }

        // Check requirements
        if (! $item->component->meetsRequirements($player)) {
            return [
                'success' => false,
                'error' => 'You do not meet the requirements to use this component.',
            ];
        }

        // Check slot availability
        $slotType = $item->component->slot_type;
        $maxSlots = $slotType === 'weapon_slot' ? $ship->weapon_slots : $ship->utility_slots;

        if ($slotIndex < 1 || $slotIndex > $maxSlots) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Invalid slot index. Ship has %d %s slots.',
                    $maxSlots,
                    str_replace('_', ' ', $slotType)
                ),
            ];
        }

        // Check if slot is already occupied
        $existingComponent = PlayerShipComponent::where('player_ship_id', $ship->id)
            ->where('slot_type', $slotType)
            ->where('slot_index', $slotIndex)
            ->first();

        if ($existingComponent) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Slot %d is already occupied by %s. Uninstall it first.',
                    $slotIndex,
                    $existingComponent->component->name
                ),
            ];
        }

        // Process purchase atomically
        return DB::transaction(function () use ($player, $item, $ship, $slotType, $slotIndex) {
            $player->deductCredits($item->current_price);

            $item->quantity--;
            $item->save();

            // Install component on ship
            $installedComponent = PlayerShipComponent::create([
                'player_ship_id' => $ship->id,
                'ship_component_id' => $item->ship_component_id,
                'slot_type' => $slotType,
                'slot_index' => $slotIndex,
                'condition' => $item->condition,
                'ammo' => $item->component->getEffect('max_ammo'),
                'max_ammo' => $item->component->getEffect('max_ammo'),
                'is_active' => true,
            ]);

            return [
                'success' => true,
                'component' => $installedComponent,
                'message' => sprintf(
                    '%s installed in %s slot %d.',
                    $item->component->name,
                    str_replace('_', ' ', $slotType),
                    $slotIndex
                ),
                'credits_remaining' => $player->credits,
            ];
        });
    }

    /**
     * Uninstall a component from a ship (returns to player inventory or sells).
     */
    public function uninstallComponent(
        Player $player,
        PlayerShipComponent $component,
        bool $sellToYard = false
    ): array {
        // Verify ownership
        if ($component->playerShip->player_id !== $player->id) {
            return [
                'success' => false,
                'error' => 'This component is not installed on your ship.',
            ];
        }

        $componentName = $component->component->name;

        if ($sellToYard) {
            // Get current location trading hub
            $hub = $player->currentLocation?->tradingHub;

            if (! $hub) {
                return [
                    'success' => false,
                    'error' => 'You must be at a trading hub to sell components.',
                ];
            }

            // Calculate sell value (50% of base price, adjusted for condition)
            $basePrice = (float) $component->component->base_price;
            $conditionMultiplier = $component->condition / 100;
            $sellValue = (int) ($basePrice * 0.5 * $conditionMultiplier);

            return DB::transaction(function () use ($player, $component, $hub, $componentName, $basePrice, $sellValue) {
                $player->addCredits($sellValue);

                // Add to salvage yard inventory
                $existingInventory = SalvageYardInventory::where('trading_hub_id', $hub->id)
                    ->where('ship_component_id', $component->ship_component_id)
                    ->where('condition', $component->condition)
                    ->where('source', 'salvage')
                    ->first();

                if ($existingInventory) {
                    $existingInventory->quantity++;
                    $existingInventory->save();
                } else {
                    SalvageYardInventory::create([
                        'trading_hub_id' => $hub->id,
                        'ship_component_id' => $component->ship_component_id,
                        'quantity' => 1,
                        'current_price' => $basePrice * 0.7, // Slightly cheaper as salvage
                        'condition' => $component->condition,
                        'source' => 'salvage',
                    ]);
                }

                $component->delete();

                return [
                    'success' => true,
                    'message' => sprintf('%s sold for %s credits.', $componentName, number_format($sellValue)),
                    'credits_received' => $sellValue,
                    'credits_total' => $player->credits,
                ];
            });
        }

        $component->delete();

        return [
            'success' => true,
            'message' => sprintf('%s uninstalled.', $componentName),
            'credits_received' => 0,
            'credits_total' => $player->credits,
        ];
    }

    /**
     * Get components installed on a ship.
     */
    public function getInstalledComponents(PlayerShip $ship): array
    {
        $components = PlayerShipComponent::where('player_ship_id', $ship->id)
            ->with('component')
            ->get();

        return [
            'weapon_slots' => $components
                ->filter(fn ($c) => $c->slot_type === 'weapon_slot')
                ->mapWithKeys(fn ($c) => [$c->slot_index => $this->formatInstalledComponent($c)])
                ->toArray(),
            'utility_slots' => $components
                ->filter(fn ($c) => $c->slot_type === 'utility_slot')
                ->mapWithKeys(fn ($c) => [$c->slot_index => $this->formatInstalledComponent($c)])
                ->toArray(),
            'total_weapon_slots' => $ship->weapon_slots,
            'total_utility_slots' => $ship->utility_slots,
        ];
    }

    /**
     * Format an installed component for API response.
     */
    private function formatInstalledComponent(PlayerShipComponent $component): array
    {
        return [
            'id' => $component->id,
            'component' => [
                'id' => $component->component->id,
                'uuid' => $component->component->uuid,
                'name' => $component->component->name,
                'type' => $component->component->type,
                'rarity' => $component->component->rarity,
                'rarity_color' => $component->component->getRarityColor(),
                'effects' => $component->component->effects,
            ],
            'slot_index' => $component->slot_index,
            'condition' => $component->condition,
            'is_damaged' => $component->isDamaged(),
            'is_broken' => $component->isBroken(),
            'ammo' => $component->ammo,
            'max_ammo' => $component->max_ammo,
            'needs_ammo' => $component->needsAmmo(),
            'is_active' => $component->is_active,
        ];
    }

    /**
     * Populate a trading hub's salvage yard with random components.
     */
    public function populateSalvageYard(TradingHub $hub, int $itemCount = 10): int
    {
        // Get available components
        $components = ShipComponent::where('is_available', true)->get();

        if ($components->isEmpty()) {
            return 0;
        }

        $created = 0;

        for ($i = 0; $i < $itemCount; $i++) {
            $component = $components->random();

            // Determine source and condition
            $source = $this->randomSource();
            $condition = $this->randomCondition($source);

            // Calculate price based on condition and source
            $basePrice = (float) $component->base_price;
            $priceMultiplier = $this->getPriceMultiplier($source, $condition);
            $price = $basePrice * $priceMultiplier;

            // Check if similar item exists
            $existing = SalvageYardInventory::where('trading_hub_id', $hub->id)
                ->where('ship_component_id', $component->id)
                ->where('condition', $condition)
                ->where('source', $source)
                ->first();

            if ($existing) {
                $existing->quantity += rand(1, 3);
                $existing->save();
            } else {
                SalvageYardInventory::create([
                    'trading_hub_id' => $hub->id,
                    'ship_component_id' => $component->id,
                    'quantity' => rand(1, 5),
                    'current_price' => $price,
                    'condition' => $condition,
                    'source' => $source,
                ]);
            }

            $created++;
        }

        return $created;
    }

    private function randomSource(): string
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 50 => 'salvage',
            $roll <= 85 => 'manufactured',
            default => 'stolen',
        };
    }

    private function randomCondition(string $source): int
    {
        return match ($source) {
            'manufactured' => 100,
            'salvage' => rand(40, 95),
            'stolen' => rand(60, 100),
            default => rand(50, 100),
        };
    }

    private function getPriceMultiplier(string $source, int $condition): float
    {
        $baseMultiplier = match ($source) {
            'manufactured' => 1.2,  // Premium for new
            'salvage' => 0.7,       // Discount for salvage
            'stolen' => 0.6,        // Bigger discount but risky
            default => 1.0,
        };

        // Further adjust for condition
        $conditionMultiplier = 0.5 + ($condition / 200); // 0.5 to 1.0

        return $baseMultiplier * $conditionMultiplier;
    }

    /**
     * Sell a whole ship to a salvage yard for lump-sum credits.
     * Components are extracted and placed in the salvage yard inventory.
     *
     * @return array{success: bool, credits_received?: int, components_salvaged?: int, error?: string}
     */
    public function sellShipToSalvageYard(
        Player $player,
        PlayerShip $ship,
        PointOfInterest $salvageYardPoi
    ): array {
        if ($ship->player_id !== $player->id) {
            return ['success' => false, 'error' => 'You do not own this ship.'];
        }

        // Cannot sell only ship
        $shipCount = PlayerShip::where('player_id', $player->id)->count();
        if ($shipCount <= 1) {
            return ['success' => false, 'error' => 'You cannot sell your only ship.'];
        }

        if ($ship->is_active) {
            return ['success' => false, 'error' => 'You cannot sell your active ship. Switch to another ship first.'];
        }

        $blueprint = $ship->ship;
        $basePrice = (float) ($blueprint->base_price ?? 0);
        $sellPct = config('game_config.salvage_yard.ship_sell_percentage', 0.35);
        $conditionPct = $ship->max_hull > 0 ? ($ship->hull / $ship->max_hull) : 1.0;
        $creditsReceived = (int) round($basePrice * $sellPct * $conditionPct);

        return DB::transaction(function () use ($player, $ship, $salvageYardPoi, $creditsReceived) {
            $player->addCredits($creditsReceived);

            // Extract installed components into salvage yard inventory
            $componentsSalvaged = 0;
            $installedComponents = PlayerShipComponent::where('player_ship_id', $ship->id)
                ->with('component')
                ->get();

            foreach ($installedComponents as $installed) {
                SalvageYardInventory::create([
                    'poi_id' => $salvageYardPoi->id,
                    'ship_component_id' => $installed->ship_component_id,
                    'quantity' => 1,
                    'current_price' => (float) $installed->component->base_price * 0.7,
                    'condition' => $installed->condition,
                    'source' => 'salvage',
                ]);
                $componentsSalvaged++;
            }

            // Delete cargo, components, then ship
            $ship->cargo()->delete();
            $ship->components()->delete();
            $ship->delete();

            return [
                'success' => true,
                'credits_received' => $creditsReceived,
                'components_salvaged' => $componentsSalvaged,
            ];
        });
    }

    /**
     * Ensure salvage yard inventory exists, generating lazily if needed.
     */
    public function ensureSalvageYardInventory(PointOfInterest $salvageYardPoi): void
    {
        if ($salvageYardPoi->isInventoryGenerated()) {
            return;
        }

        $this->generateSalvageYardInventory($salvageYardPoi);
    }

    /**
     * Generate salvage yard inventory at a POI.
     *
     * @return int Number of items generated
     */
    public function generateSalvageYardInventory(PointOfInterest $salvageYardPoi): int
    {
        $components = ShipComponent::where('is_available', true)->get();

        if ($components->isEmpty()) {
            $salvageYardPoi->markInventoryGenerated();

            return 0;
        }

        $importance = $salvageYardPoi->attributes['importance'] ?? 'standard';
        $ranges = config('game_config.salvage_yard.inventory_size', []);
        $range = $ranges[$importance] ?? $ranges['standard'] ?? [5, 10];
        $count = random_int($range[0], $range[1]);

        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $component = $components->random();
            $source = $this->randomSource();
            $condition = $this->randomCondition($source);
            $priceMultiplier = $this->getPriceMultiplier($source, $condition);
            $price = (float) $component->base_price * $priceMultiplier;

            SalvageYardInventory::create([
                'poi_id' => $salvageYardPoi->id,
                'ship_component_id' => $component->id,
                'quantity' => rand(1, 5),
                'current_price' => $price,
                'condition' => $condition,
                'source' => $source,
            ]);

            $created++;
        }

        $salvageYardPoi->markInventoryGenerated();

        return $created;
    }

    /**
     * Get inventory at a salvage yard POI.
     */
    public function getInventoryByPoi(PointOfInterest $poi): Collection
    {
        return SalvageYardInventory::where('poi_id', $poi->id)
            ->where('quantity', '>', 0)
            ->with('component')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'component' => [
                        'id' => $item->component->id,
                        'uuid' => $item->component->uuid,
                        'name' => $item->component->name,
                        'type' => $item->component->type,
                        'slot_type' => $item->component->slot_type,
                        'description' => $item->component->description,
                        'slots_required' => $item->component->slots_required,
                        'rarity' => $item->component->rarity,
                        'rarity_color' => $item->component->getRarityColor(),
                        'effects' => $item->component->effects,
                        'requirements' => $item->component->requirements,
                    ],
                    'quantity' => $item->quantity,
                    'price' => (float) $item->current_price,
                    'condition' => $item->condition,
                    'condition_description' => $item->getConditionDescription(),
                    'source' => $item->source,
                    'source_description' => $item->getSourceDescription(),
                    'is_new' => $item->isNew(),
                ];
            });
    }
}

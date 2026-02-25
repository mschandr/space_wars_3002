<?php

namespace App\Services;

use App\Enums\RarityTier;
use App\Enums\SlotType;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerShipComponent;
use App\Models\PointOfInterest;
use App\Models\SalvageYardInventory;
use App\Models\ShipComponent;
use App\Models\TradingHub;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;

/**
 * Service for handling salvage yard operations.
 *
 * Salvage yards sell ship components:
 * - Weapons for weapon_slots (lasers, missiles, torpedoes)
 * - Utilities for utility_slots (shield regenerators, hull patches, scanners)
 */
class SalvageYardService
{
    public function __construct(
        private readonly MerchantCommentaryService $commentaryService
    ) {}

    /**
     * Get all items available at a salvage yard.
     */
    public function getInventory(TradingHub $hub, ?Player $player = null): Collection
    {
        return SalvageYardInventory::where('trading_hub_id', $hub->id)
            ->where('quantity', '>', 0)
            ->with('component')
            ->get()
            ->map(fn ($item) => $this->formatInventoryItem($item, $player));
    }

    /**
     * Get items grouped by slot type (8 categories).
     */
    public function getInventoryByType(TradingHub $hub, ?Player $player = null): array
    {
        $inventory = $this->getInventory($hub, $player);

        $grouped = [];
        foreach (SlotType::cases() as $slotType) {
            $items = $inventory->filter(
                fn ($item) => $item['component']['slot_type'] === $slotType->value
            )->values();

            if ($items->isNotEmpty()) {
                $grouped[$slotType->value] = $items;
            }
        }

        return $grouped;
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

        // Resolve slot type from the component blueprint
        $slotType = $item->component->slot_type;
        $slotTypeEnum = $slotType instanceof SlotType ? $slotType : SlotType::from($slotType);
        $maxSlots = (int) ($ship->{$slotTypeEnum->slotColumn()} ?? 0);

        if ($slotIndex < 1 || $slotIndex > $maxSlots) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Invalid slot index. Ship has %d %s slot(s).',
                    $maxSlots,
                    $slotTypeEnum->label()
                ),
            ];
        }

        // Check if slot is already occupied
        $existingComponent = PlayerShipComponent::where('player_ship_id', $ship->id)
            ->where('slot_type', $slotTypeEnum->value)
            ->where('slot_index', $slotIndex)
            ->first();

        // For core systems, auto-uninstall existing component when swapping
        $autoUninstall = $existingComponent && $slotTypeEnum->isCoreSystem();

        if ($existingComponent && ! $autoUninstall) {
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
        return DB::transaction(function () use ($player, $item, $ship, $slotTypeEnum, $slotIndex, $existingComponent, $autoUninstall) {
            $player->deductCredits($item->current_price);

            $item->quantity--;
            $item->save();

            // Auto-uninstall existing core component (destroyed, not saved)
            $uninstalledName = null;
            if ($autoUninstall && $existingComponent) {
                $uninstalledName = $existingComponent->component->name;
                $existingComponent->delete();
            }

            // Install component on ship
            $installedComponent = PlayerShipComponent::create([
                'player_ship_id' => $ship->id,
                'ship_component_id' => $item->ship_component_id,
                'slot_type' => $slotTypeEnum->value,
                'slot_index' => $slotIndex,
                'condition' => $item->condition,
                'ammo' => $item->component->getEffect('max_ammo'),
                'max_ammo' => $item->component->getEffect('max_ammo'),
                'is_active' => true,
            ]);

            $message = sprintf(
                '%s installed in %s slot %d.',
                $item->component->name,
                $slotTypeEnum->label(),
                $slotIndex
            );

            if ($uninstalledName) {
                $message .= sprintf(' (replaced %s)', $uninstalledName);
            }

            return [
                'success' => true,
                'component' => $installedComponent,
                'message' => $message,
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
     * Get components installed on a ship, grouped by slot type.
     */
    public function getInstalledComponents(PlayerShip $ship): array
    {
        $components = PlayerShipComponent::where('player_ship_id', $ship->id)
            ->with('component')
            ->get();

        $result = [];
        foreach (SlotType::cases() as $slotType) {
            $slotColumn = $slotType->slotColumn();
            $totalSlots = (int) ($ship->{$slotColumn} ?? 0);

            if ($totalSlots > 0) {
                $result[$slotType->value] = [
                    'installed' => $components
                        ->filter(fn ($c) => ($c->slot_type instanceof SlotType ? $c->slot_type : SlotType::tryFrom($c->slot_type)) === $slotType)
                        ->mapWithKeys(fn ($c) => [$c->slot_index => $this->formatInstalledComponent($c)])
                        ->toArray(),
                    'total_slots' => $totalSlots,
                    'label' => $slotType->label(),
                ];
            }
        }

        return $result;
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
                'slot_type' => $component->component->slot_type instanceof SlotType
                    ? $component->component->slot_type->value
                    : $component->component->slot_type,
                'slot_type_label' => $component->component->slot_type instanceof SlotType
                    ? $component->component->slot_type->label()
                    : $component->component->slot_type,
                'rarity' => $component->component->rarity->value,
                'rarity_label' => $component->component->rarity->label(),
                'rarity_color' => $component->component->getRarityColor(),
                'effects' => $component->component->effects,
                'max_upgrade_level' => $component->component->max_upgrade_level,
            ],
            'slot_index' => $component->slot_index,
            'condition' => $component->condition,
            'is_damaged' => $component->isDamaged(),
            'is_broken' => $component->isBroken(),
            'ammo' => $component->ammo,
            'max_ammo' => $component->max_ammo,
            'needs_ammo' => $component->needsAmmo(),
            'is_active' => $component->is_active,
            'upgrade_level' => $component->upgrade_level ?? 0,
            'can_upgrade' => ($component->upgrade_level ?? 0) < ($component->component->max_upgrade_level ?? 0),
        ];
    }

    /**
     * Ensure a trading hub has salvage inventory, generating lazily if empty.
     */
    public function ensureHubSalvageInventory(TradingHub $hub): void
    {
        $hasInventory = SalvageYardInventory::where('trading_hub_id', $hub->id)
            ->where('quantity', '>', 0)
            ->exists();

        if ($hasInventory) {
            return;
        }

        // Determine item count based on hub tier
        $itemCount = match ($hub->getTier()) {
            'premium' => rand(12, 20),
            'major' => rand(8, 15),
            default => rand(5, 10),
        };

        $this->populateSalvageYard($hub, $itemCount);
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
        $componentsByRarity = $components->groupBy(fn ($c) => $c->rarity->value);

        for ($i = 0; $i < $itemCount; $i++) {
            $component = $this->rollWeightedComponent($componentsByRarity, $components);

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
     * Roll a rarity tier using configured weights, then pick a random component of that tier.
     * Falls back to any random component if no components exist at the rolled tier.
     */
    private function rollWeightedComponent(Collection $componentsByRarity, Collection $allComponents): ShipComponent
    {
        $weights = config('game_config.rarity.weights', []);

        $generator = new WeightedRandomGenerator;
        $values = [];
        foreach (RarityTier::cases() as $tier) {
            // Only register tiers that have components available
            if ($componentsByRarity->has($tier->value)) {
                $values[$tier->value] = $weights[$tier->value] ?? $tier->weight();
            }
        }

        if (empty($values)) {
            return $allComponents->random();
        }

        $generator->registerValues($values);
        $rolledTier = $generator->generate();

        return $componentsByRarity[$rolledTier]->random();
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
        $componentsByRarity = $components->groupBy(fn ($c) => $c->rarity->value);

        for ($i = 0; $i < $count; $i++) {
            $component = $this->rollWeightedComponent($componentsByRarity, $components);
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
    public function getInventoryByPoi(PointOfInterest $poi, ?Player $player = null): Collection
    {
        return SalvageYardInventory::where('poi_id', $poi->id)
            ->where('quantity', '>', 0)
            ->with('component')
            ->get()
            ->map(fn ($item) => $this->formatInventoryItem($item, $player));
    }

    /**
     * Format an inventory item for API response.
     */
    private function formatInventoryItem(SalvageYardInventory $item, ?Player $player = null): array
    {
        $slotType = $item->component->slot_type;

        return [
            'id' => $item->id,
            'component' => [
                'id' => $item->component->id,
                'uuid' => $item->component->uuid,
                'name' => $item->component->name,
                'type' => $item->component->type,
                'slot_type' => $slotType instanceof SlotType ? $slotType->value : $slotType,
                'slot_type_label' => $slotType instanceof SlotType ? $slotType->label() : $slotType,
                'description' => $item->component->description,
                'slots_required' => $item->component->slots_required,
                'rarity' => $item->component->rarity->value,
                'rarity_label' => $item->component->rarity->label(),
                'rarity_color' => $item->component->getRarityColor(),
                'effects' => $item->component->effects,
                'requirements' => $item->component->requirements,
                'max_upgrade_level' => $item->component->max_upgrade_level ?? 0,
            ],
            'quantity' => $item->quantity,
            'price' => (float) $item->current_price,
            'condition' => $item->condition,
            'condition_description' => $item->getConditionDescription(),
            'source' => $item->source,
            'source_description' => $item->getSourceDescription(),
            'is_new' => $item->isNew(),
            'owner_commentary' => $this->commentaryService->generateComponentCommentary(
                $item->component,
                $item,
                $player
            ),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ShipResource;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use App\Services\MerchantCommentaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShipShopController extends BaseApiController
{
    public function __construct(
        private readonly MerchantCommentaryService $commentaryService
    ) {}

    /**
     * Check if trading hub has shipyard and list available ships
     *
     * GET /api/trading-hubs/{uuid}/shipyard
     */
    public function getShipyard(Request $request, string $uuid): JsonResponse
    {
        $tradingHub = $this->findTradingHub($uuid);

        if (! $tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $hubServices = $tradingHub->services ?? [];
        $hasShipyard = $tradingHub->hasShipyard()
            || in_array('shipyard', $hubServices)
            || in_array('ship_sales', $hubServices);

        if (! $hasShipyard) {
            return $this->success([
                'has_shipyard' => false,
                'available_ships' => [],
            ]);
        }

        // Lazy-generate ship inventory if hub declares shipyard but has no stock
        if (! $tradingHub->hasShipyard()) {
            $this->generateShipInventory($tradingHub);
        }

        // Resolve buyer context for dynamic commentary
        $player = $request->user()?->player;
        $player?->load('activeShip.ship');

        // Get available ships at this trading hub
        $availableShips = $tradingHub->ships()
            ->with('ship')
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($inventory) use ($player) {
                $ship = $inventory->ship;

                // Hand-written sales_pitches override dynamic commentary
                $commentary = ! empty($ship->sales_pitches)
                    ? $ship->getSalesPitch($player?->activeShip !== null)
                    : $this->commentaryService->generateShipCommentary(
                        $ship,
                        (float) $inventory->current_price,
                        $player
                    );

                return [
                    'ship' => new ShipResource($ship),
                    'current_price' => $inventory->current_price,
                    'quantity' => $inventory->quantity,
                    'owner_commentary' => $commentary,
                ];
            });

        return $this->success([
            'has_shipyard' => true,
            'trading_hub_name' => $tradingHub->name,
            'available_ships' => $availableShips,
        ]);
    }

    /**
     * Lazy-generate ship inventory for a trading hub that declares shipyard service.
     */
    private function generateShipInventory(\App\Models\TradingHub $tradingHub): void
    {
        $ships = Ship::where('is_available', true)->get();

        if ($ships->isEmpty()) {
            return;
        }

        $tier = $tradingHub->getTier();
        $shipCount = match ($tier) {
            'premium' => rand(5, $ships->count()),
            'major' => rand(3, 6),
            'standard' => rand(2, 4),
            default => 2,
        };

        $availableShips = $ships->random(min($shipCount, $ships->count()));

        foreach ($availableShips as $ship) {
            $quantity = match ($ship->rarity?->value ?? $ship->rarity) {
                'common' => rand(3, 8),
                'uncommon' => rand(2, 5),
                'rare' => rand(1, 3),
                'very_rare' => rand(1, 2),
                'legendary' => 1,
                default => rand(2, 5),
            };

            $demandLevel = rand(30, 70);
            $supplyLevel = rand(30, 70);

            $basePrice = $ship->base_price;
            $demandMultiplier = 1 + (($demandLevel - 50) / 100);
            $supplyMultiplier = 1 - (($supplyLevel - 50) / 100);
            $currentPrice = $basePrice * $demandMultiplier * $supplyMultiplier;

            \App\Models\TradingHubShip::create([
                'trading_hub_id' => $tradingHub->id,
                'galaxy_id' => $tradingHub->pointOfInterest?->galaxy_id,
                'ship_id' => $ship->id,
                'quantity' => $quantity,
                'current_price' => $currentPrice,
                'demand_level' => $demandLevel,
                'supply_level' => $supplyLevel,
                'last_price_update' => now(),
            ]);
        }
    }

    /**
     * Browse all ship blueprints (catalog)
     *
     * GET /api/ships/catalog
     */
    public function getCatalog(Request $request): JsonResponse
    {
        $query = Ship::where('is_available', true);

        // Optional filters
        if ($request->has('rarity')) {
            $query->where('rarity', $request->rarity);
        }

        if ($request->has('class')) {
            $query->where('class', $request->class);
        }

        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        $ships = $query->orderBy('base_price')->get();

        return $this->success([
            'ships' => ShipResource::collection($ships),
            'total_count' => $ships->count(),
        ]);
    }

    /**
     * Purchase a new ship
     *
     * POST /api/players/{uuid}/ships/purchase
     */
    public function purchaseShip(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $validated = $request->validate([
            'ship_uuid' => 'sometimes|exists:ships,uuid',
            'ship_id' => 'sometimes|exists:ships,id',
            'trading_hub_uuid' => 'required|string',
            'trade_in_current_ship' => 'sometimes|boolean',
        ]);

        // Accept ship_uuid (preferred) or ship_id (BC)
        $ship = isset($validated['ship_uuid'])
            ? Ship::where('uuid', $validated['ship_uuid'])->firstOrFail()
            : Ship::findOrFail($validated['ship_id'] ?? 0);

        $tradingHub = $this->findTradingHub($validated['trading_hub_uuid']);

        if (! $tradingHub) {
            return $this->error('Trading hub not found', 'NOT_FOUND', null, 404);
        }

        // Check if hub has shipyard (check both records and services array)
        $hubServices = $tradingHub->services ?? [];
        if (! $tradingHub->hasShipyard() && ! in_array('shipyard', $hubServices) && ! in_array('ship_sales', $hubServices)) {
            return $this->error('This trading hub does not have a shipyard', 400);
        }

        // Check inventory
        $inventory = $tradingHub->ships()
            ->where('ship_id', $ship->id)
            ->where('quantity', '>', 0)
            ->first();

        if (! $inventory) {
            return $this->error('This ship is not available at this trading hub', 400);
        }

        // Calculate cost with trade-in
        $cost = $inventory->current_price;
        $tradeInValue = 0;
        $tradedInShip = null;

        if (($validated['trade_in_current_ship'] ?? false) && $player->activeShip) {
            $tradedInShip = $player->activeShip;
            $tradeInValue = $this->calculateTradeInValue($tradedInShip);
            $cost -= $tradeInValue;
        }

        // Check credits
        if ($player->credits < $cost) {
            return $this->error('Insufficient credits', 400);
        }

        // Deduct credits
        $player->credits -= $cost;
        $player->save();

        // Delete trade-in ship if applicable
        if ($tradedInShip) {
            // Deactivate all ships first
            PlayerShip::where('player_id', $player->id)->update(['is_active' => false]);
            $tradedInShip->delete();
        } else {
            // Just deactivate current active ship
            PlayerShip::where('player_id', $player->id)->update(['is_active' => false]);
        }

        // Create new ship
        $newShip = PlayerShip::create([
            'uuid' => Str::uuid(),
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_poi_id' => $player->current_poi_id,
            'name' => $ship->name,
            'current_fuel' => $ship->attributes['max_fuel'] ?? 100,
            'max_fuel' => $ship->attributes['max_fuel'] ?? 100,
            'fuel_last_updated_at' => now(),
            'hull' => $ship->hull_strength,
            'max_hull' => $ship->hull_strength,
            'weapons' => $ship->attributes['starting_weapons'] ?? 10,
            'cargo_hold' => $ship->cargo_capacity,
            'sensors' => $ship->attributes['starting_sensors'] ?? 1,
            'warp_drive' => $ship->attributes['starting_warp_drive'] ?? 1,
            'current_cargo' => 0,
            'is_active' => true,
            'status' => 'operational',
        ]);

        // Reduce inventory
        $inventory->decrement('quantity');

        // Reload player with new ship
        $player->refresh();
        $player->load('activeShip.ship');

        return $this->success([
            'ship' => new ShipResource($newShip),
            'cost_paid' => $cost + $tradeInValue,
            'trade_in_value' => $tradeInValue,
            'net_cost' => $cost,
            'remaining_credits' => $player->credits,
        ], 'Ship purchased successfully');
    }

    /**
     * Switch active ship
     *
     * POST /api/players/{uuid}/ships/switch
     */
    public function switchShip(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $validated = $request->validate([
            'ship_uuid' => 'required|exists:player_ships,uuid',
        ]);

        $targetShip = PlayerShip::where('uuid', $validated['ship_uuid'])->firstOrFail();

        // Verify ownership
        if ($targetShip->player_id !== $player->id) {
            return $this->error('You do not own this ship', 'FORBIDDEN', null, 403);
        }

        // Deactivate all ships
        PlayerShip::where('player_id', $player->id)->update(['is_active' => false]);

        // Activate target ship
        $targetShip->is_active = true;
        $targetShip->save();

        // Teleport player to the target ship's location
        $previousLocation = $player->current_poi_id;
        if ($targetShip->current_poi_id) {
            $player->current_poi_id = $targetShip->current_poi_id;
            $player->save();
        }

        // Reload player
        $player->refresh();
        $player->load('activeShip.ship');

        return $this->success([
            'active_ship' => new ShipResource($targetShip),
            'teleported' => $previousLocation !== $player->current_poi_id,
            'location' => $targetShip->currentLocation ? [
                'name' => $targetShip->currentLocation->name,
                'x' => $targetShip->currentLocation->x,
                'y' => $targetShip->currentLocation->y,
            ] : null,
        ], 'Active ship switched successfully');
    }

    /**
     * List all ships owned by player (fleet)
     *
     * GET /api/players/{uuid}/ships/fleet
     */
    public function getFleet(string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();

        $ships = PlayerShip::where('player_id', $player->id)
            ->with(['ship', 'currentLocation'])
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success([
            'fleet' => ShipResource::collection($ships),
            'total_ships' => $ships->count(),
            'active_ship_uuid' => $player->activeShip?->uuid,
        ]);
    }

    /**
     * Calculate trade-in value for a ship (50% of base price)
     */
    private function calculateTradeInValue(PlayerShip $playerShip): float
    {
        $basePrice = $playerShip->ship->base_price;

        // Base trade-in is 50% of original price
        $tradeInValue = $basePrice * 0.5;

        // Apply condition penalty (hull damage reduces value)
        $hullPercentage = $playerShip->max_hull > 0 ? $playerShip->hull / $playerShip->max_hull : 1;
        $conditionMultiplier = max(0.5, $hullPercentage); // At least 50% value even if destroyed

        return round($tradeInValue * $conditionMultiplier, 2);
    }
}

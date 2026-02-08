<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ShipResource;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShipShopController extends BaseApiController
{
    /**
     * Check if trading hub has shipyard and list available ships
     *
     * GET /api/trading-hubs/{uuid}/shipyard
     */
    public function getShipyard(string $uuid): JsonResponse
    {
        $tradingHub = $this->findTradingHub($uuid);

        if (! $tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $hasShipyard = $tradingHub->hasShipyard();

        if (! $hasShipyard) {
            return $this->success([
                'has_shipyard' => false,
                'available_ships' => [],
            ]);
        }

        // Get available ships at this trading hub
        $availableShips = $tradingHub->ships()
            ->with('ship')
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($inventory) {
                return [
                    'ship' => new ShipResource($inventory->ship),
                    'current_price' => $inventory->current_price,
                    'quantity' => $inventory->quantity,
                ];
            });

        return $this->success([
            'has_shipyard' => true,
            'trading_hub_name' => $tradingHub->name,
            'available_ships' => $availableShips,
        ]);
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
            'ship_id' => 'required|exists:ships,id',
            'trading_hub_uuid' => 'required|string',
            'trade_in_current_ship' => 'sometimes|boolean',
        ]);

        $ship = Ship::findOrFail($validated['ship_id']);
        $tradingHub = $this->findTradingHub($validated['trading_hub_uuid']);

        if (! $tradingHub) {
            return $this->error('Trading hub not found', 'NOT_FOUND', null, 404);
        }

        // Check if hub has shipyard
        if (! $tradingHub->hasShipyard()) {
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

        // Reload player
        $player->refresh();
        $player->load('activeShip.ship');

        return $this->success([
            'active_ship' => new ShipResource($targetShip),
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
            ->with('ship')
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MineralResource;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\TradingHubInventory;
use App\Services\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles trading transactions (buy/sell)
 */
class TradingTransactionController extends BaseApiController
{
    public function __construct(
        private TradingService $tradingService
    ) {}

    /**
     * Resolve the ship by UUID, verify ownership, and verify it's at the hub.
     */
    private function resolveShipAtHub(string $shipUuid, Player $player, PointOfInterest $hubPoi): PlayerShip|JsonResponse
    {
        $ship = PlayerShip::where('uuid', $shipUuid)
            ->where('player_id', $player->id)
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        if ($ship->current_poi_id !== $hubPoi->id) {
            return $this->error('Ship is not at this trading hub', 'SHIP_NOT_AT_HUB');
        }

        return $ship;
    }

    /**
     * Resolve a mineral by UUID (preferred) or name (fallback).
     * At least one must be provided.
     */
    private function resolveMineral(array $validated): ?Mineral
    {
        if (! empty($validated['mineral_uuid'])) {
            return Mineral::where('uuid', $validated['mineral_uuid'])->first();
        }

        if (! empty($validated['mineral_name'])) {
            return Mineral::where('name', $validated['mineral_name'])->first();
        }

        return null;
    }

    /**
     * Buy minerals from hub
     *
     * POST /api/trading-hubs/{uuid}/buy
     */
    public function buyMinerals(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'ship_uuid' => ['required', 'string'],
                'mineral_uuid' => ['required_without:mineral_name', 'string'],
                'mineral_name' => ['required_without:mineral_uuid', 'string'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $poi = TradingController::resolveTradingHub($uuid);

        if (! $poi) {
            return $this->notFound('Trading hub not found');
        }

        $ship = $this->resolveShipAtHub($validated['ship_uuid'], $player, $poi);

        if ($ship instanceof JsonResponse) {
            return $ship;
        }

        $mineral = $this->resolveMineral($validated);

        if (! $mineral) {
            return $this->notFound('Mineral not found');
        }

        // Lazy population: stock the hub on first access
        $this->tradingService->ensureInventoryPopulated($poi->tradingHub);

        $inventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->where('mineral_id', $mineral->id)
            ->first();

        if (! $inventory) {
            return $this->notFound('Mineral not available at this hub');
        }

        $result = $this->tradingService->buyMineral(
            $player,
            $ship,
            $inventory,
            $validated['quantity']
        );

        if (! $result['success']) {
            return $this->error($result['message'], 'BUY_FAILED');
        }

        return $this->success([
            'transaction_type' => 'buy',
            'mineral' => new MineralResource($inventory->mineral),
            'quantity' => $validated['quantity'],
            'price_per_unit' => (float) $inventory->sell_price,
            'total_cost' => $result['total_cost'],
            'credits_remaining' => (float) $player->fresh()->credits,
            'cargo_remaining' => $ship->fresh()->current_cargo,
            'xp_earned' => $result['xp_earned'],
        ], $result['message']);
    }

    /**
     * Sell minerals to hub
     *
     * POST /api/trading-hubs/{uuid}/sell
     */
    public function sellMinerals(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'ship_uuid' => ['required', 'string'],
                'mineral_uuid' => ['required_without:mineral_name', 'string'],
                'mineral_name' => ['required_without:mineral_uuid', 'string'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $poi = TradingController::resolveTradingHub($uuid);

        if (! $poi) {
            return $this->notFound('Trading hub not found');
        }

        $ship = $this->resolveShipAtHub($validated['ship_uuid'], $player, $poi);

        if ($ship instanceof JsonResponse) {
            return $ship;
        }

        $mineral = $this->resolveMineral($validated);

        if (! $mineral) {
            return $this->notFound('Mineral not found');
        }

        // Lazy population: stock the hub on first access
        $this->tradingService->ensureInventoryPopulated($poi->tradingHub);

        $cargo = PlayerCargo::where('player_ship_id', $ship->id)
            ->where('mineral_id', $mineral->id)
            ->with('mineral')
            ->first();

        if (! $cargo) {
            return $this->error('You do not have this mineral in cargo', 'NO_CARGO');
        }

        $hubInventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->where('mineral_id', $mineral->id)
            ->first();

        if (! $hubInventory) {
            return $this->notFound('This hub does not trade this mineral');
        }

        $result = $this->tradingService->sellMineral(
            $player,
            $ship,
            $cargo,
            $hubInventory,
            $validated['quantity']
        );

        if (! $result['success']) {
            return $this->error($result['message'], 'SELL_FAILED');
        }

        return $this->success([
            'transaction_type' => 'sell',
            'mineral' => new MineralResource($cargo->mineral),
            'quantity' => $validated['quantity'],
            'price_per_unit' => (float) $hubInventory->buy_price,
            'total_revenue' => $result['total_revenue'],
            'credits_remaining' => (float) $player->fresh()->credits,
            'cargo_remaining' => $ship->fresh()->current_cargo,
            'xp_earned' => $result['xp_earned'],
        ], $result['message']);
    }

    /**
     * Get player cargo manifest
     *
     * GET /api/players/{uuid}/cargo
     */
    public function getCargo(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with('activeShip')
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->activeShip) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP');
        }

        $cargo = PlayerCargo::where('player_ship_id', $player->activeShip->id)
            ->with('mineral')
            ->get();

        return $this->success([
            'ship_uuid' => $player->activeShip->uuid,
            'ship_name' => $player->activeShip->name,
            'current_cargo' => $player->activeShip->current_cargo,
            'cargo_capacity' => $player->activeShip->cargo_hold,
            'available_space' => $player->activeShip->cargo_hold - $player->activeShip->current_cargo,
            'cargo' => $cargo->map(fn ($item) => [
                'mineral' => new MineralResource($item->mineral),
                'quantity' => $item->quantity,
            ]),
        ]);
    }

    /**
     * Calculate max affordable quantity
     *
     * GET /api/trading/affordability?player_uuid=xxx&hub_uuid=xxx&mineral_id=xxx
     */
    public function calculateAffordability(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'hub_uuid' => ['required', 'string'],
                'ship_uuid' => ['required', 'string'],
                'mineral_uuid' => ['required_without:mineral_name', 'string'],
                'mineral_name' => ['required_without:mineral_uuid', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $poi = TradingController::resolveTradingHub($validated['hub_uuid']);

        if (! $poi) {
            return $this->notFound('Trading hub not found');
        }

        $ship = $this->resolveShipAtHub($validated['ship_uuid'], $player, $poi);

        if ($ship instanceof JsonResponse) {
            return $ship;
        }

        $mineral = $this->resolveMineral($validated);

        if (! $mineral) {
            return $this->notFound('Mineral not found');
        }

        // Lazy population: stock the hub on first access
        $this->tradingService->ensureInventoryPopulated($poi->tradingHub);

        $inventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->where('mineral_id', $mineral->id)
            ->first();

        if (! $inventory) {
            return $this->notFound('Mineral not available');
        }

        $maxAffordable = $this->tradingService->getMaxAffordableQuantity($player, $inventory);
        $maxBySpace = $ship->cargo_hold - $ship->current_cargo;
        $maxPurchasable = min($maxAffordable, $maxBySpace);

        return $this->success([
            'max_affordable' => $maxAffordable,
            'max_by_cargo_space' => $maxBySpace,
            'max_purchasable' => $maxPurchasable,
            'price_per_unit' => (float) $inventory->sell_price,
            'total_cost' => $maxPurchasable * $inventory->sell_price,
        ]);
    }
}

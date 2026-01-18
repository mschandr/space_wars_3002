<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MineralResource;
use App\Models\Player;
use App\Models\PlayerCargo;
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
     * Buy minerals from hub
     *
     * POST /api/trading-hubs/{uuid}/buy
     */
    public function buyMinerals(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'mineral_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->with('activeShip')
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->activeShip) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP');
        }

        $poi = PointOfInterest::where('uuid', $uuid)->with('tradingHub')->first();

        if (! $poi || ! $poi->tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $inventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->where('mineral_id', $validated['mineral_id'])
            ->first();

        if (! $inventory) {
            return $this->notFound('Mineral not available at this hub');
        }

        $result = $this->tradingService->buyMineral(
            $player,
            $player->activeShip,
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
            'cargo_remaining' => $player->activeShip->fresh()->current_cargo,
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
                'mineral_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->with('activeShip')
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->activeShip) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP');
        }

        $poi = PointOfInterest::where('uuid', $uuid)->with('tradingHub')->first();

        if (! $poi || ! $poi->tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $cargo = PlayerCargo::where('player_ship_id', $player->activeShip->id)
            ->where('mineral_id', $validated['mineral_id'])
            ->with('mineral')
            ->first();

        if (! $cargo) {
            return $this->error('You do not have this mineral in cargo', 'NO_CARGO');
        }

        $hubInventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->where('mineral_id', $validated['mineral_id'])
            ->first();

        if (! $hubInventory) {
            return $this->notFound('This hub does not trade this mineral');
        }

        $result = $this->tradingService->sellMineral(
            $player,
            $player->activeShip,
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
            'cargo_remaining' => $player->activeShip->fresh()->current_cargo,
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
                'mineral_id' => ['required', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->with('activeShip')
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $poi = PointOfInterest::where('uuid', $validated['hub_uuid'])->with('tradingHub')->first();

        if (! $poi || ! $poi->tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $inventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->where('mineral_id', $validated['mineral_id'])
            ->first();

        if (! $inventory) {
            return $this->notFound('Mineral not available');
        }

        $maxAffordable = $this->tradingService->getMaxAffordableQuantity($player, $inventory);
        $maxBySpace = $player->activeShip->cargo_hold - $player->activeShip->current_cargo;
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

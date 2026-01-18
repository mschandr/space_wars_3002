<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MineralResource;
use App\Http\Resources\TradingHubResource;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles trading hub and mineral information
 */
class TradingController extends BaseApiController
{
    /**
     * List nearby trading hubs
     *
     * GET /api/trading-hubs?player_uuid=xxx
     */
    public function listNearbyHubs(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'radius' => ['sometimes', 'numeric', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->with(['currentLocation', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $currentLocation = $player->currentLocation;
        $radius = $validated['radius'] ?? ($player->activeShip->sensors ?? 1) * 100;

        $hubs = TradingHub::query()
            ->where('is_active', true)
            ->whereHas('pointOfInterest', function ($query) use ($currentLocation, $radius) {
                $query->where('galaxy_id', $currentLocation->galaxy_id)
                    ->whereRaw("SQRT(POW(x - ?, 2) + POW(y - ?, 2)) <= ?", [
                        $currentLocation->x,
                        $currentLocation->y,
                        $radius,
                    ]);
            })
            ->with('pointOfInterest')
            ->get();

        return $this->success([
            'hubs' => TradingHubResource::collection($hubs),
            'search_radius' => $radius,
        ]);
    }

    /**
     * Get trading hub details
     *
     * GET /api/trading-hubs/{uuid}
     */
    public function getHubDetails(string $uuid): JsonResponse
    {
        $poi = PointOfInterest::where('uuid', $uuid)->with('tradingHub')->first();

        if (! $poi || ! $poi->tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        return $this->success(new TradingHubResource($poi->tradingHub));
    }

    /**
     * Get hub inventory and prices
     *
     * GET /api/trading-hubs/{uuid}/inventory
     */
    public function getHubInventory(string $uuid): JsonResponse
    {
        $poi = PointOfInterest::where('uuid', $uuid)->with('tradingHub')->first();

        if (! $poi || ! $poi->tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $inventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->with('mineral')
            ->get();

        return $this->success([
            'hub' => new TradingHubResource($poi->tradingHub),
            'inventory' => $inventory->map(fn ($item) => [
                'mineral' => new MineralResource($item->mineral),
                'quantity' => $item->quantity,
                'buy_price' => (float) $item->buy_price,
                'sell_price' => (float) $item->sell_price,
            ]),
        ]);
    }

    /**
     * List all minerals
     *
     * GET /api/minerals
     */
    public function listMinerals(): JsonResponse
    {
        $minerals = Mineral::all();

        return $this->success(MineralResource::collection($minerals));
    }
}


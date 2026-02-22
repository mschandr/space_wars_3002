<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MineralResource;
use App\Http\Resources\TradingHubResource;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerPriceSighting;
use App\Models\PlayerTradeTransaction;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Handles trading hub and mineral information
 */
class TradingController extends BaseApiController
{
    public function __construct(
        private readonly TradingService $tradingService
    ) {}

    /**
     * Resolve a trading hub from either a POI UUID or TradingHub UUID.
     *
     * The frontend may send either identifier, so we check both.
     */
    public static function resolveTradingHub(string $uuid): ?PointOfInterest
    {
        // Try as POI UUID first
        $poi = PointOfInterest::where('uuid', $uuid)->with('tradingHub')->first();

        if ($poi && $poi->tradingHub) {
            return $poi;
        }

        // Fallback: try as TradingHub UUID
        $hub = TradingHub::where('uuid', $uuid)->with('pointOfInterest')->first();

        if ($hub && $hub->pointOfInterest) {
            return $hub->pointOfInterest->load('tradingHub');
        }

        return null;
    }

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

        // Use bounding box pre-filter to reduce spatial calculations
        // This allows the database to use indexes before doing expensive math
        $x = $currentLocation->x;
        $y = $currentLocation->y;

        $hubs = TradingHub::query()
            ->where('is_active', true)
            ->whereHas('pointOfInterest', function ($query) use ($currentLocation, $radius, $x, $y) {
                $query->where('galaxy_id', $currentLocation->galaxy_id)
                    // Bounding box filter (can use indexes)
                    ->where('x', '>=', $x - $radius)
                    ->where('x', '<=', $x + $radius)
                    ->where('y', '>=', $y - $radius)
                    ->where('y', '<=', $y + $radius)
                    // Precise circular distance (on remaining candidates)
                    ->whereRaw('SQRT(POW(x - ?, 2) + POW(y - ?, 2)) <= ?', [$x, $y, $radius]);
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
        $poi = self::resolveTradingHub($uuid);

        if (! $poi) {
            return $this->notFound('Trading hub not found');
        }

        return $this->success(new TradingHubResource($poi->tradingHub));
    }

    /**
     * Get hub inventory and prices
     *
     * GET /api/trading-hubs/{uuid}/inventory?player_uuid=xxx
     */
    public function getHubInventory(Request $request, string $uuid): JsonResponse
    {
        $poi = self::resolveTradingHub($uuid);

        if (! $poi) {
            return $this->notFound('Trading hub not found');
        }

        // Lazy population: stock the hub on first access
        $this->tradingService->ensureInventoryPopulated($poi->tradingHub);

        $inventory = TradingHubInventory::where('trading_hub_id', $poi->tradingHub->id)
            ->with('mineral')
            ->get();

        // Record price sightings if player_uuid provided
        $playerUuid = $request->query('player_uuid');
        if ($playerUuid && $request->user()) {
            $player = Player::where('uuid', $playerUuid)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($player) {
                $this->tradingService->recordPriceSightings($player, $poi->tradingHub, $inventory);
            }
        }

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
     *
     * Minerals are static game data, so we cache them for 1 hour.
     */
    public function listMinerals(): JsonResponse
    {
        $minerals = Cache::remember('minerals:all', 3600, function () {
            return Mineral::all();
        });

        return $this->success(MineralResource::collection($minerals));
    }

    /**
     * Get player's price history (sightings)
     *
     * GET /api/players/{playerUuid}/price-history?mineral_id=X&hub_uuid=Y&days=30
     */
    public function getPriceHistory(Request $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $days = (int) $request->query('days', 30);

        $query = PlayerPriceSighting::where('player_id', $player->id)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->with(['mineral', 'tradingHub']);

        if ($mineralId = $request->query('mineral_id')) {
            $query->where('mineral_id', $mineralId);
        }

        if ($hubUuid = $request->query('hub_uuid')) {
            $hub = TradingHub::where('uuid', $hubUuid)->first();
            if ($hub) {
                $query->where('trading_hub_id', $hub->id);
            }
        }

        $sightings = $query->orderBy('recorded_at', 'asc')->get();

        return $this->success([
            'sightings' => $sightings->map(fn (PlayerPriceSighting $s) => [
                'mineral' => new MineralResource($s->mineral),
                'hub_uuid' => $s->tradingHub->uuid,
                'hub_name' => $s->tradingHub->name,
                'buy_price' => (float) $s->buy_price,
                'sell_price' => (float) $s->sell_price,
                'quantity' => $s->quantity,
                'recorded_at' => $s->recorded_at->toIso8601String(),
            ]),
            'days' => $days,
        ]);
    }

    /**
     * Get player's trade log (transactions)
     *
     * GET /api/players/{playerUuid}/trade-log?mineral_id=X&hub_uuid=Y&days=30&type=buy|sell
     */
    public function getTradeLog(Request $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $days = (int) $request->query('days', 30);

        $query = PlayerTradeTransaction::where('player_id', $player->id)
            ->where('transacted_at', '>=', now()->subDays($days))
            ->with(['mineral', 'tradingHub']);

        if ($mineralId = $request->query('mineral_id')) {
            $query->where('mineral_id', $mineralId);
        }

        if ($hubUuid = $request->query('hub_uuid')) {
            $hub = TradingHub::where('uuid', $hubUuid)->first();
            if ($hub) {
                $query->where('trading_hub_id', $hub->id);
            }
        }

        if ($type = $request->query('type')) {
            $query->where('transaction_type', $type);
        }

        $transactions = $query->orderByDesc('transacted_at')->get();

        return $this->success([
            'transactions' => $transactions->map(fn (PlayerTradeTransaction $t) => [
                'uuid' => $t->uuid,
                'mineral' => new MineralResource($t->mineral),
                'hub_uuid' => $t->tradingHub->uuid,
                'hub_name' => $t->tradingHub->name,
                'transaction_type' => $t->transaction_type,
                'quantity' => $t->quantity,
                'unit_price' => (float) $t->unit_price,
                'total_amount' => (float) $t->total_amount,
                'credits_after' => (float) $t->credits_after,
                'transacted_at' => $t->transacted_at->toIso8601String(),
            ]),
            'days' => $days,
        ]);
    }
}

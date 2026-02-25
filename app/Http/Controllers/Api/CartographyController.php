<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\StellarCartographer;
use App\Services\StarChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartographyController extends BaseApiController
{
    public function __construct(
        private readonly StarChartService $starChartService
    ) {}

    /**
     * Get player's revealed star charts
     *
     * GET /api/players/{uuid}/star-charts
     */
    public function getPlayerCharts(string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();

        $charts = $player->starCharts()
            ->orderByPivot('purchased_at', 'desc')
            ->get();

        $revealedSystems = $charts->map(function ($poi) use ($player) {
            $systemInfo = $this->starChartService->getSystemInfo($poi, $player);

            return array_merge($systemInfo ?? [], [
                'uuid' => $poi->uuid,
                'purchased_at' => $poi->pivot->purchased_at,
                'price_paid' => $poi->pivot->price_paid,
            ]);
        });

        return $this->success([
            'revealed_systems' => $revealedSystems,
            'total_charts' => $charts->count(),
        ]);
    }

    /**
     * Check if trading hub has stellar cartographer
     *
     * GET /api/trading-hubs/{uuid}/cartographer
     */
    public function getCartographer(string $uuid): JsonResponse
    {
        $tradingHub = $this->findTradingHub($uuid);

        if (! $tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $tradingHub->load('pointOfInterest');

        $cartographer = StellarCartographer::where('poi_id', $tradingHub->poi_id)
            ->with('pointOfInterest')
            ->first();

        if (! $cartographer) {
            return $this->success([
                'has_cartographer' => false,
            ]);
        }

        return $this->success([
            'has_cartographer' => true,
            'cartographer' => [
                'shop_name' => $cartographer->name,
                'markup_multiplier' => $cartographer->markup_multiplier,
                'location' => [
                    'uuid' => $tradingHub->pointOfInterest->uuid,
                    'name' => $tradingHub->pointOfInterest->name,
                    'x' => $tradingHub->pointOfInterest->x,
                    'y' => $tradingHub->pointOfInterest->y,
                ],
            ],
        ]);
    }

    /**
     * Preview chart coverage before purchase
     *
     * GET /api/star-charts/preview
     */
    public function previewCoverage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_uuid' => 'required|exists:players,uuid',
            'center_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'center_poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'cartographer_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'cartographer_poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
        ]);

        $player = Player::where('uuid', $validated['player_uuid'])->firstOrFail();
        $centerPoiUuid = $validated['center_uuid'] ?? $validated['center_poi_uuid'] ?? null;
        if (! $centerPoiUuid) {
            return $this->validationError(['center_uuid' => 'A center system UUID is required']);
        }
        $centerPoi = PointOfInterest::where('uuid', $centerPoiUuid)->firstOrFail();

        $cartographer = null;
        $cartographerPoiUuid = $validated['cartographer_uuid'] ?? $validated['cartographer_poi_uuid'] ?? null;
        if ($cartographerPoiUuid) {
            $cartographerPoi = PointOfInterest::where('uuid', $cartographerPoiUuid)->firstOrFail();
            $cartographer = StellarCartographer::where('poi_id', $cartographerPoi->id)->first();
        }

        // Get coverage
        $coverage = $this->starChartService->getChartCoverage($centerPoi);

        // Count known vs unknown
        $knownSystems = $coverage->filter(fn ($poi) => $player->hasChartFor($poi));
        $unknownSystems = $coverage->filter(fn ($poi) => ! $player->hasChartFor($poi));

        // Calculate price
        $price = $this->starChartService->calculateChartPrice($centerPoi, $player, $cartographer);

        $coverageData = $coverage->map(function ($poi) use ($player) {
            return [
                'uuid' => $poi->uuid,
                'name' => $poi->name,
                'x' => $poi->x,
                'y' => $poi->y,
                'is_inhabited' => $poi->is_inhabited,
                'already_known' => $player->hasChartFor($poi),
            ];
        });

        return $this->success([
            'center' => [
                'uuid' => $centerPoi->uuid,
                'name' => $centerPoi->name,
            ],
            'coverage' => $coverageData,
            'total_systems' => $coverage->count(),
            'known_systems' => $knownSystems->count(),
            'unknown_systems' => $unknownSystems->count(),
            'price' => $price,
            'can_afford' => $player->credits >= $price,
        ]);
    }

    /**
     * Get dynamic pricing for chart coverage
     *
     * GET /api/star-charts/pricing
     */
    public function getPricing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_uuid' => 'required|exists:players,uuid',
            'center_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'center_poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'cartographer_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'cartographer_poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
        ]);

        $player = Player::where('uuid', $validated['player_uuid'])->firstOrFail();
        $centerPoiUuid = $validated['center_uuid'] ?? $validated['center_poi_uuid'] ?? null;
        if (! $centerPoiUuid) {
            return $this->validationError(['center_uuid' => 'A center system UUID is required']);
        }
        $centerPoi = PointOfInterest::where('uuid', $centerPoiUuid)->firstOrFail();

        $cartographer = null;
        $cartographerPoiUuid = $validated['cartographer_uuid'] ?? $validated['cartographer_poi_uuid'] ?? null;
        if ($cartographerPoiUuid) {
            $cartographerPoi = PointOfInterest::where('uuid', $cartographerPoiUuid)->firstOrFail();
            $cartographer = StellarCartographer::where('poi_id', $cartographerPoi->id)->first();
        }

        $coverage = $this->starChartService->getChartCoverage($centerPoi);
        $unknownCount = $coverage->filter(fn ($poi) => ! $player->hasChartFor($poi))->count();

        $price = $this->starChartService->calculateChartPrice($centerPoi, $player, $cartographer);

        // Get config values for transparency
        $basePrice = config('game_config.star_charts.base_price', 1000);
        $multiplier = config('game_config.star_charts.unknown_multiplier', 1.5);

        return $this->success([
            'price' => $price,
            'unknown_systems_count' => $unknownCount,
            'base_price' => $basePrice,
            'multiplier' => $multiplier,
            'markup' => $cartographer?->markup_multiplier ?? 1.0,
            'formula' => 'base_price × (multiplier ^ (unknown_count - 1)) × markup',
            'can_afford' => $player->credits >= $price,
            'player_credits' => $player->credits,
        ]);
    }

    /**
     * Purchase a star chart
     *
     * POST /api/players/{uuid}/star-charts/purchase
     */
    public function purchaseChart(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $validated = $request->validate([
            'cartographer_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'cartographer_poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
        ]);

        $cartographerPoiUuid = $validated['cartographer_uuid'] ?? $validated['cartographer_poi_uuid'] ?? null;
        if (! $cartographerPoiUuid) {
            return $this->validationError(['cartographer_uuid' => 'A cartographer UUID is required']);
        }
        $cartographerPoi = PointOfInterest::where('uuid', $cartographerPoiUuid)->firstOrFail();
        $cartographer = StellarCartographer::where('poi_id', $cartographerPoi->id)->firstOrFail();

        if (! $cartographer) {
            return $this->error('No stellar cartographer found at this location', 'NOT_FOUND', null, 404);
        }

        // Purchase chart centered on cartographer's location
        $result = $this->starChartService->purchaseChart($player, $cartographer, $cartographerPoi);

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        // Reload player
        $player->refresh();

        return $this->success([
            'systems_revealed' => $result['systems_revealed'],
            'total_systems' => $result['total_systems'],
            'price_paid' => $result['price_paid'],
            'credits_remaining' => $result['credits_remaining'],
        ], $result['message']);
    }

    /**
     * Get detailed system information (if player has chart)
     *
     * GET /api/star-charts/system/{poiUuid}
     */
    public function getSystemInfo(Request $request, string $poiUuid): JsonResponse
    {
        $validated = $request->validate([
            'player_uuid' => 'required|exists:players,uuid',
        ]);

        $player = Player::where('uuid', $validated['player_uuid'])->firstOrFail();
        $poi = PointOfInterest::where('uuid', $poiUuid)->firstOrFail();

        $systemInfo = $this->starChartService->getSystemInfo($poi, $player);

        if (! $systemInfo) {
            return $this->error('You do not have a star chart for this system', 'FORBIDDEN', null, 403);
        }

        return $this->success([
            'system' => $systemInfo,
            'uuid' => $poi->uuid,
        ]);
    }
}

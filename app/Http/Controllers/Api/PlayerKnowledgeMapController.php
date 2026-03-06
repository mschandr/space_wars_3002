<?php

namespace App\Http\Controllers\Api;

use App\Enums\Exploration\KnowledgeLevel;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use App\Services\LaneKnowledgeService;
use App\Services\PlayerKnowledgeService;
use App\Support\SensorRangeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerKnowledgeMapController extends BaseApiController
{
    public function __construct(
        private readonly PlayerKnowledgeService $knowledgeService,
        private readonly LaneKnowledgeService $laneKnowledgeService,
    ) {}

    /**
     * GET /api/players/{playerUuid}/knowledge-map
     *
     * Returns ONLY what the player knows — everything else is fog.
     * Optional query: ?sector_uuid=xxx
     */
    public function index(Request $request, string $playerUuid): JsonResponse
    {
        // Validate and authorize player
        $player = $this->validatePlayer($playerUuid, $request);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        $currentLocation = $player->currentLocation;

        // Load all required data
        $sectorId = $this->resolveSectorId($request);
        $knowledgeMap = $this->knowledgeService->getKnowledgeMap($player, $sectorId);
        $pois = $this->loadPoiData(array_keys($knowledgeMap));
        $knownLanes = $this->laneKnowledgeService->getKnownLanesInGalaxy($player, $currentLocation->galaxy_id);
        $scanLevels = $this->loadScanLevels($player, array_keys($knowledgeMap));

        // Build response components
        $sensorLevel = $player->activeShip?->sensors ?? 1;
        $sensorRange = SensorRangeCalculator::getRangeLY($sensorLevel);
        $laneConnectedPoiIds = $this->getLaneConnectedPoiIds($currentLocation, $knownLanes, $player->activeShip);

        $knownSystems = [];
        $statistics = ['total_known' => 0, 'by_level' => [], 'known_lanes' => 0, 'pirate_warnings' => 0];

        // Build system entries
        foreach ($knowledgeMap as $poiId => $entry) {
            $poi = $pois->get($poiId);
            if (! $poi) {
                continue;
            }

            $system = $this->buildSystemEntry($poi, $entry, $laneConnectedPoiIds, $scanLevels, $sensorLevel);
            $knownSystems[] = $system;
            $statistics['total_known']++;
            $statistics['by_level'][$entry['knowledge_level']] = ($statistics['by_level'][$entry['knowledge_level']] ?? 0) + 1;
            if (isset($system['pirate_warning'])) {
                $statistics['pirate_warnings']++;
            }
        }

        // Build lanes response
        $knownLanesResponse = $this->buildLanesResponse($currentLocation, $knownLanes, $player->activeShip);
        $statistics['known_lanes'] = count($knownLanesResponse);

        // Build danger zones
        $dangerZones = $this->buildDangerZones($knownSystems);

        return $this->success($this->buildFinalResponse($player, $currentLocation, $sensorLevel, $sensorRange, $knownSystems, $knownLanesResponse, $dangerZones, $statistics));
    }

    private function validatePlayer(string $playerUuid, Request $request): Player|JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->with(['currentLocation.galaxy', 'currentLocation.sector', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $user = $request->user();
        if ($user && $player->user_id !== $user->id) {
            return $this->forbidden();
        }

        if (! $player->currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION');
        }

        return $player;
    }

    private function resolveSectorId(Request $request): ?int
    {
        if (! $request->has('sector_uuid')) {
            return null;
        }

        return \App\Models\Sector::where('uuid', $request->query('sector_uuid'))->first()?->id;
    }

    private function loadPoiData(array $poiIds): \Illuminate\Support\Collection
    {
        return PointOfInterest::whereIn('id', $poiIds)
            ->with(['tradingHub', 'sector'])
            ->get()
            ->keyBy('id');
    }

    private function loadScanLevels(Player $player, array $poiIds): array
    {
        if (empty($poiIds)) {
            return [];
        }

        return $player->systemScans()
            ->whereIn('poi_id', $poiIds)
            ->pluck('scan_level', 'poi_id')
            ->toArray();
    }

    private function buildSystemEntry(PointOfInterest $poi, array $entry, array $laneConnectedPoiIds, array $scanLevels, int $sensorLevel): array
    {
        $level = $entry['knowledge_level'];
        $isLaneConnected = in_array($poi->id, $laneConnectedPoiIds);
        $attrs = $poi->attributes ?? [];

        $system = [
            'uuid' => $poi->uuid,
            'x' => (float) $poi->x,
            'y' => (float) $poi->y,
            'knowledge_level' => $level,
            'knowledge_label' => KnowledgeLevel::from($level)->label(),
            'freshness' => $entry['freshness'],
            'source' => $entry['source'],
            'star' => $this->buildStarInfo($poi, $attrs['stellar_class'] ?? null, $level),
        ];

        if ($level >= KnowledgeLevel::BASIC->value || $isLaneConnected) {
            $system['name'] = $poi->name;
            $system['is_inhabited'] = $poi->is_inhabited;
            $system['planet_count'] = $poi->children()->count();
        }

        if ($level >= KnowledgeLevel::SURVEYED->value && $poi->is_inhabited) {
            $system['services'] = $this->buildServices($poi, $entry);
        }

        if ($entry['has_pirate_warning']) {
            $system['pirate_warning'] = [
                'active' => true,
                'danger_radius_ly' => config('game_config.knowledge.pirate_danger_radius_ly', 5),
                'confidence' => $this->getPirateConfidence($sensorLevel),
            ];
        }

        if (isset($scanLevels[$poi->id])) {
            $system['scan_level'] = $scanLevels[$poi->id];
            $system['has_scan_data'] = true;
        }

        return $system;
    }

    private function buildServices(PointOfInterest $poi, array $entry): array
    {
        $servicesData = $entry['services_data'];
        if ($servicesData) {
            return $servicesData;
        }

        $tradingHub = $poi->tradingHub;
        return [
            'trading_hub' => $tradingHub !== null && $tradingHub->is_active,
            'shipyard' => $tradingHub?->has_shipyard ?? false,
            'salvage_yard' => $tradingHub?->has_salvage_yard ?? false,
            'cartographer' => $tradingHub?->stellarCartographer !== null,
        ];
    }

    private function buildLanesResponse(PointOfInterest $currentLocation, $knownLanes, ?PlayerShip $activeShip): array
    {
        $lanesResponse = [];

        // Build known lanes
        foreach ($knownLanes as $laneKnowledge) {
            $gate = $laneKnowledge->warpGate;
            if (! $gate) {
                continue;
            }

            $lanesResponse[] = $this->formatLaneResponse($gate, [
                'has_pirate' => $laneKnowledge->pirate_risk_known,
                'pirate_freshness' => $laneKnowledge->last_pirate_check
                    ? max(0.1, 1.0 - ($laneKnowledge->last_pirate_check->diffInHours(now()) / 168))
                    : null,
                'discovery_method' => $laneKnowledge->discovery_method,
            ]);
        }

        // Add current location gates
        $knownGateUuids = collect($lanesResponse)->pluck('gate_uuid')->toArray();
        $currentLocationGates = WarpGate::where(function ($q) use ($currentLocation) {
            $q->where('source_poi_id', $currentLocation->id)
                ->orWhere('destination_poi_id', $currentLocation->id);
        })
            ->where('status', 'active')
            ->whereNotIn('uuid', $knownGateUuids)
            ->with(['sourcePoi', 'destinationPoi'])
            ->get();

        foreach ($currentLocationGates as $gate) {
            if ($activeShip && ! $gate->canPlayerDetect($activeShip)) {
                continue;
            }

            $lanesResponse[] = $this->formatLaneResponse($gate, [
                'has_pirate' => false,
                'pirate_freshness' => null,
                'discovery_method' => 'current_location',
            ]);
        }

        return $lanesResponse;
    }

    private function buildDangerZones(array $knownSystems): array
    {
        $dangerZones = [];
        foreach ($knownSystems as $system) {
            if (! isset($system['pirate_warning']) || ! $system['pirate_warning']['active']) {
                continue;
            }

            $dangerZones[] = [
                'center' => ['x' => $system['x'], 'y' => $system['y']],
                'radius_ly' => $system['pirate_warning']['danger_radius_ly'],
                'source' => 'pirate_warning',
                'confidence' => $system['pirate_warning']['confidence'],
            ];
        }

        return $dangerZones;
    }

    private function buildFinalResponse(Player $player, PointOfInterest $currentLocation, int $sensorLevel, float $sensorRange, array $knownSystems, array $knownLanesResponse, array $dangerZones, array $statistics): array
    {
        return [
            'galaxy' => [
                'uuid' => $currentLocation->galaxy->uuid,
                'name' => $currentLocation->galaxy->name,
                'width' => $currentLocation->galaxy->width,
                'height' => $currentLocation->galaxy->height,
            ],
            'player' => [
                'uuid' => $player->uuid,
                'x' => (float) $currentLocation->x,
                'y' => (float) $currentLocation->y,
                'system_uuid' => $currentLocation->uuid,
                'sector_uuid' => $currentLocation->sector?->uuid,
                'sensor_range_ly' => $sensorRange,
                'sensor_level' => $sensorLevel,
            ],
            'known_systems' => $knownSystems,
            'known_lanes' => $knownLanesResponse,
            'danger_zones' => $dangerZones,
            'statistics' => $statistics,
        ];
    }

    /**
     * Build star classification data based on knowledge level.
     *
     * BASIC (2): stellar class, description, type label
     * SURVEYED (3+): adds temperature, luminosity, goldilocks zone
     */
    private function buildStarInfo(PointOfInterest $poi, ?string $stellarClass, int $level): array
    {
        $attrs = $poi->attributes ?? [];
        $star = [
            'type' => $poi->type?->label(),
        ];

        if ($stellarClass) {
            $classification = \App\Enums\PointsOfInterest\StellarClassification::tryFrom($stellarClass);
            $star['stellar_class'] = $stellarClass;
            $star['stellar_description'] = $classification?->label() ?? "{$stellarClass}-class star";

            if ($classification) {
                [$tempMin, $tempMax] = $classification->temperatureRange();
                $star['temperature_range_k'] = ['min' => $tempMin, 'max' => $tempMax];
            }
        }

        // SURVEYED+ (level 3): precise temperature, luminosity, goldilocks zone
        if ($level >= KnowledgeLevel::SURVEYED->value) {
            if (isset($attrs['temperature'])) {
                $star['temperature_k'] = $attrs['temperature'];
            }
            if (isset($attrs['luminosity'])) {
                $star['luminosity'] = $attrs['luminosity'];
            }
            if (isset($attrs['goldilocks_zone'])) {
                $star['goldilocks_zone'] = $attrs['goldilocks_zone'];
            }
        }

        return $star;
    }

    private function getPirateConfidence(int $sensorLevel): string
    {
        return match (true) {
            $sensorLevel >= 5 => 'High',
            $sensorLevel >= 3 => 'Medium',
            default => 'Low',
        };
    }

    /**
     * Format a warp gate into a lane response with system names and coordinates.
     */
    private function formatLaneResponse(WarpGate $gate, array $extra): array
    {
        return array_merge([
            'gate_uuid' => $gate->uuid,
            'from_uuid' => $gate->sourcePoi?->uuid,
            'from_name' => $gate->sourcePoi?->name,
            'to_uuid' => $gate->destinationPoi?->uuid,
            'to_name' => $gate->destinationPoi?->name,
            'from' => [
                'x' => (float) ($gate->source_x ?? $gate->sourcePoi?->x),
                'y' => (float) ($gate->source_y ?? $gate->sourcePoi?->y),
            ],
            'to' => [
                'x' => (float) ($gate->dest_x ?? $gate->destinationPoi?->x),
                'y' => (float) ($gate->dest_y ?? $gate->destinationPoi?->y),
            ],
            'distance' => round($gate->distance ?? $gate->calculateDistance(), 2),
        ], $extra);
    }

    /**
     * Get POIs connected to a given POI via warp gates (bidirectional).
     *
     * Uses a UNION query to handle bidirectional gates efficiently:
     * gates where the POI is the source OR the destination.
     */
    public function getWarpGateConnectedPois(PointOfInterest $poi): \Illuminate\Support\Collection
    {
        return PointOfInterest::query()
            ->joinSub(
                \Illuminate\Support\Facades\DB::query()
                    ->select('source_poi_id as from_id', 'destination_poi_id as to_id')
                    ->from('warp_gates')
                    ->where('source_poi_id', $poi->id)
                    ->unionAll(
                        \Illuminate\Support\Facades\DB::query()
                            ->select('destination_poi_id as from_id', 'source_poi_id as to_id')
                            ->from('warp_gates')
                            ->where('destination_poi_id', $poi->id)
                    ),
                'wp',
                'points_of_interest.id',
                '=',
                'wp.to_id'
            )
            ->get();
    }

    /**
     * Get POI IDs connected to the player's current location via known warp lanes.
     *
     * Systems visible through warp gates get their name revealed — the gate's
     * navigation display would show the destination system name.
     */
    private function getLaneConnectedPoiIds(PointOfInterest $currentLocation, $knownLanes, ?PlayerShip $ship = null): array
    {
        $connectedIds = [];

        // From persisted lane knowledge
        foreach ($knownLanes as $laneKnowledge) {
            $gate = $laneKnowledge->warpGate;
            if (! $gate) {
                continue;
            }

            if ($gate->source_poi_id === $currentLocation->id) {
                $connectedIds[] = $gate->destination_poi_id;
            } elseif ($gate->destination_poi_id === $currentLocation->id) {
                $connectedIds[] = $gate->source_poi_id;
            }
        }

        // Also include gates at current location that may not be in lane knowledge yet
        // Only gates the player's sensors can detect
        $currentGates = WarpGate::where(function ($q) use ($currentLocation) {
            $q->where('source_poi_id', $currentLocation->id)
                ->orWhere('destination_poi_id', $currentLocation->id);
        })
            ->where('status', 'active')
            ->get();

        foreach ($currentGates as $gate) {
            // Skip gates the player's sensors can't detect
            if ($ship && ! $gate->canPlayerDetect($ship)) {
                continue;
            }

            if ($gate->source_poi_id === $currentLocation->id) {
                $connectedIds[] = $gate->destination_poi_id;
            } else {
                $connectedIds[] = $gate->source_poi_id;
            }
        }

        return array_unique($connectedIds);
    }
}

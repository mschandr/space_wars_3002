<?php

namespace App\Http\Controllers\Api;

use App\Enums\Defense\SystemDefenseType;
use App\Enums\OrbitalStructureType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Controllers\Api\Builders\PoiCategorizer;
use App\Http\Resources\PointOfInterestResource;
use App\Models\ColonyBuilding;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\TravelService;
use App\Support\SensorRangeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends BaseApiController
{
    public function __construct(
        private TravelService $travelService
    ) {}

    /**
     * Get current location details
     *
     * GET /api/players/{uuid}/location
     */
    public function getLocation(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['currentLocation.galaxy', 'currentLocation.tradingHub', 'currentLocation.outgoingGates'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $location = $player->currentLocation;

        if (! $location) {
            return $this->error('Player has no current location', 'NO_LOCATION');
        }

        // Get warp gate count for this location
        $warpGateCount = $location->outgoingGates()
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->count();

        // Check if there's a trading hub
        $hasTradingHub = $location->tradingHub !== null;
        $tradingHubInfo = null;

        if ($hasTradingHub) {
            $tradingHubInfo = [
                'uuid' => $location->tradingHub->uuid,
                'name' => $location->tradingHub->name,
                'type' => $location->tradingHub->type,
                'has_salvage_yard' => $location->tradingHub->has_salvage_yard,
                'services' => $location->tradingHub->services,
            ];
        }

        return $this->success([
            'location' => new PointOfInterestResource($location),
            'galaxy' => [
                'uuid' => $location->galaxy->uuid,
                'name' => $location->galaxy->name,
            ],
            'warp_gates_available' => $warpGateCount,
            'trading_hub' => $tradingHubInfo,
            'is_inhabited' => $location->is_inhabited,
        ]);
    }

    /**
     * Get systems within sensor range
     *
     * GET /api/players/{uuid}/nearby-systems
     */
    public function getNearbySystems(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['currentLocation', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION');
        }

        if (! $player->activeShip) {
            return $this->error('Player has no active ship', 'NO_ACTIVE_SHIP');
        }

        $currentLocation = $player->currentLocation;
        $ship = $player->activeShip;
        $sensorLevel = $ship->sensors;
        $sensorRange = SensorRangeCalculator::getRangeLY($sensorLevel);

        // Find all POIs within sensor range
        // Use bounding box pre-filter to reduce spatial calculations
        $x = $currentLocation->x;
        $y = $currentLocation->y;

        $nearbySystems = PointOfInterest::where('galaxy_id', $currentLocation->galaxy_id)
            ->where('id', '!=', $currentLocation->id)
            ->where('type', PointOfInterestType::STAR)
            // Bounding box filter (can use indexes)
            ->where('x', '>=', $x - $sensorRange)
            ->where('x', '<=', $x + $sensorRange)
            ->where('y', '>=', $y - $sensorRange)
            ->where('y', '<=', $y + $sensorRange)
            ->selectRaw('*, SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) as distance', [$x, $y])
            // Precise circular distance (on remaining candidates)
            ->whereRaw('SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) <= ?', [$x, $y, $sensorRange])
            ->orderBy('distance')
            ->limit(50)
            ->get();

        // Pre-load outgoing warp gates for fuel cost calculations (single query)
        $outgoingGates = $currentLocation->outgoingGates()
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->get()
            ->keyBy('destination_poi_id');

        $maxJumpRange = $this->travelService->getMaxJumpDistance($ship);
        $currentFuel = $ship->current_fuel;

        // Pre-load all charted POI IDs once (prevents N+1 queries)
        $chartedPoiIds = $player->getChartedPoiIds();

        $systems = $nearbySystems->map(function ($system) use ($chartedPoiIds, $ship, $outgoingGates, $maxJumpRange, $currentFuel) {
            // Check if player has a star chart for this system (in-memory lookup)
            $hasChart = in_array($system->id, $chartedPoiIds, true);
            $typeLabel = is_object($system->type) ? $system->type->label() : $system->type;
            $distance = (float) $system->distance;

            // Warp gate option (instantaneous travel, lower fuel cost)
            $warpGateOption = null;
            $gate = $outgoingGates->get($system->id);
            if ($gate) {
                $gateDistance = $gate->distance ?? $distance;
                $gateFuelCost = $this->travelService->calculateFuelCost($gateDistance, $ship);
                $warpGateOption = [
                    'gate_uuid' => $gate->uuid,
                    'fuel_cost' => $gateFuelCost,
                    'can_afford' => $currentFuel >= $gateFuelCost,
                ];
            }

            // Direct jump option (4x fuel penalty, range-limited by warp drive)
            $directJumpFuelCost = $this->travelService->calculateDirectJumpFuelCost($distance, $ship);
            $inRange = $distance <= $maxJumpRange;
            $directJumpOption = [
                'fuel_cost' => $directJumpFuelCost,
                'in_range' => $inRange,
                'can_afford' => $inRange && $currentFuel >= $directJumpFuelCost,
            ];

            // Determine cheapest reachable option
            $cheapestCost = null;
            $cheapestOption = null;

            if ($warpGateOption && $currentFuel >= $warpGateOption['fuel_cost']) {
                $cheapestCost = $warpGateOption['fuel_cost'];
                $cheapestOption = 'warp_gate';
            }

            if ($inRange && $currentFuel >= $directJumpFuelCost) {
                if ($cheapestCost === null || $directJumpFuelCost < $cheapestCost) {
                    $cheapestCost = $directJumpFuelCost;
                    $cheapestOption = 'direct_jump';
                }
            }

            return [
                'uuid' => $system->uuid,
                'name' => $hasChart ? $system->name : 'Unknown System',
                'type' => $typeLabel,
                'distance' => round($distance, 2),
                'x' => $hasChart ? (float) $system->x : null,
                'y' => $hasChart ? (float) $system->y : null,
                'is_inhabited' => $system->is_inhabited,
                'has_chart' => $hasChart,
                'travel' => [
                    'warp_gate' => $warpGateOption,
                    'direct_jump' => $directJumpOption,
                    'cheapest_option' => $cheapestOption,
                    'cheapest_fuel_cost' => $cheapestCost,
                    'can_reach' => $cheapestOption !== null,
                ],
            ];
        });

        return $this->success([
            'current_location' => [
                'uuid' => $currentLocation->uuid,
                'name' => $currentLocation->name,
                'x' => (float) $currentLocation->x,
                'y' => (float) $currentLocation->y,
            ],
            'sensor_range' => $sensorRange,
            'sensor_level' => $sensorLevel,
            'ship' => [
                'current_fuel' => $currentFuel,
                'max_fuel' => $ship->max_fuel,
                'warp_drive' => $ship->warp_drive,
                'max_jump_range' => $maxJumpRange,
            ],
            'systems_detected' => $systems->count(),
            'nearby_systems' => $systems,
        ]);
    }

    /**
     * Get detailed scan of all nearby POIs (not just stars)
     *
     * GET /api/players/{uuid}/scan-local
     */
    public function scanLocal(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['currentLocation', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION');
        }

        if (! $player->activeShip) {
            return $this->error('Player has no active ship', 'NO_ACTIVE_SHIP');
        }

        $currentLocation = $player->currentLocation;
        $sensorLevel = $player->activeShip->sensors;
        $sensorRange = SensorRangeCalculator::getRangeLY($sensorLevel);

        // Find all POIs within sensor range (including planets, asteroids, nebulae, etc.)
        // Use bounding box pre-filter to reduce spatial calculations
        $x = $currentLocation->x;
        $y = $currentLocation->y;

        $nearbyPOIs = PointOfInterest::where('galaxy_id', $currentLocation->galaxy_id)
            ->where('id', '!=', $currentLocation->id)
            // Bounding box filter (can use indexes)
            ->where('x', '>=', $x - $sensorRange)
            ->where('x', '<=', $x + $sensorRange)
            ->where('y', '>=', $y - $sensorRange)
            ->where('y', '<=', $y + $sensorRange)
            ->selectRaw('*, SQRT(POW(x - ?, 2) + POW(y - ?, 2)) as distance', [$x, $y])
            // Precise circular distance (on remaining candidates)
            ->whereRaw('SQRT(POW(x - ?, 2) + POW(y - ?, 2)) <= ?', [$x, $y, $sensorRange])
            ->orderBy('distance')
            ->limit(100)
            ->get();

        // Pre-load all charted POI IDs once (prevents N+1 queries)
        $chartedPoiIds = $player->getChartedPoiIds();

        // Group by type for easier navigation
        $groupedByType = $nearbyPOIs->groupBy('type')->map(function ($group, $type) use ($chartedPoiIds) {
            return $group->map(function ($poi) use ($chartedPoiIds) {
                // In-memory lookup instead of database query
                $hasChart = in_array($poi->id, $chartedPoiIds, true);

                $typeLabel = is_object($poi->type) ? $poi->type->label() : $poi->type;

                return [
                    'uuid' => $poi->uuid,
                    'name' => $hasChart ? $poi->name : "Unknown {$typeLabel}",
                    'type' => $typeLabel,
                    'distance' => round($poi->distance, 2),
                    'x' => $hasChart ? (float) $poi->x : null,
                    'y' => $hasChart ? (float) $poi->y : null,
                    'is_inhabited' => $poi->is_inhabited ?? false,
                    'has_chart' => $hasChart,
                    'parent_poi' => $poi->parent_poi_id ? [
                        'id' => $poi->parent_poi_id,
                    ] : null,
                ];
            })->values();
        });

        return $this->success([
            'current_location' => [
                'uuid' => $currentLocation->uuid,
                'name' => $currentLocation->name,
                'type' => $currentLocation->type,
                'x' => (float) $currentLocation->x,
                'y' => (float) $currentLocation->y,
            ],
            'sensor_range' => $sensorRange,
            'sensor_level' => $sensorLevel,
            'total_pois_detected' => $nearbyPOIs->count(),
            'pois_by_type' => $groupedByType,
        ]);
    }

    /**
     * Get local bodies (planets, moons, asteroids) at the current location.
     *
     * GET /api/players/{uuid}/local-bodies
     *
     * Returns all orbital bodies within the current star system.
     */
    public function getLocalBodies(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->with(['currentLocation.sector', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $currentLocation = $player->currentLocation;

        if (! $currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION', null, 400);
        }

        $sensorLevel = $player->activeShip?->sensors ?? 1;

        // Get all children with eager-loaded orbital and defense data
        $children = $currentLocation->children()
            ->with([
                'orbitalStructures' => fn ($q) => $q->where('status', '!=', 'destroyed'),
                'orbitalStructures.player:id,uuid,call_sign',
                'systemDefenses' => fn ($q) => $q->active(),
                'colony.buildings',
                'colony.player:id,uuid,call_sign',
                'children' => fn ($q) => $q->where('type', PointOfInterestType::MOON),
                'children.orbitalStructures' => fn ($q) => $q->where('status', '!=', 'destroyed'),
                'children.orbitalStructures.player:id,uuid,call_sign',
                'children.systemDefenses' => fn ($q) => $q->active(),
                'children.colony.buildings',
                'children.colony.player:id,uuid,call_sign',
            ])
            ->orderBy('orbital_index')
            ->get();

        // Categorize bodies using PoiCategorizer
        $bodies = [
            'planets' => [],
            'moons' => [],
            'asteroid_belts' => [],
            'stations' => [],
            'defense_platforms' => [],
            'other' => [],
        ];

        foreach ($children as $child) {
            $bodyData = [
                'uuid' => $child->uuid,
                'name' => $child->name,
                'type' => $child->type?->value,
                'type_label' => $child->type?->label(),
                'orbital_index' => $child->orbital_index,
                'is_inhabited' => $child->is_inhabited ?? false,
                'has_colony' => $child->colony !== null,
                'attributes' => $this->getVisibleAttributes($child),
                'orbital_presence' => $this->getOrbitalPresence($child),
                'defensive_capability' => $this->getDefensiveCapability($child, $sensorLevel),
            ];

            // Check for moons of this body (already eager-loaded, filtered to MOON type)
            $moons = $child->children;

            if ($moons && $moons->isNotEmpty()) {
                $bodyData['moons'] = $moons->map(fn ($moon) => [
                    'uuid' => $moon->uuid,
                    'name' => $moon->name,
                    'is_inhabited' => $moon->is_inhabited ?? false,
                    'has_colony' => $moon->colony !== null,
                    'orbital_presence' => $this->getOrbitalPresence($moon),
                    'defensive_capability' => $this->getDefensiveCapability($moon, $sensorLevel),
                ])->toArray();
            }

            // Categorize by type using PoiCategorizer
            $category = PoiCategorizer::categorize($child->type);

            // Map derelicts to stations for this response (simplified view)
            if ($category === PoiCategorizer::CATEGORY_DERELICTS) {
                $category = 'stations';
            } elseif ($category === PoiCategorizer::CATEGORY_ANOMALIES) {
                $category = 'other';
            }

            $bodies[$category][] = $bodyData;
        }

        // Get sector info
        $sector = $currentLocation->sector;

        return $this->success([
            'system' => [
                'uuid' => $currentLocation->uuid,
                'name' => $currentLocation->name,
                'type' => $currentLocation->type?->value,
                'x' => (float) $currentLocation->x,
                'y' => (float) $currentLocation->y,
                'is_inhabited' => $currentLocation->is_inhabited,
            ],
            'sector' => $sector ? [
                'uuid' => $sector->uuid,
                'name' => $sector->name,
                'grid' => ['x' => $sector->grid_x, 'y' => $sector->grid_y],
            ] : null,
            'bodies' => $bodies,
            'summary' => [
                'total_bodies' => $children->count(),
                'planets' => count($bodies['planets']),
                'moons' => count($bodies['moons']),
                'asteroid_belts' => count($bodies['asteroid_belts']),
                'stations' => count($bodies['stations']),
            ],
        ]);
    }

    /**
     * Get visible attributes for a body based on common properties.
     */
    private function getVisibleAttributes(PointOfInterest $body): array
    {
        $attrs = $body->attributes ?? [];
        $visible = [];

        // Basic properties that are always visible
        if (isset($attrs['habitable'])) {
            $visible['habitable'] = $attrs['habitable'];
        }
        if (isset($attrs['in_goldilocks_zone'])) {
            $visible['in_goldilocks_zone'] = $attrs['in_goldilocks_zone'];
        }
        if (isset($attrs['temperature'])) {
            $visible['temperature'] = $attrs['temperature'];
        }
        if (isset($attrs['atmosphere'])) {
            $visible['atmosphere'] = $attrs['atmosphere'];
        }
        if (isset($attrs['has_rings'])) {
            $visible['has_rings'] = $attrs['has_rings'];
        }
        if (isset($attrs['mineral_richness'])) {
            $visible['mineral_richness'] = $attrs['mineral_richness'];
        }

        return $visible;
    }

    /**
     * Build always-visible orbital presence data for a body.
     */
    private function getOrbitalPresence(PointOfInterest $body): array
    {
        $structures = $body->orbitalStructures->map(fn ($s) => [
            'type' => $s->structure_type->value,
            'name' => $s->name,
            'level' => $s->level,
            'status' => $s->status,
            'owner' => $s->player ? [
                'uuid' => $s->player->uuid,
                'call_sign' => $s->player->call_sign,
            ] : null,
        ])->toArray();

        $systemDefenses = $body->systemDefenses->map(fn ($d) => [
            'type' => $d->defense_type->value,
            'quantity' => $d->quantity,
            'level' => $d->level,
        ])->toArray();

        return [
            'structures' => $structures,
            'system_defenses' => $systemDefenses,
        ];
    }

    /**
     * Calculate detailed defensive capability for a body (sensor-gated).
     *
     * Returns null if sensor level is below the advanced threshold.
     */
    private function getDefensiveCapability(PointOfInterest $body, int $sensorLevel): ?array
    {
        $advancedSensorThreshold = 5;
        if ($sensorLevel < $advancedSensorThreshold) {
            return null;
        }

        // Player-built orbital defense platforms
        $orbitalDefenseDamage = $body->orbitalStructures
            ->sum(fn ($s) => $s->calculateDamage());

        // System defenses (pre-built)
        $systemDefenseDamage = 0;
        $fighterDamage = 0;
        $planetaryShieldHp = 0;

        foreach ($body->systemDefenses as $defense) {
            if ($defense->defense_type === SystemDefenseType::PLANETARY_SHIELD) {
                $planetaryShieldHp += $defense->health;
            } elseif ($defense->defense_type === SystemDefenseType::FIGHTER_PORT) {
                $fighterDamage += $defense->calculateDamage() + $defense->calculateFighterDamage();
            } else {
                $systemDefenseDamage += $defense->calculateDamage();
            }
        }

        // Magnetic mines (count only — damage is per-detonation, not per-round)
        $magneticMineCount = $body->orbitalStructures
            ->where('structure_type', OrbitalStructureType::MAGNETIC_MINE)
            ->count();

        // Colony defenses
        $colonyGarrison = 0;
        $colonyDefenseBuildings = 0;
        $colony = $body->colony;

        if ($colony) {
            // Garrison damage mirrors ColonyCombatService formula
            $garrisonUnits = (int) floor(($colony->garrison_strength ?? 0) / 50);
            $colonyGarrison = $garrisonUnits * (15 + ($colony->development_level ?? 0) * 3);

            // Colony defense buildings (orbital_defense type)
            if ($colony->relationLoaded('buildings') && $colony->buildings) {
                $colonyDefenseBuildings = $colony->buildings
                    ->where('building_type', 'orbital_defense')
                    ->where('status', 'operational')
                    ->sum(fn ($b) => ColonyBuilding::getBuildingEffects($b->building_type, $b->level)['defense_rating'] ?? 0);
            }
        }

        $totalDamage = $orbitalDefenseDamage + $systemDefenseDamage + $fighterDamage + $colonyGarrison + $colonyDefenseBuildings;

        return [
            'orbital_defense_platforms' => $orbitalDefenseDamage,
            'system_defenses' => $systemDefenseDamage,
            'fighter_squadrons' => $fighterDamage,
            'colony_garrison' => $colonyGarrison,
            'colony_defense_buildings' => $colonyDefenseBuildings,
            'magnetic_mines' => $magneticMineCount,
            'planetary_shield_hp' => $planetaryShieldHp,
            'total_damage_per_round' => $totalDamage,
            'threat_level' => $this->getThreatLevel($totalDamage),
        ];
    }

    /**
     * Map total damage per round to a human-readable threat label.
     */
    private function getThreatLevel(int $totalDamage): string
    {
        return match (true) {
            $totalDamage === 0 => 'none',
            $totalDamage <= 50 => 'minimal',
            $totalDamage <= 150 => 'moderate',
            $totalDamage <= 400 => 'heavy',
            default => 'fortress',
        };
    }
}

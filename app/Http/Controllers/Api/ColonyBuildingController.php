<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ColonyBuildingResource;
use App\Models\Colony;
use App\Models\ColonyBuilding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColonyBuildingController extends BaseApiController
{
    /**
     * List colony buildings
     *
     * GET /api/colonies/{uuid}/buildings
     */
    public function listBuildings(string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();

        $buildings = $colony->buildings()
            ->orderBy('building_type')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success([
            'buildings' => ColonyBuildingResource::collection($buildings),
            'total_count' => $buildings->count(),
            'max_buildings' => $colony->development_level * 2,
            'can_build_more' => $buildings->count() < ($colony->development_level * 2),
        ]);
    }

    /**
     * Construct a new building
     *
     * POST /api/colonies/{uuid}/buildings
     */
    public function constructBuilding(Request $request, string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($colony->player, $request->user());

        $validated = $request->validate([
            'building_type' => 'required|in:hydroponics,mining_facility,trade_station,shipyard,orbital_mining,warp_gate,defense_station,research_lab',
        ]);

        // Check if colony can build
        if (! $colony->canBuildBuilding($validated['building_type'])) {
            return $this->error('Colony cannot build more buildings at current development level', 'BUILDING_LIMIT', null, 400);
        }

        // Get building costs and effects
        $buildingConfig = $this->getBuildingConfig($validated['building_type']);

        // Check if player has resources
        $player = $colony->player;
        if ($player->credits < $buildingConfig['cost']['credits']) {
            return $this->error('Insufficient credits', 'INSUFFICIENT_CREDITS', null, 400);
        }

        if ($colony->mineral_storage < $buildingConfig['cost']['minerals']) {
            return $this->error('Insufficient minerals in colony storage', 'INSUFFICIENT_MINERALS', null, 400);
        }

        // Deduct costs
        $player->deductCredits($buildingConfig['cost']['credits']);

        $colony->mineral_storage -= $buildingConfig['cost']['minerals'];
        $colony->save();

        // Create building
        $building = ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => $validated['building_type'],
            'level' => 1,
            'status' => 'operational',
            'effects' => $buildingConfig['effects'],
        ]);

        // Recalculate colony production
        $colony->calculateProduction();

        return $this->success([
            'building' => new ColonyBuildingResource($building),
            'cost_paid' => $buildingConfig['cost'],
            'remaining_credits' => $player->credits,
        ], 'Building constructed successfully', 201);
    }

    /**
     * Upgrade or repair a building
     *
     * PUT /api/colonies/{uuid}/buildings/{buildingUuid}
     */
    public function upgradeBuilding(Request $request, string $uuid, string $buildingUuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($colony->player, $request->user());

        $building = ColonyBuilding::where('uuid', $buildingUuid)
            ->where('colony_id', $colony->id)
            ->firstOrFail();

        $validated = $request->validate([
            'action' => 'required|in:upgrade,repair',
        ]);

        if ($validated['action'] === 'upgrade') {
            return $this->handleUpgrade($colony, $building);
        }

        return $this->handleRepair($colony, $building);
    }

    /**
     * Demolish a building
     *
     * DELETE /api/colonies/{uuid}/buildings/{buildingUuid}
     */
    public function demolishBuilding(Request $request, string $uuid, string $buildingUuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($colony->player, $request->user());

        $building = ColonyBuilding::where('uuid', $buildingUuid)
            ->where('colony_id', $colony->id)
            ->firstOrFail();

        $buildingName = $building->name;
        $building->delete();

        // Recalculate colony production
        $colony->calculateProduction();

        return $this->success([
            'message' => "Building '{$buildingName}' has been demolished",
        ]);
    }

    /**
     * Handle building upgrade
     */
    private function handleUpgrade(Colony $colony, ColonyBuilding $building): JsonResponse
    {
        if ($building->level >= 10) {
            return $this->error('Building is already at maximum level', 'MAX_LEVEL', null, 400);
        }

        // Calculate upgrade cost
        $creditCost = 5000 * $building->level;
        $mineralCost = 500 * $building->level;

        $player = $colony->player;

        if ($player->credits < $creditCost) {
            return $this->error('Insufficient credits', 'INSUFFICIENT_CREDITS', null, 400);
        }

        if ($colony->mineral_storage < $mineralCost) {
            return $this->error('Insufficient minerals in colony storage', 'INSUFFICIENT_MINERALS', null, 400);
        }

        // Deduct costs
        $player->deductCredits($creditCost);

        $colony->mineral_storage -= $mineralCost;
        $colony->save();

        // Upgrade building
        $building->level++;
        $building->save();

        // Recalculate colony production
        $colony->calculateProduction();

        return $this->success([
            'building' => new ColonyBuildingResource($building),
            'cost_paid' => [
                'credits' => $creditCost,
                'minerals' => $mineralCost,
            ],
            'new_level' => $building->level,
            'remaining_credits' => $player->credits,
        ], 'Building upgraded successfully');
    }

    /**
     * Handle building repair
     */
    private function handleRepair(Colony $colony, ColonyBuilding $building): JsonResponse
    {
        if ($building->status === 'operational') {
            return $this->error('Building is already operational', 'ALREADY_OPERATIONAL', null, 400);
        }

        // Calculate repair cost
        $creditCost = 2000;
        $mineralCost = 200;

        $player = $colony->player;

        if ($player->credits < $creditCost) {
            return $this->error('Insufficient credits', 'INSUFFICIENT_CREDITS', null, 400);
        }

        if ($colony->mineral_storage < $mineralCost) {
            return $this->error('Insufficient minerals in colony storage', 'INSUFFICIENT_MINERALS', null, 400);
        }

        // Deduct costs
        $player->deductCredits($creditCost);

        $colony->mineral_storage -= $mineralCost;
        $colony->save();

        // Repair building
        $building->status = 'operational';
        $building->save();

        // Recalculate colony production
        $colony->calculateProduction();

        return $this->success([
            'building' => new ColonyBuildingResource($building),
            'cost_paid' => [
                'credits' => $creditCost,
                'minerals' => $mineralCost,
            ],
            'remaining_credits' => $player->credits,
        ], 'Building repaired successfully');
    }

    /**
     * Get building configuration
     */
    private function getBuildingConfig(string $buildingType): array
    {
        $configs = [
            'hydroponics' => [
                'default_name' => 'Hydroponics Bay',
                'cost' => ['credits' => 5000, 'minerals' => 500],
                'effects' => ['food_production' => 50],
                'upkeep' => ['quantium' => 1, 'credits' => 100],
            ],
            'mining_facility' => [
                'default_name' => 'Mining Facility',
                'cost' => ['credits' => 8000, 'minerals' => 800],
                'effects' => ['mineral_production' => 100],
                'upkeep' => ['quantium' => 2, 'credits' => 150],
            ],
            'trade_station' => [
                'default_name' => 'Trade Station',
                'cost' => ['credits' => 10000, 'minerals' => 1000],
                'effects' => ['credits_per_cycle' => 500],
                'upkeep' => ['quantium' => 1, 'food' => 10],
            ],
            'shipyard' => [
                'default_name' => 'Shipyard',
                'cost' => ['credits' => 50000, 'minerals' => 5000],
                'effects' => ['ship_production' => true],
                'upkeep' => ['quantium' => 5, 'credits' => 1000],
            ],
            'orbital_mining' => [
                'default_name' => 'Orbital Mining Station',
                'cost' => ['credits' => 15000, 'minerals' => 1500],
                'effects' => ['mineral_production' => 200],
                'upkeep' => ['quantium' => 3, 'credits' => 200],
            ],
            'warp_gate' => [
                'default_name' => 'Warp Gate',
                'cost' => ['credits' => 100000, 'minerals' => 10000],
                'effects' => ['warp_capability' => true],
                'upkeep' => ['quantium' => 10, 'credits' => 500],
            ],
            'defense_station' => [
                'default_name' => 'Defense Station',
                'cost' => ['credits' => 25000, 'minerals' => 2500],
                'effects' => ['defense_rating' => 100],
                'upkeep' => ['quantium' => 4, 'credits' => 300],
            ],
            'research_lab' => [
                'default_name' => 'Research Laboratory',
                'cost' => ['credits' => 30000, 'minerals' => 3000],
                'effects' => ['research_points' => 10],
                'upkeep' => ['quantium' => 3, 'credits' => 500],
            ],
        ];

        return $configs[$buildingType] ?? [
            'default_name' => ucfirst(str_replace('_', ' ', $buildingType)),
            'cost' => ['credits' => 5000, 'minerals' => 500],
            'effects' => [],
            'upkeep' => [],
        ];
    }
}

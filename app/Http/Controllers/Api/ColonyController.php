<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Resources\ColonyResource;
use App\Models\Colony;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColonyController extends BaseApiController
{
    /**
     * List player's colonies
     *
     * GET /api/players/{uuid}/colonies
     */
    public function listColonies(string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();

        $colonies = $player->colonies()
            ->with(['poi', 'buildings'])
            ->orderBy('established_at', 'desc')
            ->get();

        return $this->success([
            'colonies' => ColonyResource::collection($colonies),
            'total_count' => $colonies->count(),
        ]);
    }

    /**
     * Establish a new colony
     *
     * POST /api/players/{uuid}/colonies
     */
    public function establishColony(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $validated = $request->validate([
            'uuid' => 'sometimes|exists:points_of_interest,uuid',
            'poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'name' => 'required|string|max:100',
        ]);

        $poiUuid = $validated['uuid'] ?? $validated['poi_uuid'] ?? null;
        if (! $poiUuid) {
            return $this->validationError(['uuid' => 'A system UUID is required']);
        }
        $poi = PointOfInterest::where('uuid', $poiUuid)->firstOrFail();

        // Validate POI is suitable for colonization
        if (! in_array($poi->type, [PointOfInterestType::PLANET, PointOfInterestType::MOON])) {
            return $this->error('Only planets and moons can be colonized', 'INVALID_POI', null, 400);
        }

        // Check if POI already has a colony
        if (Colony::where('poi_id', $poi->id)->exists()) {
            return $this->error('This location already has a colony', 'ALREADY_COLONIZED', null, 400);
        }

        // Check if player is at this location
        if ($player->current_poi_id !== $poi->id) {
            return $this->error('Your ship must be at the target location', 'NOT_AT_LOCATION', null, 400);
        }

        // Create colony
        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => $validated['name'],
            'population' => 100, // Starting population
            'population_growth_rate' => 0.02, // 2% growth rate
            'max_population' => 1000,
            'food_production' => 10,
            'food_storage' => 100,
            'mineral_production' => 5,
            'mineral_storage' => 50,
            'quantium_storage' => 0,
            'credits_per_cycle' => 10,
            'development_level' => 1,
            'habitability_rating' => $poi->habitability_score ?? 0.5,
            'status' => 'establishing',
        ]);

        return $this->success([
            'colony' => new ColonyResource($colony->load(['poi', 'buildings'])),
        ], 'Colony established successfully', 201);
    }

    /**
     * Get colony details
     *
     * GET /api/colonies/{uuid}
     */
    public function getColony(string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)
            ->with(['poi', 'buildings', 'player'])
            ->firstOrFail();

        return $this->success([
            'colony' => new ColonyResource($colony),
        ]);
    }

    /**
     * Update colony
     *
     * PUT /api/colonies/{uuid}
     */
    public function updateColony(Request $request, string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($colony->player, $request->user());

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:establishing,growing,established,threatened',
        ]);

        $colony->update($validated);

        return $this->success([
            'colony' => new ColonyResource($colony->load(['poi', 'buildings'])),
        ], 'Colony updated successfully');
    }

    /**
     * Abandon colony
     *
     * DELETE /api/colonies/{uuid}
     */
    public function abandonColony(Request $request, string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($colony->player, $request->user());

        $colonyName = $colony->name;
        $colony->delete();

        return $this->success([
            'message' => "Colony '{$colonyName}' has been abandoned",
        ]);
    }

    /**
     * Get production summary
     *
     * GET /api/colonies/{uuid}/production
     */
    public function getProduction(string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)
            ->with('buildings')
            ->firstOrFail();

        // Recalculate production
        $colony->calculateProduction();

        return $this->success([
            'food_production' => $colony->food_production,
            'food_storage' => $colony->food_storage,
            'mineral_production' => $colony->mineral_production,
            'mineral_storage' => $colony->mineral_storage,
            'quantium_storage' => $colony->quantium_storage,
            'credits_per_cycle' => $colony->credits_per_cycle,
            'population' => $colony->population,
            'max_population' => $colony->max_population,
            'population_growth_rate' => $colony->population_growth_rate,
        ]);
    }

    /**
     * Upgrade development level
     *
     * POST /api/colonies/{uuid}/upgrade
     */
    public function upgradeDevelopment(Request $request, string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($colony->player, $request->user());

        if ($colony->development_level >= 10) {
            return $this->error('Colony is already at maximum development level', 'MAX_LEVEL', null, 400);
        }

        // Calculate costs based on current level
        $creditCost = 10000 * $colony->development_level;
        $mineralCost = 1000 * $colony->development_level;

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
        $colony->development_level++;
        $colony->max_population += 1000;
        $colony->save();

        return $this->success([
            'colony' => new ColonyResource($colony->load(['poi', 'buildings'])),
            'cost_paid' => [
                'credits' => $creditCost,
                'minerals' => $mineralCost,
            ],
            'new_development_level' => $colony->development_level,
            'remaining_credits' => $player->credits,
        ], 'Colony development upgraded successfully');
    }

    /**
     * Get ship production queue
     *
     * GET /api/colonies/{uuid}/ship-production
     */
    public function getShipProduction(string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();

        if (! $colony->hasShipyard()) {
            return $this->success([
                'has_shipyard' => false,
                'queue' => [],
            ]);
        }

        $queue = $colony->getProductionQueue();

        return $this->success([
            'has_shipyard' => true,
            'queue' => $queue->map(function ($production) {
                return [
                    'uuid' => $production->uuid,
                    'ship_name' => $production->ship->name ?? 'Unknown',
                    'status' => $production->status,
                    'progress_percent' => $production->progress_percent,
                    'estimated_completion' => $production->estimated_completion,
                    'queue_position' => $production->queue_position,
                ];
            }),
            'queue_count' => $queue->count(),
        ]);
    }
}

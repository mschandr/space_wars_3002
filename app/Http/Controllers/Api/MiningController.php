<?php

namespace App\Http\Controllers\Api;

use App\Models\Colony;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Services\MiningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiningController extends BaseApiController
{
    public function __construct(
        private readonly MiningService $miningService
    ) {}

    /**
     * Get mining opportunities at a POI
     *
     * GET /api/poi/{uuid}/mining-opportunities
     */
    public function getMiningOpportunities(string $uuid): JsonResponse
    {
        $poi = PointOfInterest::where('uuid', $uuid)->firstOrFail();

        // Get available minerals
        $minerals = $this->miningService->getAvailableMinerals($poi);

        if (empty($minerals)) {
            return $this->success([
                'has_deposits' => false,
                'minerals' => [],
                'poi_type' => $poi->type,
                'planet_class' => $poi->planet_class,
            ]);
        }

        $mineralData = collect($minerals)->map(function ($deposit) {
            return [
                'mineral_id' => $deposit['mineral']->id,
                'mineral_name' => $deposit['mineral']->name,
                'deposit_size' => $deposit['deposit_size'],
                'richness' => $deposit['richness'],
                'rarity' => $deposit['mineral']->rarity,
            ];
        });

        return $this->success([
            'has_deposits' => true,
            'minerals' => $mineralData,
            'poi_name' => $poi->name,
            'poi_type' => $poi->type,
            'planet_class' => $poi->planet_class,
        ]);
    }

    /**
     * Start automated mining operation
     *
     * POST /api/colonies/{uuid}/mining/start
     */
    public function startAutomatedMining(Request $request, string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($colony->player, $request->user());

        $validated = $request->validate([
            'uuid' => 'sometimes|exists:points_of_interest,uuid',
            'poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'mineral_id' => 'required|exists:minerals,id',
        ]);

        $poiUuid = $validated['uuid'] ?? $validated['poi_uuid'] ?? null;
        if (! $poiUuid) {
            return $this->validationError(['uuid' => 'A system UUID is required']);
        }
        $poi = PointOfInterest::where('uuid', $poiUuid)->firstOrFail();
        $mineral = \App\Models\Mineral::findOrFail($validated['mineral_id']);

        // Check if colony has orbital mining
        if (! $this->miningService->hasOrbitalMining($colony)) {
            return $this->error('Colony does not have an operational orbital mining facility', 'NO_MINING_FACILITY', null, 400);
        }

        // Start automated mining
        $result = $this->miningService->startAutomatedMining($colony, $poi, $mineral);

        if (! $result['success']) {
            return $this->error($result['message'], 'MINING_FAILED', null, 400);
        }

        return $this->success([
            'mineral_name' => $result['mineral'],
            'production_per_cycle' => $result['production_per_cycle'],
            'sensor_efficiency' => $result['sensor_efficiency'],
        ], 'Automated mining operation started');
    }

    /**
     * Extract resources manually with ship
     *
     * POST /api/ships/{uuid}/mining/extract
     */
    public function extractResources(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($ship->player, $request->user());

        $validated = $request->validate([
            'uuid' => 'sometimes|exists:points_of_interest,uuid',
            'poi_uuid' => 'sometimes|exists:points_of_interest,uuid',
            'mineral_id' => 'required|exists:minerals,id',
        ]);

        $poiUuid = $validated['uuid'] ?? $validated['poi_uuid'] ?? null;
        if (! $poiUuid) {
            return $this->validationError(['uuid' => 'A system UUID is required']);
        }
        $poi = PointOfInterest::where('uuid', $poiUuid)->firstOrFail();
        $mineral = \App\Models\Mineral::findOrFail($validated['mineral_id']);

        // Check if ship is at this location
        $player = $ship->player;
        if ($player->current_poi_id !== $poi->id) {
            return $this->error('Your ship must be at the target location', 'NOT_AT_LOCATION', null, 400);
        }

        // Check if POI has this mineral
        if (! $this->miningService->canMineFromPOI($poi, $mineral)) {
            return $this->error("Cannot mine {$mineral->name} from this location", 'MINERAL_NOT_AVAILABLE', null, 400);
        }

        // Get mineral deposit info
        $deposits = $poi->mineral_deposits ?? [];
        $depositInfo = $deposits[$mineral->name] ?? null;

        if (! $depositInfo) {
            return $this->error('No deposits of this mineral found', 'NO_DEPOSIT', null, 400);
        }

        $depositSize = $depositInfo['size'] ?? 1000;

        // Extract using sensors
        $efficiency = $this->miningService->calculateSensorEfficiency($ship->sensors);
        $extractedAmount = (int) ($depositSize * min($efficiency, 1.0)); // Cap at 100% for manual extraction

        // Check cargo space
        $availableSpace = $ship->getEffectiveCargoHold() - $ship->current_cargo;
        if ($extractedAmount > $availableSpace) {
            $extractedAmount = $availableSpace;
            if ($extractedAmount === 0) {
                return $this->error('No cargo space available', 'CARGO_FULL', null, 400);
            }
        }

        // Add to ship's cargo
        $existingCargo = \App\Models\PlayerCargo::where('player_ship_id', $ship->id)
            ->where('mineral_id', $mineral->id)
            ->first();

        if ($existingCargo) {
            $existingCargo->quantity += $extractedAmount;
            $existingCargo->save();
        } else {
            \App\Models\PlayerCargo::create([
                'player_ship_id' => $ship->id,
                'mineral_id' => $mineral->id,
                'quantity' => $extractedAmount,
            ]);
        }

        // Update ship cargo
        $ship->current_cargo += $extractedAmount;
        $ship->save();

        // Update POI deposit (reduce size)
        if (isset($poi->mineral_deposits[$mineral->name])) {
            $deposits = $poi->mineral_deposits;
            $deposits[$mineral->name]['size'] = max(0, $depositInfo['size'] - $extractedAmount);
            $poi->mineral_deposits = $deposits;
            $poi->save();
        }

        return $this->success([
            'mineral_name' => $mineral->name,
            'amount_extracted' => $extractedAmount,
            'efficiency_percent' => round($efficiency * 100, 1),
            'sensor_level' => $ship->sensors,
            'cargo_used' => $ship->current_cargo,
            'cargo_remaining' => $ship->getEffectiveCargoHold() - $ship->current_cargo,
            'deposit_remaining' => $poi->mineral_deposits[$mineral->name]['size'] ?? 0,
        ], 'Resources extracted successfully');
    }
}

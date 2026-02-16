<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrbitalStructureResource;
use App\Models\OrbitalStructure;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\OrbitalStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrbitalStructureController extends BaseApiController
{
    public function __construct(
        private readonly OrbitalStructureService $service
    ) {}

    /**
     * List all orbital structures at a body.
     * GET /api/poi/{uuid}/orbital-structures
     */
    public function listAtBody(string $uuid): JsonResponse
    {
        $poi = PointOfInterest::where('uuid', $uuid)->first();

        if (! $poi) {
            return $this->notFound('Point of interest not found');
        }

        $structures = $this->service->getStructuresAtBody($poi);

        return $this->success(
            OrbitalStructureResource::collection($structures->load('player')),
            'Orbital structures retrieved'
        );
    }

    /**
     * List all orbital structures owned by a player.
     * GET /api/players/{uuid}/orbital-structures
     */
    public function listPlayerStructures(Request $request, string $uuid): JsonResponse
    {
        $result = $this->findAuthenticatedPlayerOrFail($uuid, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $structures = $this->service->getPlayerStructures($result);

        return $this->success(
            OrbitalStructureResource::collection($structures->load('poi')),
            'Player orbital structures retrieved'
        );
    }

    /**
     * Build a new orbital structure.
     * POST /api/players/{uuid}/orbital-structures/build
     */
    public function build(Request $request, string $uuid): JsonResponse
    {
        $result = $this->findAuthenticatedPlayerOrFail($uuid, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $player = $result;

        $poiUuid = $request->input('poi_uuid');
        $structureType = $request->input('type');

        if (! $poiUuid || ! $structureType) {
            return $this->validationError(['poi_uuid' => ['Required'], 'type' => ['Required']]);
        }

        $poi = PointOfInterest::where('uuid', $poiUuid)->first();
        if (! $poi) {
            return $this->notFound('Point of interest not found');
        }

        $buildResult = $this->service->buildStructure($player, $poi, $structureType);

        if (! $buildResult['success']) {
            return $this->error($buildResult['message'], 'BUILD_FAILED');
        }

        return $this->success(
            new OrbitalStructureResource($buildResult['structure']->load('poi', 'player')),
            $buildResult['message'],
            201
        );
    }

    /**
     * Show a single orbital structure.
     * GET /api/orbital-structures/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $structure = OrbitalStructure::where('uuid', $uuid)->with('poi', 'player')->first();

        if (! $structure) {
            return $this->notFound('Orbital structure not found');
        }

        return $this->success(
            new OrbitalStructureResource($structure),
            'Orbital structure retrieved'
        );
    }

    /**
     * Upgrade an orbital structure.
     * PUT /api/orbital-structures/{uuid}/upgrade
     */
    public function upgrade(string $uuid): JsonResponse
    {
        $structure = OrbitalStructure::where('uuid', $uuid)->first();

        if (! $structure) {
            return $this->notFound('Orbital structure not found');
        }

        $player = $this->resolveStructureOwner($structure);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        $result = $this->service->upgradeStructure($player, $structure);

        if (! $result['success']) {
            return $this->error($result['message'], 'UPGRADE_FAILED');
        }

        return $this->success(
            new OrbitalStructureResource($structure->fresh()->load('poi', 'player')),
            $result['message']
        );
    }

    /**
     * Demolish an orbital structure.
     * DELETE /api/orbital-structures/{uuid}
     */
    public function demolish(string $uuid): JsonResponse
    {
        $structure = OrbitalStructure::where('uuid', $uuid)->first();

        if (! $structure) {
            return $this->notFound('Orbital structure not found');
        }

        $player = $this->resolveStructureOwner($structure);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        $result = $this->service->demolishStructure($player, $structure);

        if (! $result['success']) {
            return $this->error($result['message'], 'DEMOLISH_FAILED');
        }

        return $this->success(null, $result['message']);
    }

    /**
     * Collect resources from a mining platform.
     * POST /api/orbital-structures/{uuid}/collect
     */
    public function collect(string $uuid): JsonResponse
    {
        $structure = OrbitalStructure::where('uuid', $uuid)->first();

        if (! $structure) {
            return $this->notFound('Orbital structure not found');
        }

        $player = $this->resolveStructureOwner($structure);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        $result = $this->service->processMiningExtraction($structure);

        if (! $result['success']) {
            return $this->error('Mining extraction failed', 'EXTRACTION_FAILED');
        }

        return $this->success([
            'extracted' => $result['extracted'],
        ], 'Resources collected');
    }

    /**
     * Resolve the owning player of a structure with auth check.
     */
    private function resolveStructureOwner(OrbitalStructure $structure): Player|JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::find($structure->player_id);
        if (! $player || $player->user_id !== $user->id) {
            return $this->forbidden('You do not own this structure');
        }

        return $player;
    }
}

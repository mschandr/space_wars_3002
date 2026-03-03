<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomsOfficial;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\CustomsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomsController extends BaseApiController
{
    public function __construct(
        private readonly CustomsService $customsService
    ) {}

    /**
     * Attempt to bribe a customs official
     *
     * POST /api/customs/{poiUuid}/bribe
     * Body: { player_uuid: string, amount: int }
     */
    public function bribe(Request $request, string $poiUuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'amount' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Find player and POI
        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$player) {
            return $this->notFound('Player not found');
        }

        $poi = PointOfInterest::where('uuid', $poiUuid)->first();
        if (!$poi) {
            return $this->notFound('Location not found');
        }

        // Find customs official
        $official = CustomsOfficial::where('poi_id', $poi->id)->first();
        if (!$official) {
            return $this->error('No customs authority at this location', 400);
        }

        // Check if player has enough credits
        if ($player->credits < $validated['amount']) {
            return $this->error('Insufficient credits for bribe', 402);
        }

        // Attempt bribe
        if ($this->customsService->applyBribe($player, $official, $validated['amount'])) {
            return $this->success([
                'message' => 'Bribe accepted. You\'re cleared to proceed.',
                'outcome' => 'bribed',
                'credits_remaining' => (float) $player->fresh()->credits,
            ]);
        }

        return $this->error('Bribe failed. Official refused.', 400);
    }

    /**
     * Accept a fine or cargo seizure
     *
     * POST /api/customs/{poiUuid}/accept
     * Body: { player_uuid: string, ship_uuid: string, fine_amount: int }
     */
    public function acceptFine(Request $request, string $poiUuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'ship_uuid' => ['required', 'string'],
                'fine_amount' => ['required', 'integer', 'min:0'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Find player and ship
        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$player) {
            return $this->notFound('Player not found');
        }

        $ship = $player->ships()
            ->where('uuid', $validated['ship_uuid'])
            ->first();

        if (!$ship) {
            return $this->notFound('Ship not found');
        }

        // Seize illegal cargo and apply fine
        $this->customsService->seizeIllegalCargo($ship, $player, $validated['fine_amount']);

        return $this->success([
            'message' => 'Fine accepted. Illegal cargo seized.',
            'fine_amount' => $validated['fine_amount'],
            'credits_remaining' => (float) $player->fresh()->credits,
            'cargo_remaining' => $ship->fresh()->current_cargo,
        ]);
    }
}

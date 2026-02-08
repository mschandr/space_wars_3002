<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Player Settings Controller
 *
 * Handles updating player settings with ownership validation.
 */
class PlayerSettingsController extends BaseApiController
{
    /**
     * Update player settings.
     *
     * PATCH /api/players/{uuid}/settings
     *
     * Allowed fields:
     * - call_sign: string (must be unique within the galaxy)
     * - settings: array (JSON blob for client-side preferences)
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Authentication required', 'UNAUTHENTICATED', null, 401);
        }

        $player = Player::where('uuid', $uuid)->first();
        if (! $player) {
            return $this->notFound('Player not found');
        }

        // Ownership validation - player must belong to authenticated user
        if ($player->user_id !== $user->id) {
            return $this->forbidden('You do not own this player');
        }

        try {
            $validated = $request->validate([
                'call_sign' => ['sometimes', 'string', 'min:2', 'max:50'],
                'settings' => ['sometimes', 'array'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // If updating call_sign, check uniqueness within the galaxy
        if (isset($validated['call_sign'])) {
            $existingPlayer = Player::where('galaxy_id', $player->galaxy_id)
                ->where('call_sign', $validated['call_sign'])
                ->where('id', '!=', $player->id)
                ->first();

            if ($existingPlayer) {
                return $this->error(
                    'Call sign already exists in this galaxy',
                    'DUPLICATE_CALL_SIGN',
                    null,
                    422
                );
            }

            $player->call_sign = $validated['call_sign'];
        }

        // Update settings if provided
        if (isset($validated['settings'])) {
            // Merge with existing settings (don't overwrite entirely)
            $existingSettings = $player->settings ?? [];
            $player->settings = array_merge($existingSettings, $validated['settings']);
        }

        $player->save();
        $player->load(['galaxy', 'currentLocation', 'activeShip.ship']);

        return $this->success(
            new PlayerResource($player),
            'Player settings updated successfully'
        );
    }
}

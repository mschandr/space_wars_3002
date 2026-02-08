<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\GalaxyResource;
use App\Models\Galaxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Galaxy Settings Controller
 *
 * Handles updating galaxy settings with ownership validation.
 */
class GalaxySettingsController extends BaseApiController
{
    /**
     * Update galaxy settings.
     *
     * PATCH /api/galaxies/{uuid}/settings
     *
     * Only the galaxy owner (owner_user_id) can update settings.
     *
     * Allowed fields:
     * - name: string (max 100 chars)
     * - description: string (max 500 chars)
     * - is_public: boolean
     * - max_players: integer (10-1000)
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Authentication required', 'UNAUTHENTICATED', null, 401);
        }

        $galaxy = Galaxy::where('uuid', $uuid)->first();
        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        // Ownership validation - must be the galaxy owner
        if ($galaxy->owner_user_id !== $user->id) {
            return $this->forbidden('You do not own this galaxy');
        }

        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'min:2', 'max:100'],
                'description' => ['sometimes', 'nullable', 'string', 'max:500'],
                'is_public' => ['sometimes', 'boolean'],
                'max_players' => ['sometimes', 'integer', 'min:10', 'max:1000'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Check name uniqueness if updating
        if (isset($validated['name'])) {
            $existingGalaxy = Galaxy::where('name', $validated['name'])
                ->where('id', '!=', $galaxy->id)
                ->first();

            if ($existingGalaxy) {
                return $this->error(
                    'Galaxy name already exists',
                    'DUPLICATE_GALAXY_NAME',
                    null,
                    422
                );
            }

            $galaxy->name = $validated['name'];
        }

        // Update other fields if provided
        if (isset($validated['description'])) {
            $galaxy->description = $validated['description'];
        }

        if (isset($validated['is_public'])) {
            $galaxy->is_public = $validated['is_public'];
        }

        if (isset($validated['max_players'])) {
            // Ensure max_players is not less than current player count
            $currentPlayerCount = $galaxy->players()->where('status', 'active')->count();
            if ($validated['max_players'] < $currentPlayerCount) {
                return $this->error(
                    'Max players cannot be less than current player count',
                    'INVALID_MAX_PLAYERS',
                    [
                        'current_players' => $currentPlayerCount,
                        'requested_max' => $validated['max_players'],
                    ],
                    422
                );
            }

            $galaxy->max_players = $validated['max_players'];
        }

        $galaxy->save();
        $galaxy->loadCount([
            'players as total_players',
            'players as active_player_count' => fn ($q) => $q->where('status', 'active'),
        ]);

        return $this->success(
            new GalaxyResource($galaxy),
            'Galaxy settings updated successfully'
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ShipResource;
use App\Models\PlayerShip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShipController extends BaseApiController
{
    /**
     * Get active ship details for a player
     *
     * GET /api/players/{playerUuid}/ship
     */
    public function getActiveShip(Request $request, string $playerUuid): JsonResponse
    {
        $player = $request->user()
            ->players()
            ->where('uuid', $playerUuid)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $ship = $player->activeShip()->with('ship')->first();

        if (! $ship) {
            return $this->notFound('No active ship found');
        }

        return $this->success(new ShipResource($ship));
    }

    /**
     * Get ship status
     *
     * GET /api/ships/{uuid}/status
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with('ship')
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        // Regenerate fuel before showing status
        $ship->regenerateFuel();

        $hullPercentage = ($ship->hull / $ship->max_hull) * 100;
        $fuelPercentage = ($ship->current_fuel / $ship->max_fuel) * 100;

        return $this->success([
            'uuid' => $ship->uuid,
            'name' => $ship->name,
            'ship_class' => $ship->ship->name,
            'status' => $ship->status,
            'hull' => [
                'current' => $ship->hull,
                'max' => $ship->max_hull,
                'percentage' => round($hullPercentage, 2),
                'is_damaged' => $ship->hull < $ship->max_hull * 0.3,
            ],
            'fuel' => [
                'current' => $ship->current_fuel,
                'max' => $ship->max_fuel,
                'percentage' => round($fuelPercentage, 2),
                'time_to_full' => $ship->getTimeUntilFullFuel(),
            ],
            'cargo' => [
                'current' => $ship->current_cargo,
                'capacity' => $ship->cargo_hold,
                'available_space' => $ship->cargo_hold - $ship->current_cargo,
            ],
            'components' => [
                'weapons' => $ship->weapons,
                'sensors' => $ship->sensors,
                'warp_drive' => $ship->warp_drive,
            ],
        ]);
    }

    /**
     * Get fuel status and regeneration info
     *
     * GET /api/ships/{uuid}/fuel
     */
    public function fuel(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        // Regenerate fuel before showing status
        $ship->regenerateFuel();

        $timeToFull = $ship->getTimeUntilFullFuel();
        $regenRate = 3600 / PlayerShip::FUEL_REGEN_RATE; // Fuel units per hour

        return $this->success([
            'current_fuel' => $ship->current_fuel,
            'max_fuel' => $ship->max_fuel,
            'fuel_percentage' => round(($ship->current_fuel / $ship->max_fuel) * 100, 2),
            'regen_rate_per_hour' => $regenRate,
            'seconds_to_full' => $timeToFull,
            'last_updated' => $ship->fuel_last_updated_at,
        ]);
    }

    /**
     * Trigger manual fuel regeneration (recalculate current fuel)
     *
     * POST /api/ships/{uuid}/regenerate-fuel
     */
    public function regenerateFuel(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        $fuelBefore = $ship->current_fuel;
        $ship->regenerateFuel();
        $fuelAfter = $ship->current_fuel;

        $fuelRegenerated = $fuelAfter - $fuelBefore;

        return $this->success([
            'fuel_before' => $fuelBefore,
            'fuel_after' => $fuelAfter,
            'fuel_regenerated' => $fuelRegenerated,
            'max_fuel' => $ship->max_fuel,
            'is_full' => $fuelAfter >= $ship->max_fuel,
            'time_to_full' => $ship->getTimeUntilFullFuel(),
        ], 'Fuel regenerated successfully');
    }

    /**
     * Get all ship upgrade information
     *
     * GET /api/ships/{uuid}/upgrades
     */
    public function upgrades(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['ship', 'player.plans'])
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        $player = $ship->player;
        $maxLevelPerComponent = config('game_config.upgrades.max_level_per_component', 20);

        $components = ['max_fuel', 'max_hull', 'weapons', 'cargo_hold', 'sensors', 'warp_drive'];
        $upgradeInfo = [];

        foreach ($components as $component) {
            $currentValue = $ship->{$component === 'max_fuel' ? 'max_fuel' : ($component === 'max_hull' ? 'max_hull' : $component)};
            $bonusLevels = $player->getAdditionalLevelsForComponent($component);
            $effectiveMax = $maxLevelPerComponent + $bonusLevels;

            $upgradeInfo[$component] = [
                'current_value' => $currentValue,
                'max_level' => $effectiveMax,
                'bonus_from_plans' => $bonusLevels,
                'can_upgrade' => $currentValue < $effectiveMax,
            ];
        }

        return $this->success([
            'ship_uuid' => $ship->uuid,
            'ship_name' => $ship->name,
            'upgrades' => $upgradeInfo,
        ]);
    }

    /**
     * Get damage assessment
     *
     * GET /api/ships/{uuid}/damage
     */
    public function damage(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        $hullDamage = $ship->max_hull - $ship->hull;
        $hullPercentage = ($ship->hull / $ship->max_hull) * 100;

        $assessmentLevel = match (true) {
            $hullPercentage >= 90 => 'excellent',
            $hullPercentage >= 70 => 'good',
            $hullPercentage >= 50 => 'moderate',
            $hullPercentage >= 30 => 'damaged',
            $hullPercentage >= 10 => 'critical',
            default => 'destroyed',
        };

        return $this->success([
            'ship_uuid' => $ship->uuid,
            'hull' => [
                'current' => $ship->hull,
                'max' => $ship->max_hull,
                'damage' => $hullDamage,
                'percentage' => round($hullPercentage, 2),
            ],
            'status' => $ship->status,
            'assessment' => $assessmentLevel,
            'needs_repair' => $hullDamage > 0,
            'repair_cost_estimate' => $hullDamage * 10, // Placeholder formula
        ]);
    }

    /**
     * Rename ship
     *
     * PATCH /api/ships/{uuid}/name
     */
    public function rename(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $ship->name = $validated['name'];
        $ship->save();

        return $this->success(
            new ShipResource($ship->load('ship')),
            'Ship renamed successfully'
        );
    }
}

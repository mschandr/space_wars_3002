<?php

namespace App\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FlotillaService
{
    /**
     * Create a new flotilla with a flagship ship
     *
     * @param Player $player
     * @param PlayerShip $flagship
     * @param string|null $name
     * @return Flotilla
     * @throws \Exception
     */
    public function createFlotilla(Player $player, PlayerShip $flagship, ?string $name = null): Flotilla
    {
        return DB::transaction(function () use ($player, $flagship, $name) {
            // Validate ship belongs to player
            if ($flagship->player_id !== $player->id) {
                throw new \Exception('Ship does not belong to this player');
            }

            // Validate ship is not already in a flotilla
            if ($flagship->isInFlotilla()) {
                throw new \Exception('Ship is already part of a flotilla');
            }

            // Create flotilla
            $flotilla = Flotilla::create([
                'player_id' => $player->id,
                'flagship_ship_id' => $flagship->id,
                'name' => $name ?? "Flotilla {$flagship->name}",
            ]);

            // Add flagship to flotilla
            $flagship->update(['flotilla_id' => $flotilla->id]);

            return $flotilla->refresh();
        });
    }

    /**
     * Add a ship to an existing flotilla
     *
     * @param Flotilla $flotilla
     * @param PlayerShip $ship
     * @return void
     * @throws \Exception
     */
    public function addShipToFlotilla(Flotilla $flotilla, PlayerShip $ship): void
    {
        DB::transaction(function () use ($flotilla, $ship) {
            // Validate ship belongs to same player
            if ($ship->player_id !== $flotilla->player_id) {
                throw new \Exception('Ship does not belong to the flotilla owner');
            }

            // Validate ship is not already in a flotilla
            if ($ship->isInFlotilla()) {
                throw new \Exception('Ship is already part of another flotilla');
            }

            // Validate flotilla is not full
            if ($flotilla->isFull()) {
                throw new \Exception('Flotilla is at maximum capacity (' . config('game_config.flotilla.max_ships') . ' ships)');
            }

            // Validate all ships at same POI
            $flagshipPoi = $flotilla->flagship->current_poi_id;
            if ($ship->current_poi_id !== $flagshipPoi) {
                throw new \Exception('All ships must be at the same location to form a flotilla');
            }

            // Add ship to flotilla
            $ship->update(['flotilla_id' => $flotilla->id]);
        });
    }

    /**
     * Remove a ship from a flotilla
     *
     * @param Flotilla $flotilla
     * @param PlayerShip $ship
     * @return void
     * @throws \Exception
     */
    public function removeShipFromFlotilla(Flotilla $flotilla, PlayerShip $ship): void
    {
        DB::transaction(function () use ($flotilla, $ship) {
            // Validate ship belongs to this flotilla
            if ($ship->flotilla_id !== $flotilla->id) {
                throw new \Exception('Ship does not belong to this flotilla');
            }

            // Cannot remove the flagship
            if ($ship->isFlagship()) {
                throw new \Exception('Cannot remove the flagship. Designate a new flagship first');
            }

            // Remove ship from flotilla
            $ship->update(['flotilla_id' => null]);
        });
    }

    /**
     * Change the flagship of a flotilla
     *
     * @param Flotilla $flotilla
     * @param PlayerShip $newFlagship
     * @return void
     * @throws \Exception
     */
    public function setFlagship(Flotilla $flotilla, PlayerShip $newFlagship): void
    {
        DB::transaction(function () use ($flotilla, $newFlagship) {
            // Validate new flagship is in this flotilla
            if ($newFlagship->flotilla_id !== $flotilla->id) {
                throw new \Exception('New flagship must be part of this flotilla');
            }

            // Update flagship designation
            $flotilla->update(['flagship_ship_id' => $newFlagship->id]);
        });
    }

    /**
     * Dissolve a flotilla, releasing all ships
     *
     * @param Flotilla $flotilla
     * @return void
     */
    public function dissolveFlotilla(Flotilla $flotilla): void
    {
        DB::transaction(function () use ($flotilla) {
            // Release all ships from the flotilla
            $flotilla->ships()->update(['flotilla_id' => null]);

            // Delete the flotilla
            $flotilla->delete();
        });
    }

    /**
     * Get complete flotilla status with all relevant information
     *
     * @param Flotilla $flotilla
     * @return array
     */
    public function getFlotillaStatus(Flotilla $flotilla): array
    {
        return [
            'id' => $flotilla->id,
            'uuid' => $flotilla->uuid,
            'name' => $flotilla->name,
            'player_id' => $flotilla->player_id,
            'flagship' => [
                'id' => $flotilla->flagship->id,
                'uuid' => $flotilla->flagship->uuid,
                'name' => $flotilla->flagship->name,
                'hull' => $flotilla->flagship->hull,
                'max_hull' => $flotilla->flagship->getEffectiveMaxHull(),
                'current_fuel' => $flotilla->flagship->getCurrentFuel(),
                'max_fuel' => $flotilla->flagship->getEffectiveMaxFuel(),
            ],
            'ships' => $this->formatShips($flotilla->ships),
            'formation_stats' => [
                'ship_count' => $flotilla->shipCount(),
                'is_full' => $flotilla->isFull(),
                'total_hull' => $flotilla->totalHull(),
                'weakest_ship_hull' => $flotilla->lowestHull()->hull ?? null,
                'slowest_warp_drive' => $flotilla->slowestShip()->warp_drive ?? null,
                'total_cargo_hold' => $flotilla->totalCargoHold(),
                'available_cargo_space' => $flotilla->getAvailableCargoSpace(),
                'total_weapon_damage' => $flotilla->getTotalWeaponDamage(),
            ],
            'location' => [
                'poi_id' => $flotilla->getCurrentLocation()?->id,
                'poi_name' => $flotilla->getCurrentLocation()?->name,
                'at_same_location' => $flotilla->areAllShipsAtSamePoi(),
            ],
            'created_at' => $flotilla->created_at->toIso8601String(),
            'updated_at' => $flotilla->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format ships for API response
     *
     * @param Collection $ships
     * @return array
     */
    private function formatShips(Collection $ships): array
    {
        return $ships->map(function (PlayerShip $ship) {
            return [
                'id' => $ship->id,
                'uuid' => $ship->uuid,
                'name' => $ship->name,
                'is_flagship' => $ship->isFlagship(),
                'hull' => $ship->hull,
                'max_hull' => $ship->getEffectiveMaxHull(),
                'current_fuel' => $ship->getCurrentFuel(),
                'max_fuel' => $ship->getEffectiveMaxFuel(),
                'warp_drive' => $ship->warp_drive,
                'cargo_hold' => $ship->getEffectiveCargoHold(),
                'current_cargo' => $ship->current_cargo,
                'weapons' => $ship->weapons,
            ];
        })->all();
    }

    /**
     * Get player's current flotilla (if they have one)
     *
     * @param Player $player
     * @return Flotilla|null
     */
    public function getPlayerFlotilla(Player $player): ?Flotilla
    {
        return Flotilla::where('player_id', $player->id)->first();
    }

    /**
     * Check if a flotilla can be created between two or more ships
     *
     * @param Player $player
     * @param array $shipIds
     * @return array ['can_create' => bool, 'reason' => string|null]
     */
    public function canCreateFlotilla(Player $player, array $shipIds): array
    {
        // Must have at least 2 ships
        if (count($shipIds) < 2) {
            return ['can_create' => false, 'reason' => 'Need at least 2 ships to form a flotilla'];
        }

        // Cannot exceed max ships
        if (count($shipIds) > config('game_config.flotilla.max_ships')) {
            return [
                'can_create' => false,
                'reason' => 'Too many ships (max: ' . config('game_config.flotilla.max_ships') . ')',
            ];
        }

        // Fetch ships
        $ships = PlayerShip::whereIn('id', $shipIds)
            ->where('player_id', $player->id)
            ->get();

        // Validate all ships exist and belong to player
        if ($ships->count() !== count($shipIds)) {
            return ['can_create' => false, 'reason' => 'One or more ships not found or do not belong to player'];
        }

        // Check no ship is already in a flotilla
        if ($ships->where('flotilla_id', '!=', null)->count() > 0) {
            return ['can_create' => false, 'reason' => 'One or more ships are already in a flotilla'];
        }

        // Check all ships are at the same location
        $firstPoi = $ships->first()->current_poi_id;
        if ($ships->where('current_poi_id', '!=', $firstPoi)->count() > 0) {
            return ['can_create' => false, 'reason' => 'All ships must be at the same location'];
        }

        return ['can_create' => true];
    }
}

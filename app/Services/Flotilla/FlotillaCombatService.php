<?php

namespace App\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\PlayerShip;
use Illuminate\Support\Facades\DB;

class FlotillaCombatService
{
    /**
     * Calculate total weapon damage from all ships in a flotilla
     *
     * @param Flotilla $flotilla
     * @return int Total damage with random variance per ship
     */
    public function getTotalFlotillaWeaponDamage(Flotilla $flotilla): int
    {
        $totalDamage = 0;

        foreach ($flotilla->ships as $ship) {
            // Base damage from weapons attribute and weapon level
            $baseDamage = ($ship->weapons ?? 0) * 10; // Each weapon level = 10 damage units

            // Add random variance (±25%)
            $variance = (int) ($baseDamage * 0.25);
            $shipDamage = $baseDamage + random_int(-$variance, $variance);

            $totalDamage += max(1, $shipDamage);
        }

        return $totalDamage;
    }

    /**
     * Select the pirate's focus target (ship with lowest hull)
     * Pirates in this system target weakest ship first
     *
     * @param Flotilla $flotilla
     * @return PlayerShip|null
     */
    public function selectPirateFocusTarget(Flotilla $flotilla): ?PlayerShip
    {
        return $flotilla->getWeakestShip();
    }

    /**
     * Apply damage to a flotilla
     * Damage is applied to the pirate-selected target ship (weakest)
     *
     * @param Flotilla $flotilla
     * @param int $damageAmount
     * @return array ['damage_applied' => int, 'ship_destroyed' => bool, 'destroyed_ship_id' => int|null]
     * @throws \Exception
     */
    public function applyDamageToFlotilla(Flotilla $flotilla, int $damageAmount): array
    {
        return DB::transaction(function () use ($flotilla, $damageAmount) {
            $target = $this->selectPirateFocusTarget($flotilla);

            if (!$target) {
                throw new \Exception('No valid target ship in flotilla');
            }

            // Apply damage to target ship
            $target->takeDamage($damageAmount);

            // Check if ship was destroyed
            $destroyed = $target->hull <= 0;

            if ($destroyed) {
                // Handle ship destruction in combat
                $this->handleShipDestructionInCombat($flotilla, $target);
            }

            return [
                'damage_applied' => $damageAmount,
                'ship_destroyed' => $destroyed,
                'destroyed_ship_id' => $destroyed ? $target->id : null,
                'target_ship_id' => $target->id,
                'target_ship_name' => $target->name,
                'remaining_ships' => $flotilla->ships()->count(),
            ];
        });
    }

    /**
     * Handle a ship being destroyed during combat
     * - If flagship destroyed: promote next largest ship
     * - Remove ship from flotilla
     *
     * @param Flotilla $flotilla
     * @param PlayerShip $destroyedShip
     * @return void
     * @throws \Exception
     */
    public function handleShipDestructionInCombat(Flotilla $flotilla, PlayerShip $destroyedShip): void
    {
        DB::transaction(function () use ($flotilla, $destroyedShip) {
            $isFlagship = $destroyedShip->isFlagship();

            // Remove ship from flotilla (cargo stays on wreck)
            $destroyedShip->update(['flotilla_id' => null, 'status' => 'destroyed']);

            // If flagship was destroyed, promote next largest ship
            if ($isFlagship) {
                $nextFlagship = $flotilla->highestHull();

                if ($nextFlagship && $nextFlagship->id !== $destroyedShip->id) {
                    $flotilla->update(['flagship_ship_id' => $nextFlagship->id]);
                } else {
                    // All ships destroyed - flotilla is effectively destroyed
                    // (handled by caller in combat resolution)
                }
            }
        });
    }

    /**
     * Check if flotilla is still combat-capable (has ships)
     *
     * @param Flotilla $flotilla
     * @return bool
     */
    public function isFlotillaCombatCapable(Flotilla $flotilla): bool
    {
        return $flotilla->ships()->count() > 0;
    }

    /**
     * Get combat readiness of a flotilla
     *
     * @param Flotilla $flotilla
     * @return array
     */
    public function getFlotillaCombatStatus(Flotilla $flotilla): array
    {
        return [
            'total_ships' => $flotilla->shipCount(),
            'total_hull' => $flotilla->totalHull(),
            'total_weapon_damage' => $this->getTotalFlotillaWeaponDamage($flotilla),
            'weakest_ship' => [
                'id' => $flotilla->lowestHull()?->id,
                'name' => $flotilla->lowestHull()?->name,
                'hull' => $flotilla->lowestHull()?->hull,
            ],
            'flagship_status' => [
                'id' => $flotilla->flagship->id,
                'name' => $flotilla->flagship->name,
                'hull' => $flotilla->flagship->hull,
                'is_intact' => $flotilla->flagship->hull > 0,
            ],
            'is_combat_capable' => $this->isFlotillaCombatCapable($flotilla),
        ];
    }

    /**
     * Calculate aggregate combat statistics for a flotilla
     * Used in pre-combat analysis
     *
     * @param Flotilla $flotilla
     * @return array
     */
    public function getAggregateCombatStats(Flotilla $flotilla): array
    {
        $totalWeapons = 0;
        $totalHull = 0;
        $avgHull = 0;
        $shipCount = 0;

        foreach ($flotilla->ships as $ship) {
            $totalWeapons += $ship->weapons ?? 0;
            $totalHull += $ship->hull;
            $shipCount++;
        }

        $avgHull = $shipCount > 0 ? (int) ($totalHull / $shipCount) : 0;

        return [
            'ship_count' => $shipCount,
            'total_weapons' => $totalWeapons,
            'total_hull' => $totalHull,
            'average_hull' => $avgHull,
            'total_weapon_damage' => $this->getTotalFlotillaWeaponDamage($flotilla),
            'weakest_hull' => $flotilla->lowestHull()?->hull ?? 0,
            'combat_efficiency' => $shipCount > 0 ? (int) (($totalHull / ($shipCount * 100)) * 100) : 0,
        ];
    }

    /**
     * Get list of destroyed ships during combat
     * (Ships with hull <= 0)
     *
     * @param Flotilla $flotilla
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDestroyedShips(Flotilla $flotilla)
    {
        return $flotilla->ships()->where('hull', '<=', 0)->get();
    }

    /**
     * Get list of surviving ships after combat
     *
     * @param Flotilla $flotilla
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSurvivingShips(Flotilla $flotilla)
    {
        return $flotilla->ships()->where('hull', '>', 0)->get();
    }
}

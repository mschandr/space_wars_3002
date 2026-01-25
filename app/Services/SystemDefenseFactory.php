<?php

namespace App\Services;

use App\Enums\Defense\SystemDefenseType;
use App\Models\PointOfInterest;
use App\Models\SystemDefense;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Factory for creating system defenses at fortified POIs.
 *
 * Fortress deployments include:
 * - 4 orbital cannons
 * - 2 space lasers
 * - 6 ground missiles
 * - 1 planetary shield (10000 strength)
 * - 1 fighter port (1000 fighters)
 */
class SystemDefenseFactory
{
    /**
     * Deploys a full fortress set of defenses at the given point of interest and marks the POI as fortified.
     *
     * @param PointOfInterest $poi The POI to fortify; this method sets `$poi->is_fortified = true` and saves the model.
     * @param int $level Defense level that scales defenses' health and damage.
     * @return \Illuminate\Support\Collection<\App\Models\SystemDefense> Collection of created SystemDefense models.
     */
    public function deployFortressDefenses(PointOfInterest $poi, int $level = 1): Collection
    {
        $defenses = collect();

        // Deploy orbital cannons (4)
        for ($i = 0; $i < SystemDefenseType::ORBITAL_CANNON->getFortressQuantity(); $i++) {
            $defenses->push($this->createDefense($poi, SystemDefenseType::ORBITAL_CANNON, $level));
        }

        // Deploy space lasers (2)
        for ($i = 0; $i < SystemDefenseType::SPACE_LASER->getFortressQuantity(); $i++) {
            $defenses->push($this->createDefense($poi, SystemDefenseType::SPACE_LASER, $level));
        }

        // Deploy ground missiles (6)
        for ($i = 0; $i < SystemDefenseType::GROUND_MISSILE->getFortressQuantity(); $i++) {
            $defenses->push($this->createDefense($poi, SystemDefenseType::GROUND_MISSILE, $level));
        }

        // Deploy planetary shield (1 with 10000 strength)
        $defenses->push($this->createDefense(
            $poi,
            SystemDefenseType::PLANETARY_SHIELD,
            $level,
            ['health' => 10000, 'max_health' => 10000]
        ));

        // Deploy fighter port (1 with 1000 fighters)
        $defenses->push($this->createDefense(
            $poi,
            SystemDefenseType::FIGHTER_PORT,
            $level,
            ['attributes' => ['fighter_count' => 1000, 'fighter_damage' => 25, 'fighter_health' => 50]]
        ));

        // Mark POI as fortified
        $poi->is_fortified = true;
        $poi->save();

        return $defenses;
    }

    /**
     * Create and persist a single defense record for the given POI.
     *
     * Creates a defense using the provided type and level (health scales 20% per level above 1) and applies optional overrides.
     *
     * @param PointOfInterest $poi The point of interest to attach the defense to.
     * @param SystemDefenseType $type The defense type providing defaults for health and attributes.
     * @param int $level Level used to scale the defense's base health; each level above 1 increases health by 20%.
     * @param array $overrides Optional associative overrides. Supported keys: `health` (int), `max_health` (int), and `attributes` (array) to merge with type defaults.
     * @return SystemDefense The newly created SystemDefense instance.
     */
    public function createDefense(
        PointOfInterest $poi,
        SystemDefenseType $type,
        int $level = 1,
        array $overrides = []
    ): SystemDefense {
        $baseHealth = $type->getBaseHealth();
        $levelMultiplier = 1 + (($level - 1) * 0.2);  // 20% health per level

        $data = [
            'uuid' => Str::uuid(),
            'poi_id' => $poi->id,
            'defense_type' => $type,
            'level' => $level,
            'quantity' => 1,
            'health' => (int) ($baseHealth * $levelMultiplier),
            'max_health' => (int) ($baseHealth * $levelMultiplier),
            'is_active' => true,
            'attributes' => $type->getDefaultAttributes(),
        ];

        // Apply overrides
        if (isset($overrides['health'])) {
            $data['health'] = $overrides['health'];
        }
        if (isset($overrides['max_health'])) {
            $data['max_health'] = $overrides['max_health'];
        }
        if (isset($overrides['attributes'])) {
            $data['attributes'] = array_merge($data['attributes'], $overrides['attributes']);
        }

        return SystemDefense::create($data);
    }

    /**
     * Deploys a minimal set of defenses at the given POI and marks the POI as fortified.
     *
     * @param PointOfInterest $poi The POI to defend.
     * @param int $level Defense level used to scale health and attributes.
     * @return \Illuminate\Support\Collection<\App\Models\SystemDefense> Collection of created SystemDefense instances.
     */
    public function deployMinimalDefenses(PointOfInterest $poi, int $level = 1): Collection
    {
        $defenses = collect();

        // Deploy 2 orbital cannons
        for ($i = 0; $i < 2; $i++) {
            $defenses->push($this->createDefense($poi, SystemDefenseType::ORBITAL_CANNON, $level));
        }

        // Deploy 1 space laser
        $defenses->push($this->createDefense($poi, SystemDefenseType::SPACE_LASER, $level));

        // Deploy planetary shield (weaker)
        $defenses->push($this->createDefense(
            $poi,
            SystemDefenseType::PLANETARY_SHIELD,
            $level,
            ['health' => 5000, 'max_health' => 5000]
        ));

        // Mark POI as fortified
        $poi->is_fortified = true;
        $poi->save();

        return $defenses;
    }

    /**
     * Compute aggregate defense metrics for a point of interest.
     *
     * @param PointOfInterest $poi The POI whose active defenses will be evaluated.
     * @return array{
     *   total_damage_per_round: int|float,
     *   total_health: int|float,
     *   active_defenses: int,
     *   fighter_count: int,
     *   defense_breakdown: \Illuminate\Support\Collection
     * } An array with:
     *   - `total_damage_per_round`: sum of base defense damage and fighter damage per round.
     *   - `total_health`: sum of current health of all active defenses.
     *   - `active_defenses`: number of active defenses considered.
     *   - `fighter_count`: total number of fighters available across fighter ports.
     *   - `defense_breakdown`: collection keyed by `defense_type`, each value containing:
     *       - `count`: number of defenses of that type.
     *       - `total_health`: summed health for that type.
     *       - `total_damage`: summed base damage for that type.
     */
    public function calculateTotalDefensePower(PointOfInterest $poi): array
    {
        $defenses = $poi->systemDefenses()->active()->get();

        $totalDamage = 0;
        $totalHealth = 0;
        $fighterCount = 0;
        $fighterDamage = 0;

        foreach ($defenses as $defense) {
            $totalDamage += $defense->calculateDamage();
            $totalHealth += $defense->health;

            if ($defense->defense_type === SystemDefenseType::FIGHTER_PORT) {
                $fighterCount += $defense->attributes['fighter_count'] ?? 0;
                $fighterDamage += $defense->calculateFighterDamage();
            }
        }

        return [
            'total_damage_per_round' => $totalDamage + $fighterDamage,
            'total_health' => $totalHealth,
            'active_defenses' => $defenses->count(),
            'fighter_count' => $fighterCount,
            'defense_breakdown' => $defenses->groupBy('defense_type')->map(fn ($group) => [
                'count' => $group->count(),
                'total_health' => $group->sum('health'),
                'total_damage' => $group->sum(fn ($d) => $d->calculateDamage()),
            ]),
        ];
    }

    /**
     * Repair all defenses at the given POI by a fixed amount.
     *
     * Repairs only defenses whose current health is greater than zero by calling each defense's repair method and returns the sum of health restored.
     *
     * @param PointOfInterest $poi The POI whose defenses will be repaired.
     * @param int $repairAmount The number of health points to attempt to restore on each defense.
     * @return int Total health points restored across all repaired defenses.
     */
    public function repairAllDefenses(PointOfInterest $poi, int $repairAmount): int
    {
        $totalRepaired = 0;

        foreach ($poi->systemDefenses()->where('health', '>', 0)->get() as $defense) {
            $totalRepaired += $defense->repair($repairAmount);
        }

        return $totalRepaired;
    }
}
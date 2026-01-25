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
     * Deploy fortress-level defenses at a POI.
     *
     * @param  PointOfInterest  $poi  The POI to fortify
     * @param  int  $level  Defense level (affects damage and health)
     * @return Collection<SystemDefense> Created defenses
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
     * Create a single defense at a POI.
     *
     * @param  PointOfInterest  $poi  The POI to defend
     * @param  SystemDefenseType  $type  Defense type
     * @param  int  $level  Defense level
     * @param  array  $overrides  Optional attribute overrides
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
     * Deploy minimal defenses (for less important systems).
     *
     * @param  PointOfInterest  $poi  The POI to defend
     * @param  int  $level  Defense level
     * @return Collection<SystemDefense> Created defenses
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
     * Calculate total defense power at a POI.
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
     * Repair all defenses at a POI.
     *
     * @param  PointOfInterest  $poi  The POI with defenses
     * @param  int  $repairAmount  Amount to repair each defense
     * @return int Total health restored
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

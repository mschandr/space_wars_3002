<?php

namespace App\Services;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Npc;
use App\Models\NpcShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NpcGenerationService
{
    /**
     * NPC name prefixes for variety
     */
    private const NAME_PREFIXES = [
        'Captain', 'Commander', 'Admiral', 'Pilot', 'Trader',
        'Merchant', 'Scout', 'Hunter', 'Miner', 'Explorer',
    ];

    /**
     * NPC name suffixes/call signs
     */
    private const NAME_SUFFIXES = [
        'Alpha', 'Beta', 'Gamma', 'Delta', 'Echo', 'Foxtrot',
        'Ghost', 'Hawk', 'Iron', 'Jade', 'Knight', 'Luna',
        'Nova', 'Omega', 'Phoenix', 'Raven', 'Shadow', 'Titan',
        'Viper', 'Wolf', 'Zephyr', 'Nexus', 'Cipher', 'Spark',
    ];

    /**
     * Archetype distribution weights for random generation
     */
    private const ARCHETYPE_WEIGHTS = [
        'trader' => 35,
        'merchant' => 20,
        'explorer' => 15,
        'miner' => 20,
        'pirate_hunter' => 10,
    ];

    /**
     * Ship class preferences by archetype
     */
    private const ARCHETYPE_SHIP_PREFERENCES = [
        'trader' => ['freighter', 'hauler', 'transport'],
        'merchant' => ['freighter', 'hauler', 'luxury_liner'],
        'explorer' => ['scout', 'explorer', 'corvette'],
        'miner' => ['mining_vessel', 'freighter', 'hauler'],
        'pirate_hunter' => ['corvette', 'fighter', 'gunship', 'battleship'],
    ];

    /**
     * Generate NPCs for a galaxy
     */
    public function generateNpcs(
        Galaxy $galaxy,
        int $count,
        string $difficulty = 'medium',
        ?array $archetypeDistribution = null
    ): Collection {
        $npcs = collect();

        // Get inhabited starting locations
        $startingLocations = $this->getStartingLocations($galaxy, $count);

        if ($startingLocations->isEmpty()) {
            throw new \RuntimeException('No suitable starting locations found for NPCs');
        }

        // Get available ships
        $availableShips = Ship::all();
        if ($availableShips->isEmpty()) {
            throw new \RuntimeException('No ship blueprints found. Please run seeders first.');
        }

        // Use provided distribution or default weights
        $distribution = $archetypeDistribution ?? self::ARCHETYPE_WEIGHTS;

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $count; $i++) {
                $archetype = $this->selectArchetype($distribution);
                $startingLocation = $startingLocations->random();

                $npc = $this->createNpc(
                    $galaxy,
                    $archetype,
                    $difficulty,
                    $startingLocation,
                    $i + 1
                );

                // Create ship for NPC
                $ship = $this->selectShipForArchetype($archetype, $availableShips);
                $this->createNpcShip($npc, $ship, $difficulty);

                $npcs->push($npc);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $npcs;
    }

    /**
     * Create a single NPC
     */
    public function createNpc(
        Galaxy $galaxy,
        string $archetype,
        string $difficulty,
        PointOfInterest $startingLocation,
        int $index
    ): Npc {
        $callSign = $this->generateCallSign($galaxy, $index);
        $archetypeConfig = Npc::ARCHETYPES[$archetype] ?? Npc::ARCHETYPES['trader'];
        $difficultyMultiplier = Npc::DIFFICULTY_MULTIPLIERS[$difficulty]['credits'] ?? 1.0;

        // Base credits vary by archetype
        $baseCredits = match ($archetype) {
            'merchant' => random_int(50000, 150000),
            'trader' => random_int(15000, 50000),
            'miner' => random_int(10000, 30000),
            'explorer' => random_int(8000, 25000),
            'pirate_hunter' => random_int(20000, 60000),
            default => random_int(10000, 30000),
        };

        // Add some randomness to personality traits
        $aggression = $this->clamp($archetypeConfig['aggression'] + $this->randomVariance(0.1), 0, 1);
        $riskTolerance = $this->clamp($archetypeConfig['risk_tolerance'] + $this->randomVariance(0.1), 0, 1);
        $tradeFocus = $this->clamp($archetypeConfig['trade_focus'] + $this->randomVariance(0.1), 0, 1);

        return Npc::create([
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $startingLocation->id,
            'call_sign' => $callSign,
            'archetype' => $archetype,
            'credits' => $baseCredits * $difficultyMultiplier,
            'experience' => 0,
            'level' => 1,
            'difficulty' => $difficulty,
            'aggression' => $aggression,
            'risk_tolerance' => $riskTolerance,
            'trade_focus' => $tradeFocus,
            'personality' => [
                'generated_at' => now()->toIso8601String(),
                'seed' => random_int(1, 999999),
            ],
            'status' => 'active',
            'current_activity' => 'idle',
            'last_action_at' => now(),
        ]);
    }

    /**
     * Create a ship for an NPC
     */
    public function createNpcShip(Npc $npc, Ship $ship, string $difficulty): NpcShip
    {
        $difficultyMultiplier = Npc::DIFFICULTY_MULTIPLIERS[$difficulty]['combat_skill'] ?? 1.0;

        // Scale ship stats slightly based on difficulty
        $weaponsBoost = (int) (($ship->base_weapons ?? 10) * ($difficultyMultiplier - 1) * 0.5);
        $hullBoost = (int) (($ship->base_hull ?? 100) * ($difficultyMultiplier - 1) * 0.3);

        return NpcShip::create([
            'npc_id' => $npc->id,
            'ship_id' => $ship->id,
            'name' => $npc->call_sign."'s ".ucfirst($ship->class ?? 'Ship'),
            'current_fuel' => $ship->base_max_fuel ?? 100,
            'max_fuel' => $ship->base_max_fuel ?? 100,
            'fuel_last_updated_at' => now(),
            'hull' => ($ship->base_hull ?? 100) + $hullBoost,
            'max_hull' => ($ship->base_hull ?? 100) + $hullBoost,
            'weapons' => ($ship->base_weapons ?? 10) + $weaponsBoost,
            'cargo_hold' => $ship->base_cargo ?? 100,
            'sensors' => $ship->base_sensors ?? 1,
            'warp_drive' => $ship->base_warp_drive ?? 1,
            'current_cargo' => 0,
            'is_active' => true,
            'status' => 'operational',
        ]);
    }

    /**
     * Generate a unique call sign for an NPC
     */
    private function generateCallSign(Galaxy $galaxy, int $index): string
    {
        $maxAttempts = 50;
        $attempt = 0;

        do {
            $prefix = self::NAME_PREFIXES[array_rand(self::NAME_PREFIXES)];
            $suffix = self::NAME_SUFFIXES[array_rand(self::NAME_SUFFIXES)];

            // Add a number if we're having collision issues
            $number = $attempt > 10 ? '-'.($index + $attempt) : '';
            $callSign = "{$prefix} {$suffix}{$number}";

            $exists = Npc::where('galaxy_id', $galaxy->id)
                ->where('call_sign', $callSign)
                ->exists();

            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            // Fallback to guaranteed unique name
            return 'NPC-'.strtoupper(substr(md5(uniqid()), 0, 8));
        }

        return $callSign;
    }

    /**
     * Get suitable starting locations (inhabited systems)
     */
    private function getStartingLocations(Galaxy $galaxy, int $count): Collection
    {
        return PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_inhabited', true)
            ->inRandomOrder()
            ->limit(max($count, 50)) // Get more than needed for variety
            ->get();
    }

    /**
     * Select archetype based on weighted distribution
     */
    private function selectArchetype(array $weights): string
    {
        $total = array_sum($weights);
        $random = random_int(1, $total);

        $cumulative = 0;
        foreach ($weights as $archetype => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $archetype;
            }
        }

        return 'trader'; // Fallback
    }

    /**
     * Select appropriate ship for archetype
     */
    private function selectShipForArchetype(string $archetype, Collection $availableShips): Ship
    {
        $preferredClasses = self::ARCHETYPE_SHIP_PREFERENCES[$archetype] ?? ['scout'];

        // Try to find a ship matching preferred classes
        foreach ($preferredClasses as $class) {
            $ship = $availableShips->where('class', $class)->first();
            if ($ship) {
                return $ship;
            }
        }

        // Fallback to scout or first available ship
        return $availableShips->where('class', 'scout')->first()
            ?? $availableShips->first();
    }

    /**
     * Generate random variance
     */
    private function randomVariance(float $maxVariance): float
    {
        return (random_int(-100, 100) / 100) * $maxVariance;
    }

    /**
     * Clamp a value between min and max
     */
    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Get statistics about NPCs in a galaxy
     */
    public function getNpcStatistics(Galaxy $galaxy): array
    {
        $npcs = $galaxy->npcs()->with('activeShip')->get();

        return [
            'total' => $npcs->count(),
            'by_archetype' => $npcs->groupBy('archetype')->map->count(),
            'by_difficulty' => $npcs->groupBy('difficulty')->map->count(),
            'by_status' => $npcs->groupBy('status')->map->count(),
            'average_credits' => $npcs->avg('credits'),
            'average_level' => $npcs->avg('level'),
            'total_credits' => $npcs->sum('credits'),
        ];
    }
}

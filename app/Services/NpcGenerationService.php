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
     * Create and persist a batch of NPCs in the given galaxy.
     *
     * Generates $count NPCs, assigns each a starting location, archetype, and ship, and saves them inside a single database transaction.
     *
     * @param Galaxy $galaxy The galaxy in which to create NPCs.
     * @param int $count Number of NPCs to generate.
     * @param string $difficulty Difficulty level to apply to generated NPC attributes (e.g., "easy", "medium", "hard").
     * @param array|null $archetypeDistribution Optional weighted distribution of archetypes; if null the service's default weights are used.
     * @return \Illuminate\Support\Collection Collection of created Npc models.
     * @throws \RuntimeException If no suitable starting locations or no ship blueprints are available.
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
     * Create a new NPC in the given galaxy with attributes derived from the archetype and difficulty.
     *
     * The NPC is persisted and initialized with credits, personality traits, status, and activity metadata.
     *
     * @param Galaxy $galaxy The galaxy where the NPC will be created.
     * @param string $archetype The NPC archetype (e.g., 'trader', 'merchant', 'miner', 'explorer', 'pirate_hunter').
     * @param string $difficulty Difficulty level affecting credits and stat scaling (e.g., 'easy', 'medium', 'hard').
     * @param PointOfInterest $startingLocation The inhabited location where the NPC will start.
     * @param int $index An index used to help generate a unique call sign.
     * @return Npc The newly created Npc model.
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
     * Create and persist an NpcShip configured for the given NPC using the provided ship blueprint.
     *
     * @param Npc $npc The NPC that will own the created ship.
     * @param Ship $ship The ship blueprint to instantiate for the NPC.
     * @param string $difficulty Difficulty key used to adjust ship stats.
     * @return NpcShip The newly created NpcShip model.
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
         * Produce a unique call sign for an NPC within the given galaxy.
         *
         * Generates a name from configured prefixes and suffixes, appending a numeric suffix after repeated collisions.
         * Attempts up to 50 random candidates; if none are unique, returns a guaranteed unique fallback beginning with `NPC-`.
         *
         * @param Galaxy $galaxy The galaxy to ensure call sign uniqueness within.
         * @param int $index An index used as part of the numeric suffix when collisions occur.
         * @return string A unique call sign for the NPC.
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
     * Retrieve a randomized set of inhabited star system points of interest for a galaxy.
     *
     * The query returns up to the greater of the requested count or 50 items to provide variety.
     *
     * @param Galaxy $galaxy The galaxy to search within.
     * @param int $count The desired number of starting locations; at minimum 50 locations will be returned.
     * @return Collection A collection of PointOfInterest models representing inhabited star systems.
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
     * Choose an NPC archetype according to a weighted distribution.
     *
     * @param int[] $weights Associative array mapping archetype names to integer weights.
     * @return string The selected archetype; returns `'trader'` if selection cannot be determined.
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
     * Choose a Ship for the given NPC archetype using configured class preferences.
     *
     * Searches the provided collection for a ship whose class matches the archetype's preferred classes; if none are found, falls back to a ship of class "scout" or the first ship in the collection.
     *
     * @param string $archetype The NPC archetype key used to look up preferred ship classes.
     * @param \Illuminate\Support\Collection $availableShips Collection of Ship models available for assignment.
     * @return \App\Models\Ship The selected Ship model (preference match, a "scout" fallback, or the first available ship). 
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
     * Produce a random variance value within the inclusive range [-$maxVariance, +$maxVariance].
     *
     * @param float $maxVariance The maximum absolute magnitude of the variance.
     * @return float A random float between -$maxVariance and +$maxVariance.
     */
    private function randomVariance(float $maxVariance): float
    {
        return (random_int(-100, 100) / 100) * $maxVariance;
    }

    /**
         * Clamp a number to the inclusive range [$min, $max].
         *
         * @param float $value The value to clamp.
         * @param float $min The minimum allowed value (inclusive).
         * @param float $max The maximum allowed value (inclusive).
         * @return float The input constrained to be between `$min` and `$max`.
         */
    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Compute aggregated statistics for NPCs in the given galaxy.
     *
     * @param Galaxy $galaxy The galaxy whose NPCs will be aggregated.
     * @return array{
     *   total:int,
     *   by_archetype:array<string,int>,
     *   by_difficulty:array<string,int>,
     *   by_status:array<string,int>,
     *   average_credits:float|null,
     *   average_level:float|null,
     *   total_credits:float|int
     * } An associative array of aggregated metrics:
     * - `total`: total number of NPCs.
     * - `by_archetype`: counts keyed by archetype.
     * - `by_difficulty`: counts keyed by difficulty.
     * - `by_status`: counts keyed by status.
     * - `average_credits`: average credits across NPCs, or `null` if none.
     * - `average_level`: average level across NPCs, or `null` if none.
     * - `total_credits`: sum of credits across all NPCs.
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
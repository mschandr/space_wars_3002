<?php

namespace App\Services;

use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\StellarCartographer;
use App\Models\TradingHub;
use App\Models\TradingHubShip;
use Illuminate\Support\Collection;

class PlayerSpawnService
{
    /**
     * Find an optimal spawn location for a new player
     *
     * Prioritizes:
     * 1. "Super hubs" — systems with all services (trading, shipyard, salvage, cartography, warp gates)
     * 2. Best available candidate upgraded to super hub if none exist naturally
     * 3. Fallback chain for sparse galaxies
     */
    public function findOptimalSpawnLocation(Galaxy $galaxy): ?PointOfInterest
    {
        // First: try to find natural super hub candidates
        $superHubs = $this->findSuperHubCandidates($galaxy);

        if ($superHubs->isNotEmpty()) {
            $scoredCandidates = $this->scoreAndSort($superHubs, $galaxy);
            $topCandidates = $scoredCandidates->take(3);
            $spawnStar = $topCandidates->random()['star'];
            $this->ensureRichStarSystem($spawnStar);

            return $spawnStar;
        }

        // Fallback: find the best inhabited star with a trading hub and upgrade it
        $candidateStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_hidden', false)
            ->where('is_inhabited', true)
            ->whereHas('tradingHub', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($candidateStars->isNotEmpty()) {
            $scoredCandidates = $this->scoreAndSort($candidateStars, $galaxy);
            $best = $scoredCandidates->first()['star'];
            $this->ensureSuperHub($best, $galaxy);

            return $best;
        }

        // Fallback: any inhabited star, upgrade it
        $candidateStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_hidden', false)
            ->where('is_inhabited', true)
            ->get();

        if ($candidateStars->isNotEmpty()) {
            $best = $candidateStars->first();
            $this->ensureSuperHub($best, $galaxy);

            return $best;
        }

        // Last resort: any star
        return PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_hidden', false)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Find systems that qualify as "super hubs" — all essential services present
     */
    public function findSuperHubCandidates(Galaxy $galaxy): Collection
    {
        return PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_hidden', false)
            ->where('is_inhabited', true)
            ->whereHas('tradingHub', function ($query) {
                $query->where('is_active', true)
                    ->where('has_salvage_yard', true)
                    ->whereHas('ships', function ($q) {
                        $q->where('quantity', '>', 0);
                    });
            })
            ->whereHas('stellarCartographer')
            ->whereHas('outgoingGates', function ($query) {
                $query->where('is_hidden', false);
            })
            ->get();
    }

    /**
     * Upgrade a POI to super hub status by ensuring all required services exist
     */
    public function ensureSuperHub(PointOfInterest $poi, Galaxy $galaxy): void
    {
        // Ensure trading hub exists
        $tradingHub = $poi->tradingHub;
        if (! $tradingHub) {
            $tradingHub = TradingHub::create([
                'poi_id' => $poi->id,
                'name' => $poi->name.' Trading Post',
                'type' => 'standard',
                'gate_count' => $poi->outgoingGates()->count(),
                'tax_rate' => 8.00,
                'is_active' => true,
                'has_salvage_yard' => true,
                'services' => ['shipyard', 'salvage', 'upgrades', 'plans', 'cartography'],
            ]);
            $poi->unsetRelation('tradingHub');
        } else {
            $updates = [];

            if (! $tradingHub->has_salvage_yard) {
                $updates['has_salvage_yard'] = true;
            }

            $services = $tradingHub->services ?? [];
            $requiredServices = ['shipyard', 'salvage', 'upgrades', 'plans', 'cartography'];
            $missingServices = array_diff($requiredServices, $services);
            if (! empty($missingServices)) {
                $updates['services'] = array_values(array_unique(array_merge($services, $requiredServices)));
            }

            if (! empty($updates)) {
                $tradingHub->update($updates);
            }
        }

        // Ensure starter ship is available (reuse existing logic)
        $this->ensureStarterShipAvailable($poi, $galaxy);

        // Ensure stellar cartographer exists
        if (! $poi->stellarCartographer) {
            StellarCartographer::create([
                'poi_id' => $poi->id,
                'name' => $poi->name.' Star Charts',
                'is_active' => true,
                'chart_base_price' => config('game_config.star_charts.base_price', 1000),
                'markup_multiplier' => 1.50,
            ]);
            $poi->unsetRelation('stellarCartographer');
        }

        // Ensure mineral inventory is populated
        $tradingHub = $poi->tradingHub;
        if ($tradingHub && ! $tradingHub->inventories()->exists()) {
            app(TradingService::class)->ensureInventoryPopulated($tradingHub);
        }

        // Ensure the star system has a rich planetary system for the tutorial
        $this->ensureRichStarSystem($poi);
    }

    /**
     * Check if a POI qualifies as a super hub
     */
    public function isSuperHub(PointOfInterest $star): bool
    {
        if (! $star->tradingHub || ! $star->tradingHub->is_active) {
            return false;
        }

        if (! $star->tradingHub->has_salvage_yard) {
            return false;
        }

        if (! $star->tradingHub->hasShipyard()) {
            return false;
        }

        if (! $star->stellarCartographer) {
            return false;
        }

        if ($star->outgoingGates()->where('is_hidden', false)->count() < 1) {
            return false;
        }

        return true;
    }

    /**
     * Calculate a "spawn friendliness" score for a star system
     * Higher score = better starting location
     */
    private function calculateSpawnScore(PointOfInterest $star, Galaxy $galaxy): int
    {
        $score = 0;

        // +50 points: Has active trading hub (critical for trading)
        if ($star->tradingHub && $star->tradingHub->is_active) {
            $score += 50;

            // +10 bonus: Trading hub has shipyard
            if ($star->tradingHub->hasShipyard()) {
                $score += 10;
            }

            // +10 bonus: Has salvage yard
            if ($star->tradingHub->has_salvage_yard) {
                $score += 10;
            }
        }

        // +15 bonus: Has stellar cartographer
        if ($star->stellarCartographer) {
            $score += 15;
        }

        // Warp gate connectivity (more gates = better connectivity)
        $gateCount = $star->outgoingGates()->where('is_hidden', false)->count();
        $score += $gateCount * 10; // +10 per gate

        // Bonus for major hubs (5+ gates)
        if ($gateCount >= 5) {
            $score += 20;
        }

        // Check proximity to other trading hubs
        $nearbyHubCount = $this->countNearbyTradingHubs($star, $galaxy, 200);
        $score += $nearbyHubCount * 5; // +5 per nearby hub

        // Bonus for being in a dense area (more exploration opportunities)
        if ($nearbyHubCount >= 3) {
            $score += 15;
        }

        // +5: Inhabited system (generally safer, more services)
        if ($star->is_inhabited) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Score and sort candidates by spawn friendliness
     */
    private function scoreAndSort(Collection $candidates, Galaxy $galaxy): Collection
    {
        return $candidates->map(function ($star) use ($galaxy) {
            return [
                'star' => $star,
                'score' => $this->calculateSpawnScore($star, $galaxy),
            ];
        })
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Count how many trading hubs are within a certain distance
     */
    private function countNearbyTradingHubs(PointOfInterest $star, Galaxy $galaxy, float $maxDistance): int
    {
        $tradingHubStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('id', '!=', $star->id)
            ->whereHas('tradingHub', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        return $tradingHubStars->filter(function ($otherStar) use ($star, $maxDistance) {
            $distance = sqrt(
                pow($otherStar->x - $star->x, 2) +
                pow($otherStar->y - $star->y, 2)
            );

            return $distance <= $maxDistance;
        })->count();
    }

    /**
     * Get a debug report about a spawn location
     */
    public function getSpawnLocationReport(PointOfInterest $star, Galaxy $galaxy): array
    {
        $gateCount = $star->outgoingGates()->where('is_hidden', false)->count();
        $nearbyHubs = $this->countNearbyTradingHubs($star, $galaxy, 200);
        $score = $this->calculateSpawnScore($star, $galaxy);

        return [
            'name' => $star->name,
            'coordinates' => "({$star->x}, {$star->y})",
            'inhabited' => $star->is_inhabited,
            'has_trading_hub' => $star->tradingHub && $star->tradingHub->is_active,
            'has_shipyard' => $star->tradingHub && $star->tradingHub->hasShipyard(),
            'has_salvage_yard' => $star->tradingHub && $star->tradingHub->has_salvage_yard,
            'has_cartographer' => (bool) $star->stellarCartographer,
            'is_super_hub' => $this->isSuperHub($star),
            'warp_gates' => $gateCount,
            'nearby_hubs' => $nearbyHubs,
            'spawn_score' => $score,
            'rating' => $this->getRating($score),
        ];
    }

    private const ROMAN_NUMERALS = [
        '', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
        'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX',
    ];

    private const TARGET_PLANET_COUNT = 12;

    private const MIN_GAS_GIANT_MOONS = 4;

    /**
     * Planet type distribution by orbital zone for spawn systems.
     * Inner (1-4), Mid (5-8), Outer (9-12).
     */
    private const SPAWN_INNER_TYPES = [
        PointOfInterestType::TERRESTRIAL,
        PointOfInterestType::SUPER_EARTH,
        PointOfInterestType::OCEAN,
        PointOfInterestType::LAVA,
    ];

    private const SPAWN_MID_TYPES = [
        PointOfInterestType::TERRESTRIAL,
        PointOfInterestType::GAS_GIANT,
        PointOfInterestType::SUPER_EARTH,
        PointOfInterestType::OCEAN,
    ];

    private const SPAWN_OUTER_TYPES = [
        PointOfInterestType::GAS_GIANT,
        PointOfInterestType::ICE_GIANT,
        PointOfInterestType::ICE_GIANT,
        PointOfInterestType::DWARF_PLANET,
    ];

    /**
     * Ensure the spawn star has a rich planetary system for the tutorial.
     *
     * Guarantees:
     * - Exactly 12 planets
     * - At least one gas giant with 4+ moons
     * - An asteroid belt
     */
    public function ensureRichStarSystem(PointOfInterest $star): void
    {
        $existingPlanets = PointOfInterest::where('parent_poi_id', $star->id)
            ->whereIn('type', [
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::SUPER_EARTH,
                PointOfInterestType::OCEAN,
                PointOfInterestType::LAVA,
                PointOfInterestType::GAS_GIANT,
                PointOfInterestType::ICE_GIANT,
                PointOfInterestType::HOT_JUPITER,
                PointOfInterestType::CHTHONIC,
                PointOfInterestType::DWARF_PLANET,
                PointOfInterestType::PLANET,
            ])
            ->orderBy('orbital_index')
            ->get();

        $currentCount = $existingPlanets->count();
        $highestOrbital = $existingPlanets->max('orbital_index') ?? 0;

        // Generate planets to reach exactly 12
        if ($currentCount < self::TARGET_PLANET_COUNT) {
            $needed = self::TARGET_PLANET_COUNT - $currentCount;
            for ($i = 0; $i < $needed; $i++) {
                $orbitalIndex = $highestOrbital + $i + 1;
                $type = $this->getSpawnPlanetType($orbitalIndex);
                $size = $this->getSpawnPlanetSize($type);

                $planet = PointOfInterest::create([
                    'galaxy_id' => $star->galaxy_id,
                    'parent_poi_id' => $star->id,
                    'orbital_index' => $orbitalIndex,
                    'type' => $type,
                    'status' => PointOfInterestStatus::ACTIVE,
                    'x' => $star->x,
                    'y' => $star->y,
                    'name' => $star->name.' '.(self::ROMAN_NUMERALS[$orbitalIndex] ?? (string) $orbitalIndex),
                    'attributes' => [
                        'orbital_distance' => $orbitalIndex * 10 + ($orbitalIndex % 6),
                        'size' => $size,
                    ],
                    'is_hidden' => false,
                    'is_inhabited' => false,
                    'region' => $star->region,
                ]);

                $existingPlanets->push($planet);
            }
        }

        // Ensure at least one gas giant exists
        $gasGiant = $existingPlanets->first(function ($planet) {
            return $planet->type === PointOfInterestType::GAS_GIANT
                || (is_int($planet->type) && $planet->type === PointOfInterestType::GAS_GIANT->value);
        });

        if (! $gasGiant) {
            // Convert the highest-orbital-index planet to a gas giant
            $candidate = $existingPlanets->sortByDesc('orbital_index')->first();
            if ($candidate) {
                $candidate->update([
                    'type' => PointOfInterestType::GAS_GIANT,
                    'attributes' => array_merge($candidate->attributes ?? [], ['size' => 'massive']),
                ]);
                $gasGiant = $candidate->fresh();
            }
        }

        // Ensure gas giant has at least 4 moons
        if ($gasGiant) {
            $this->ensureMinimumMoons($gasGiant, self::MIN_GAS_GIANT_MOONS);
        }

        // Ensure asteroid belt exists
        $hasBelt = PointOfInterest::where('parent_poi_id', $star->id)
            ->where('type', PointOfInterestType::ASTEROID_BELT)
            ->exists();

        if (! $hasBelt) {
            $beltIndex = $highestOrbital > 0
                ? 3 + ($existingPlanets->count() % 4)
                : 5;

            PointOfInterest::create([
                'galaxy_id' => $star->galaxy_id,
                'parent_poi_id' => $star->id,
                'orbital_index' => $beltIndex,
                'type' => PointOfInterestType::ASTEROID_BELT,
                'status' => PointOfInterestStatus::ACTIVE,
                'x' => $star->x,
                'y' => $star->y,
                'name' => $star->name.' Asteroid Belt',
                'attributes' => [
                    'orbital_distance' => $beltIndex * 10,
                    'density' => 'moderate',
                ],
                'is_hidden' => false,
                'is_inhabited' => false,
                'region' => $star->region,
            ]);
        }
    }

    /**
     * Ensure a planet has at least the specified number of moons.
     */
    private function ensureMinimumMoons(PointOfInterest $planet, int $minimumMoons): void
    {
        $existingMoons = PointOfInterest::where('parent_poi_id', $planet->id)
            ->where('type', PointOfInterestType::MOON)
            ->count();

        if ($existingMoons >= $minimumMoons) {
            return;
        }

        $needed = $minimumMoons - $existingMoons;
        $highestMoonIndex = PointOfInterest::where('parent_poi_id', $planet->id)
            ->where('type', PointOfInterestType::MOON)
            ->max('orbital_index') ?? 0;

        $moonTypes = ['rocky', 'icy', 'volcanic', 'habitable'];

        for ($i = 0; $i < $needed; $i++) {
            $moonIndex = $highestMoonIndex + $i + 1;
            $moonType = $moonTypes[$moonIndex % count($moonTypes)];
            $size = in_array($moonType, ['habitable', 'forest']) ? 'medium' : ['tiny', 'small', 'medium'][random_int(0, 2)];

            PointOfInterest::create([
                'galaxy_id' => $planet->galaxy_id,
                'parent_poi_id' => $planet->id,
                'orbital_index' => $moonIndex,
                'type' => PointOfInterestType::MOON,
                'status' => PointOfInterestStatus::ACTIVE,
                'x' => $planet->x,
                'y' => $planet->y,
                'name' => $planet->name.'-'.chr(96 + $moonIndex),
                'attributes' => [
                    'orbital_distance' => ($moonIndex * 2) + ($moonIndex % 3),
                    'size' => $size,
                    'moon_type' => $moonType,
                    'habitability_score' => $moonType === 'habitable' ? round(random_int(40, 70) / 100, 2) : 0.0,
                    'habitable' => $moonType === 'habitable',
                ],
                'is_hidden' => false,
                'is_inhabited' => false,
                'region' => $planet->region,
            ]);
        }
    }

    /**
     * Get planet type based on orbital position for spawn systems.
     */
    private function getSpawnPlanetType(int $orbitalIndex): PointOfInterestType
    {
        if ($orbitalIndex <= 4) {
            return self::SPAWN_INNER_TYPES[array_rand(self::SPAWN_INNER_TYPES)];
        }

        if ($orbitalIndex <= 8) {
            return self::SPAWN_MID_TYPES[array_rand(self::SPAWN_MID_TYPES)];
        }

        return self::SPAWN_OUTER_TYPES[array_rand(self::SPAWN_OUTER_TYPES)];
    }

    /**
     * Get planet size string based on type.
     */
    private function getSpawnPlanetSize(PointOfInterestType $type): string
    {
        return match ($type) {
            PointOfInterestType::GAS_GIANT, PointOfInterestType::HOT_JUPITER => 'massive',
            PointOfInterestType::ICE_GIANT, PointOfInterestType::SUPER_EARTH => 'large',
            PointOfInterestType::TERRESTRIAL, PointOfInterestType::OCEAN, PointOfInterestType::LAVA => 'medium',
            default => 'small',
        };
    }

    /**
     * Ensure a free Sparrow-class starter ship is available at the spawn location's trading hub.
     * Creates the trading hub and/or inventory entry if needed.
     */
    public function ensureStarterShipAvailable(PointOfInterest $spawnLocation, Galaxy $galaxy): void
    {
        $starterShip = Ship::where('class', 'starter')
            ->orWhere('attributes->is_starter', true)
            ->first();

        if (! $starterShip) {
            return;
        }

        // Ensure trading hub exists at spawn location
        $tradingHub = $spawnLocation->tradingHub;
        if (! $tradingHub) {
            $tradingHub = TradingHub::create([
                'poi_id' => $spawnLocation->id,
                'name' => $spawnLocation->name.' Trading Post',
                'type' => 'standard',
                'gate_count' => $spawnLocation->outgoingGates()->count(),
                'tax_rate' => 8.00,
                'is_active' => true,
            ]);
        }

        // Ensure starter ship is in stock with price 0
        $existingEntry = TradingHubShip::where('trading_hub_id', $tradingHub->id)
            ->where('ship_id', $starterShip->id)
            ->first();

        if ($existingEntry) {
            if ($existingEntry->quantity < 1) {
                $existingEntry->update(['quantity' => 1, 'current_price' => 0]);
            } elseif ($existingEntry->current_price > 0) {
                $existingEntry->update(['current_price' => 0]);
            }
        } else {
            TradingHubShip::create([
                'trading_hub_id' => $tradingHub->id,
                'ship_id' => $starterShip->id,
                'galaxy_id' => $galaxy->id,
                'quantity' => 1,
                'current_price' => 0,
                'demand_level' => 50,
                'supply_level' => 50,
            ]);
        }
    }

    /**
     * Get a human-readable rating based on spawn score
     */
    private function getRating(int $score): string
    {
        if ($score >= 100) {
            return 'Excellent';
        } elseif ($score >= 80) {
            return 'Very Good';
        } elseif ($score >= 60) {
            return 'Good';
        } elseif ($score >= 40) {
            return 'Fair';
        } elseif ($score >= 20) {
            return 'Poor';
        } else {
            return 'Very Poor';
        }
    }
}

<?php

namespace App\Services;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubShip;

class PlayerSpawnService
{
    /**
     * Find an optimal spawn location for a new player
     *
     * Prioritizes:
     * 1. Inhabited systems with active trading hubs
     * 2. Good warp gate connectivity (3+ gates = major hub)
     * 3. Proximity to other trading hubs (within 200 units)
     * 4. Avoid isolated or dangerous areas
     */
    public function findOptimalSpawnLocation(Galaxy $galaxy): ?PointOfInterest
    {
        // Get all inhabited stars with active trading hubs
        $candidateStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_hidden', false)
            ->where('is_inhabited', true)
            ->whereHas('tradingHub', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($candidateStars->isEmpty()) {
            // Fallback: any inhabited star
            $candidateStars = PointOfInterest::where('galaxy_id', $galaxy->id)
                ->where('type', PointOfInterestType::STAR)
                ->where('is_hidden', false)
                ->where('is_inhabited', true)
                ->get();
        }

        if ($candidateStars->isEmpty()) {
            // Last resort: any star
            return PointOfInterest::where('galaxy_id', $galaxy->id)
                ->where('type', PointOfInterestType::STAR)
                ->where('is_hidden', false)
                ->inRandomOrder()
                ->first();
        }

        // Score each candidate based on player-friendliness
        $scoredCandidates = $candidateStars->map(function ($star) use ($galaxy) {
            return [
                'star' => $star,
                'score' => $this->calculateSpawnScore($star, $galaxy),
            ];
        })
            ->sortByDesc('score')
            ->values();

        // Return one of the top 3 candidates randomly (adds variety while ensuring quality)
        $topCandidates = $scoredCandidates->take(3);
        $selected = $topCandidates->random();

        return $selected['star'];
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
            'warp_gates' => $gateCount,
            'nearby_hubs' => $nearbyHubs,
            'spawn_score' => $score,
            'rating' => $this->getRating($score),
        ];
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

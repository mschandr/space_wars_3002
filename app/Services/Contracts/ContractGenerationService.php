<?php

namespace App\Services\Contracts;

use App\Models\Commodity;
use App\Models\Contract;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use Illuminate\Support\Str;

/**
 * ContractGenerationService
 *
 * Generates contracts for trading hubs based on economic signals
 * Called from EconomyTickCommand
 */
class ContractGenerationService
{
    /**
     * Generate contracts for a trading hub during economy tick
     *
     * - 2-3 contracts per hub per day (on average)
     * - Based on supply/demand levels
     * - Low demand commodity = transport contract
     * - High demand commodity = supply contract
     *
     * @param TradingHub $hub Hub to generate contracts for
     * @param int $count How many contracts to attempt to generate
     * @return array Array of created contracts
     */
    public function generateContractsForHub(TradingHub $hub, int $count = 2): array
    {
        $contracts = [];

        for ($i = 0; $i < $count; $i++) {
            // Pick a random commodity
            $commodity = Commodity::inRandomOrder()->first();
            if (!$commodity) {
                continue;
            }

            // Get origin POI (trading hub location)
            $origin = $hub->pointOfInterest;
            if (!$origin) {
                continue;
            }

            // Pick random destination in same galaxy
            $destination = PointOfInterest::where('galaxy_id', $origin->galaxy_id)
                ->where('id', '!=', $origin->id)
                ->inRandomOrder()
                ->first();

            if (!$destination) {
                continue;
            }

            // Pick contract type based on current demand at this hub
            $inventory = $hub->inventory()->where('commodity_id', $commodity->id)->first();
            if (!$inventory) {
                continue;
            }

            $type = $inventory->demand_level > 60 ? 'SUPPLY' : 'TRANSPORT';

            // Generate appropriate contract
            try {
                if ($type === 'TRANSPORT') {
                    $contract = $this->generateTransportContract($origin, $destination, $commodity, rand(100, 500));
                } else {
                    $contract = $this->generateSupplyContract($destination, $commodity, rand(200, 1000));
                }

                if ($contract) {
                    $contracts[] = $contract;
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to generate contract for hub {$hub->id}: {$e->getMessage()}");
                continue;
            }
        }

        return $contracts;
    }

    /**
     * Generate a transport contract (move cargo A → B)
     *
     * @param PointOfInterest $origin Source location
     * @param PointOfInterest $destination Target location
     * @param Commodity $commodity What to transport
     * @param int $quantity How much
     * @return Contract
     */
    public function generateTransportContract(PointOfInterest $origin, PointOfInterest $destination, Commodity $commodity, int $quantity): Contract
    {
        $distance = $origin->distanceTo($destination);

        // Reward = 10% of cargo value, scaled by distance
        $base_reward = $commodity->base_value * $quantity * 0.1;
        $distance_factor = max(1, $distance / 1000); // bonus for long hauls
        $reward = (int) ceil($base_reward * $distance_factor);

        // Risk based on distance
        $risk_rating = match (true) {
            $distance > 2000 => 'HIGH',
            $distance > 1000 => 'MEDIUM',
            default => 'LOW',
        };

        return Contract::create([
            'uuid' => Str::uuid(),
            'type' => 'TRANSPORT',
            'status' => 'POSTED',
            'scope' => 'LOCAL',
            'bar_location_id' => $origin->tradingHub->id,
            'issuer_type' => 'SYSTEM',
            'issuer_id' => null,
            'title' => "Transport {$quantity} {$commodity->name} to {$destination->name}",
            'description' => "Haul {$quantity} units of {$commodity->name} from {$origin->name} to {$destination->name}. Payment: {$reward} credits upon delivery.",
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'cargo_manifest' => [
                ['commodity_id' => $commodity->id, 'quantity' => $quantity],
            ],
            'reward_credits' => $reward,
            'risk_rating' => $risk_rating,
            'reputation_min' => 0,
            'active_contract_limit' => 5,
            'posted_at' => now(),
            'expires_at' => now()->addDays(3), // Available for 3 days
            'deadline_at' => now()->addDays(7), // Must complete within 7 days of accepting
            'seed' => rand(1, 999999),
        ]);
    }

    /**
     * Generate a supply contract (deliver materials to destination)
     *
     * @param PointOfInterest $destination Where to deliver
     * @param Commodity $commodity What to supply
     * @param int $quantity How much
     * @return Contract|null
     */
    public function generateSupplyContract(PointOfInterest $destination, Commodity $commodity, int $quantity): ?Contract
    {
        // Pick random origin in same galaxy
        $origin = PointOfInterest::where('galaxy_id', $destination->galaxy_id)
            ->where('id', '!=', $destination->id)
            ->inRandomOrder()
            ->first();

        if (!$origin || !$origin->tradingHub) {
            return null;
        }

        // Reward = 15% of cargo value (higher than transport since destination is dictated)
        $reward = (int) ceil($commodity->base_value * $quantity * 0.15);

        return Contract::create([
            'uuid' => Str::uuid(),
            'type' => 'SUPPLY',
            'status' => 'POSTED',
            'scope' => 'LOCAL',
            'bar_location_id' => $origin->tradingHub->id,
            'issuer_type' => 'SYSTEM',
            'issuer_id' => null,
            'title' => "Supply {$quantity} {$commodity->name} to {$destination->name}",
            'description' => "Deliver {$quantity} units of {$commodity->name} to {$destination->name}. Payment: {$reward} credits upon delivery.",
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'cargo_manifest' => [
                ['commodity_id' => $commodity->id, 'quantity' => $quantity],
            ],
            'reward_credits' => $reward,
            'risk_rating' => 'MEDIUM',
            'reputation_min' => 10,
            'active_contract_limit' => 5,
            'posted_at' => now(),
            'expires_at' => now()->addDays(2), // Supply contracts expire faster
            'deadline_at' => now()->addDays(5), // Shorter deadline than transport
            'seed' => rand(1, 999999),
        ]);
    }
}

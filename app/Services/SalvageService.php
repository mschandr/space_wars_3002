<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use Illuminate\Support\Collection;

class SalvageService
{
    /**
     * Collect salvage from destroyed pirate ships
     *
     * @param  Collection  $destroyedPirateFleet  Collection of destroyed PirateFleet models
     * @return Collection Collection of PirateCargo items available for salvage
     */
    public function collectSalvage(Collection $destroyedPirateFleet): Collection
    {
        $salvageItems = collect();

        foreach ($destroyedPirateFleet as $pirateShip) {
            // Get all cargo from the destroyed ship
            $cargo = $pirateShip->cargo;
            $salvageItems = $salvageItems->merge($cargo);
        }

        return $salvageItems;
    }

    /**
     * Organize salvage by type for display
     *
     * @return array ['minerals' => Collection, 'plans' => Collection]
     */
    public function organizeSalvage(Collection $salvageItems): array
    {
        $minerals = $salvageItems->filter(fn ($item) => $item->mineral_id !== null);
        $plans = $salvageItems->filter(fn ($item) => $item->plan_id !== null);

        // Group minerals by type and sum quantities
        $mineralsSummed = $minerals->groupBy('mineral_id')->map(function ($group) {
            $first = $group->first();

            return [
                'mineral' => $first->mineral,
                'total_quantity' => $group->sum('quantity'),
                'items' => $group,
            ];
        })->values();

        return [
            'minerals' => $mineralsSummed,
            'plans' => $plans,
        ];
    }

    /**
     * Transfer selected salvage items to player
     *
     * @param  array  $selectedMinerals  Array of ['mineral_id' => quantity]
     * @param  array  $selectedPlanIds  Array of plan IDs
     * @return array Result with success status and messages
     */
    public function transferSalvage(
        Player $player,
        PlayerShip $playerShip,
        array $selectedMinerals = [],
        array $selectedPlanIds = []
    ): array {
        $result = [
            'success' => true,
            'minerals_added' => [],
            'plans_added' => [],
            'messages' => [],
        ];

        $cargoSpaceUsed = 0;
        $availableSpace = $playerShip->cargo_hold - $playerShip->current_cargo;

        // Transfer minerals (require cargo space)
        foreach ($selectedMinerals as $mineral) {
            $mineralId = $mineral['mineral_id'];
            $quantity = $mineral['quantity'];

            if ($cargoSpaceUsed + $quantity > $availableSpace) {
                $quantity = $availableSpace - $cargoSpaceUsed;
                if ($quantity <= 0) {
                    $result['messages'][] = "Cargo hold full - couldn't take more minerals";
                    break;
                }
                $result['messages'][] = "Cargo hold full - only took {$quantity} units";
            }

            // Add to player cargo
            $existingCargo = PlayerCargo::where('player_ship_id', $playerShip->id)
                ->where('mineral_id', $mineralId)
                ->first();

            if ($existingCargo) {
                $existingCargo->quantity += $quantity;
                $existingCargo->save();
            } else {
                PlayerCargo::create([
                    'player_ship_id' => $playerShip->id,
                    'mineral_id' => $mineralId,
                    'plan_id' => null,
                    'quantity' => $quantity,
                ]);
            }

            $cargoSpaceUsed += $quantity;
            $result['minerals_added'][] = [
                'mineral_id' => $mineralId,
                'quantity' => $quantity,
            ];
        }

        // Update cargo space
        $playerShip->current_cargo += $cargoSpaceUsed;
        $playerShip->save();

        // Transfer plans (don't consume cargo space)
        foreach ($selectedPlanIds as $planId) {
            // Check if player already has this plan
            if (! $player->plans()->where('plan_id', $planId)->exists()) {
                $player->plans()->attach($planId, [
                    'acquired_at' => now(),
                ]);
                $result['plans_added'][] = $planId;
            } else {
                $result['messages'][] = 'You already have this upgrade plan';
            }
        }

        return $result;
    }

    /**
     * Calculate total cargo space needed for minerals
     *
     * @param  array  $selectedMinerals  Array of ['mineral_id' => id, 'quantity' => qty]
     * @return int Total space needed
     */
    public function calculateSpaceNeeded(array $selectedMinerals): int
    {
        return array_sum(array_column($selectedMinerals, 'quantity'));
    }

    /**
     * Validate salvage selection against cargo capacity
     *
     * @return array ['valid' => bool, 'space_needed' => int, 'space_available' => int]
     */
    public function validateSelection(PlayerShip $playerShip, array $selectedMinerals): array
    {
        $spaceNeeded = $this->calculateSpaceNeeded($selectedMinerals);
        $spaceAvailable = $playerShip->cargo_hold - $playerShip->current_cargo;

        return [
            'valid' => $spaceNeeded <= $spaceAvailable,
            'space_needed' => $spaceNeeded,
            'space_available' => $spaceAvailable,
        ];
    }
}

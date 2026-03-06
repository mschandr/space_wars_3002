<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ContractService
 *
 * Manages contract lifecycle: acceptance, completion, validation
 */
class ContractService
{
    public function __construct(
        private ReputationService $reputationService,
    ) {}

    /**
     * List contracts available at a location's bar
     *
     * @param PointOfInterest $location Bar location
     * @param array $filters {type?, min_reward?, max_risk?}
     * @return Collection
     */
    public function listContractsAtLocation(PointOfInterest $location, array $filters = []): Collection
    {
        $query = Contract::where('bar_location_id', $location->id)->posted();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['min_reward'])) {
            $query->where('reward_credits', '>=', $filters['min_reward']);
        }

        if (isset($filters['max_risk'])) {
            $risk_levels = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3];
            if (isset($risk_levels[$filters['max_risk']])) {
                $query->whereRaw("FIELD(risk_rating, 'LOW', 'MEDIUM', 'HIGH') <= ?", [$risk_levels[$filters['max_risk']]]);
            }
        }

        return $query->get();
    }

    /**
     * Check if player can accept a contract
     *
     * Validation:
     * - Contract must be POSTED
     * - Player reputation >= contract minimum
     * - Player doesn't exceed active contract limit
     *
     * @return array {success: bool, reason?: string}
     */
    public function canAcceptContract(Contract $contract, Player $player): array
    {
        if (!$contract->isPosted()) {
            return ['success' => false, 'reason' => 'Contract is no longer available'];
        }

        $reputation = $this->reputationService->getPlayerReputation($player);
        if ($reputation < $contract->reputation_min) {
            return [
                'success' => false,
                'reason' => "Reputation too low (need {$contract->reputation_min}, have {$reputation})"
            ];
        }

        $active = $player->activeContracts()->count();
        if ($active >= $contract->active_contract_limit) {
            return [
                'success' => false,
                'reason' => "Too many active contracts ({$active}/{$contract->active_contract_limit})"
            ];
        }

        return ['success' => true];
    }

    /**
     * Accept a contract
     *
     * Atomically:
     * - Validate acceptance
     * - Mark contract ACCEPTED
     * - Record event
     *
     * @throws \Exception
     */
    public function acceptContract(Contract $contract, Player $player): Contract
    {
        $canAccept = $this->canAcceptContract($contract, $player);
        if (!$canAccept['success']) {
            throw new \Exception($canAccept['reason']);
        }

        DB::transaction(function () use ($contract, $player) {
            $contract->update([
                'status' => 'ACCEPTED',
                'accepted_by_player_id' => $player->id,
                'accepted_at' => now(),
            ]);

            ContractEvent::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'contract_id' => $contract->id,
                'event_type' => 'ACCEPTED',
                'actor_type' => 'PLAYER',
                'actor_id' => $player->id,
            ]);
        });

        return $contract->refresh();
    }

    /**
     * Complete a contract via cargo delivery
     *
     * Validates:
     * - Contract is ACCEPTED by this player
     * - Player is at destination
     * - Player has correct cargo
     * - Cargo quantity matches manifest
     *
     * Atomically:
     * - Remove cargo from ship
     * - Add cargo to destination hub inventory
     * - Mark contract COMPLETED
     * - Pay reward to player
     * - Record reputation success
     * - Record event
     *
     * @param Contract $contract Contract to complete
     * @param Player $player Player completing contract
     * @param array $cargo_delivered {commodity_id: quantity, ...}
     *
     * @throws \Exception
     */
    public function completeContract(Contract $contract, Player $player, array $cargo_delivered): Contract
    {
        if (!$contract->isAccepted() || $contract->accepted_by_player_id !== $player->id) {
            throw new \Exception('You have not accepted this contract');
        }

        if ($player->current_poi_id !== $contract->destination_location_id) {
            throw new \Exception('You are not at the contract destination');
        }

        // Validate cargo matches manifest
        $this->validateCargoDelivery($contract, $cargo_delivered);

        DB::transaction(function () use ($contract, $player, $cargo_delivered) {
            // NOTE: Cargo removal/addition deferred to Phase N
            // For now, just complete the contract and pay reward

            // Mark complete
            $contract->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Pay reward
            $player->addCredits($contract->reward_credits);

            // Record success reputation
            $this->reputationService->recordSuccess($player, $contract);

            // Record event
            ContractEvent::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'contract_id' => $contract->id,
                'event_type' => 'COMPLETED',
                'actor_type' => 'PLAYER',
                'actor_id' => $player->id,
                'payload' => [
                    'reward_credits' => $contract->reward_credits,
                    'cargo_delivered' => $cargo_delivered,
                ],
            ]);
        });

        return $contract->refresh();
    }

    /**
     * Validate cargo matches contract manifest exactly
     *
     * @throws \Exception
     */
    private function validateCargoDelivery(Contract $contract, array $cargo_delivered): void
    {
        $manifest = collect($contract->cargo_manifest)->keyBy('commodity_id');
        $delivered = collect($cargo_delivered);

        foreach ($manifest as $commodity_id => $manifest_item) {
            if (!isset($cargo_delivered[$commodity_id])) {
                throw new \Exception("Missing commodity {$commodity_id} in delivery");
            }

            $delivered_qty = $cargo_delivered[$commodity_id];
            $manifest_qty = $manifest_item['quantity'];

            if ($delivered_qty != $manifest_qty) {
                throw new \Exception("Quantity mismatch for commodity {$commodity_id} (expected {$manifest_qty}, delivered {$delivered_qty})");
            }
        }

        if (count($cargo_delivered) > count($manifest)) {
            throw new \Exception('Extra cargo in delivery (not in contract)');
        }
    }
}

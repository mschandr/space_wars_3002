<?php

namespace App\Http\Controllers\Api;

use App\Models\Contract;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\Contracts\ContractService;
use App\Services\Contracts\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContractController extends BaseApiController
{
    public function __construct(
        private ContractService $contractService,
        private ReputationService $reputationService,
    ) {}

    /**
     * List available contracts at a trading hub
     *
     * GET /api/trading-hubs/{uuid}/contracts
     *
     * Query params:
     *   ?type=TRANSPORT|SUPPLY
     *   ?min_reward=5000
     *   ?max_risk=MEDIUM
     */
    public function listContracts(Request $request, string $hub_uuid): Response
    {
        $hub = PointOfInterest::where('uuid', $hub_uuid)->firstOrFail();

        $filters = [];
        if ($request->has('type')) {
            $filters['type'] = $request->input('type');
        }
        if ($request->has('min_reward')) {
            $filters['min_reward'] = (int) $request->input('min_reward');
        }
        if ($request->has('max_risk')) {
            $filters['max_risk'] = $request->input('max_risk');
        }

        $contracts = $this->contractService->listContractsAtLocation($hub, $filters);

        return response()->json([
            'data' => $contracts->map(fn ($contract) => $this->formatContract($contract)),
            'meta' => [
                'total' => $contracts->count(),
                'location' => $hub->name,
            ],
        ]);
    }

    /**
     * Accept a contract
     *
     * POST /api/contracts/{uuid}/accept
     */
    public function acceptContract(Request $request, string $contract_uuid): Response
    {
        $contract = Contract::where('uuid', $contract_uuid)->firstOrFail();
        $player = $this->resolvePlayer($request);

        try {
            $accepted = $this->contractService->acceptContract($contract, $player);

            return response()->json([
                'data' => $this->formatContract($accepted),
                'message' => "Contract accepted. You have until {$accepted->deadline_at->format('Y-m-d H:i')} to deliver.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deliver cargo and complete contract
     *
     * POST /api/contracts/{uuid}/deliver
     *
     * Request body:
     * {
     *   "cargo": {
     *     "1": 200,  // commodity_id: quantity
     *     "2": 150
     *   }
     * }
     */
    public function deliverCargo(Request $request, string $contract_uuid): Response
    {
        $contract = Contract::where('uuid', $contract_uuid)->firstOrFail();
        $player = $this->resolvePlayer($request);

        $request->validate([
            'cargo' => 'required|array',
            'cargo.*' => 'required|integer|min:0',
        ]);

        try {
            $completed = $this->contractService->completeContract(
                $contract,
                $player,
                $request->input('cargo', [])
            );

            $player->refresh();
            $reputation = $this->reputationService->getPlayerReputation($player);

            return response()->json([
                'data' => $this->formatContract($completed),
                'message' => "Contract completed! {$completed->reward_credits} credits awarded.",
                'player' => [
                    'credits' => $player->credits,
                    'reputation' => $reputation,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get player's contracts
     *
     * GET /api/players/{uuid}/contracts?status=ACCEPTED|COMPLETED
     */
    public function getMyContracts(Request $request, string $player_uuid): Response
    {
        $player = Player::where('uuid', $player_uuid)->firstOrFail();

        $query = $player->contracts();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $contracts = $query->orderBy('created_at', 'desc')->get();

        $active_count = $player->activeContracts()->count();
        $completed_count = $player->contracts()->where('status', 'COMPLETED')->count();
        $failed_count = $player->contracts()->where('status', 'FAILED')->count();

        return response()->json([
            'data' => $contracts->map(fn ($contract) => $this->formatContractForPlayer($contract)),
            'meta' => [
                'total' => $contracts->count(),
                'active' => $active_count,
                'completed' => $completed_count,
                'failed' => $failed_count,
            ],
        ]);
    }

    /**
     * Get player's contract reputation
     *
     * GET /api/players/{uuid}/reputation
     */
    public function getReputation(Request $request, string $player_uuid): Response
    {
        $player = Player::where('uuid', $player_uuid)->firstOrFail();
        $reputation = $player->contractReputation;

        // Create if doesn't exist
        if (!$reputation) {
            $reputation = \App\Models\PlayerContractReputation::create([
                'player_id' => $player->id,
                'reliability_score' => 50,
            ]);
        }

        return response()->json([
            'data' => [
                'reliability_score' => $reputation->reliability_score,
                'status_tier' => $reputation->status_tier,
                'completed_count' => $reputation->completed_count,
                'failed_count' => $reputation->failed_count,
                'abandoned_count' => $reputation->abandoned_count,
            ],
        ]);
    }

    /**
     * Format contract for listing view
     */
    private function formatContract(Contract $contract): array
    {
        return [
            'uuid' => $contract->uuid,
            'type' => $contract->type,
            'status' => $contract->status,
            'title' => $contract->title,
            'description' => $contract->description,
            'origin' => [
                'uuid' => $contract->originLocation->uuid,
                'name' => $contract->originLocation->name,
            ],
            'destination' => [
                'uuid' => $contract->destinationLocation->uuid,
                'name' => $contract->destinationLocation->name,
            ],
            'cargo_manifest' => $contract->cargo_manifest,
            'reward_credits' => $contract->reward_credits,
            'risk_rating' => $contract->risk_rating,
            'reputation_required' => $contract->reputation_min,
            'posted_at' => $contract->posted_at,
            'expires_at' => $contract->expires_at,
            'accepted_at' => $contract->accepted_at,
            'deadline_at' => $contract->deadline_at,
            'completed_at' => $contract->completed_at,
        ];
    }

    /**
     * Format contract for player's contract list
     */
    private function formatContractForPlayer(Contract $contract): array
    {
        $formatted = $this->formatContract($contract);

        // Add time remaining info
        if ($contract->isAccepted()) {
            $formatted['time_remaining_hours'] = $contract->deadline_at->diffInHours(now());
        }

        return $formatted;
    }
}

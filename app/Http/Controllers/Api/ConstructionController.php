<?php

namespace App\Http\Controllers\Api;

use App\Models\Blueprint;
use App\Models\ConstructionJob;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\TradingHub;
use App\Services\Economy\ConstructionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * ConstructionController
 *
 * Handles construction job endpoints:
 * - List available blueprints at a trading hub
 * - Start a construction job
 * - List player's construction jobs
 */
class ConstructionController extends BaseApiController
{
    public function __construct(
        private readonly ConstructionService $constructionService,
    ) {}

    /**
     * List available blueprints for a trading hub
     *
     * GET /api/trading-hubs/{uuid}/blueprints
     */
    public function listAvailableBlueprints(string $uuid): JsonResponse
    {
        $hub = $this->findTradingHub($uuid);

        if (!$hub) {
            return $this->notFound('Trading hub not found');
        }

        // For now, all hubs have all blueprints (has_plans=true)
        // In future: filter by hub->has_plans and specific blueprint lists
        $blueprints = Blueprint::all();

        $blueprintData = $blueprints->map(function (Blueprint $blueprint) use ($hub) {
            $missing = $blueprint->canBuildAt($hub);
            $canBuild = empty($missing);

            return [
                'uuid' => $blueprint->uuid,
                'code' => $blueprint->code,
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'type' => $blueprint->type,
                'output_item_code' => $blueprint->output_item_code,
                'build_time_seconds' => $blueprint->build_time_ticks,
                'can_build' => $canBuild,
                'inputs' => $blueprint->getInputsWithCommodities()->map(fn ($input) => [
                    'commodity_id' => $input->commodity_id,
                    'commodity' => [
                        'uuid' => $input->commodity->uuid,
                        'code' => $input->commodity->code,
                        'name' => $input->commodity->name,
                    ],
                    'qty_required' => $input->qty_required,
                ])->toArray(),
                'shortages' => $missing,
            ];
        })->toArray();

        return $this->success($blueprintData, 'Blueprints retrieved');
    }

    /**
     * Start a construction job
     *
     * POST /api/trading-hubs/{uuid}/build
     *
     * Request body:
     * {
     *   "player_uuid": "...",
     *   "ship_uuid": "...",
     *   "blueprint_uuid": "...",
     *   "quantity": 1
     * }
     */
    public function startConstruction(Request $request, string $uuid): JsonResponse
    {
        // Validate input
        try {
            $validated = $request->validate([
                'player_uuid' => ['required', 'string'],
                'ship_uuid' => ['required', 'string'],
                'blueprint_uuid' => ['required', 'string'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Find trading hub
        $hub = $this->findTradingHub($uuid);
        if (!$hub) {
            return $this->notFound('Trading hub not found');
        }

        // Resolve player
        $player = Player::where('uuid', $validated['player_uuid'])->first();
        if (!$player) {
            return $this->notFound('Player not found');
        }

        // Verify player owns the ship
        $ship = PlayerShip::where('uuid', $validated['ship_uuid'])
            ->where('player_id', $player->id)
            ->first();

        if (!$ship) {
            return $this->notFound('Ship not found or does not belong to player');
        }

        // Find blueprint
        $blueprint = Blueprint::where('uuid', $validated['blueprint_uuid'])->first();
        if (!$blueprint) {
            return $this->notFound('Blueprint not found');
        }

        // Attempt construction
        $result = $this->constructionService->build(
            player: $player,
            ship: $ship,
            hub: $hub,
            blueprint: $blueprint,
            quantity: $validated['quantity']
        );

        if (!$result['success']) {
            return $this->error(
                $result['message'],
                'CONSTRUCTION_FAILED',
                [
                    'shortages' => $result['shortages'],
                ]
            );
        }

        // Load the created job
        $job = ConstructionJob::where('uuid', $result['job_uuid'])->first();

        return $this->success([
            'job_uuid' => $job->uuid,
            'blueprint' => [
                'uuid' => $blueprint->uuid,
                'name' => $blueprint->name,
            ],
            'quantity' => $job->quantity,
            'started_at' => $job->started_at->toIso8601String(),
            'completes_at' => $job->completes_at->toIso8601String(),
            'status' => $job->status,
        ], 'Construction started', 201);
    }

    /**
     * List player's construction jobs
     *
     * GET /api/players/{uuid}/construction-jobs?status=PENDING
     */
    public function listJobs(Request $request, string $uuid): JsonResponse
    {
        // Resolve player
        $player = Player::where('uuid', $uuid)->first();
        if (!$player) {
            return $this->notFound('Player not found');
        }

        // Build query
        $query = $player->constructionJobs();

        // Filter by status if provided
        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        // Paginate results
        $jobs = $query->with('blueprint', 'tradingHub')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Format response
        $data = $jobs->map(function (ConstructionJob $job) {
            $timeRemaining = max(0, $job->completes_at->diffInSeconds(now()));

            return [
                'uuid' => $job->uuid,
                'blueprint' => [
                    'uuid' => $job->blueprint->uuid,
                    'name' => $job->blueprint->name,
                ],
                'trading_hub' => [
                    'uuid' => $job->tradingHub->uuid,
                    'name' => $job->tradingHub->name,
                ],
                'quantity' => $job->quantity,
                'status' => $job->status,
                'started_at' => $job->started_at->toIso8601String(),
                'completes_at' => $job->completes_at->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
                'time_remaining_seconds' => $timeRemaining,
                'output_item_code' => $job->output_item_code,
            ];
        })->toArray();

        return $this->success([
            'jobs' => $data,
            'pagination' => [
                'total' => $jobs->total(),
                'count' => $jobs->count(),
                'per_page' => $jobs->perPage(),
                'current_page' => $jobs->currentPage(),
                'total_pages' => $jobs->lastPage(),
            ],
        ], 'Construction jobs retrieved');
    }
}

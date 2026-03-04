<?php

namespace App\Services\Economy;

use App\Models\Blueprint;
use App\Models\ConstructionJob;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ConstructionService
 *
 * Handles construction job creation and completion.
 * Integrates with LedgerService for input consumption tracking.
 */
class ConstructionService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Start a construction job
     *
     * @return array ['success' => bool, 'message' => string, 'job_uuid' => string|null, 'completes_at' => datetime|null, 'shortages' => []]
     */
    public function build(
        Player $player,
        PlayerShip $ship,
        TradingHub $hub,
        Blueprint $blueprint,
        int $quantity = 1
    ): array {
        // Verify ship is at hub
        if ($ship->current_poi_id !== $hub->pointOfInterest->id) {
            return [
                'success' => false,
                'message' => 'Ship is not at this trading hub',
                'job_uuid' => null,
                'completes_at' => null,
                'shortages' => [],
            ];
        }

        // Resolve galaxy
        $galaxy = $hub->pointOfInterest->galaxy;

        // Get blueprint inputs
        $inputs = $blueprint->getInputsWithCommodities();

        // Check if quantity is valid
        if ($quantity < 1) {
            return [
                'success' => false,
                'message' => 'Quantity must be at least 1',
                'job_uuid' => null,
                'completes_at' => null,
                'shortages' => [],
            ];
        }

        // Execute transaction
        return DB::transaction(function () use ($player, $ship, $hub, $blueprint, $quantity, $inputs, $galaxy) {
            // Lock all input inventory rows in ascending mineral_id order (deadlock prevention)
            $inventoryRows = [];
            $shortages = [];

            foreach ($inputs->sortBy('commodity_id') as $input) {
                $inventory = TradingHubInventory::where('trading_hub_id', $hub->id)
                    ->where('mineral_id', $input->commodity_id)
                    ->lockForUpdate()
                    ->first();

                if (!$inventory) {
                    $shortages[] = [
                        'commodity' => $input->commodity,
                        'required_per_unit' => $input->qty_required,
                        'total_required' => $input->qty_required * $quantity,
                        'available' => 0,
                        'shortfall' => $input->qty_required * $quantity,
                    ];
                    continue;
                }

                $totalRequired = $input->qty_required * $quantity;

                if ($inventory->on_hand_qty < $totalRequired) {
                    $shortages[] = [
                        'commodity' => $input->commodity,
                        'required_per_unit' => $input->qty_required,
                        'total_required' => $totalRequired,
                        'available' => $inventory->on_hand_qty,
                        'shortfall' => $totalRequired - $inventory->on_hand_qty,
                    ];
                }

                $inventoryRows[] = $inventory;
            }

            // If any shortages, fail and return them
            if (!empty($shortages)) {
                return [
                    'success' => false,
                    'message' => 'Insufficient resources for construction',
                    'job_uuid' => null,
                    'completes_at' => null,
                    'shortages' => $shortages,
                ];
            }

            // Generate correlation ID for this construction
            $correlationId = Str::uuid()->toString();

            // Record construction in ledger (creates negative-delta entries for each input)
            $ledgerEntries = $this->ledgerService->recordConstruction(
                galaxy: $galaxy,
                hub: $hub,
                blueprint: $blueprint,
                actorId: $player->id,
                correlationId: $correlationId,
                metadata: [
                    'quantity' => $quantity,
                ]
            );

            // Apply ledger entries to inventory (atomic batch operation)
            $this->inventoryService->applyLedgerBatch($ledgerEntries);

            // Calculate completion time
            $completesAt = now()->addSeconds($blueprint->build_time_ticks);

            // Snapshot the consumed inputs for the job record
            $inputsConsumed = $inputs->map(function ($input) use ($quantity) {
                return [
                    'commodity_id' => $input->commodity_id,
                    'qty_each' => $input->qty_required,
                    'total_qty' => $input->qty_required * $quantity,
                ];
            })->toArray();

            // Create construction job
            $job = ConstructionJob::create([
                'uuid' => Str::uuid(),
                'galaxy_id' => $galaxy->id,
                'trading_hub_id' => $hub->id,
                'player_id' => $player->id,
                'blueprint_id' => $blueprint->id,
                'quantity' => $quantity,
                'status' => 'PENDING',
                'inputs_consumed' => $inputsConsumed,
                'output_item_code' => $blueprint->output_item_code,
                'started_at' => now(),
                'completes_at' => $completesAt,
            ]);

            return [
                'success' => true,
                'message' => "Construction job started for {$quantity}x {$blueprint->name}",
                'job_uuid' => $job->uuid,
                'completes_at' => $completesAt,
                'shortages' => [],
            ];
        });
    }

    /**
     * Complete a matured construction job
     *
     * Phase 3 stub: logs completion, actual item delivery deferred to Phase N+
     */
    public function completeJob(ConstructionJob $job): void
    {
        if (!$job->isPending()) {
            return;
        }

        if (!$job->isMatured()) {
            return;
        }

        $job->status = 'COMPLETE';
        $job->completed_at = now();
        $job->save();

        // TODO: Future phases - implement item delivery
        // - Create player_items table
        // - Call ItemDeliveryService::deliver($job)
        // - Log "Construction complete: {output_item_code} ready for pickup at {hub->name}"
    }
}

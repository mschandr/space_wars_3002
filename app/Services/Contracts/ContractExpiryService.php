<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use Illuminate\Support\Facades\DB;

/**
 * ContractExpiryService
 *
 * Handles scheduled contract expiration and failure processing
 * Called hourly by ContractExpiryCommand
 */
class ContractExpiryService
{
    public function __construct(private ReputationService $reputationService) {}

    /**
     * Process contract expirations and failures
     *
     * - Mark POSTED contracts past expires_at as EXPIRED
     * - Mark ACCEPTED contracts past deadline_at as FAILED
     * - Apply reputation penalties for failures
     *
     * @return array {expired: int, failed: int}
     */
    public function processExpirations(): array
    {
        $now = now();
        $expired_count = 0;
        $failed_count = 0;

        DB::transaction(function () use (&$expired_count, &$failed_count, $now) {
            // Expire POSTED contracts that passed expires_at
            $expired = Contract::where('status', 'POSTED')
                ->where('expires_at', '<=', $now)
                ->get();

            foreach ($expired as $contract) {
                $contract->update(['status' => 'EXPIRED']);

                ContractEvent::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'contract_id' => $contract->id,
                    'event_type' => 'EXPIRED',
                    'actor_type' => 'SYSTEM',
                    'actor_id' => null,
                    'payload' => ['reason' => 'Contract expired without acceptance'],
                ]);

                $expired_count++;
            }

            // Fail ACCEPTED contracts that passed deadline_at
            $overdue = Contract::where('status', 'ACCEPTED')
                ->where('deadline_at', '<=', $now)
                ->get();

            foreach ($overdue as $contract) {
                $player = $contract->acceptedBy;

                $contract->update([
                    'status' => 'FAILED',
                    'failed_at' => now(),
                    'failure_reason' => 'Deadline exceeded',
                ]);

                // Apply reputation penalty
                if ($player) {
                    $this->reputationService->recordFailure($player, $contract, 'Deadline exceeded');
                }

                ContractEvent::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'contract_id' => $contract->id,
                    'event_type' => 'FAILED',
                    'actor_type' => 'SYSTEM',
                    'actor_id' => $player ? $player->id : null,
                    'payload' => ['reason' => 'Deadline exceeded'],
                ]);

                $failed_count++;
            }
        });

        return [
            'expired' => $expired_count,
            'failed' => $failed_count,
        ];
    }
}

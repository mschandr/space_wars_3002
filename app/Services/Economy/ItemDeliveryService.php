<?php

namespace App\Services\Economy;

use App\Models\ConstructionJob;
use App\Models\PlayerItem;
use Illuminate\Support\Str;

/**
 * ItemDeliveryService
 *
 * Delivers constructed items to players via the player_items table.
 * Items are marked as "ready_for_pickup" at the construction hub location.
 */
class ItemDeliveryService
{
    /**
     * Deliver a constructed item to player
     *
     * @return PlayerItem The created player item record
     */
    public function deliver(ConstructionJob $job): PlayerItem
    {
        $item = PlayerItem::create([
            'uuid' => Str::uuid(),
            'player_id' => $job->player_id,
            'trading_hub_id' => $job->trading_hub_id,
            'construction_job_id' => $job->id,
            'item_code' => $job->output_item_code,
            'quantity' => $job->quantity,
            'status' => 'ready_for_pickup',
            'metadata' => [
                'blueprint_id' => $job->blueprint_id,
                'construction_time_ticks' => $job->completes_at->diffInSeconds($job->started_at),
                'completed_at' => now(),
            ],
        ]);

        return $item;
    }
}

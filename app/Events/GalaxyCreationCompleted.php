<?php

namespace App\Events;

use App\Models\Galaxy;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when galaxy creation is completed.
 *
 * Clients can listen to this event to know when the galaxy is ready.
 */
class GalaxyCreationCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $galaxyId;

    public string $galaxyUuid;

    public string $galaxyName;

    public string $status;

    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Galaxy $galaxy)
    {
        $this->galaxyId = $galaxy->id;
        $this->galaxyUuid = $galaxy->uuid;
        $this->galaxyName = $galaxy->name;
        $this->status = $galaxy->status->value ?? 'active';
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('galaxy-creation.'.$this->galaxyId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'galaxy_id' => $this->galaxyId,
            'galaxy_uuid' => $this->galaxyUuid,
            'galaxy_name' => $this->galaxyName,
            'status' => $this->status,
            'timestamp' => $this->timestamp,
        ];
    }
}

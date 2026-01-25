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
     * Initialize the event with data from a Galaxy model and prepare its broadcast payload.
     *
     * Populates the public properties `galaxyId`, `galaxyUuid`, `galaxyName`, `status`, and `timestamp`.
     * The `status` defaults to 'active' if not present on the model and `timestamp` is set to the current time in ISO 8601 format.
     *
     * @param Galaxy $galaxy The Galaxy model to extract event data from.
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
     * Specify the broadcast channels for this event.
     *
     * @return array<int, Channel> An array containing the Channel for the 'galaxy-creation.{galaxyId}' channel.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('galaxy-creation.'.$this->galaxyId),
        ];
    }

    /**
     * Determine the broadcast event name for this event.
     *
     * @return string The broadcast name used for the event, `'completed'`.
     */
    public function broadcastAs(): string
    {
        return 'completed';
    }

    /**
     * Provide the payload sent with the broadcast event.
     *
     * @return array{galaxy_id:int,galaxy_uuid:string,galaxy_name:string,status:string,timestamp:string} Associative array containing the galaxy data included in the broadcast payload.
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
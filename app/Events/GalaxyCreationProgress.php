<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast during galaxy creation to report progress.
 *
 * Clients can listen to this event to show real-time progress updates.
 */
class GalaxyCreationProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $galaxyId;

    public string $step;

    public int $percentage;

    public string $message;

    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(int $galaxyId, int $step, int $percentage, string $message)
    {
        $this->galaxyId = $galaxyId;
        $this->step = (string) $step;
        $this->percentage = $percentage;
        $this->message = $message;
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
        return 'progress';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'galaxy_id' => $this->galaxyId,
            'step' => $this->step,
            'percentage' => $this->percentage,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
        ];
    }
}

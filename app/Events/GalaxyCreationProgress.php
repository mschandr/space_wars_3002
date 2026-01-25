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
     * Initialize the event with galaxy identifier and progress details.
     *
     * @param int    $galaxyId  The ID of the galaxy being created.
     * @param int    $step      The current creation step number.
     * @param int    $percentage The completion percentage (0-100) of the current step.
     * @param string $message   A human-readable message describing the current progress.
     *
     * The constructor also captures the event creation time as an ISO-8601 timestamp stored in `$timestamp`.
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
     * Specify the broadcast channels this event will be sent on.
     *
     * @return array<int, Channel> An array of Channel instances the event will broadcast to.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('galaxy-creation.'.$this->galaxyId),
        ];
    }

    /**
     * Get the broadcast event name used when this event is emitted.
     *
     * @return string The broadcast event name 'progress'.
     */
    public function broadcastAs(): string
    {
        return 'progress';
    }

    /**
     * Payload for the broadcasted event.
     *
     * @return array{galaxy_id:int, step:string, percentage:int, message:string, timestamp:string} Associative array containing:
     *  - `galaxy_id`: Galaxy identifier.
     *  - `step`: Current creation step as a string.
     *  - `percentage`: Completion percentage (0-100).
     *  - `message`: Human-readable status message.
     *  - `timestamp`: ISO 8601 formatted timestamp.
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
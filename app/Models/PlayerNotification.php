<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerNotification extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'player_id',
        'type',
        'severity',
        'title',
        'message',
        'colony_id',
        'poi_id',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the player this notification belongs to
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get the colony related to this notification
     */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /**
     * Get the POI related to this notification
     */
    public function poi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->is_read = true;
        $this->read_at = now();
        $this->save();
    }

    /**
     * Get severity icon
     */
    public function getSeverityIcon(): string
    {
        return match ($this->severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📢',
        };
    }

    /**
     * Get formatted display
     */
    public function getFormattedDisplay(): string
    {
        $icon = $this->getSeverityIcon();

        return "{$icon} {$this->title}: {$this->message}";
    }

    /**
     * Scope to unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to specific severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
}

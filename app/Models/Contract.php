<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'type',
        'status',
        'scope',
        'bar_location_id',
        'issuer_type',
        'issuer_id',
        'title',
        'description',
        'origin_location_id',
        'destination_location_id',
        'cargo_manifest',
        'reward_credits',
        'risk_rating',
        'reputation_min',
        'active_contract_limit',
        'posted_at',
        'expires_at',
        'deadline_at',
        'accepted_by_player_id',
        'accepted_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'seed',
    ];

    protected $casts = [
        'cargo_manifest' => 'array',
        'posted_at' => 'datetime',
        'expires_at' => 'datetime',
        'deadline_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Get the bar location where this contract is posted
     */
    public function barLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'bar_location_id');
    }

    /**
     * Get the origin location for this contract
     */
    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'origin_location_id');
    }

    /**
     * Get the destination location for this contract
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'destination_location_id');
    }

    /**
     * Get the player who accepted this contract
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'accepted_by_player_id');
    }

    /**
     * Get all events for this contract
     */
    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class);
    }

    /**
     * Scope: Posted contracts only
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'POSTED');
    }

    /**
     * Scope: Accepted contracts only
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'ACCEPTED');
    }

    /**
     * Scope: Completed contracts only
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    /**
     * Scope: Contracts by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Contracts at a specific location
     */
    public function scopeAtLocation($query, $location_id)
    {
        return $query->where('bar_location_id', $location_id)->posted();
    }

    /**
     * Scope: Contracts that have expired (POSTED past expires_at)
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())->posted();
    }

    /**
     * Scope: Contracts that are overdue (ACCEPTED past deadline_at)
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline_at', '<=', now())->accepted();
    }

    /**
     * Check if contract is in POSTED status
     */
    public function isPosted(): bool
    {
        return $this->status === 'POSTED';
    }

    /**
     * Check if contract is in ACCEPTED status
     */
    public function isAccepted(): bool
    {
        return $this->status === 'ACCEPTED';
    }

    /**
     * Check if contract is in COMPLETED status
     */
    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    /**
     * Check if contract is in FAILED status
     */
    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    /**
     * Check if contract is in EXPIRED status
     */
    public function isExpired(): bool
    {
        return $this->status === 'EXPIRED';
    }

    /**
     * Check if contract is in CANCELLED status
     */
    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }
}

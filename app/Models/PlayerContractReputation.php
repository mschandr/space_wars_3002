<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerContractReputation extends Model
{
    protected $table = 'player_contract_reputation';

    protected $fillable = [
        'player_id',
        'reliability_score',
        'completed_count',
        'failed_count',
        'abandoned_count',
        'expired_count',
        'failure_penalty',
        'abandonment_penalty',
    ];

    /**
     * Get the player this reputation belongs to
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get status tier based on reliability score
     */
    public function getStatusTierAttribute(): string
    {
        $score = $this->reliability_score;
        if ($score >= 90) return 'LEGENDARY';
        if ($score >= 75) return 'VETERAN';
        if ($score >= 60) return 'TRUSTED';
        if ($score >= 50) return 'NEUTRAL';
        if ($score >= 35) return 'NOVICE';
        return 'SUSPECT';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvPTeamInvitation extends Model
{
    use HasFactory;

    protected $table = 'pvp_team_invitations';

    protected $fillable = [
        'pvp_challenge_id',
        'invited_player_id',
        'invited_by_player_id',
        'side',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function pvpChallenge(): BelongsTo
    {
        return $this->belongsTo(PvPChallenge::class);
    }

    public function invitedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'invited_player_id');
    }

    public function invitedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'invited_by_player_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    public function decline(): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    public function isAttackerSide(): bool
    {
        return $this->side === 'attacker';
    }

    public function isDefenderSide(): bool
    {
        return $this->side === 'defender';
    }
}

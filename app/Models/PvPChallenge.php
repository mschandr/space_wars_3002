<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PvPChallenge extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'pvp_challenges';

    protected $fillable = [
        'uuid',
        'challenger_id',
        'target_id',
        'status',
        'message',
        'wager_credits',
        'max_team_size',
        'challenge_poi_id',
        'challenged_at',
        'responded_at',
        'expires_at',
    ];

    protected $casts = [
        'wager_credits' => 'decimal:2',
        'challenged_at' => 'datetime',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($challenge) {
            if (empty($challenge->challenged_at)) {
                $challenge->challenged_at = now();
            }
            if (empty($challenge->expires_at)) {
                $challenge->expires_at = now()->addMinutes(5); // Challenge expires in 5 minutes
            }
        });
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'challenger_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'target_id');
    }

    public function challengePoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'challenge_poi_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
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

    public function expire(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function complete(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(PvPTeamInvitation::class, 'pvp_challenge_id');
    }

    public function acceptedInvitations(): HasMany
    {
        return $this->hasMany(PvPTeamInvitation::class, 'pvp_challenge_id')->where('status', 'accepted');
    }

    public function getAttackersTeam(): \Illuminate\Support\Collection
    {
        // Challenger + accepted attacker allies
        return collect([$this->challenger])
            ->merge(
                $this->acceptedInvitations()
                    ->where('side', 'attacker')
                    ->with('invitedPlayer')
                    ->get()
                    ->pluck('invitedPlayer')
            );
    }

    public function getDefendersTeam(): \Illuminate\Support\Collection
    {
        // Target + accepted defender allies
        return collect([$this->target])
            ->merge(
                $this->acceptedInvitations()
                    ->where('side', 'defender')
                    ->with('invitedPlayer')
                    ->get()
                    ->pluck('invitedPlayer')
            );
    }
}

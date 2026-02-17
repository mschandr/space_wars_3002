<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CombatSession extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'combat_sessions';

    protected $fillable = [
        'uuid',
        'combat_type',
        'status',
        'current_round',
        'poi_id',
        'victor_type',
        'victor_player_id',
        'combat_log',
        'rewards',
        'pvp_challenge_id',
        'target_colony_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'combat_log' => 'array',
        'rewards' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->started_at)) {
                $session->started_at = now();
            }
        });
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CombatParticipant::class);
    }

    public function attackers(): HasMany
    {
        return $this->hasMany(CombatParticipant::class)->whereIn('side', ['attacker', 'ally_attacker']);
    }

    public function defenders(): HasMany
    {
        return $this->hasMany(CombatParticipant::class)->whereIn('side', ['defender', 'ally_defender']);
    }

    public function poi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class);
    }

    public function victorPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'victor_player_id');
    }

    public function pvpChallenge(): BelongsTo
    {
        return $this->belongsTo(PvPChallenge::class);
    }

    public function targetColony(): BelongsTo
    {
        return $this->belongsTo(Colony::class, 'target_colony_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function complete(string $victorType, ?int $victorPlayerId = null): void
    {
        $this->update([
            'status' => 'completed',
            'victor_type' => $victorType,
            'victor_player_id' => $victorPlayerId,
            'ended_at' => now(),
        ]);
    }

    // TODO: (Unbounded Growth) Combat log appends entries indefinitely with no size limit.
    // A large PvP battle (50 participants x 200 rounds x 5 events) could produce 50,000 entries
    // (~25MB) in a single JSON column. Add a max entry limit (e.g., 500) with array_slice,
    // or move detailed logs to a separate combat_log_entries table.
    public function addLogEntry(array $entry): void
    {
        $log = $this->combat_log ?? [];
        $log[] = $entry;
        $this->update(['combat_log' => $log]);
    }
}

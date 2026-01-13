<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatParticipant extends Model
{
    use HasFactory;

    protected $table = 'combat_participants';

    protected $fillable = [
        'combat_session_id',
        'player_id',
        'player_ship_id',
        'side',
        'starting_hull',
        'current_hull',
        'damage_dealt',
        'damage_taken',
        'survived',
        'xp_earned',
        'credits_earned',
        'loot_received',
    ];

    protected $casts = [
        'survived' => 'boolean',
        'credits_earned' => 'decimal:2',
        'loot_received' => 'array',
    ];

    public function combatSession(): BelongsTo
    {
        return $this->belongsTo(CombatSession::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function playerShip(): BelongsTo
    {
        return $this->belongsTo(PlayerShip::class);
    }

    public function isAttacker(): bool
    {
        return in_array($this->side, ['attacker', 'ally_attacker']);
    }

    public function isDefender(): bool
    {
        return in_array($this->side, ['defender', 'ally_defender']);
    }

    public function isAlive(): bool
    {
        return $this->current_hull > 0;
    }

    public function takeDamage(int $damage): void
    {
        $this->current_hull = max(0, $this->current_hull - $damage);
        $this->damage_taken += $damage;
        $this->survived = $this->current_hull > 0;
        $this->save();
    }

    public function recordDamageDealt(int $damage): void
    {
        $this->damage_dealt += $damage;
        $this->save();
    }

    public function awardRewards(int $xp, float $credits, ?array $loot = null): void
    {
        $this->xp_earned += $xp;
        $this->credits_earned += $credits;

        if ($loot) {
            $currentLoot = $this->loot_received ?? [];
            $this->loot_received = array_merge_recursive($currentLoot, $loot);
        }

        $this->save();

        // Apply rewards to player
        $this->player->addExperience($xp);
        $this->player->addCredits($credits);
    }
}

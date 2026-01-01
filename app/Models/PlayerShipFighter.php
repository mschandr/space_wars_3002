<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerShipFighter extends Model
{
    use HasUuidAndVersion;

    protected $fillable = [
        'uuid',
        'player_ship_id',
        'ship_id',
        'fighter_name',
        'hull',
        'max_hull',
        'weapons',
        'is_deployed',
        'attributes',
    ];

    protected $casts = [
        'hull' => 'integer',
        'max_hull' => 'integer',
        'weapons' => 'integer',
        'is_deployed' => 'boolean',
        'attributes' => 'array',
    ];

    /**
     * Get the carrier ship that owns this fighter
     */
    public function playerShip(): BelongsTo
    {
        return $this->belongsTo(PlayerShip::class);
    }

    /**
     * Get the ship type of this fighter
     */
    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    /**
     * Take damage to the fighter
     */
    public function takeDamage(int $damage): void
    {
        $this->hull = max(0, $this->hull - $damage);
        $this->save();
    }

    /**
     * Repair the fighter
     */
    public function repair(int $amount = null): void
    {
        if ($amount === null) {
            $this->hull = $this->max_hull;
        } else {
            $this->hull = min($this->max_hull, $this->hull + $amount);
        }
        $this->save();
    }

    /**
     * Check if fighter is destroyed
     */
    public function isDestroyed(): bool
    {
        return $this->hull <= 0;
    }

    /**
     * Deploy fighter for combat
     */
    public function deploy(): void
    {
        $this->is_deployed = true;
        $this->save();
    }

    /**
     * Recall fighter from combat
     */
    public function recall(): void
    {
        $this->is_deployed = false;
        $this->save();
    }

    /**
     * Get combat rating for this fighter
     */
    public function getCombatRating(): int
    {
        return ($this->hull + $this->weapons * 2) / 2;
    }
}

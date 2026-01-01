<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PirateFleet extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'captain_id',
        'ship_id',
        'ship_name',
        'hull',
        'max_hull',
        'weapons',
        'speed',
        'warp_drive',
        'cargo_capacity',
        'status',
    ];

    protected $casts = [
        'hull' => 'integer',
        'max_hull' => 'integer',
        'weapons' => 'integer',
        'speed' => 'integer',
        'warp_drive' => 'integer',
        'cargo_capacity' => 'integer',
    ];

    // Relationships
    public function captain(): BelongsTo
    {
        return $this->belongsTo(PirateCaptain::class);
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function cargo(): HasMany
    {
        return $this->hasMany(PirateCargo::class, 'pirate_fleet_id');
    }

    // Combat methods (mirror PlayerShip)
    public function takeDamage(int $damage): void
    {
        $this->hull = max(0, $this->hull - $damage);

        if ($this->hull <= 0) {
            $this->status = 'destroyed';
        }

        $this->save();
    }

    public function getCombatRating(): int
    {
        return (int) (($this->hull / 10) + $this->weapons);
    }

    public function isDestroyed(): bool
    {
        return $this->status === 'destroyed' || $this->hull <= 0;
    }
}

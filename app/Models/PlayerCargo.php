<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerCargo extends Model
{
    protected $fillable = [
        'player_ship_id',
        'mineral_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function playerShip(): BelongsTo
    {
        return $this->belongsTo(PlayerShip::class);
    }

    public function mineral(): BelongsTo
    {
        return $this->belongsTo(Mineral::class);
    }
}

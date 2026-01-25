<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpcCargo extends Model
{
    protected $fillable = [
        'npc_ship_id',
        'mineral_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function npcShip(): BelongsTo
    {
        return $this->belongsTo(NpcShip::class);
    }

    public function mineral(): BelongsTo
    {
        return $this->belongsTo(Mineral::class);
    }
}

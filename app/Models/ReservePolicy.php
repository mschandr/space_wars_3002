<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservePolicy extends Model
{
    use HasUuid;

    protected $fillable = [
        'galaxy_id',
        'commodity_id',
        'min_qty_on_hand',
        'npc_fallback_enabled',
        'npc_price_multiplier',
        'description',
    ];

    protected $casts = [
        'min_qty_on_hand' => 'float',
        'npc_price_multiplier' => 'float',
        'npc_fallback_enabled' => 'boolean',
    ];

    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }
}

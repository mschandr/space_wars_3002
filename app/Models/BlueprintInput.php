<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintInput extends Model
{
    protected $fillable = [
        'blueprint_id',
        'commodity_id',
        'qty_required',
    ];

    protected $casts = [
        'qty_required' => 'decimal:4',
    ];

    /**
     * Get the blueprint
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Get the commodity
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }
}

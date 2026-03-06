<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    use HasUuid;

    protected $table = 'contract_events';

    protected $fillable = [
        'uuid',
        'contract_id',
        'event_type',
        'actor_type',
        'actor_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the contract this event belongs to
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}

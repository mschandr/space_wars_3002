<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ResourceDeposit extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'trading_hub_id',
        'commodity_id',
        'quality',
        'max_extraction_per_tick',
        'total_extracted',
        'max_total_qty',
        'discovered_at',
        'discovered_by_actor_id',
        'discovered_by_actor_type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'quality' => 'integer',
        'max_extraction_per_tick' => 'decimal:4',
        'total_extracted' => 'decimal:4',
        'max_total_qty' => 'decimal:4',
        'discovered_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the galaxy
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the commodity
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * Get the trading hub (optional)
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Scope: only active deposits
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope: only depleted deposits
     */
    public function scopeDepleted($query)
    {
        return $query->where('status', 'DEPLETED');
    }

    /**
     * Check if deposit is depleted
     */
    public function isDepleted(): bool
    {
        if ($this->max_total_qty === null) {
            return false; // No limit
        }
        return $this->total_extracted >= $this->max_total_qty;
    }

    /**
     * Get extraction amount this tick (respects limit)
     */
    public function getExtractionThisTick(): float
    {
        if ($this->isDepleted()) {
            return 0;
        }

        $maxThisTick = (float)$this->max_extraction_per_tick;

        if ($this->max_total_qty !== null) {
            $remaining = (float)$this->max_total_qty - (float)$this->total_extracted;
            $maxThisTick = min($maxThisTick, $remaining);
        }

        return max(0, $maxThisTick);
    }

    /**
     * Record extraction atomically
     * Prevents lost updates under concurrency by using DB::transaction and atomic increment
     */
    public function recordExtraction(float $amount): void
    {
        DB::transaction(function () use ($amount) {
            // Lock row for update
            $deposit = DB::table('resource_deposits')
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            if (!$deposit) {
                return;
            }

            // Calculate new values
            $newTotal = floatval($deposit->total_extracted) + $amount;
            $newStatus = $newTotal >= floatval($deposit->max_total_qty) ? 'DEPLETED' : 'ACTIVE';

            // Update atomically
            DB::table('resource_deposits')
                ->where('id', $this->id)
                ->update([
                    'total_extracted' => $newTotal,
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]);
        });

        // Reload to reflect changes
        $this->refresh();
    }
}

<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalaxyCustomsRecord extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'customs_official_id',
        'total_checks',
        'times_fined',
        'times_bribed',
        'total_bribes_paid',
        'actual_honesty',
        'relationship_score',
    ];

    protected $casts = [
        'actual_honesty' => 'decimal:2',
    ];

    /**
     * Get the galaxy this record belongs to
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the customs official template
     */
    public function customsOfficial(): BelongsTo
    {
        return $this->belongsTo(CustomsOfficial::class);
    }

    /**
     * Get the effective honesty for this official in this galaxy
     * Can diverge from the template if they've been repeatedly bribed
     */
    public function getEffectiveHonesty(): float
    {
        if ($this->actual_honesty !== null) {
            return (float)$this->actual_honesty;
        }
        return (float)($this->customsOfficial->honesty ?? 0.5);
    }

    /**
     * Record a bribe payment (potentially corrupting the official)
     */
    public function recordBribe(int $amount): void
    {
        $this->times_bribed++;
        $this->total_bribes_paid += $amount;

        // Each bribe slightly reduces honesty (official becomes more corrupt)
        if ($this->actual_honesty === null) {
            $this->actual_honesty = $this->customsOfficial->honesty;
        }
        $this->actual_honesty = max(0, (float)$this->actual_honesty - 0.05);

        $this->save();
    }

    /**
     * Record a fine (potentially building resentment)
     */
    public function recordFine(): void
    {
        $this->times_fined++;
        $this->relationship_score -= 10; // Player resents being fined
        $this->save();
    }

    /**
     * Record a successful check (no violations found)
     */
    public function recordSuccessfulCheck(): void
    {
        $this->total_checks++;
        $this->relationship_score += 2; // Slight reputation boost for clean record
        $this->save();
    }
}

<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
     * Atomic update to prevent lost updates under concurrency
     */
    public function recordBribe(int $amount): void
    {
        // Initialize actual_honesty if null
        $currentHonesty = $this->actual_honesty ?? $this->customsOfficial->honesty;
        $newHonesty = max(0, $currentHonesty - 0.05);

        // Atomic update using DB::raw
        DB::table('galaxy_customs_records')
            ->where('id', $this->id)
            ->update([
                'times_bribed' => DB::raw('times_bribed + 1'),
                'total_bribes_paid' => DB::raw('total_bribes_paid + ' . (int)$amount),
                'actual_honesty' => $newHonesty,
                'updated_at' => now(),
            ]);

        // Reload to reflect changes
        $this->refresh();
    }

    /**
     * Record a fine (potentially building resentment)
     * Atomic update to prevent lost updates under concurrency
     */
    public function recordFine(): void
    {
        DB::table('galaxy_customs_records')
            ->where('id', $this->id)
            ->update([
                'times_fined' => DB::raw('times_fined + 1'),
                'relationship_score' => DB::raw('relationship_score - 10'),
                'updated_at' => now(),
            ]);

        // Reload to reflect changes
        $this->refresh();
    }

    /**
     * Record a successful check (no violations found)
     * Atomic update to prevent lost updates under concurrency
     */
    public function recordSuccessfulCheck(): void
    {
        DB::table('galaxy_customs_records')
            ->where('id', $this->id)
            ->update([
                'total_checks' => DB::raw('total_checks + 1'),
                'relationship_score' => DB::raw('relationship_score + 2'),
                'updated_at' => now(),
            ]);

        // Reload to reflect changes
        $this->refresh();
    }
}

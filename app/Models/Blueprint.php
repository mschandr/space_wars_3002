<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blueprint extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'type',
        'output_item_code',
        'build_time_ticks',
    ];

    /**
     * Get the input requirements
     */
    public function inputs(): HasMany
    {
        return $this->hasMany(BlueprintInput::class);
    }

    /**
     * Get input commodities with quantities
     */
    public function getInputsWithCommodities(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->inputs()->with('commodity')->get();
    }

    /**
     * Check if a hub has sufficient resources
     */
    public function canBuildAt(TradingHub $hub): array
    {
        $missing = [];

        foreach ($this->getInputsWithCommodities() as $input) {
            $inventory = $hub->inventories()
                ->where('mineral_id', $input->commodity_id)
                ->first();

            $onHand = $inventory?->on_hand_qty ?? 0;

            if ($onHand < $input->qty_required) {
                $missing[] = [
                    'commodity' => $input->commodity,
                    'required' => $input->qty_required,
                    'available' => $onHand,
                    'shortfall' => $input->qty_required - $onHand,
                ];
            }
        }

        return $missing;
    }

    /**
     * Find by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}

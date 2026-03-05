<?php

namespace App\Services\Trading;

use App\Enums\Trading\CommodityCategory;
use App\Models\Player;
use App\Models\TradingHubInventory;
use Illuminate\Support\Collection;

class CommodityAccessService
{
    /**
     * Filter hub inventory based on player's commodity access level.
     *
     * Rules:
     * 1. Civilian items always visible
     * 2. Industrial items visible if no reputation restriction, or player meets it
     * 3. Black market items ONLY visible if:
     *    - Player's shady interaction count >= threshold
     *    - Player's reputation >= mineral.min_reputation (if set)
     *    - Destination sector security <= mineral.min_sector_security (if set)
     * 4. Black market items are NEVER mentioned in responses if not visible (silent filtering)
     */
    public function filterForPlayer(Collection $inventory, Player $player): Collection
    {
        $shadyThreshold = config('economy.black_market.visibility_threshold', 10);
        $playerShadyCount = $player->getShadyInteractionCount();
        $canSeeBlackMarket = $playerShadyCount >= $shadyThreshold;

        return $inventory->filter(function (TradingHubInventory $item) use ($player, $canSeeBlackMarket) {
            $mineral = $item->mineral;
            $category = $mineral->category ?? CommodityCategory::CIVILIAN;

            // Civilian items always visible
            if ($category === CommodityCategory::CIVILIAN) {
                return true;
            }

            // Industrial items: check reputation if required
            if ($category === CommodityCategory::INDUSTRIAL) {
                if ($mineral->min_reputation === null) {
                    return true;
                }
                return $player->reputation >= $mineral->min_reputation;
            }

            // Black market items: require threshold + reputation + security
            if ($category === CommodityCategory::BLACK) {
                // Must have crossed visibility threshold
                if (!$canSeeBlackMarket) {
                    return false;
                }

                // Check reputation requirement
                if ($mineral->min_reputation !== null && $player->reputation < $mineral->min_reputation) {
                    return false;
                }

                // Check sector security requirement
                if ($mineral->min_sector_security !== null) {
                    // Fail closed: if no POI or no security_level, deny access
                    if ($player->currentPoi === null || $player->currentPoi->security_level === null) {
                        return false;
                    }
                    if ($player->currentPoi->security_level > $mineral->min_sector_security) {
                        return false;
                    }
                }

                return true;
            }

            // Default: hide
            return false;
        });
    }

    /**
     * Filter hub inventory for anonymous/unauthenticated users.
     *
     * Shows only CIVILIAN items. Industrial and black market items are hidden.
     * Used when no valid player context is available (e.g., unauthenticated requests).
     */
    public function filterForAnonymous(Collection $inventory): Collection
    {
        return $inventory->filter(function (TradingHubInventory $item) {
            $mineral = $item->mineral;
            $category = $mineral->category ?? CommodityCategory::CIVILIAN;

            // Only civilian items visible to anonymous users
            return $category === CommodityCategory::CIVILIAN;
        });
    }

    /**
     * Check if a player can see a specific mineral in hub inventory
     */
    public function canAccessMineral(Player $player, TradingHubInventory $inventory): bool
    {
        $collection = collect([$inventory]);
        return $this->filterForPlayer($collection, $player)->isNotEmpty();
    }
}

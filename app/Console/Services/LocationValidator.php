<?php

namespace App\Console\Services;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use App\Models\TradingHub;

class LocationValidator
{
    /**
     * Get the trading hub at the player's current location
     */
    public static function getTradingHub(Player $player): ?TradingHub
    {
        $location = $player->currentLocation;
        if (!$location) {
            return null;
        }

        $star = $location->type === PointOfInterestType::STAR
            ? $location
            : $location->getRootStar();

        return $star?->tradingHub;
    }

    /**
     * Check if the player is at an active trading hub
     */
    public static function isAtTradingHub(Player $player): bool
    {
        $hub = self::getTradingHub($player);
        return $hub && $hub->is_active;
    }

    /**
     * Check if the player is at a trading hub that has plans
     */
    public static function isAtPlansHub(Player $player): bool
    {
        $hub = self::getTradingHub($player);
        return $hub && $hub->is_active && $hub->has_plans;
    }
}

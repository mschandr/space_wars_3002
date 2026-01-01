<?php

namespace App\Services;

use App\Models\PlayerShip;
use Illuminate\Support\Collection;

class EscapeCalculationService
{
    /**
     * Attempt to escape from pirates
     *
     * Player must have BOTH higher speed AND higher warp drive than ALL pirate ships
     * to successfully escape.
     *
     * @param PlayerShip $playerShip
     * @param Collection $pirateFleet Collection of PirateFleet models
     * @return array ['success' => bool, 'message' => string, 'interceptor' => PirateFleet|null]
     */
    public function attemptEscape(PlayerShip $playerShip, Collection $pirateFleet): array
    {
        if ($pirateFleet->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No pirates to escape from.',
                'interceptor' => null,
            ];
        }

        $playerSpeed = $playerShip->ship->speed;

        // Check if player can outrun ALL pirates
        foreach ($pirateFleet as $pirateShip) {
            // Player needs BOTH higher speed AND higher warp drive
            if ($playerSpeed <= $pirateShip->speed || $playerShip->warp_drive <= $pirateShip->warp_drive) {
                return [
                    'success' => false,
                    'message' => "{$pirateShip->ship_name} intercepts you! Their superior " .
                                ($playerSpeed <= $pirateShip->speed ? 'speed' : 'warp drive') .
                                " prevents your escape.",
                    'interceptor' => $pirateShip,
                ];
            }
        }

        return [
            'success' => true,
            'message' => "Your ship's superior speed and warp capabilities allow you to escape!",
            'interceptor' => null,
        ];
    }

    /**
     * Calculate the probability of escape (for display purposes)
     *
     * @param PlayerShip $playerShip
     * @param Collection $pirateFleet
     * @return int Percentage (0-100)
     */
    public function calculateEscapeChance(PlayerShip $playerShip, Collection $pirateFleet): int
    {
        if ($pirateFleet->isEmpty()) {
            return 100;
        }

        $playerSpeed = $playerShip->ship->speed;
        $maxPirateSpeed = $pirateFleet->max('speed');
        $maxPirateWarp = $pirateFleet->max('warp_drive');

        // Need to beat both speed and warp
        $canOutrunSpeed = $playerSpeed > $maxPirateSpeed;
        $canOutrunWarp = $playerShip->warp_drive > $maxPirateWarp;

        if ($canOutrunSpeed && $canOutrunWarp) {
            return 100;
        } elseif (!$canOutrunSpeed && !$canOutrunWarp) {
            return 0;
        } else {
            return 50; // One stat is better, one is worse
        }
    }

    /**
     * Get escape analysis for display
     *
     * @param PlayerShip $playerShip
     * @param Collection $pirateFleet
     * @return array
     */
    public function getEscapeAnalysis(PlayerShip $playerShip, Collection $pirateFleet): array
    {
        $playerSpeed = $playerShip->ship->speed;
        $maxPirateSpeed = $pirateFleet->max('speed');
        $maxPirateWarp = $pirateFleet->max('warp_drive');

        return [
            'your_speed' => $playerSpeed,
            'their_max_speed' => $maxPirateSpeed,
            'speed_advantage' => $playerSpeed - $maxPirateSpeed,
            'your_warp' => $playerShip->warp_drive,
            'their_max_warp' => $maxPirateWarp,
            'warp_advantage' => $playerShip->warp_drive - $maxPirateWarp,
            'can_escape' => ($playerSpeed > $maxPirateSpeed && $playerShip->warp_drive > $maxPirateWarp),
        ];
    }
}

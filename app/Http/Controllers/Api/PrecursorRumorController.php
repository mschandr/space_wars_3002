<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\TradingHub;
use App\Services\PrecursorRumorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Controller for Precursor Ship rumors at ship yards.
 *
 * Every ship yard has heard rumors about the legendary Precursor ship.
 * Every ship yard thinks they know where it is.
 * Every ship yard is wrong.
 *
 * Players can bribe ship yard owners for their (incorrect) location information.
 */
class PrecursorRumorController extends BaseApiController
{
    public function __construct(
        private PrecursorRumorService $rumorService
    ) {}

    /**
     * Get gossip about the Precursor ship at current location.
     * Free information that hints at the legend but gives no coordinates.
     *
     * GET /api/players/{uuid}/precursor/gossip
     */
    public function getGossip(Request $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        // Get trading hub at current location
        $tradingHub = TradingHub::where('poi_id', $player->current_poi_id)->first();

        if (! $tradingHub) {
            return $this->error('You must be at a trading hub to hear gossip about the Precursor ship.');
        }

        $gossip = $this->rumorService->getShipyardGossip($tradingHub);

        return $this->success([
            'gossip' => $gossip,
            'has_rumor' => $tradingHub->hasPrecursorRumor(),
            'bribe_cost' => $tradingHub->hasPrecursorRumor() ? $tradingHub->precursor_bribe_cost : null,
            'owner_name' => $tradingHub->shipyard_owner_name,
            'already_obtained' => $tradingHub->playerHasRumor($player),
        ]);
    }

    /**
     * Bribe the ship yard owner for their rumored Precursor ship location.
     *
     * POST /api/players/{uuid}/precursor/bribe
     */
    public function bribeForRumor(Request $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        // Get trading hub at current location
        $tradingHub = TradingHub::where('poi_id', $player->current_poi_id)->first();

        if (! $tradingHub) {
            return $this->error('You must be at a trading hub to bribe the ship yard owner.');
        }

        $result = $this->rumorService->bribeForRumor($player, $tradingHub);

        if (! $result['success']) {
            $statusCode = isset($result['already_obtained']) ? 409 : 400;

            return $this->error($result['error'], 'BRIBE_FAILED', null, $statusCode);
        }

        return $this->success([
            'rumor' => $result['rumor'],
            'bribe_paid' => $result['bribe_paid'],
            'remaining_credits' => $result['remaining_credits'],
            'message' => $result['message'],
        ], 'Rumor obtained successfully');
    }

    /**
     * Get all rumors the player has collected.
     *
     * GET /api/players/{uuid}/precursor/rumors
     */
    public function getCollectedRumors(Request $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $rumors = $this->rumorService->getPlayerRumors($player);

        // Calculate total invested
        $totalInvested = $rumors->sum('bribe_paid');

        return $this->success([
            'rumors' => $rumors->toArray(),
            'total_rumors' => $rumors->count(),
            'total_invested' => $totalInvested,
            'hint' => $rumors->isEmpty()
                ? 'Visit ship yards and bribe the owners for rumors about the Precursor ship location.'
                : 'Each ship yard believes they know where the Precursor ship is hidden. None of them are right... but comparing their stories might help narrow it down.',
        ]);
    }

    /**
     * Check if the current location's ship yard has a rumor available.
     *
     * GET /api/players/{uuid}/precursor/check
     */
    public function checkForRumor(Request $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        // Get trading hub at current location
        $tradingHub = TradingHub::where('poi_id', $player->current_poi_id)->first();

        if (! $tradingHub) {
            return $this->success([
                'has_trading_hub' => false,
                'has_shipyard' => false,
                'has_rumor' => false,
            ]);
        }

        $hasShipyard = $tradingHub->hasShipyard();
        $hasRumor = $tradingHub->hasPrecursorRumor();
        $alreadyObtained = $hasRumor ? $tradingHub->playerHasRumor($player) : false;

        return $this->success([
            'has_trading_hub' => true,
            'trading_hub_name' => $tradingHub->name,
            'has_shipyard' => $hasShipyard,
            'has_rumor' => $hasRumor,
            'already_obtained' => $alreadyObtained,
            'bribe_cost' => $hasRumor ? $tradingHub->precursor_bribe_cost : null,
            'owner_name' => $tradingHub->shipyard_owner_name,
            'can_afford' => $hasRumor ? ($player->credits >= $tradingHub->precursor_bribe_cost) : null,
        ]);
    }
}

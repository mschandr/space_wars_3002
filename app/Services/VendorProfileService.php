<?php

namespace App\Services;

use App\Models\Player;
use App\Models\VendorProfile;
use App\Models\PlayerVendorRelationship;

/**
 * Manages vendor profiles, relationships, and pricing
 *
 * Vendors have archetypes (honest broker, fence, socialite, etc.) that determine
 * their base markup and how they respond to player interactions. Player relationships
 * track goodwill, shady dealings, and visit counts to determine effective markup.
 */
class VendorProfileService
{
    /**
     * Find an existing player-vendor relationship without creating one
     * Returns null if no relationship exists
     */
    public function findRelationship(Player $player, VendorProfile $vendor): ?PlayerVendorRelationship
    {
        return PlayerVendorRelationship::where('player_id', $player->id)
            ->where('vendor_profile_id', $vendor->id)
            ->first();
    }

    /**
     * Get or create a player-vendor relationship
     */
    public function getOrCreateRelationship(Player $player, VendorProfile $vendor): PlayerVendorRelationship
    {
        return PlayerVendorRelationship::firstOrCreate(
            ['player_id' => $player->id, 'vendor_profile_id' => $vendor->id],
            ['goodwill' => 0, 'shady_dealings' => 0, 'visit_count' => 0]
        );
    }

    /**
     * Calculate effective markup for a player at a vendor
     *
     * Formula:
     * - base = archetype.baseMarkup()
     * - goodwill_discount = min(goodwill / 100, archetype.maxGoodwillBonus())
     * - crew_bonus = vendor_bonuses['trading_discount'] from ShipPersonaService
     * - shady_bonus = (crew is shady && vendor is fence/pirate) ? 0.05 : 0
     * - final = base - goodwill_discount - crew_bonus - shady_bonus + relationship_modifier
     *
     * @param VendorProfile $vendor The vendor
     * @param Player $player The player
     * @return float Markup multiplier (e.g., 0.10 = 10% markup)
     */
    public function getEffectiveMarkup(VendorProfile $vendor, Player $player): float
    {
        // Get relationship
        $relationship = $this->getOrCreateRelationship($player, $vendor);

        // Check lockout
        if ($relationship->is_locked_out) {
            return 0.50;  // 50% markup for locked out players (prohibitive)
        }

        // Start with instance markup_base (falls back to archetype default if null)
        $markup = $vendor->markup_base !== null
            ? (float) $vendor->markup_base
            : (float) $vendor->archetype->baseMarkup();

        // Apply goodwill discount
        $maxBonus = $vendor->archetype->maxGoodwillBonus();
        $goodwillDiscount = min($relationship->goodwill / 100, $maxBonus);
        $markup -= $goodwillDiscount;

        // Apply crew bonuses
        if ($player->activeShip) {
            $persona = $player->activeShip->getCrewPersona();
            if (isset($persona['vendor_bonuses']['trading_discount'])) {
                $markup -= $persona['vendor_bonuses']['trading_discount'];
            }

            // High-criminality vendors: shady crew discount, lawful crew penalty
            if ($vendor->isBlackMarketDealer()) {
                if ($persona['overall_alignment'] === 'shady') {
                    $markup -= 0.05;  // Shady crew are trusted here
                } elseif ($persona['overall_alignment'] === 'lawful') {
                    $markup += 0.05;  // Lawful crew are unwelcome
                }
            }
        }

        // Apply personal relationship modifier
        $markup += (float) $relationship->markup_modifier;

        // Clamp to reasonable bounds
        return max(-0.30, min(0.50, $markup));  // -30% to +50%
    }

    /**
     * Get a static fallback dialogue line for a vendor profile template.
     *
     * VendorProfile is now a global template — structured dialogue lives in
     * vendor_dialogue rows keyed to GalaxyVendorProfile instances.
     * This method provides a basic fallback by service_type for contexts
     * that have not yet been through the Go generator.
     */
    public function getDialogueLine(VendorProfile $vendor, string $context, Player $player): string
    {
        $fallbacks = [
            'trading_hub'  => ['greeting' => 'Looking to trade?', 'farewell' => 'Safe travels.', 'deal_accepted' => 'Pleasure doing business.', 'deal_rejected' => 'Your decision.'],
            'salvage_yard' => ['greeting' => 'See anything worth salvaging?', 'farewell' => 'Try not to get yourself spaced.', 'deal_accepted' => 'Not a bad deal.', 'deal_rejected' => 'Your loss.'],
            'shipyard'     => ['greeting' => 'Looking for a new hull?', 'farewell' => 'Come back when you want something faster.', 'deal_accepted' => 'Fine choice.', 'deal_rejected' => 'Come back when you\'re ready.'],
            'market'       => ['greeting' => 'Take a look around.', 'farewell' => 'Come again.', 'deal_accepted' => 'Good choice.', 'deal_rejected' => 'Suit yourself.'],
        ];

        return $fallbacks[$vendor->service_type][$context] ?? 'Hmph.';
    }

    /**
     * Record a trade interaction with a vendor
     *
     * Updates goodwill, visit count, and last interaction time
     */
    public function recordInteraction(VendorProfile $vendor, Player $player, string $type = 'trade'): void
    {
        $relationship = $this->getOrCreateRelationship($player, $vendor);

        if ($type === 'shady_trade') {
            $relationship->recordShadyTrade(1);

            // Record shady action on player's crew
            if ($player->activeShip) {
                foreach ($player->activeShip->crew as $member) {
                    $member->recordShadyAction();
                }
            }
        } else {
            $relationship->recordTrade(1);
        }
    }
}

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

        // Start with archetype base
        $markup = (float) $vendor->archetype->baseMarkup();

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

            // Shady crew get discount from fence/pirate contacts
            if (
                $persona['overall_alignment'] === 'shady' &&
                in_array($vendor->archetype, [
                    \App\Enums\Vendor\VendorArchetype::FENCE,
                    \App\Enums\Vendor\VendorArchetype::PIRATE_CONTACT,
                    \App\Enums\Vendor\VendorArchetype::BLACK_MARKET_DEALER,
                ])
            ) {
                $markup -= 0.05;  // Additional 5% discount
            }
        }

        // Apply personal relationship modifier
        $markup += (float) $relationship->markup_modifier;

        // Clamp to reasonable bounds
        return max(-0.30, min(0.50, $markup));  // -30% to +50%
    }

    /**
     * Get dialogue for a vendor in a specific context
     *
     * Architecture supports both static dialogue (current) and LLM-generated dialogue (future)
     */
    public function getDialogueLine(VendorProfile $vendor, string $context, Player $player): string
    {
        return $vendor->getDialogue($context);
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

<?php

namespace App\Enums\Vendor;

enum VendorArchetype: string
{
    case HONEST_BROKER = 'honest_broker';
    case HARD_BARGAINER = 'hard_bargainer';
    case FENCE = 'fence';
    case CORPORATE_AGENT = 'corporate_agent';
    case EXPLORER_OUTFITTER = 'explorer_outfitter';
    case PIRATE_CONTACT = 'pirate_contact';
    case BLACK_MARKET_DEALER = 'black_market_dealer';
    case GRUFF_MECHANIC = 'gruff_mechanic';
    case SOCIALITE = 'socialite';

    /**
     * Get human-readable label for this archetype
     */
    public function label(): string
    {
        return match ($this) {
            self::HONEST_BROKER => 'Honest Broker',
            self::HARD_BARGAINER => 'Hard Bargainer',
            self::FENCE => 'Fence',
            self::CORPORATE_AGENT => 'Corporate Agent',
            self::EXPLORER_OUTFITTER => 'Explorer Outfitter',
            self::PIRATE_CONTACT => 'Pirate Contact',
            self::BLACK_MARKET_DEALER => 'Black Market Dealer',
            self::GRUFF_MECHANIC => 'Gruff Mechanic',
            self::SOCIALITE => 'Socialite',
        };
    }

    /**
     * Get description of this vendor archetype
     */
    public function description(): string
    {
        return match ($this) {
            self::HONEST_BROKER => 'No markup, honest quotes, good rep with lawful crew',
            self::HARD_BARGAINER => '10% markup base, discounts for return customers',
            self::FENCE => 'Deals in "no questions asked" goods, shady crew bonus',
            self::CORPORATE_AGENT => 'Fixed prices, no negotiation, prefers industrial goods',
            self::EXPLORER_OUTFITTER => 'Discounts on exploration gear, rare item access',
            self::PIRATE_CONTACT => 'Steep markup for unknowns, discount for shady crew',
            self::BLACK_MARKET_DEALER => 'Only visible to those past the threshold',
            self::GRUFF_MECHANIC => 'No charm, discounts for engineers, flat prices',
            self::SOCIALITE => 'Heavy charm dependency, big variance based on crew',
        };
    }

    /**
     * Get base markup for this archetype (before relationship modifiers)
     */
    public function baseMarkup(): float
    {
        return match ($this) {
            self::HONEST_BROKER => 0.00,
            self::HARD_BARGAINER => 0.10,
            self::FENCE => 0.05,
            self::CORPORATE_AGENT => 0.05,
            self::EXPLORER_OUTFITTER => -0.05,    // Discount!
            self::PIRATE_CONTACT => 0.20,
            self::BLACK_MARKET_DEALER => 0.15,
            self::GRUFF_MECHANIC => 0.00,
            self::SOCIALITE => 0.08,
        };
    }

    /**
     * Get max goodwill bonus this vendor can give
     */
    public function maxGoodwillBonus(): float
    {
        return match ($this) {
            self::HONEST_BROKER => 0.05,      // Honest already cheap, small bonus
            self::HARD_BARGAINER => 0.10,     // Rewards loyal customers
            self::FENCE => 0.08,
            self::CORPORATE_AGENT => 0.02,    // Fixed prices, minimal change
            self::EXPLORER_OUTFITTER => 0.10,
            self::PIRATE_CONTACT => 0.15,
            self::BLACK_MARKET_DEALER => 0.12,
            self::GRUFF_MECHANIC => 0.08,
            self::SOCIALITE => 0.20,          // Charm goes far
        };
    }
}

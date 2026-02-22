<?php

namespace App\Services;

use App\Enums\RarityTier;
use App\Enums\SlotType;
use App\Models\Player;
use App\Models\SalvageYardInventory;
use App\Models\Ship;
use App\Models\ShipComponent;

class MerchantCommentaryService
{
    /**
     * Commentary pools organized by tag combinations.
     * Multi-tag combos first (highest specificity), single-tag fallbacks last.
     * Each pool has 3-4 lines. Placeholders: {item_name}, {price}, {rarity}, {slot_type}
     */
    private const COMMENTARY_POOLS = [
        // === Multi-tag combos (specificity 2+) ===
        'quality:exceptional+value:deal' => [
            "I probably shouldn't be telling you this, but {item_name} at this price is practically theft. Legal theft, but still.",
            'Between you and me, I priced {item_name} before I realized what I had. My loss, your gain.',
            'An {rarity} {item_name} for {price} credits? I must be losing my mind. Buy it before I come to my senses.',
        ],
        'quality:junk+popularity:shelf_warmer' => [
            "Been trying to move {item_name} for weeks. I'd pay you to take it at this point, but I still have some dignity.",
            "Look, nobody's lining up for this one. But it works. Mostly. On good days.",
            'I keep pushing {item_name} to the back of the shelf, and it keeps crawling forward. Persistent little thing.',
        ],
        'danger:deadly+buyer_affordability:stretching' => [
            "She'll eat your savings... and your enemies. Whether that's a good trade depends on your priorities.",
            "{item_name} isn't cheap, but neither is surviving a firefight. Think of it as life insurance with teeth.",
            "I can see you doing the math in your head. Let me save you time — yes, it's worth it. Probably.",
        ],
        'popularity:hot_item+value:overpriced' => [
            'Supply and demand. Three other buyers are waiting if you pass.',
            "Yeah, {price} credits is steep. But {rarity} gear doesn't exactly grow on asteroids.",
            "I know, I know. The price hurts. But try finding another {item_name} within three sectors. I'll wait.",
        ],
        'buyer_affordability:cant_afford' => [
            'Your eyes say yes, your wallet says no. Come back when your credits catch up to your taste.',
            "I appreciate the window shopping, but {item_name} costs {price} credits and you're... not there yet.",
            'Tell you what — go run a few cargo hauls and come back. {item_name} will still be here. Probably.',
        ],
        'buyer_comparison:upgrade' => [
            "Now that's a proper upgrade from what you're flying. You'll feel the difference immediately.",
            'Smart move. Your current setup is fine, but {item_name} is in a different league entirely.',
            "I've seen a lot of pilots make this exact upgrade. Not a single one has come back complaining.",
        ],
        'buyer_comparison:downgrade' => [
            "I'm legally required to tell you this is worse than what you've got. Interested anyway?",
            'Bit of an odd choice, going backwards. But hey, your credits, your call.',
            "You sure about this? Your current rig is actually better. But I won't stop you from spending money.",
        ],
        'buyer_comparison:first_ship+value:free' => [
            "Every captain starts somewhere. This one's on the house — just get out there and make a name for yourself.",
            "Your first ship! Try not to crash it immediately. Actually, go ahead — it's free.",
            "Welcome to the spacefaring life. She's not much, but she's yours. No charge.",
        ],
        'quality:exceptional+danger:deadly' => [
            'This {item_name} is the real deal. {rarity}-grade hardware that hits like a freight hauler at full burn.',
            "I've only seen a handful of these come through. {item_name} doesn't just perform — it dominates.",
            "If you're looking for the best, you found it. {item_name} is as deadly as they come.",
        ],
        'source:stolen+value:deal' => [
            "Don't ask where it came from. Do ask yourself if you want a bargain on {item_name}.",
            'Fell off the back of a transport, if you know what I mean. {price} credits, no questions asked.',
            "The previous owner... isn't looking for it anymore. Let's leave it at that. Great price, though.",
        ],
        'condition:broken+value:deal' => [
            "It's seen better days, sure. But at {price} credits, you're basically paying for the parts.",
            "A little broken, a lot cheap. If you've got a wrench and some patience, {item_name} is a project worth taking on.",
            "I'll be honest — it barely works. But the core's intact and the price reflects the, uh, cosmetic issues.",
        ],
        'buyer_affordability:way_too_rich+quality:exceptional' => [
            'Ah, a connoisseur with deep pockets. {item_name} is worthy of your collection.',
            'Credits are no object for you, I can tell. {item_name} is exactly what someone of your means deserves.',
            'For a pilot of your financial stature, this {rarity} {item_name} is practically a must-have.',
        ],

        // === Specialty pools (specificity 1) ===
        'specialty:stealth' => [
            "Perfect for pilots who prefer to be neither seen nor heard. Until it's too late, of course.",
            'Stealth is an underrated virtue. {item_name} lets you pick your fights instead of having them pick you.',
            "Ghost runner's choice. The pirates can't shoot what they can't find.",
        ],
        'specialty:cargo' => [
            'More cargo space means more credits per run. Simple math, big profits.',
            "Haulers love {item_name}. She's not glamorous, but your accountant will adore her.",
            "If you're in the trading business, capacity is king. And {item_name} is very, very royal.",
        ],
        'specialty:firepower' => [
            'For pilots who believe the best defense is an overwhelming offense.',
            '{item_name} turns polite disagreements into very short conversations.',
            'When diplomacy fails, {item_name} picks up where words leave off.',
        ],
        'specialty:exploration' => [
            'The outer reaches are calling, and {item_name} is how you answer.',
            "Built for the unknown. Long-range, reliable, and ready for whatever's out there.",
            "Explorers need gear they can trust three sectors from civilization. {item_name} won't let you down.",
        ],
        'specialty:mining' => [
            'Crack rocks, haul ore, stack credits. {item_name} makes the grind profitable.',
            'The miners who buy {item_name} tend to retire early. Coincidence? I think not.',
            'Purpose-built for extraction work. Every credit you spend on this pays for itself tenfold.',
        ],
        'specialty:defense' => [
            "You can't make credits if you're dead. {item_name} keeps you in the game.",
            'Some pilots invest in offense. The smart ones invest in not dying. {item_name} is smart money.',
            "When everything goes sideways, you'll be glad you had {item_name} installed.",
        ],
        'specialty:speed' => [
            'Fast enough to outrun trouble and get to the good trades first.',
            "Speed kills — your competition's profit margins. {item_name} is pure velocity.",
            "If you can't fight it, outrun it. {item_name} ensures you always have that option.",
        ],
        'specialty:colonial' => [
            'Colony builders need specialized gear. {item_name} is essential for frontier operations.',
            'Planning to stake a claim? {item_name} is what separates visitors from settlers.',
            "The frontier doesn't forgive the unprepared. {item_name} gives you a fighting chance out there.",
        ],
        'specialty:utility' => [
            'Jack of all trades, master of versatility. {item_name} fills in whatever gap your setup has.',
            "Utility gear doesn't get the glory, but it keeps everything running smooth.",
            'The unsung hero of any loadout. {item_name} does the quiet work that matters.',
        ],
        'specialty:legendary' => [
            "I don't even know how this ended up on my lot. {item_name} is the stuff of legends.",
            "Once in a lifetime. Maybe twice if you're lucky. {item_name} is in a class of its own.",
            "They'll write stories about the pilot who flew with {item_name}. Could be you.",
        ],

        // === Source pools ===
        'source:stolen' => [
            "Black market special. Don't worry about the serial numbers — I already did.",
            "Let's just say the supply chain for this one was... unconventional.",
            'Pirate salvage, freshly laundered. The discount reflects the, uh, provenance.',
        ],
        'source:salvage' => [
            'Pulled from a wreck, cleaned up, and ready for a second life. Recycling at its finest.',
            'Previous owner had an unfortunate encounter with pirates. Their loss, your savings.',
            'Salvage gear has character. Every scratch tells a story. Usually a violent one.',
        ],
        'source:manufactured' => [
            'Factory fresh, still has that new-component smell. Full manufacturer warranty.',
            'Straight from the production line. No surprises, no history, no drama.',
            "Brand new. Zero hours on the clock. Doesn't get cleaner than this.",
        ],

        // === Condition pools ===
        'condition:broken' => [
            "I won't sugarcoat it — this needs work. But the bones are good and the price is right.",
            'Fixer-upper special. Bring your own toolkit and low expectations.',
            "It's broken, yes. But it's broken in an fixable way. There's a difference.",
        ],
        'condition:pristine' => [
            "Mint condition. Not a scratch, not a scuff. Treat it well and it'll last a lifetime.",
            "Pristine. I almost feel bad selling it — it's that clean.",
            "Factory perfect. You could eat off this thing. Don't, but you could.",
        ],

        // === Value fallbacks ===
        'value:free' => [
            "Free. As in, zero credits. I know, I'm too generous for this business.",
            "On the house. Don't say I never gave you anything.",
            "Complimentary. Gratis. No charge. However you want to say 'your wallet can relax.'",
        ],
        'value:deal' => [
            "Priced to move. You won't find {item_name} cheaper this side of the core.",
            "I'm practically giving this away. Don't tell my supplier.",
            "That's a good price and we both know it. Grab it before I reconsider.",
        ],
        'value:fair' => [
            'Fair price for fair gear. No tricks, no markups, no games.',
            "Market rate. You comparison shop all you want — you'll end up back here.",
            "Honest price for honest hardware. That's how I do business.",
        ],
        'value:overpriced' => [
            'Look, quality costs. You want cheap, try the junk dealers two sectors over.',
            "The price is the price. But you're paying for reliability, not just hardware.",
            'Expensive? Maybe. Worth it? Absolutely. You get what you pay for out here.',
        ],

        // === Universal fallback ===
        'universal' => [
            'Solid piece of equipment. Take it or leave it.',
            "It does what it says on the label. Can't ask for more than that.",
            'Standard issue, no complaints. Gets the job done.',
            'Not the flashiest thing on the lot, but it works. Reliably.',
        ],
    ];

    /**
     * Generate commentary for a ship listing.
     */
    public function generateShipCommentary(Ship $ship, float $currentPrice, ?Player $player = null): string
    {
        $tags = $this->scoreShip($ship, $currentPrice, $player);

        return $this->selectCommentary($tags, [
            'item_name' => $ship->name,
            'price' => number_format($currentPrice),
            'rarity' => $ship->rarity ?? 'standard',
            'slot_type' => $ship->class ?? 'ship',
        ]);
    }

    /**
     * Generate commentary for a salvage yard component listing.
     */
    public function generateComponentCommentary(
        ShipComponent $component,
        SalvageYardInventory $item,
        ?Player $player = null
    ): string {
        $tags = $this->scoreComponent($component, $item, $player);
        $slotType = $component->slot_type instanceof SlotType
            ? $component->slot_type->label()
            : $component->slot_type;

        return $this->selectCommentary($tags, [
            'item_name' => $component->name,
            'price' => number_format($item->current_price),
            'rarity' => $component->rarity instanceof RarityTier ? $component->rarity->label() : ($component->rarity ?? 'standard'),
            'slot_type' => $slotType,
        ]);
    }

    /**
     * Score a ship on all tag dimensions.
     *
     * @return array<string, string>
     */
    public function scoreShip(Ship $ship, float $currentPrice, ?Player $player = null): array
    {
        $thresholds = config('game_config.merchant_commentary.thresholds', []);
        $tags = [];

        // Value tag
        $basePrice = (float) $ship->base_price;
        $tags['value'] = $this->scoreValue($currentPrice, $basePrice, $thresholds);

        // Quality tag from rarity
        $tags['quality'] = $this->scoreQualityFromRarity($ship->rarity);

        // Popularity tag (inverse of rarity)
        $tags['popularity'] = $this->scorePopularity($ship->rarity);

        // Danger tag from combat rating
        $combatRating = $ship->getCombatRating();
        $dangerThresholds = $thresholds['danger'] ?? [];
        $tags['danger'] = match (true) {
            $combatRating >= ($dangerThresholds['ship_deadly'] ?? 200) => 'deadly',
            $combatRating >= ($dangerThresholds['ship_moderate'] ?? 80) => 'moderate',
            default => 'safe',
        };

        // Specialty tag from ship class + attributes
        $tags['specialty'] = $this->scoreShipSpecialty($ship);

        // Buyer context tags (only if player provided)
        if ($player) {
            $tags['buyer_affordability'] = $this->scoreBuyerAffordability(
                $player->credits,
                $currentPrice,
                $thresholds
            );

            $tags['buyer_comparison'] = $this->scoreBuyerComparison($ship, $player);
        }

        return $tags;
    }

    /**
     * Score a component on all tag dimensions.
     *
     * @return array<string, string>
     */
    public function scoreComponent(
        ShipComponent $component,
        SalvageYardInventory $item,
        ?Player $player = null
    ): array {
        $thresholds = config('game_config.merchant_commentary.thresholds', []);
        $tags = [];

        // Value tag (account for condition discount)
        $basePrice = (float) $component->base_price;
        $conditionAdjustedBase = $basePrice * ($item->condition / 100);
        $tags['value'] = $this->scoreValue((float) $item->current_price, $conditionAdjustedBase, $thresholds);

        // Quality tag (rarity, degraded by poor condition)
        $rarity = $component->rarity;
        $qualityBase = $this->scoreQualityFromRarity($rarity instanceof RarityTier ? $rarity->value : $rarity);
        if ($item->condition < 40 && $qualityBase !== 'junk') {
            $qualityBase = 'decent'; // Degrade quality for very poor condition
        }
        $tags['quality'] = $qualityBase;

        // Popularity
        $tags['popularity'] = $this->scorePopularity(
            $rarity instanceof RarityTier ? $rarity->value : $rarity
        );

        // Danger tag for weapons
        $tags['danger'] = $this->scoreComponentDanger($component);

        // Specialty from slot type
        $tags['specialty'] = $this->scoreComponentSpecialty($component);

        // Source
        $tags['source'] = $item->source ?? 'manufactured';

        // Condition
        $tags['condition'] = match (true) {
            $item->condition < 40 => 'broken',
            $item->condition < 60 => 'poor',
            $item->condition === 100 => 'pristine',
            default => 'decent',
        };

        // Buyer context
        if ($player) {
            $tags['buyer_affordability'] = $this->scoreBuyerAffordability(
                $player->credits,
                (float) $item->current_price,
                $thresholds
            );
        }

        return $tags;
    }

    /**
     * Select the most specific matching commentary and interpolate placeholders.
     *
     * @param  array<string, string>  $tags
     * @param  array<string, string>  $replacements
     */
    public function selectCommentary(array $tags, array $replacements): string
    {
        $bestPool = null;
        $bestSpecificity = -1;

        foreach (self::COMMENTARY_POOLS as $poolKey => $lines) {
            if ($poolKey === 'universal') {
                continue; // Handle separately as ultimate fallback
            }

            $requiredTags = $this->parsePoolKey($poolKey);
            $specificity = count($requiredTags);

            if ($specificity <= $bestSpecificity) {
                continue; // Skip lower-specificity pools if we already have a match
            }

            // Check if all required tags match
            $matches = true;
            foreach ($requiredTags as $dimension => $value) {
                if (! isset($tags[$dimension]) || $tags[$dimension] !== $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $bestPool = $lines;
                $bestSpecificity = $specificity;
            }
        }

        // Fallback to universal if no specific match
        if ($bestPool === null) {
            $bestPool = self::COMMENTARY_POOLS['universal'];
        }

        $line = $bestPool[array_rand($bestPool)];

        return $this->interpolate($line, $replacements);
    }

    /**
     * Parse a pool key like "quality:exceptional+value:deal" into ['quality' => 'exceptional', 'value' => 'deal'].
     *
     * @return array<string, string>
     */
    private function parsePoolKey(string $key): array
    {
        $tags = [];
        $parts = explode('+', $key);
        foreach ($parts as $part) {
            $split = explode(':', $part, 2);
            if (count($split) === 2) {
                $tags[$split[0]] = $split[1];
            }
        }

        return $tags;
    }

    /**
     * Replace {placeholders} with actual values.
     */
    private function interpolate(string $template, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Score value dimension based on price ratio.
     */
    private function scoreValue(float $currentPrice, float $basePrice, array $thresholds): string
    {
        if ($currentPrice <= 0) {
            return 'free';
        }

        if ($basePrice <= 0) {
            return 'fair';
        }

        $ratio = $currentPrice / $basePrice;
        $valueThresholds = $thresholds['value'] ?? [];

        return match (true) {
            $ratio <= ($valueThresholds['deal_ratio'] ?? 0.75) => 'deal',
            $ratio >= ($valueThresholds['overpriced_ratio'] ?? 1.10) => 'overpriced',
            default => 'fair',
        };
    }

    /**
     * Score quality from rarity tier.
     */
    private function scoreQualityFromRarity(mixed $rarity): string
    {
        $rarityValue = $rarity instanceof RarityTier ? $rarity->value : $rarity;

        return match ($rarityValue) {
            'exotic', 'unique' => 'exceptional',
            'epic', 'rare' => 'good',
            'uncommon' => 'decent',
            default => 'junk',
        };
    }

    /**
     * Score popularity (inverse of rarity — common items are shelf warmers).
     */
    private function scorePopularity(mixed $rarity): string
    {
        $rarityValue = $rarity instanceof RarityTier ? $rarity->value : $rarity;

        return match ($rarityValue) {
            'exotic', 'unique', 'epic' => 'hot_item',
            'rare' => 'steady',
            default => 'shelf_warmer',
        };
    }

    /**
     * Score buyer affordability.
     */
    private function scoreBuyerAffordability(float $credits, float $price, array $thresholds): string
    {
        if ($price <= 0) {
            return 'way_too_rich';
        }

        $buyerThresholds = $thresholds['buyer'] ?? [];
        $ratio = $credits / $price;

        return match (true) {
            $ratio >= ($buyerThresholds['rich_multiplier'] ?? 3.0) => 'way_too_rich',
            $ratio >= ($buyerThresholds['comfortable_multiplier'] ?? 1.5) => 'comfortable',
            $ratio >= 1.0 => 'stretching',
            default => 'cant_afford',
        };
    }

    /**
     * Score buyer comparison for ships (upgrade/downgrade/sidegrade/first_ship).
     */
    private function scoreBuyerComparison(Ship $ship, Player $player): string
    {
        $activeShip = $player->activeShip;

        if (! $activeShip || ! $activeShip->ship) {
            return 'first_ship';
        }

        $currentBlueprint = $activeShip->ship;
        $thresholds = config('game_config.merchant_commentary.thresholds.buyer', []);

        // Combined score: combat rating + utility score
        $currentScore = $currentBlueprint->getCombatRating() + $currentBlueprint->getUtilityScore();
        $newScore = $ship->getCombatRating() + $ship->getUtilityScore();

        if ($currentScore <= 0) {
            return 'first_ship';
        }

        $ratio = $newScore / $currentScore;

        return match (true) {
            $ratio >= ($thresholds['upgrade_ratio'] ?? 1.3) => 'upgrade',
            $ratio <= ($thresholds['downgrade_ratio'] ?? 0.7) => 'downgrade',
            default => 'sidegrade',
        };
    }

    /**
     * Determine ship specialty from class and attributes.
     */
    private function scoreShipSpecialty(Ship $ship): string
    {
        $class = strtolower($ship->class ?? '');
        $attributes = $ship->attributes ?? [];

        // Check explicit class/attribute indicators
        if (str_contains($class, 'precursor') || ($attributes['is_precursor'] ?? false)) {
            return 'legendary';
        }

        if ($attributes['is_carrier'] ?? false) {
            return 'firepower';
        }

        if (str_contains($class, 'stealth') || str_contains($class, 'ghost') || str_contains($class, 'shadow')) {
            return 'stealth';
        }

        if (str_contains($class, 'mining') || str_contains($class, 'miner')) {
            return 'mining';
        }

        if (str_contains($class, 'colony') || str_contains($class, 'colonial') || str_contains($class, 'settler')) {
            return 'colonial';
        }

        if (str_contains($class, 'explorer') || str_contains($class, 'scout') || str_contains($class, 'survey')) {
            return 'exploration';
        }

        // Stat-based classification
        if ($ship->cargo_capacity >= 200) {
            return 'cargo';
        }

        if ($ship->weapon_slots >= 4) {
            return 'firepower';
        }

        if ($ship->speed >= 8) {
            return 'speed';
        }

        if ($ship->shield_strength >= 200) {
            return 'defense';
        }

        return 'utility';
    }

    /**
     * Score component danger based on type and effects.
     */
    private function scoreComponentDanger(ShipComponent $component): string
    {
        $slotType = $component->slot_type;

        // Shield/hull/defense components are defensive
        if ($slotType === SlotType::SHIELD_GENERATOR || $slotType === SlotType::HULL_PLATING) {
            return 'defensive';
        }

        // Weapons: check damage effect
        if ($slotType === SlotType::WEAPON) {
            $damage = (int) ($component->getEffect('damage') ?? $component->getEffect('base_damage') ?? 0);
            $thresholds = config('game_config.merchant_commentary.thresholds.danger', []);

            return match (true) {
                $damage >= ($thresholds['weapon_deadly'] ?? 80) => 'deadly',
                $damage >= ($thresholds['weapon_moderate'] ?? 40) => 'moderate',
                default => 'safe',
            };
        }

        return 'safe';
    }

    /**
     * Determine component specialty from slot type.
     */
    private function scoreComponentSpecialty(ShipComponent $component): string
    {
        $slotType = $component->slot_type;
        $slotTypeValue = $slotType instanceof SlotType ? $slotType : SlotType::tryFrom($slotType);

        return match ($slotTypeValue) {
            SlotType::WEAPON => 'firepower',
            SlotType::ENGINE => 'speed',
            SlotType::SHIELD_GENERATOR => 'defense',
            SlotType::HULL_PLATING => 'defense',
            SlotType::SENSOR_ARRAY => 'exploration',
            SlotType::CARGO_MODULE => 'cargo',
            SlotType::REACTOR => 'utility',
            SlotType::UTILITY => 'utility',
            default => 'utility',
        };
    }
}

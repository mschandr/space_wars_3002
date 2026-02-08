<?php

namespace App\Services;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use Illuminate\Support\Collection;

/**
 * Bar Rumor Service
 *
 * Generates rumors and gossip that players can overhear at bars.
 * Rumors include actionable intelligence (pirate activity, market predictions)
 * mixed with useless flavor text to create atmosphere.
 */
class BarRumorService
{
    /**
     * Certainty levels for rumors.
     */
    public const CERTAINTY_CONFIRMED = 'confirmed';      // 90%+ accurate

    public const CERTAINTY_RELIABLE = 'reliable';        // 70-90% accurate

    public const CERTAINTY_UNCERTAIN = 'uncertain';      // 40-70% accurate

    public const CERTAINTY_DUBIOUS = 'dubious';          // 10-40% accurate

    public const CERTAINTY_GOSSIP = 'gossip';            // Completely unreliable

    /**
     * Number of rumors to generate per bar visit.
     */
    protected int $rumorsPerVisit = 5;

    /**
     * Get rumors from the bar at a given location.
     *
     * @param  Player  $player  The player visiting the bar
     * @param  PointOfInterest  $location  The current location (system or station)
     * @return array Array of rumors
     */
    public function getRumors(Player $player, PointOfInterest $location): array
    {
        $galaxy = $location->galaxy;
        $system = $this->getParentSystem($location);

        $rumors = collect();

        // Generate a mix of rumor types
        // 30% chance of pirate intel
        // 20% chance of market prediction
        // 10% chance of exploration lead
        // 40% chance of useless gossip

        for ($i = 0; $i < $this->rumorsPerVisit; $i++) {
            $roll = rand(1, 100);

            if ($roll <= 30) {
                $rumors->push($this->generatePirateRumor($galaxy, $system, $player));
            } elseif ($roll <= 50) {
                $rumors->push($this->generateMarketRumor($galaxy, $system));
            } elseif ($roll <= 60) {
                $rumors->push($this->generateExplorationRumor($galaxy, $system));
            } else {
                $rumors->push($this->generateGossip($galaxy, $system));
            }
        }

        // Sort by usefulness (actionable rumors first)
        return $rumors->sortByDesc(fn ($r) => $r['actionable'] ?? false)->values()->toArray();
    }

    /**
     * Generate a pirate-related rumor.
     */
    protected function generatePirateRumor(Galaxy $galaxy, PointOfInterest $system, Player $player): array
    {
        $templates = $this->getPirateRumorTemplates();
        $template = $templates[array_rand($templates)];

        // Find a real connected system for more believable rumors
        $connectedSystems = $this->getConnectedSystems($system);
        $targetSystem = $connectedSystems->isNotEmpty()
            ? $connectedSystems->random()
            : $this->getRandomNearbySystem($galaxy, $system);

        $targetName = $targetSystem?->name ?? 'a nearby system';

        // Check if there's actual pirate activity
        $hasRealPirates = $this->checkForPirateActivity($system, $targetSystem);

        // Determine certainty based on whether the rumor is true
        $certainty = $hasRealPirates
            ? $this->weightedCertainty(['confirmed' => 20, 'reliable' => 50, 'uncertain' => 30])
            : $this->weightedCertainty(['uncertain' => 30, 'dubious' => 50, 'gossip' => 20]);

        $message = $this->interpolateTemplate($template, [
            'system' => $targetName,
            'current_system' => $system->name,
            'pirate_type' => $this->getRandomPirateType(),
            'ship_type' => $this->getRandomShipType(),
            'time_frame' => $this->getRandomTimeFrame(),
        ]);

        return [
            'type' => 'pirate_intel',
            'message' => $message,
            'certainty' => $certainty,
            'certainty_label' => $this->getCertaintyLabel($certainty),
            'actionable' => true,
            'related_system' => $targetSystem ? [
                'uuid' => $targetSystem->uuid,
                'name' => $targetSystem->name,
            ] : null,
            'speaker' => $this->getRandomSpeaker('pirate_intel'),
        ];
    }

    /**
     * Generate a market-related rumor.
     */
    protected function generateMarketRumor(Galaxy $galaxy, PointOfInterest $system): array
    {
        $templates = $this->getMarketRumorTemplates();
        $template = $templates[array_rand($templates)];

        $targetSystem = $this->getRandomNearbySystem($galaxy, $system);
        $commodity = $this->getRandomCommodity();
        $effect = rand(0, 1) ? 'shortage' : 'surplus';

        // Market rumors are generally less reliable
        $certainty = $this->weightedCertainty([
            'reliable' => 15,
            'uncertain' => 35,
            'dubious' => 35,
            'gossip' => 15,
        ]);

        $message = $this->interpolateTemplate($template, [
            'system' => $targetSystem?->name ?? 'a trading hub',
            'commodity' => $commodity,
            'effect' => $effect,
            'time_frame' => $this->getRandomTimeFrame(),
            'reason' => $this->getRandomMarketReason($effect),
        ]);

        return [
            'type' => 'market_prediction',
            'message' => $message,
            'certainty' => $certainty,
            'certainty_label' => $this->getCertaintyLabel($certainty),
            'actionable' => true,
            'commodity' => $commodity,
            'effect' => $effect,
            'related_system' => $targetSystem ? [
                'uuid' => $targetSystem->uuid,
                'name' => $targetSystem->name,
            ] : null,
            'speaker' => $this->getRandomSpeaker('market'),
        ];
    }

    /**
     * Generate an exploration-related rumor.
     */
    protected function generateExplorationRumor(Galaxy $galaxy, PointOfInterest $system): array
    {
        $templates = $this->getExplorationRumorTemplates();
        $template = $templates[array_rand($templates)];

        // Sometimes point to real uninhabited systems
        $targetSystem = $this->getRandomUninhabitedSystem($galaxy, $system);

        $certainty = $this->weightedCertainty([
            'uncertain' => 40,
            'dubious' => 40,
            'gossip' => 20,
        ]);

        $discovery = $this->getRandomDiscovery();

        $message = $this->interpolateTemplate($template, [
            'coordinates' => $targetSystem
                ? "({$targetSystem->x}, {$targetSystem->y})"
                : $this->getRandomCoordinates($galaxy),
            'discovery' => $discovery,
            'explorer' => $this->getRandomExplorerName(),
        ]);

        return [
            'type' => 'exploration_lead',
            'message' => $message,
            'certainty' => $certainty,
            'certainty_label' => $this->getCertaintyLabel($certainty),
            'actionable' => true,
            'discovery_type' => $discovery,
            'coordinates' => $targetSystem ? [
                'x' => $targetSystem->x,
                'y' => $targetSystem->y,
            ] : null,
            'speaker' => $this->getRandomSpeaker('explorer'),
        ];
    }

    /**
     * Generate useless but atmospheric gossip.
     */
    protected function generateGossip(Galaxy $galaxy, PointOfInterest $system): array
    {
        $gossipTypes = ['personal', 'political', 'sports', 'entertainment', 'philosophical', 'complaint'];
        $type = $gossipTypes[array_rand($gossipTypes)];

        $templates = $this->getGossipTemplates($type);
        $template = $templates[array_rand($templates)];

        $message = $this->interpolateTemplate($template, [
            'name' => $this->getRandomPersonName(),
            'place' => $this->getRandomPlaceName(),
            'ship_name' => $this->getRandomShipName(),
            'drink' => $this->getRandomDrink(),
            'number' => rand(2, 50),
            'credits' => rand(100, 10000),
        ]);

        return [
            'type' => 'gossip',
            'subtype' => $type,
            'message' => $message,
            'certainty' => self::CERTAINTY_GOSSIP,
            'certainty_label' => $this->getCertaintyLabel(self::CERTAINTY_GOSSIP),
            'actionable' => false,
            'speaker' => $this->getRandomSpeaker('gossip'),
        ];
    }

    /**
     * Get templates for pirate rumors.
     */
    protected function getPirateRumorTemplates(): array
    {
        return [
            'I heard pirates are waiting to ambush traders on the lanes between here and {system}.',
            'Word is, {pirate_type} have set up along the route to {system}. Watch yourself.',
            'A {ship_type} captain told me he barely escaped a pirate attack heading toward {system}.',
            "Don't take the direct route to {system}. {pirate_type} have been hitting every ship that passes.",
            "Pirates raided a convoy near {system} {time_frame}. They're getting bolder.",
            'The lane to {system}? Forget it. I lost my cargo to raiders there just last week.',
            "Someone's paying pirates to blockade {system}. Traders are avoiding it like the plague.",
            'Heard the {pirate_type} are planning something big near {system}. Stay sharp out there.',
            'A friend of mine got jumped between {current_system} and {system}. Three ships, no warning.',
            "The pirates operating near {system} have a new leader. They're more organized now.",
        ];
    }

    /**
     * Get templates for market rumors.
     */
    protected function getMarketRumorTemplates(): array
    {
        return [
            "There's going to be a {commodity} {effect} in {system} {time_frame}. {reason}",
            'Smart traders are stockpiling {commodity}. Word is {system} is about to have a {effect}.',
            'The {commodity} market in {system} is about to go crazy. {reason}',
            "If you've got {commodity} to sell, head to {system}. {reason}",
            "Don't buy {commodity} right now. {system} is dumping their reserves {time_frame}.",
            "{system}'s mining operations just hit a snag. {commodity} prices are going to spike.",
            "Colony expansion in {system} means they'll need {commodity} badly {time_frame}.",
            'A merchant told me {system} is sitting on a {commodity} {effect}. Prices are about to move.',
            "The {commodity} situation in {system}? Let's just say someone's going to make a fortune.",
            "Federation contracts are shifting. {system}'s going to need a lot more {commodity}.",
        ];
    }

    /**
     * Get templates for exploration rumors.
     */
    protected function getExplorationRumorTemplates(): array
    {
        return [
            "An old explorer mentioned coordinates {coordinates}. Said there's {discovery} out there.",
            '{explorer} claimed to have found {discovery} near {coordinates}. Nobody believed him though.',
            "There's an uncharted system around {coordinates}. Rumor has it there's {discovery}.",
            'Before he died, Captain {explorer} kept mumbling about {discovery} at {coordinates}.',
            'I found this data chip with coordinates {coordinates}. Something about {discovery}.',
            "The charts don't show it, but there's something at {coordinates}. {discovery}, they say.",
            'A survey team disappeared near {coordinates}. They were chasing reports of {discovery}.',
            'My grandfather used to tell stories about {discovery} hidden around {coordinates}.',
        ];
    }

    /**
     * Get templates for gossip based on type.
     */
    protected function getGossipTemplates(string $type): array
    {
        return match ($type) {
            'personal' => [
                'Did you hear about {name}? Ran off with a freighter captain. Can you believe it?',
                "{name} owes me {credits} credits. If you see them, tell them I'm looking.",
                'My cousin {name} just got a job on {ship_name}. Moving up in the world!',
                "That {name} character is bad news. Trust me, I've seen some things.",
                'I used to work with {name}. Decent pilot, terrible taste in {drink}.',
            ],
            'political' => [
                'The Federation is up to something. Mark my words.',
                'Station authority here is completely corrupt. Everyone knows it.',
                "Elections are coming up in the outer sectors. Like it'll change anything.",
                'They say the Governor of {place} is in bed with the corporations.',
                "The trade regulations are strangling small operators. It's all rigged for the big haulers.",
            ],
            'sports' => [
                'Did you catch the zero-G championships? That last match was incredible!',
                'The Crimson Flames are going to take the league this year. Calling it now.',
                'I lost {credits} credits betting on that asteroid derby. Fixed, I tell you.',
                '{place} has the best combat arena in the sector. You should check it out.',
                "Racing these days isn't what it used to be. Too many safety regulations.",
            ],
            'entertainment' => [
                'Have you tried the {drink} here? Best in the sector, I swear.',
                "There's a singer at the club in {place}. Voice like an angel.",
                "This holovid everyone's watching? Overrated garbage if you ask me.",
                'The food synthesizers on {ship_name} are broken. Been eating protein bars for weeks.',
                "I miss real gravity. These stations just aren't the same.",
            ],
            'philosophical' => [
                "You ever think about what's really out there? Beyond the rim?",
                "We're all just dust in the cosmic wind, friend. Dust in the wind.",
                "Money, power, fame... none of it matters when you're floating in the void.",
                "I've seen things that would make you question everything. Trust no one.",
                "The universe is too big for us to be alone. I've seen the proof.",
            ],
            'complaint' => [
                'These docking fees are highway robbery. Remember when space was free?',
                "My ship's been in the shop for {number} days. These mechanics are useless.",
                'The recycled air in here tastes like engine coolant. Terrible.',
                'Service in this place has really gone downhill. Used to be decent.',
                "They watered down the {drink}. I can tell. Don't think I can't tell.",
            ],
        };
    }

    /**
     * Interpolate template with values.
     */
    protected function interpolateTemplate(string $template, array $values): string
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{'.$key.'}', $value, $template);
        }

        return $template;
    }

    /**
     * Get connected systems via warp gates.
     */
    protected function getConnectedSystems(PointOfInterest $system): Collection
    {
        return WarpGate::where('source_poi_id', $system->id)
            ->where('status', 'active')
            ->with('destinationPoi')
            ->get()
            ->map(fn ($gate) => $gate->destinationPoi)
            ->filter();
    }

    /**
     * Get a random nearby system.
     */
    protected function getRandomNearbySystem(Galaxy $galaxy, PointOfInterest $currentSystem, int $maxDistance = 200): ?PointOfInterest
    {
        // Cast to signed to avoid underflow with unsigned integers
        return PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', 17) // STAR
            ->where('is_inhabited', true)
            ->where('id', '!=', $currentSystem->id)
            ->whereRaw('SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) <= ?', [
                (int) $currentSystem->x,
                (int) $currentSystem->y,
                $maxDistance,
            ])
            ->inRandomOrder()
            ->first();
    }

    /**
     * Get a random uninhabited system for exploration rumors.
     */
    protected function getRandomUninhabitedSystem(Galaxy $galaxy, PointOfInterest $currentSystem): ?PointOfInterest
    {
        return PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', 17) // STAR
            ->where('is_inhabited', false)
            ->where('id', '!=', $currentSystem->id)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Check for actual pirate activity on routes.
     */
    protected function checkForPirateActivity(PointOfInterest $from, ?PointOfInterest $to): bool
    {
        if (! $to) {
            return rand(1, 100) <= 20; // 20% chance of random true rumor
        }

        // Check if gate between these systems has pirates
        $gate = WarpGate::where('source_poi_id', $from->id)
            ->where('destination_poi_id', $to->id)
            ->first();

        if ($gate && method_exists($gate, 'pirates')) {
            return $gate->pirates()->exists();
        }

        // Random chance based on sector danger level
        return rand(1, 100) <= 25;
    }

    /**
     * Get parent system if location is a child POI.
     */
    protected function getParentSystem(PointOfInterest $location): PointOfInterest
    {
        if ($location->parent_poi_id) {
            $parent = $location->parent;
            while ($parent && $parent->parent_poi_id) {
                $parent = $parent->parent;
            }

            return $parent ?? $location;
        }

        return $location;
    }

    /**
     * Get a weighted random certainty level.
     */
    protected function weightedCertainty(array $weights): string
    {
        $total = array_sum($weights);
        $roll = rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $certainty => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $certainty;
            }
        }

        return self::CERTAINTY_GOSSIP;
    }

    /**
     * Get human-readable certainty label.
     */
    protected function getCertaintyLabel(string $certainty): string
    {
        return match ($certainty) {
            self::CERTAINTY_CONFIRMED => 'Highly Reliable',
            self::CERTAINTY_RELIABLE => 'Seems Reliable',
            self::CERTAINTY_UNCERTAIN => 'Uncertain',
            self::CERTAINTY_DUBIOUS => 'Dubious',
            self::CERTAINTY_GOSSIP => 'Just Gossip',
            default => 'Unknown',
        };
    }

    /**
     * Get random speaker description.
     */
    protected function getRandomSpeaker(string $context): string
    {
        $speakers = match ($context) {
            'pirate_intel' => [
                'A grizzled freighter captain',
                'A nervous-looking trader',
                'A scarred veteran pilot',
                'A drunk security officer',
                'A shifty merchant',
                'A retired bounty hunter',
                'A young courier pilot',
            ],
            'market' => [
                'A well-dressed merchant',
                'A commodities trader',
                'A supply chain analyst',
                'A chatty broker',
                'A mining consortium rep',
                'A logistics coordinator',
            ],
            'explorer' => [
                'A weathered explorer',
                'A mysterious stranger',
                'An old prospector',
                'A survey team veteran',
                'A drunk cartographer',
            ],
            default => [
                'A regular at the bar',
                'Someone at the next table',
                'A loud patron',
                'A tipsy spacer',
                'The person next to you',
                'A bored-looking traveler',
            ],
        };

        return $speakers[array_rand($speakers)];
    }

    /**
     * Get random pirate faction type.
     */
    protected function getRandomPirateType(): string
    {
        $types = [
            'the Red Corsairs',
            'Void Reavers',
            'Blood Moon pirates',
            'raiders',
            'a pirate fleet',
            'the Shadow Syndicate',
            'Rim Runners',
            'unknown hostiles',
        ];

        return $types[array_rand($types)];
    }

    /**
     * Get random ship type.
     */
    protected function getRandomShipType(): string
    {
        $types = ['freighter', 'hauler', 'transport', 'cargo vessel', 'merchant', 'trading ship'];

        return $types[array_rand($types)];
    }

    /**
     * Get random time frame.
     */
    protected function getRandomTimeFrame(): string
    {
        $frames = [
            'soon',
            'within the week',
            'any day now',
            'in the coming days',
            'before long',
            'shortly',
            'in a few cycles',
        ];

        return $frames[array_rand($frames)];
    }

    /**
     * Get random commodity.
     */
    protected function getRandomCommodity(): string
    {
        $commodities = [
            'water', 'fuel', 'ore', 'rare metals', 'food supplies', 'medical supplies',
            'weapons', 'electronics', 'luxury goods', 'spare parts', 'helium-3',
            'titanium', 'copper', 'organic compounds', 'construction materials',
        ];

        return $commodities[array_rand($commodities)];
    }

    /**
     * Get random market reason.
     */
    protected function getRandomMarketReason(string $effect): string
    {
        $shortageReasons = [
            'Mining operations have stalled.',
            'A convoy was raided.',
            'Supply lines are disrupted.',
            'Colony demand is spiking.',
            'There was an accident at the processing plant.',
        ];

        $surplusReasons = [
            'They overproduced this quarter.',
            'A big contract fell through.',
            'New mining operations came online.',
            'Demand dropped unexpectedly.',
            'Warehouses are overflowing.',
        ];

        $reasons = $effect === 'shortage' ? $shortageReasons : $surplusReasons;

        return $reasons[array_rand($reasons)];
    }

    /**
     * Get random discovery type.
     */
    protected function getRandomDiscovery(): string
    {
        $discoveries = [
            'an abandoned station',
            'rich mineral deposits',
            'precursor ruins',
            'a derelict fleet',
            'strange alien artifacts',
            'a hidden colony',
            'rare isotopes',
            'ancient technology',
            'a wormhole anomaly',
        ];

        return $discoveries[array_rand($discoveries)];
    }

    /**
     * Get random explorer name.
     */
    protected function getRandomExplorerName(): string
    {
        $names = ['Vance', 'Marlowe', 'Chen', 'Blackwood', 'Reyes', 'Kowalski', 'Okonkwo', 'Singh'];

        return $names[array_rand($names)];
    }

    /**
     * Get random person name.
     */
    protected function getRandomPersonName(): string
    {
        $names = [
            'Old Martinez', 'Jenny Two-Stars', 'Crazy Pete', 'The Duke',
            'Lucky Sam', 'Iron Mike', 'Red Kelly', 'Slim Patterson',
        ];

        return $names[array_rand($names)];
    }

    /**
     * Get random place name.
     */
    protected function getRandomPlaceName(): string
    {
        $places = [
            'Port Harmony', 'New Osaka Station', 'The Fringe', 'Outpost Seven',
            'Crimson Dock', 'Haven Colony', 'The Junction', 'Waypoint Delta',
        ];

        return $places[array_rand($places)];
    }

    /**
     * Get random ship name.
     */
    protected function getRandomShipName(): string
    {
        $names = [
            'the Stellar Fortune', 'the Black Diamond', 'the Wandering Star',
            'the Iron Maiden', 'the Lucky Break', 'the Midnight Runner',
            'the Dusty Horizon', 'the Crimson Eclipse',
        ];

        return $names[array_rand($names)];
    }

    /**
     * Get random drink.
     */
    protected function getRandomDrink(): string
    {
        $drinks = [
            'Rigellian ale', 'synth-whiskey', 'nebula wine', 'void coffee',
            'engine cleaner', 'station brew', 'imported spirits', 'the local swill',
        ];

        return $drinks[array_rand($drinks)];
    }

    /**
     * Get random coordinates string.
     */
    protected function getRandomCoordinates(Galaxy $galaxy): string
    {
        $x = rand(0, $galaxy->width ?? 2000);
        $y = rand(0, $galaxy->height ?? 2000);

        return "({$x}, {$y})";
    }
}

<?php

namespace App\Services;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerPrecursorRumor;
use App\Models\PrecursorShip;
use App\Models\TradingHub;
use Illuminate\Support\Collection;

/**
 * Service for handling Precursor ship rumors at ship yards.
 *
 * Every ship yard has heard rumors about where the legendary Precursor ship is
 *hidden.
 * Every ship yard thinks they know where it is.
 * Every ship yard is wrong.
 *
 * The rumors are generated to be plausible but incorrect - they point to
 * deep space locations that are similar to where the real ship is hidden
 * (far from stars) but never the actual location.
 */
class PrecursorRumorService
{
    /**
     * Ship yard owner first names for flavor
     */
    private const OWNER_FIRST_NAMES = [
        'Zara', 'Viktor', 'Mei', 'Omar', 'Elena', 'Koji', 'Astrid', 'Darius',
        'Nadia', 'Felix', 'Lena', 'Marcus', 'Yuki', 'Sven', 'Priya', 'Chen',
        'Ingrid', 'Hassan', 'Vera', 'Dmitri', 'Kenji', 'Freya', 'Raj', 'Olga',
    ];

    /**
     * Ship yard owner last names for flavor
     */
    private const OWNER_LAST_NAMES = [
        'Vance', 'Kowalski', 'Chen', 'Okonkwo', 'Petrov', 'Tanaka', 'Berg',
        'Al-Rashid', 'Volkov', 'Kim', 'Larsson', 'Singh', 'Nakamura', 'Walsh',
        'Rodriguez', 'Johansson', 'Patel', 'Ivanov', 'Yamamoto', 'Hansen',
    ];

    /**
     * Rumor flavor texts - how they "found out" about the location
     */
    private const RUMOR_FLAVORS = [
        "I got this from a dying smuggler who claimed he'd seen it with his own eyes.",
        'My grandfather served on a survey ship that mapped this sector 50 years ago. He swore he detected something... ancient.',
        'A Precursor researcher stayed here once. Left in a hurry but forgot his notes. These coordinates were circled three times.',
        "I've been collecting star charts my whole life. Pattern analysis points to this location - I'd bet my shipyard on it.",
        'A freighter captain traded this information for emergency repairs. Said he saw lights in the void.',
        "The old nav beacons in sector 7 have always been slightly off. I finally figured out why - they're being pulled toward something.",
        "I hacked an old military database. There's a no-fly zone that's been classified for centuries. These are the coordinates.",
        "My ex-wife's brother worked for the Stellar Cartography Guild. Before he vanished, he sent me these coordinates.",
        "Dreams. I know how it sounds, but I've had the same dream for twenty years. A ship waiting in the dark.",
        'A Precursor artifact I found years ago - it hums when I point it at these coordinates. Never any other direction.',
        'I tracked the trajectory of ancient debris. It all originates from one point. Something catastrophic happened there.',
        'Intercepted transmissions. Very old. Very faint. Repeating from these coordinates for who knows how long.',
    ];

    /**
     * Generate rumors for all ship yards in a galaxy.
     * Called during galaxy generation.
     *
     * Note: We generate rumors for ALL trading hubs (not just those with active
     * shipyard inventory) because every trading hub has mechanics who've heard
     * the legends of the Precursor ship. The actual shipyard inventory is dynamic
     * and may be populated later.
     */
    public function generateRumorsForGalaxy(Galaxy $galaxy): int
    {
        $precursorShip = PrecursorShip::where('galaxy_id', $galaxy->id)->first();

        if (! $precursorShip) {
            return 0;
        }

        // Get all trading hubs in this galaxy
        // Every hub has mechanics who've heard the legends
        $tradingHubs = TradingHub::whereHas('pointOfInterest', function ($query) use ($galaxy) {
            $query->where('galaxy_id', $galaxy->id);
        })->get();

        $count = 0;
        foreach ($tradingHubs as $hub) {
            $this->generateRumorForHub($hub, $precursorShip, $galaxy);
            $count++;
        }

        return $count;
    }

    /**
     * Generate a single (wrong) rumor for a ship yard.
     */
    public function generateRumorForHub(TradingHub $hub,
        PrecursorShip $precursorShip,
        Galaxy $galaxy): void
    {
        // Generate a plausible but WRONG location
        // Should be in "empty space" like the real one, but definitely not the
        // real coordinates
        $wrongCoords = $this->generateWrongLocation($precursorShip, $galaxy);

        // Generate owner name
        $ownerName = self::OWNER_FIRST_NAMES[
                     array_rand(self::OWNER_FIRST_NAMES)]
                     .' '.self::OWNER_LAST_NAMES[
                     array_rand(self::OWNER_LAST_NAMES)];

        // Generate flavor text
        $flavor = self::RUMOR_FLAVORS[array_rand(self::RUMOR_FLAVORS)];

        // Calculate bribe cost based on hub tier and confidence
        $confidence = round(rand(30, 95) / 100, 2);
        $baseBribeCost = match ($hub->getTier()) {
            'premium' => 50000,
            'major' => 25000,
            default => 10000,
        };
        $bribeCost = (int) ($baseBribeCost * (1 + $confidence));

        $hub->update([
            'precursor_rumor_x' => $wrongCoords['x'],
            'precursor_rumor_y' => $wrongCoords['y'],
            'precursor_rumor_confidence' => $confidence,
            'precursor_bribe_cost' => $bribeCost,
            'shipyard_owner_name' => $ownerName,
            'precursor_rumor_flavor' => $flavor,
        ]);
    }

    /**
     * Generate a plausible but incorrect location.
     *
     * Rules:
     * - Must be at least 50 units from the real location
     * - Must be in "empty space" (far from POIs)
     * - Should be within galaxy bounds
     */
    private function generateWrongLocation(PrecursorShip $precursorShip,
        Galaxy $galaxy): array
    {
        $realX = $precursorShip->x;
        $realY = $precursorShip->y;
        $minDistanceFromReal = 50;

        $attempts = 0;
        $maxAttempts = 50;

        do {
            // Generate random coordinates in "deep space"
            $margin = 0.1;
            $rand_x_min = (int) $galaxy->width * $margin;
            $rand_x_max = (int) $galaxy->width * (1 - $margin);
            $rand_y_min = (int) $galaxy->height * $margin;
            $rand_y_max = (int) $galaxy->height * (1 - $margin);
            $x = rand($rand_x_min, $rand_x_max);
            $y = rand($rand_y_min, $rand_y_max);

            // Check distance from real location
            $distanceFromReal = sqrt(
                pow(abs($x - $realX), 2) + pow(abs($y - $realY), 2)
            );

            if ($distanceFromReal >= $minDistanceFromReal) {
                return ['x' => $x, 'y' => $y];
            }

            $attempts++;
        } while ($attempts < $maxAttempts);

        // Fallback: just offset significantly from real location
        $angle = deg2rad(rand(0, 360));
        $distance = rand(100, 200);

        return [
            'x' => max(10, min($galaxy->width - 10, $realX +
                               (int) ($distance * cos($angle)))),
            'y' => max(10, min($galaxy->height - 10, $realY +
                               (int) ($distance * sin($angle)))),
        ];
    }

    /**
     * Bribe a ship yard owner for their rumor.
     *
     * @return array{success: bool, rumor?: array, error?: string}
     */
    public function bribeForRumor(Player $player, TradingHub $hub): array
    {
        // Verify player is at this hub
        $playerLocation = $player->currentLocation;
        if (! $playerLocation || $hub->poi_id !== $playerLocation->id) {
            return [
                'success' => false,
                'error' => 'You must be at this trading hub to bribe the ship '.
                           'yard owner.',
            ];
        }

        // Check if hub has a rumor
        if (! $hub->hasPrecursorRumor()) {
            return [
                'success' => false,
                'error' => 'The ship yard owner here hasn\'t heard any rumors '.
                'about the Precursor ship.',
            ];
        }

        // Check if player already has this rumor
        if ($hub->playerHasRumor($player)) {
            return [
                'success' => false,
                'error' => 'You\'ve already obtained this rumor. The ship yard'.
                           ' owner has nothing new to tell you.',
                'already_obtained' => true,
            ];
        }

        // Check credits
        if ($player->credits < $hub->precursor_bribe_cost) {
            return [
                'success' => false,
                'error' => sprintf(
                    '%s looks you over and scoffs. "Information like this '.
                    'doesn\'t come cheap. Come back when you have %s credits."',
                    $hub->shipyard_owner_name,
                    number_format($hub->precursor_bribe_cost)
                ),
            ];
        }

        // TODO: TECH DEBT - Wrap in DB::transaction()
        //       Issue: Credit deduction and rumor creation are not atomic
        //       Risk: If PlayerPrecursorRumor::create() fails, credits are
        //             already deducted with no rollback
        //       Fix: Use DB::transaction(function() { ... }) around both
        //            operations
        //       Priority: Low (pre-release)

        // Process the bribe
        $player->deductCredits($hub->precursor_bribe_cost);

        // Record the rumor
        PlayerPrecursorRumor::create([
            'player_id' => $player->id,
            'trading_hub_id' => $hub->id,
            'rumor_x' => $hub->precursor_rumor_x,
            'rumor_y' => $hub->precursor_rumor_y,
            'bribe_paid' => $hub->precursor_bribe_cost,
        ]);

        // Grant rumor knowledge for nearby systems (fog-of-war integration)
        $this->grantRumorKnowledge($player, $hub);

        return [
            'success' => true,
            'rumor' => [
                'x' => $hub->precursor_rumor_x,
                'y' => $hub->precursor_rumor_y,
                'confidence' => $hub->precursor_rumor_confidence,
                'owner_name' => $hub->shipyard_owner_name,
                'story' => $hub->precursor_rumor_flavor,
            ],
            'bribe_paid' => $hub->precursor_bribe_cost,
            'remaining_credits' => $player->credits,
            'message' => $this->getBribeSuccessMessage($hub),
        ];
    }

    /**
     * Get the success message after a bribe.
     */
    private function getBribeSuccessMessage(TradingHub $hub): string
    {
        $confidence = (int) ($hub->precursor_rumor_confidence * 100);

        return <<<MESSAGE
{$hub->shipyard_owner_name} pockets your credits and leans in close.

"{$hub->precursor_rumor_flavor}"

They tap a data chip against the table.

"Coordinates: ({$hub->precursor_rumor_x}, {$hub->precursor_rumor_y}).
 I'm {$confidence}% sure that's where you'll find it."

"But hey, I've been wrong before. Good luck, pilot."
MESSAGE;
    }

    /**
     * Get all rumors a player has collected.
     */
    public function getPlayerRumors(Player $player): Collection
    {
        return PlayerPrecursorRumor::where('player_id', $player->id)
            ->with('tradingHub.pointOfInterest')
            ->get()
            ->map(function ($rumor) {
                return [
                    'hub_name' => $rumor->tradingHub->name,
                    'hub_location' => $rumor->tradingHub->pointOfInterest?->name,
                    'rumor_x' => $rumor->rumor_x,
                    'rumor_y' => $rumor->rumor_y,
                    'bribe_paid' => $rumor->bribe_paid,
                    'obtained_at' => $rumor->created_at->toDateTimeString(),
                ];
            });
    }

    /**
     * Check if rumored coordinates are close to real Precursor ship.
     * (Useful for giving players hints about rumor quality)
     */
    public function checkRumorAccuracy(int $rumorX, int $rumorY,
        Galaxy $galaxy): array
    {
        $precursorShip = PrecursorShip::where('galaxy_id', $galaxy->id)->first();

        if (! $precursorShip) {
            return ['has_precursor' => false];
        }

        $distance = sqrt(
            pow(abs($rumorX - $precursorShip->x), 2) +
            pow(abs($rumorY - $precursorShip->y), 2)
        );

        // All rumors are wrong, but some are less wrong than others
        $accuracy = match (true) {
            $distance < 30 => 'burning', // Very close but still wrong
            $distance < 75 => 'warm',
            $distance < 150 => 'tepid',
            default => 'cold',
        };

        return [
            'has_precursor' => true,
            'accuracy' => $accuracy,
            'distance' => round($distance, 1),
        ];
    }

    /**
     * Get gossip about the Precursor ship (without paying).
     * Free info that hints at the legend but gives no coordinates.
     */
    public function getShipyardGossip(TradingHub $hub): string
    {
        if (! $hub->hasPrecursorRumor()) {
            return 'The workers here haven\'t heard any rumors about the '.
                   'ancient Precursor ship.';
        }

        $ownerName = $hub->shipyard_owner_name ?? 'A grizzled mechanic';
        $bribeCost = number_format($hub->precursor_bribe_cost);

        return <<<GOSSIP
You notice the dock workers whispering among themselves.

When you ask about the Precursor ship, {$ownerName} looks up from their work.

"The Precursor ship? Yeah, I've heard the stories. Half-million year old ship,
 hidden somewhere in the deep black between stars. Tech beyond anything we can
 build today."

They pause, studying you carefully.

"I might know something about where to look. But information like that... it'll
 cost you. {$bribeCost} credits, and I'll tell you what I know."

"Fair warning though - everyone has a father's, brother's, nephew's, cousin's,
 former roommate who thinks they know where the thing is, but if they are, then
 there's at least twenty ships scattered across the galaxy.
GOSSIP;
    }

    /**
     * Grant fog-of-war knowledge for systems near the rumored coordinates.
     */
    private function grantRumorKnowledge(Player $player, TradingHub $hub): void
    {
        $rumorX = $hub->precursor_rumor_x;
        $rumorY = $hub->precursor_rumor_y;
        $poi = $hub->pointOfInterest;

        if (! $poi) {
            return;
        }

        // Find the nearest POI to the rumored coordinates (within 10 LY)
        $radius = 10;
        $nearestPoi = \App\Models\PointOfInterest::where('galaxy_id', $poi->galaxy_id)
            ->stars()
            ->where('is_hidden', false)
            ->where('x', '>=', $rumorX - $radius)
            ->where('x', '<=', $rumorX + $radius)
            ->where('y', '>=', $rumorY - $radius)
            ->where('y', '<=', $rumorY + $radius)
            ->whereRaw(
                'SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) <= ?',
                [$rumorX, $rumorY, $radius]
            )
            ->orderByRaw(
                'SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) ASC',
                [$rumorX, $rumorY]
            )
            ->first();

        if ($nearestPoi) {
            $knowledgeService = app(PlayerKnowledgeService::class);
            $knowledgeService->applyRumorKnowledge($player, $nearestPoi);
        }
    }
}

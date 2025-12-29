<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Console\Command;

class InitializePlayerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'player:init {user_id} {call_sign}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize a new player with a starter ship';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $callSign = $this->argument('call_sign');

        // Validate user exists
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        // Check if player already exists for this user
        if ($user->player) {
            $this->error("Player already exists for user {$user->name}.");
            return 1;
        }

        // Check if call sign is already taken
        if (Player::where('call_sign', $callSign)->exists()) {
            $this->error("Call sign '{$callSign}' is already taken.");
            return 1;
        }

        // Get the starter ship (Sparrow-class)
        $starterShip = Ship::where('class', 'Light Freighter')->first();
        if (!$starterShip) {
            $this->error("Starter ship not found. Please seed the ship types first.");
            return 1;
        }

        // Find a random starting location (preferably a star system)
        $startingLocation = PointOfInterest::where('type', PointOfInterestType::STAR)
            ->where('is_hidden', false)
            ->inRandomOrder()
            ->first();

        if (!$startingLocation) {
            // Fallback to any POI if no stars found
            $startingLocation = PointOfInterest::where('is_hidden', false)
                ->inRandomOrder()
                ->first();
        }

        // Create the player
        $player = Player::create([
            'user_id' => $userId,
            'call_sign' => $callSign,
            'credits' => 10000.00,
            'experience' => 0,
            'level' => 1,
            'current_poi_id' => $startingLocation?->id,
            'status' => 'active',
        ]);

        // Create the player's starter ship instance
        $playerShip = PlayerShip::create([
            'player_id' => $player->id,
            'ship_id' => $starterShip->id,
            'name' => "{$callSign}'s {$starterShip->class}",
            'current_fuel' => $starterShip->attributes['max_fuel'],
            'max_fuel' => $starterShip->attributes['max_fuel'],
            'hull' => $starterShip->hull_strength,
            'max_hull' => $starterShip->hull_strength,
            'weapons' => $starterShip->attributes['starting_weapons'],
            'cargo_hold' => $starterShip->cargo_capacity,
            'sensors' => $starterShip->attributes['starting_sensors'],
            'warp_drive' => $starterShip->attributes['starting_warp_drive'],
            'current_cargo' => 0,
            'is_active' => true,
            'status' => 'operational',
        ]);

        $this->info("Player '{$callSign}' initialized successfully!");
        $this->info("Ship: {$playerShip->name}");
        $this->info("Credits: {$player->credits}");
        $this->info("Starting fuel: {$playerShip->current_fuel}/{$playerShip->max_fuel}");

        if ($startingLocation) {
            $this->info("Starting location: {$startingLocation->name} ({$startingLocation->type->name})");
            $this->info("Coordinates: ({$startingLocation->x}, {$startingLocation->y})");
        }

        return 0;
    }
}

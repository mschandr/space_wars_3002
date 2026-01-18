<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use App\Models\User;
use App\Services\PlayerSpawnService;
use App\Services\StarChartService;
use Illuminate\Console\Command;

class InitializePlayerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'player:init
                            {galaxy_id : The galaxy ID to initialize the player in}
                            {user_id : The user ID}
                            {call_sign : The player\'s call sign}
                            {--continue-ship=0 : Continue with existing ship from another galaxy (1=yes, 0=no, default: 0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize a new player with a starter ship in a specific galaxy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $galaxyId = $this->argument('galaxy_id');
        $userId = $this->argument('user_id');
        $callSign = $this->argument('call_sign');

        // Validate galaxy exists
        $galaxy = Galaxy::find($galaxyId);
        if (! $galaxy) {
            $this->error("Galaxy with ID {$galaxyId} not found.");

            return 1;
        }

        // Validate user exists
        $user = User::find($userId);
        if (! $user) {
            $this->error("User with ID {$userId} not found.");

            return 1;
        }

        // Check if player already exists for this user in this galaxy
        if (Player::where('user_id', $userId)->where('galaxy_id', $galaxyId)->exists()) {
            $this->error("Player already exists for user {$user->name} in galaxy '{$galaxy->name}'.");

            return 1;
        }

        // Check if call sign is already taken in this galaxy (galaxy-scoped uniqueness)
        if (Player::where('galaxy_id', $galaxyId)->where('call_sign', $callSign)->exists()) {
            $this->error("Call sign '{$callSign}' is already taken in galaxy '{$galaxy->name}'.");

            return 1;
        }

        // Check if user wants to continue with an existing ship
        $continueShip = (bool) $this->option('continue-ship');
        $sourceShip = null;

        if ($continueShip) {
            // Find the user's most recent active ship from another galaxy
            $sourceShip = PlayerShip::whereHas('player', function ($query) use ($userId, $galaxyId) {
                $query->where('user_id', $userId)
                    ->where('galaxy_id', '!=', $galaxyId);
            })
                ->where('is_active', true)
                ->orderBy('updated_at', 'desc')
                ->first();

            if (! $sourceShip) {
                $this->warn('No existing ship found for this user in other galaxies. Creating starter ship instead.');
                $continueShip = false;
            } else {
                $this->info("Found existing ship: {$sourceShip->name} (upgrading to new galaxy)");
            }
        }

        // Get the starter ship blueprint (used if not continuing with existing ship)
        $starterShipBlueprint = Ship::where('class', 'Light Freighter')->first();
        if (! $starterShipBlueprint && ! $continueShip) {
            $this->error('Starter ship not found. Please seed the ship types first.');

            return 1;
        }

        // Find an optimal starting location using PlayerSpawnService
        $spawnService = app(PlayerSpawnService::class);
        $startingLocation = $spawnService->findOptimalSpawnLocation($galaxy);

        if (! $startingLocation) {
            $this->error("Could not find a suitable starting location in galaxy '{$galaxy->name}'.");

            return 1;
        }

        // Get spawn location quality report
        $spawnReport = $spawnService->getSpawnLocationReport($startingLocation, $galaxy);

        // Create the player
        $player = Player::create([
            'user_id' => $userId,
            'galaxy_id' => $galaxyId,
            'call_sign' => $callSign,
            'credits' => 10000.00,
            'experience' => 0,
            'level' => 1,
            'current_poi_id' => $startingLocation?->id,
            'status' => 'active',
        ]);

        // Create the player's ship instance (either from existing ship or starter)
        if ($continueShip && $sourceShip) {
            // Clone the existing ship from another galaxy
            $playerShip = PlayerShip::create([
                'player_id' => $player->id,
                'ship_id' => $sourceShip->ship_id,
                'name' => "{$callSign}'s {$sourceShip->ship->class}",
                'current_fuel' => $sourceShip->max_fuel, // Start with full fuel
                'max_fuel' => $sourceShip->max_fuel,
                'hull' => $sourceShip->max_hull, // Start with full hull
                'max_hull' => $sourceShip->max_hull,
                'weapons' => $sourceShip->weapons,
                'cargo_hold' => $sourceShip->cargo_hold,
                'sensors' => $sourceShip->sensors,
                'warp_drive' => $sourceShip->warp_drive,
                'shields' => $sourceShip->shields ?? 0,
                'current_cargo' => 0, // Empty cargo
                'is_active' => true,
                'status' => 'operational',
                'fuel_last_updated_at' => now(),
            ]);
        } else {
            // Create a new starter ship
            $playerShip = PlayerShip::create([
                'player_id' => $player->id,
                'ship_id' => $starterShipBlueprint->id,
                'name' => "{$callSign}'s {$starterShipBlueprint->class}",
                'current_fuel' => $starterShipBlueprint->attributes['max_fuel'],
                'max_fuel' => $starterShipBlueprint->attributes['max_fuel'],
                'hull' => $starterShipBlueprint->hull_strength,
                'max_hull' => $starterShipBlueprint->hull_strength,
                'weapons' => $starterShipBlueprint->attributes['starting_weapons'],
                'cargo_hold' => $starterShipBlueprint->cargo_capacity,
                'sensors' => $starterShipBlueprint->attributes['starting_sensors'],
                'warp_drive' => $starterShipBlueprint->attributes['starting_warp_drive'],
                'shields' => $starterShipBlueprint->attributes['starting_shields'] ?? 0,
                'current_cargo' => 0,
                'is_active' => true,
                'status' => 'operational',
                'fuel_last_updated_at' => now(),
            ]);
        }

        // Grant starting star charts
        $chartService = app(StarChartService::class);
        $chartsGranted = $chartService->grantStartingCharts($player);

        $this->info("Player '{$callSign}' initialized successfully!");
        $this->info("Galaxy: {$galaxy->name}");
        if ($continueShip && $sourceShip) {
            $this->info("Ship: {$playerShip->name} (transferred from another galaxy)");
            $this->info("  └─ Weapons: {$playerShip->weapons}, Sensors: {$playerShip->sensors}, Warp Drive: {$playerShip->warp_drive}");
        } else {
            $this->info("Ship: {$playerShip->name} (starter ship)");
        }
        $this->info("Credits: {$player->credits}");
        $this->info("Starting fuel: {$playerShip->current_fuel}/{$playerShip->max_fuel}");

        // Display spawn location information
        $this->info("Starting location: {$startingLocation->name} ({$startingLocation->type->name})");
        $this->info("Coordinates: ({$startingLocation->x}, {$startingLocation->y})");
        $this->newLine();

        $this->info("Spawn Location Quality: {$spawnReport['rating']} (Score: {$spawnReport['spawn_score']})");
        $this->info('  Inhabited System: '.($spawnReport['inhabited'] ? 'Yes' : 'No'));
        $this->info('  Trading Hub: '.($spawnReport['has_trading_hub'] ? 'Yes' : 'No'));
        if ($spawnReport['has_trading_hub'] && $spawnReport['has_shipyard']) {
            $this->info('  Shipyard Available: Yes');
        }
        $this->info("  Warp Gates: {$spawnReport['warp_gates']}");
        $this->info("  Nearby Trading Hubs (within 200 units): {$spawnReport['nearby_hubs']}");

        if ($chartsGranted > 0) {
            $this->newLine();
            $this->info("Star charts granted: {$chartsGranted} nearby system(s)");
        }

        return 0;
    }
}

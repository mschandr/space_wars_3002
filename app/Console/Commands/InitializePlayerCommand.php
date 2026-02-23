<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\Player;
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
    protected $description = 'Initialize a new player in a specific galaxy';

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

        // Find an optimal starting location using PlayerSpawnService
        $spawnService = app(PlayerSpawnService::class);
        $startingLocation = $spawnService->findOptimalSpawnLocation($galaxy);

        if (! $startingLocation) {
            $this->error("Could not find a suitable starting location in galaxy '{$galaxy->name}'.");

            return 1;
        }

        // Get spawn location quality report
        $spawnReport = $spawnService->getSpawnLocationReport($startingLocation, $galaxy);

        // Get starting credits from config
        $startingCredits = config('game_config.ships.starting_credits', 10000);

        // Create the player (no starter ship — player must buy their first ship at a shipyard)
        $player = Player::create([
            'user_id' => $userId,
            'galaxy_id' => $galaxyId,
            'call_sign' => $callSign,
            'credits' => $startingCredits,
            'experience' => 0,
            'level' => 1,
            'current_poi_id' => $startingLocation?->id,
            'status' => 'active',
        ]);

        // Ensure a free Sparrow is available at the spawn location's shipyard
        $spawnService->ensureStarterShipAvailable($startingLocation, $galaxy);

        // Grant starting star charts
        $chartService = app(StarChartService::class);
        $chartsGranted = $chartService->grantStartingCharts($player);

        $this->info("Player '{$callSign}' initialized successfully!");
        $this->info("Galaxy: {$galaxy->name}");
        $this->info("Credits: {$player->credits}");
        $this->info('Visit the shipyard to purchase your first ship!');

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

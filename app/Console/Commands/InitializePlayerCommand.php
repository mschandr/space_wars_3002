<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\User;
use App\Services\PlayerSpawnService;
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

        // Validate inputs
        $galaxy = $this->validateGalaxy($galaxyId);
        if (! $galaxy) {
            return 1;
        }

        $user = $this->validateUser($userId);
        if (! $user) {
            return 1;
        }

        if (! $this->validatePlayerUnique($userId, $galaxyId, $galaxy)) {
            return 1;
        }

        if (! $this->validateCallSignUnique($galaxyId, $callSign, $galaxy)) {
            return 1;
        }

        // Initialize and display results
        $result = $this->initializePlayerFlow($galaxy, $user, $callSign);
        if ($result === false) {
            return 1;
        }

        [$player, $startingLocation, $spawnReport] = $result;
        $this->displayInitializationSuccess($callSign, $galaxy, $player, $startingLocation, $spawnReport);

        return 0;
    }

    /**
     * Validate that galaxy exists.
     */
    private function validateGalaxy(int $galaxyId): ?Galaxy
    {
        $galaxy = Galaxy::find($galaxyId);
        if (! $galaxy) {
            $this->error("Galaxy with ID {$galaxyId} not found.");
        }

        return $galaxy;
    }

    /**
     * Validate that user exists.
     */
    private function validateUser(int $userId): ?User
    {
        $user = User::find($userId);
        if (! $user) {
            $this->error("User with ID {$userId} not found.");
        }

        return $user;
    }

    /**
     * Validate that player doesn't already exist for this user in this galaxy.
     */
    private function validatePlayerUnique(int $userId, int $galaxyId, Galaxy $galaxy): bool
    {
        if (Player::where('user_id', $userId)->where('galaxy_id', $galaxyId)->exists()) {
            $user = User::find($userId);
            $this->error("Player already exists for user {$user->name} in galaxy '{$galaxy->name}'.");

            return false;
        }

        return true;
    }

    /**
     * Validate that call sign is not already taken in this galaxy.
     */
    private function validateCallSignUnique(int $galaxyId, string $callSign, Galaxy $galaxy): bool
    {
        if (Player::where('galaxy_id', $galaxyId)->where('call_sign', $callSign)->exists()) {
            $this->error("Call sign '{$callSign}' is already taken in galaxy '{$galaxy->name}'.");

            return false;
        }

        return true;
    }

    /**
     * Initialize player and get spawn report.
     *
     * @return array|false [player, startingLocation, spawnReport] or false on error
     */
    private function initializePlayerFlow(Galaxy $galaxy, User $user, string $callSign)
    {
        $spawnService = app(PlayerSpawnService::class);

        try {
            $player = $spawnService->initializePlayer($galaxy, $user, $callSign);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return false;
        }

        $startingLocation = $player->currentLocation;
        $spawnService->discoverSpawn($player, $startingLocation);
        $spawnReport = $spawnService->getSpawnLocationReport($startingLocation, $galaxy);

        return [$player, $startingLocation, $spawnReport];
    }

    /**
     * Display player initialization success message and spawn location details.
     */
    private function displayInitializationSuccess(string $callSign, Galaxy $galaxy, Player $player, $startingLocation, array $spawnReport): void
    {
        $this->info("Player '{$callSign}' initialized successfully!");
        $this->info("Galaxy: {$galaxy->name}");
        $this->info("Credits: {$player->credits}");
        $this->info('Visit the shipyard to purchase your first ship!');

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
    }
}

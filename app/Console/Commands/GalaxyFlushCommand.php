<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GalaxyFlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'galaxy:flush
                            {--galaxy= : Flush a specific galaxy by ID or UUID (optional)}
                            {--preserve-global : Preserve global seed data (minerals, ships, plans, pirate captains/factions)}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush all galaxy-related data from the database. Use with caution!';

    /**
     * Tables to flush in dependency order (children first, parents last).
     * Each entry is [table_name, description, join_info].
     *
     * @var array<array{0: string, 1: string, 2: string|null}>
     */
    private const TABLES_TO_FLUSH = [
        // Level 6: Deepest children (through multiple relationships)
        ['npc_cargos', 'NPC cargo inventories', 'through npc_ships → npcs'],
        ['npc_ships', 'NPC ships', 'through npcs'],
        ['pirate_cargo', 'Pirate fleet cargo', 'through pirate_fleets → pirate_captains'],
        ['pirate_fleets', 'Pirate fleets', 'through pirate_captains'],
        ['colony_missions', 'Colony missions', 'through colony_buildings → colonies'],
        ['colony_ship_production', 'Colony ship production', 'through colonies → players'],
        ['colony_buildings', 'Colony buildings', 'through colonies'],

        // Level 5: Through players/ships
        ['player_ship_components', 'Player ship components', 'through player_ships → players'],
        ['player_ship_fighters', 'Player ship fighters', 'through player_ships → players'],
        ['pvp_team_invitations', 'PvP team invitations', 'through pvp_challenges → players'],
        ['pvp_challenges', 'PvP challenges', 'through players'],
        ['combat_participants', 'Combat participants', 'through combat_sessions'],
        ['combat_sessions', 'Combat sessions', 'through points_of_interest'],

        // Level 4: Through various parent tables
        ['warp_lane_pirates', 'Warp lane pirates', 'through warp_gates'],
        ['pilot_lane_knowledge', 'Pilot lane knowledge', 'through players'],
        ['player_precursor_rumors', 'Player precursor rumors', 'through players'],
        ['player_notifications', 'Player notifications', 'through players'],
        ['player_star_charts', 'Player star charts', 'through players'],
        ['player_plans', 'Player plans', 'through players'],
        ['player_cargos', 'Player cargo', 'through player_ships → players'],
        ['player_ships', 'Player ships', 'through players'],
        ['market_events', 'Market events', 'through trading_hubs'],
        ['salvage_yard_inventory', 'Salvage yard inventory', 'through trading_hubs'],
        ['trading_hub_plans', 'Trading hub plans', 'through trading_hubs'],
        ['trading_hub_inventories', 'Trading hub inventories', 'through trading_hubs'],
        ['system_scans', 'System scans', 'through points_of_interest'],

        // Level 3: Direct POI children
        ['colonies', 'Colonies', 'through points_of_interest'],
        ['stellar_cartographers', 'Stellar cartographers', 'through points_of_interest'],
        ['system_defenses', 'System defenses', 'through points_of_interest'],
        ['trading_hub_ships', 'Trading hub ship inventory', 'direct galaxy_id'],
        ['trading_hubs', 'Trading hubs', 'through points_of_interest'],
        ['pirate_captains', 'Pirate captains', 'through pirate_factions'],

        // Level 2: Direct galaxy children
        ['npcs', 'NPCs', 'direct galaxy_id'],
        ['precursor_ships', 'Precursor ships', 'direct galaxy_id'],
        ['pirate_factions', 'Pirate factions', 'direct galaxy_id'],
        ['warp_gates', 'Warp gates', 'direct galaxy_id'],
        ['sectors', 'Sectors', 'direct galaxy_id'],
        ['players', 'Players', 'direct galaxy_id'],
        ['points_of_interest', 'Points of interest', 'direct galaxy_id'],

        // Level 1: Galaxies themselves
        ['galaxies', 'Galaxies', 'root table'],
    ];

    /**
     * Global seed tables that can optionally be preserved.
     */
    private const GLOBAL_SEED_TABLES = [
        'minerals' => 'Minerals',
        'ships' => 'Ship blueprints',
        'plans' => 'Upgrade plans',
        'pirate_factions' => 'Pirate factions (global)',
        'pirate_captains' => 'Pirate captains (global)',
        'ship_components' => 'Ship components',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  GALAXY DATA FLUSH UTILITY');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        $galaxyId = $this->option('galaxy');
        $preserveGlobal = $this->option('preserve-global');
        $dryRun = $this->option('dry-run');

        // Resolve galaxy if specified
        $targetGalaxy = null;
        if ($galaxyId) {
            $targetGalaxy = $this->resolveGalaxy($galaxyId);
            if (! $targetGalaxy) {
                $this->error("Galaxy not found: {$galaxyId}");

                return Command::FAILURE;
            }
            $this->info("Target: Galaxy #{$targetGalaxy->id} - {$targetGalaxy->name}");
        } else {
            $this->warn('Target: ALL GALAXIES (no --galaxy specified)');
        }

        $this->newLine();

        // Show what will be deleted
        $this->displayTableList($targetGalaxy, $preserveGlobal);

        if ($dryRun) {
            $this->newLine();
            $this->info('DRY RUN - No data was deleted.');

            return Command::SUCCESS;
        }

        // Confirmation
        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('⚠️  WARNING: This action cannot be undone!');

            if ($targetGalaxy) {
                $confirm = $this->confirm(
                    "Are you sure you want to delete all data for galaxy '{$targetGalaxy->name}'?"
                );
            } else {
                $this->error('⚠️  YOU ARE ABOUT TO DELETE ALL GALAXY DATA FROM THE DATABASE!');
                $confirm = $this->confirm(
                    'Type "yes" to confirm deletion of ALL galaxies and related data',
                    false
                );
            }

            if (! $confirm) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        // Perform the flush
        $this->newLine();
        $this->info('Flushing data...');
        $this->newLine();

        $stats = $this->performFlush($targetGalaxy, $preserveGlobal);

        // Display results
        $this->newLine();
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  FLUSH COMPLETE');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        $this->displayStats($stats);

        return Command::SUCCESS;
    }

    /**
     * Resolve a galaxy by ID or UUID.
     */
    private function resolveGalaxy(string $identifier): ?object
    {
        // Try as integer ID first
        if (is_numeric($identifier)) {
            return DB::table('galaxies')->where('id', (int) $identifier)->first();
        }

        // Try as UUID
        return DB::table('galaxies')->where('uuid', $identifier)->first();
    }

    /**
     * Display the list of tables that will be affected.
     */
    private function displayTableList(?object $targetGalaxy, bool $preserveGlobal): void
    {
        $this->info('Tables to be flushed (in deletion order):');
        $this->newLine();

        $rows = [];
        foreach (self::TABLES_TO_FLUSH as [$table, $description, $joinInfo]) {
            // Skip global tables if preserving
            if ($preserveGlobal && isset(self::GLOBAL_SEED_TABLES[$table])) {
                continue;
            }

            $count = $this->getRowCount($table, $targetGalaxy);
            $rows[] = [
                $table,
                $description,
                number_format($count),
                $joinInfo,
            ];
        }

        $this->table(['Table', 'Description', 'Rows', 'Relationship'], $rows);
    }

    /**
     * Get the count of rows that would be affected.
     */
    private function getRowCount(string $table, ?object $targetGalaxy): int
    {
        if (! $targetGalaxy) {
            return DB::table($table)->count();
        }

        $galaxyId = $targetGalaxy->id;

        return match ($table) {
            // Direct galaxy_id
            'galaxies' => DB::table('galaxies')->where('id', $galaxyId)->count()
                + DB::table('galaxies')->where('mirror_galaxy_id', $galaxyId)->count(),
            'points_of_interest', 'warp_gates', 'sectors', 'players', 'npcs',
            'precursor_ships', 'trading_hub_ships' => DB::table($table)->where('galaxy_id', $galaxyId)->count(),

            'pirate_factions' => DB::table('pirate_factions')
                ->where('galaxy_id', $galaxyId)
                ->orWhereNull('galaxy_id')
                ->count(),

            // Through points_of_interest
            'trading_hubs', 'stellar_cartographers', 'system_defenses', 'colonies', 'system_scans', 'combat_sessions' => $this->countThroughPoi($table, $galaxyId),

            // Through trading_hubs
            'trading_hub_inventories', 'trading_hub_plans', 'salvage_yard_inventory', 'market_events' => $this->countThroughTradingHub($table, $galaxyId),

            // Through players
            'player_ships' => DB::table('player_ships')
                ->join('players', 'player_ships.player_id', '=', 'players.id')
                ->where('players.galaxy_id', $galaxyId)
                ->count(),

            'player_cargos' => DB::table('player_cargos')
                ->join('player_ships', 'player_cargos.player_ship_id', '=', 'player_ships.id')
                ->join('players', 'player_ships.player_id', '=', 'players.id')
                ->where('players.galaxy_id', $galaxyId)
                ->count(),

            'player_plans', 'player_star_charts', 'player_notifications', 'player_precursor_rumors',
            'pilot_lane_knowledge', 'pvp_challenges' => DB::table($table)
                ->join('players', "{$table}.player_id", '=', 'players.id')
                ->where('players.galaxy_id', $galaxyId)
                ->count(),

            // Through player_ships
            'player_ship_fighters', 'player_ship_components' => DB::table($table)
                ->join('player_ships', "{$table}.player_ship_id", '=', 'player_ships.id')
                ->join('players', 'player_ships.player_id', '=', 'players.id')
                ->where('players.galaxy_id', $galaxyId)
                ->count(),

            // Through warp_gates
            'warp_lane_pirates' => DB::table('warp_lane_pirates')
                ->join('warp_gates', 'warp_lane_pirates.warp_gate_id', '=', 'warp_gates.id')
                ->where('warp_gates.galaxy_id', $galaxyId)
                ->count(),

            // Through pirate_factions
            'pirate_captains' => DB::table('pirate_captains')
                ->join('pirate_factions', 'pirate_captains.pirate_faction_id', '=', 'pirate_factions.id')
                ->where(function ($q) use ($galaxyId) {
                    $q->where('pirate_factions.galaxy_id', $galaxyId)
                        ->orWhereNull('pirate_factions.galaxy_id');
                })
                ->count(),

            // Through pirate_captains -> pirate_factions
            'pirate_fleets' => DB::table('pirate_fleets')
                ->join('pirate_captains', 'pirate_fleets.pirate_captain_id', '=', 'pirate_captains.id')
                ->join('pirate_factions', 'pirate_captains.pirate_faction_id', '=', 'pirate_factions.id')
                ->where(function ($q) use ($galaxyId) {
                    $q->where('pirate_factions.galaxy_id', $galaxyId)
                        ->orWhereNull('pirate_factions.galaxy_id');
                })
                ->count(),

            'pirate_cargo' => DB::table('pirate_cargo')
                ->join('pirate_fleets', 'pirate_cargo.pirate_fleet_id', '=', 'pirate_fleets.id')
                ->join('pirate_captains', 'pirate_fleets.pirate_captain_id', '=', 'pirate_captains.id')
                ->join('pirate_factions', 'pirate_captains.pirate_faction_id', '=', 'pirate_factions.id')
                ->where(function ($q) use ($galaxyId) {
                    $q->where('pirate_factions.galaxy_id', $galaxyId)
                        ->orWhereNull('pirate_factions.galaxy_id');
                })
                ->count(),

            // Through npcs
            'npc_ships' => DB::table('npc_ships')
                ->join('npcs', 'npc_ships.npc_id', '=', 'npcs.id')
                ->where('npcs.galaxy_id', $galaxyId)
                ->count(),

            'npc_cargos' => DB::table('npc_cargos')
                ->join('npc_ships', 'npc_cargos.npc_ship_id', '=', 'npc_ships.id')
                ->join('npcs', 'npc_ships.npc_id', '=', 'npcs.id')
                ->where('npcs.galaxy_id', $galaxyId)
                ->count(),

            // Through colonies
            'colony_buildings' => DB::table('colony_buildings')
                ->join('colonies', 'colony_buildings.colony_id', '=', 'colonies.id')
                ->join('points_of_interest', 'colonies.poi_id', '=', 'points_of_interest.id')
                ->where('points_of_interest.galaxy_id', $galaxyId)
                ->count(),

            'colony_missions' => DB::table('colony_missions')
                ->join('colony_buildings', 'colony_missions.colony_building_id', '=', 'colony_buildings.id')
                ->join('colonies', 'colony_buildings.colony_id', '=', 'colonies.id')
                ->join('points_of_interest', 'colonies.poi_id', '=', 'points_of_interest.id')
                ->where('points_of_interest.galaxy_id', $galaxyId)
                ->count(),

            'colony_ship_production' => DB::table('colony_ship_production')
                ->join('colonies', 'colony_ship_production.colony_id', '=', 'colonies.id')
                ->join('points_of_interest', 'colonies.poi_id', '=', 'points_of_interest.id')
                ->where('points_of_interest.galaxy_id', $galaxyId)
                ->count(),

            // Through combat_sessions
            'combat_participants' => DB::table('combat_participants')
                ->join('combat_sessions', 'combat_participants.combat_session_id', '=', 'combat_sessions.id')
                ->join('points_of_interest', 'combat_sessions.poi_id', '=', 'points_of_interest.id')
                ->where('points_of_interest.galaxy_id', $galaxyId)
                ->count(),

            // Through pvp_challenges
            'pvp_team_invitations' => DB::table('pvp_team_invitations')
                ->join('pvp_challenges', 'pvp_team_invitations.pvp_challenge_id', '=', 'pvp_challenges.id')
                ->join('players', 'pvp_challenges.challenger_id', '=', 'players.id')
                ->where('players.galaxy_id', $galaxyId)
                ->count(),

            default => 0,
        };
    }

    private function countThroughPoi(string $table, int $galaxyId): int
    {
        $poiColumn = $table === 'combat_sessions' ? 'poi_id' : 'poi_id';

        return DB::table($table)
            ->join('points_of_interest', "{$table}.poi_id", '=', 'points_of_interest.id')
            ->where('points_of_interest.galaxy_id', $galaxyId)
            ->count();
    }

    private function countThroughTradingHub(string $table, int $galaxyId): int
    {
        return DB::table($table)
            ->join('trading_hubs', "{$table}.trading_hub_id", '=', 'trading_hubs.id')
            ->join('points_of_interest', 'trading_hubs.poi_id', '=', 'points_of_interest.id')
            ->where('points_of_interest.galaxy_id', $galaxyId)
            ->count();
    }

    /**
     * Perform the actual flush operation.
     */
    private function performFlush(?object $targetGalaxy, bool $preserveGlobal): array
    {
        $stats = [];

        // Disable foreign key checks for faster deletion
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TABLES_TO_FLUSH as [$table, $description, $joinInfo]) {
                // Skip global tables if preserving
                if ($preserveGlobal && isset(self::GLOBAL_SEED_TABLES[$table])) {
                    $this->line("  ⊘ Skipping {$table} (preserving global data)");

                    continue;
                }

                $deleted = $this->deleteFromTable($table, $targetGalaxy);
                $stats[$table] = $deleted;

                if ($deleted > 0) {
                    $this->info("  ✓ {$table}: ".number_format($deleted).' rows deleted');
                } else {
                    $this->line("  · {$table}: 0 rows");
                }
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $stats;
    }

    /**
     * Delete rows from a specific table.
     */
    private function deleteFromTable(string $table, ?object $targetGalaxy): int
    {
        if (! $targetGalaxy) {
            // Delete all rows
            return DB::table($table)->delete();
        }

        $galaxyId = $targetGalaxy->id;

        // Get IDs of mirror galaxies too
        $mirrorGalaxyIds = DB::table('galaxies')
            ->where('mirror_galaxy_id', $galaxyId)
            ->pluck('id')
            ->toArray();

        $allGalaxyIds = array_merge([$galaxyId], $mirrorGalaxyIds);

        return match ($table) {
            // Direct galaxy_id - include mirror galaxies
            'galaxies' => DB::table('galaxies')
                ->whereIn('id', $allGalaxyIds)
                ->delete(),

            'points_of_interest', 'warp_gates', 'sectors', 'players', 'npcs',
            'precursor_ships', 'trading_hub_ships' => DB::table($table)
                ->whereIn('galaxy_id', $allGalaxyIds)
                ->delete(),

            'pirate_factions' => DB::table('pirate_factions')
                ->where(function ($q) use ($allGalaxyIds) {
                    $q->whereIn('galaxy_id', $allGalaxyIds)
                        ->orWhereNull('galaxy_id');
                })
                ->delete(),

            // Through points_of_interest
            'trading_hubs', 'stellar_cartographers', 'system_defenses', 'colonies',
            'system_scans', 'combat_sessions' => $this->deleteThroughPoi($table, $allGalaxyIds),

            // Through trading_hubs
            'trading_hub_inventories', 'trading_hub_plans', 'salvage_yard_inventory',
            'market_events' => $this->deleteThroughTradingHub($table, $allGalaxyIds),

            // Through players
            'player_ships' => $this->deleteThroughPlayers('player_ships', 'player_id', $allGalaxyIds),

            'player_cargos' => $this->deleteThroughPlayerShips('player_cargos', 'player_ship_id', $allGalaxyIds),

            'player_plans', 'player_star_charts', 'player_notifications', 'player_precursor_rumors',
            'pilot_lane_knowledge', 'pvp_challenges' => $this->deleteThroughPlayers($table, 'player_id', $allGalaxyIds),

            // Through player_ships
            'player_ship_fighters', 'player_ship_components' => $this->deleteThroughPlayerShips($table, 'player_ship_id', $allGalaxyIds),

            // Through warp_gates
            'warp_lane_pirates' => $this->deleteThroughWarpGates($allGalaxyIds),

            // Through pirate hierarchy
            'pirate_captains' => $this->deletePirateCaptains($allGalaxyIds),
            'pirate_fleets' => $this->deletePirateFleets($allGalaxyIds),
            'pirate_cargo' => $this->deletePirateCargo($allGalaxyIds),

            // Through npcs
            'npc_ships' => $this->deleteThroughNpcs('npc_ships', $allGalaxyIds),
            'npc_cargos' => $this->deleteThroughNpcShips($allGalaxyIds),

            // Through colonies
            'colony_buildings' => $this->deleteThroughColonies('colony_buildings', 'colony_id', $allGalaxyIds),
            'colony_missions' => $this->deleteColonyMissions($allGalaxyIds),
            'colony_ship_production' => $this->deleteThroughColonies('colony_ship_production', 'colony_id', $allGalaxyIds),

            // Through combat_sessions
            'combat_participants' => $this->deleteCombatParticipants($allGalaxyIds),

            // Through pvp_challenges
            'pvp_team_invitations' => $this->deletePvpTeamInvitations($allGalaxyIds),

            default => 0,
        };
    }

    private function deleteThroughPoi(string $table, array $galaxyIds): int
    {
        $poiIds = DB::table('points_of_interest')
            ->whereIn('galaxy_id', $galaxyIds)
            ->pluck('id');

        if ($poiIds->isEmpty()) {
            return 0;
        }

        return DB::table($table)
            ->whereIn('poi_id', $poiIds)
            ->delete();
    }

    private function deleteThroughTradingHub(string $table, array $galaxyIds): int
    {
        $hubIds = DB::table('trading_hubs')
            ->join('points_of_interest', 'trading_hubs.poi_id', '=', 'points_of_interest.id')
            ->whereIn('points_of_interest.galaxy_id', $galaxyIds)
            ->pluck('trading_hubs.id');

        if ($hubIds->isEmpty()) {
            return 0;
        }

        return DB::table($table)
            ->whereIn('trading_hub_id', $hubIds)
            ->delete();
    }

    private function deleteThroughPlayers(string $table, string $column, array $galaxyIds): int
    {
        $playerIds = DB::table('players')
            ->whereIn('galaxy_id', $galaxyIds)
            ->pluck('id');

        if ($playerIds->isEmpty()) {
            return 0;
        }

        return DB::table($table)
            ->whereIn($column, $playerIds)
            ->delete();
    }

    private function deleteThroughPlayerShips(string $table, string $column, array $galaxyIds): int
    {
        $shipIds = DB::table('player_ships')
            ->join('players', 'player_ships.player_id', '=', 'players.id')
            ->whereIn('players.galaxy_id', $galaxyIds)
            ->pluck('player_ships.id');

        if ($shipIds->isEmpty()) {
            return 0;
        }

        return DB::table($table)
            ->whereIn($column, $shipIds)
            ->delete();
    }

    private function deleteThroughWarpGates(array $galaxyIds): int
    {
        $gateIds = DB::table('warp_gates')
            ->whereIn('galaxy_id', $galaxyIds)
            ->pluck('id');

        if ($gateIds->isEmpty()) {
            return 0;
        }

        return DB::table('warp_lane_pirates')
            ->whereIn('warp_gate_id', $gateIds)
            ->delete();
    }

    private function deletePirateCaptains(array $galaxyIds): int
    {
        $factionIds = DB::table('pirate_factions')
            ->where(function ($q) use ($galaxyIds) {
                $q->whereIn('galaxy_id', $galaxyIds)
                    ->orWhereNull('galaxy_id');
            })
            ->pluck('id');

        if ($factionIds->isEmpty()) {
            return 0;
        }

        return DB::table('pirate_captains')
            ->whereIn('pirate_faction_id', $factionIds)
            ->delete();
    }

    private function deletePirateFleets(array $galaxyIds): int
    {
        $captainIds = DB::table('pirate_captains')
            ->join('pirate_factions', 'pirate_captains.pirate_faction_id', '=', 'pirate_factions.id')
            ->where(function ($q) use ($galaxyIds) {
                $q->whereIn('pirate_factions.galaxy_id', $galaxyIds)
                    ->orWhereNull('pirate_factions.galaxy_id');
            })
            ->pluck('pirate_captains.id');

        if ($captainIds->isEmpty()) {
            return 0;
        }

        return DB::table('pirate_fleets')
            ->whereIn('pirate_captain_id', $captainIds)
            ->delete();
    }

    private function deletePirateCargo(array $galaxyIds): int
    {
        $fleetIds = DB::table('pirate_fleets')
            ->join('pirate_captains', 'pirate_fleets.pirate_captain_id', '=', 'pirate_captains.id')
            ->join('pirate_factions', 'pirate_captains.pirate_faction_id', '=', 'pirate_factions.id')
            ->where(function ($q) use ($galaxyIds) {
                $q->whereIn('pirate_factions.galaxy_id', $galaxyIds)
                    ->orWhereNull('pirate_factions.galaxy_id');
            })
            ->pluck('pirate_fleets.id');

        if ($fleetIds->isEmpty()) {
            return 0;
        }

        return DB::table('pirate_cargo')
            ->whereIn('pirate_fleet_id', $fleetIds)
            ->delete();
    }

    private function deleteThroughNpcs(string $table, array $galaxyIds): int
    {
        $npcIds = DB::table('npcs')
            ->whereIn('galaxy_id', $galaxyIds)
            ->pluck('id');

        if ($npcIds->isEmpty()) {
            return 0;
        }

        return DB::table($table)
            ->whereIn('npc_id', $npcIds)
            ->delete();
    }

    private function deleteThroughNpcShips(array $galaxyIds): int
    {
        $shipIds = DB::table('npc_ships')
            ->join('npcs', 'npc_ships.npc_id', '=', 'npcs.id')
            ->whereIn('npcs.galaxy_id', $galaxyIds)
            ->pluck('npc_ships.id');

        if ($shipIds->isEmpty()) {
            return 0;
        }

        return DB::table('npc_cargos')
            ->whereIn('npc_ship_id', $shipIds)
            ->delete();
    }

    private function deleteThroughColonies(string $table, string $column, array $galaxyIds): int
    {
        $colonyIds = DB::table('colonies')
            ->join('points_of_interest', 'colonies.poi_id', '=', 'points_of_interest.id')
            ->whereIn('points_of_interest.galaxy_id', $galaxyIds)
            ->pluck('colonies.id');

        if ($colonyIds->isEmpty()) {
            return 0;
        }

        return DB::table($table)
            ->whereIn($column, $colonyIds)
            ->delete();
    }

    private function deleteColonyMissions(array $galaxyIds): int
    {
        $buildingIds = DB::table('colony_buildings')
            ->join('colonies', 'colony_buildings.colony_id', '=', 'colonies.id')
            ->join('points_of_interest', 'colonies.poi_id', '=', 'points_of_interest.id')
            ->whereIn('points_of_interest.galaxy_id', $galaxyIds)
            ->pluck('colony_buildings.id');

        if ($buildingIds->isEmpty()) {
            return 0;
        }

        return DB::table('colony_missions')
            ->whereIn('colony_building_id', $buildingIds)
            ->delete();
    }

    private function deleteCombatParticipants(array $galaxyIds): int
    {
        $sessionIds = DB::table('combat_sessions')
            ->join('points_of_interest', 'combat_sessions.poi_id', '=', 'points_of_interest.id')
            ->whereIn('points_of_interest.galaxy_id', $galaxyIds)
            ->pluck('combat_sessions.id');

        if ($sessionIds->isEmpty()) {
            return 0;
        }

        return DB::table('combat_participants')
            ->whereIn('combat_session_id', $sessionIds)
            ->delete();
    }

    private function deletePvpTeamInvitations(array $galaxyIds): int
    {
        $challengeIds = DB::table('pvp_challenges')
            ->join('players', 'pvp_challenges.challenger_id', '=', 'players.id')
            ->whereIn('players.galaxy_id', $galaxyIds)
            ->pluck('pvp_challenges.id');

        if ($challengeIds->isEmpty()) {
            return 0;
        }

        return DB::table('pvp_team_invitations')
            ->whereIn('pvp_challenge_id', $challengeIds)
            ->delete();
    }

    /**
     * Display final statistics.
     */
    private function displayStats(array $stats): void
    {
        $totalDeleted = array_sum($stats);

        $rows = [];
        foreach ($stats as $table => $count) {
            if ($count > 0) {
                $rows[] = [$table, number_format($count)];
            }
        }

        if (! empty($rows)) {
            $this->table(['Table', 'Rows Deleted'], $rows);
        }

        $this->newLine();
        $this->info('Total rows deleted: '.number_format($totalDeleted));
        $this->newLine();
    }
}

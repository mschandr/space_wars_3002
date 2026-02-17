<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CartographyController;
use App\Http\Controllers\Api\ColonyBuildingController;
use App\Http\Controllers\Api\ColonyCombatController;
use App\Http\Controllers\Api\ColonyController;
use App\Http\Controllers\Api\CombatController;
use App\Http\Controllers\Api\FacilitiesController;
use App\Http\Controllers\Api\GalaxyController;
use App\Http\Controllers\Api\GalaxyCreationController;
use App\Http\Controllers\Api\GalaxySettingsController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapSummaryController;
use App\Http\Controllers\Api\MarketEventController;
use App\Http\Controllers\Api\MiningController;
use App\Http\Controllers\Api\MirrorUniverseController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrbitalStructureController;
use App\Http\Controllers\Api\PirateFactionController;
use App\Http\Controllers\Api\PlansShopController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PlayerKnowledgeMapController;
use App\Http\Controllers\Api\PlayerSettingsController;
use App\Http\Controllers\Api\PlayerStatusController;
use App\Http\Controllers\Api\PoiTypeController;
use App\Http\Controllers\Api\PrecursorRumorController;
use App\Http\Controllers\Api\PvPCombatController;
use App\Http\Controllers\Api\SalvageYardController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\SectorMapController;
use App\Http\Controllers\Api\ShipController;
use App\Http\Controllers\Api\ShipServiceController;
use App\Http\Controllers\Api\ShipShopController;
use App\Http\Controllers\Api\ShipStatusController;
use App\Http\Controllers\Api\ShipyardController;
use App\Http\Controllers\Api\StarSystemController;
use App\Http\Controllers\Api\TeamCombatController;
use App\Http\Controllers\Api\TradingController;
use App\Http\Controllers\Api\TradingTransactionController;
use App\Http\Controllers\Api\TravelCalculationController;
use App\Http\Controllers\Api\TravelController;
use App\Http\Controllers\Api\UpgradeController;
use App\Http\Controllers\Api\VictoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail']);

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Galaxy information routes (public - detail views only)
// Note: {uuid} uses regex to avoid catching static routes like 'size-tiers'
Route::prefix('galaxies')->group(function () {
    Route::get('{uuid}', [GalaxyController::class, 'show'])
        ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    Route::get('{uuid}/statistics', [GalaxyController::class, 'statistics'])
        ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    Route::get('{uuid}/map', [GalaxyController::class, 'map'])
        ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
});

Route::get('sectors/{uuid}', [GalaxyController::class, 'showSector'])
    ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

// Leaderboard routes (public)
Route::prefix('galaxies/{galaxyUuid}/leaderboards')->group(function () {
    Route::get('overall', [LeaderboardController::class, 'overall']);
    Route::get('combat', [LeaderboardController::class, 'combat']);
    Route::get('economic', [LeaderboardController::class, 'economic']);
    Route::get('colonial', [LeaderboardController::class, 'colonial']);
});

// Victory conditions routes (public)
Route::prefix('galaxies/{galaxyUuid}')->group(function () {
    Route::get('victory-conditions', [VictoryController::class, 'conditions']);
    Route::get('victory-leaders', [VictoryController::class, 'victoryLeaders']);
});

// Market events routes (public)
Route::get('galaxies/{galaxyUuid}/market-events', [MarketEventController::class, 'galaxyEvents']);
Route::get('market-events/{eventUuid}', [MarketEventController::class, 'show']);

// Pirate factions routes (public)
Route::prefix('galaxies/{galaxyUuid}/pirate-factions')->group(function () {
    Route::get('/', [PirateFactionController::class, 'index']);
});
Route::get('pirate-factions/{factionUuid}', [PirateFactionController::class, 'show']);
Route::get('pirate-factions/{factionUuid}/captains', [PirateFactionController::class, 'factionCaptains']);

// POI types reference routes (public)
Route::prefix('poi-types')->group(function () {
    Route::get('/', [PoiTypeController::class, 'index']);
    Route::get('by-category', [PoiTypeController::class, 'byCategory']);
    Route::get('habitable', [PoiTypeController::class, 'habitable']);
    Route::get('mineable', [PoiTypeController::class, 'mineable']);
    Route::get('{idOrCode}', [PoiTypeController::class, 'show']);
});

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    // Galaxy list routes (authenticated - returns user's games + open games)
    Route::prefix('galaxies')->group(function () {
        Route::get('/', [GalaxyController::class, 'index']);
        Route::get('list', [GalaxyController::class, 'list']);  // Cached version
    });

    // Galaxy creation routes
    // Note: Static routes MUST come before dynamic {uuid} routes to avoid 404s
    Route::prefix('galaxies')->group(function () {
        Route::post('create', [GalaxyCreationController::class, 'createOptimized']);
        Route::get('size-tiers', [GalaxyCreationController::class, 'getSizeTiers']);
    });

    // Galaxy routes with dynamic UUID parameter (must come after static routes)
    Route::prefix('galaxies/{uuid}')->group(function () {
        Route::get('creation-status', [GalaxyCreationController::class, 'creationStatus']);
        Route::post('npcs', [GalaxyCreationController::class, 'addNpcs']);
        Route::get('npcs', [GalaxyCreationController::class, 'listNpcs']);

        // Player membership routes
        Route::get('my-player', [GalaxyController::class, 'getMyPlayer']);
        Route::get('my-ship', [ShipController::class, 'getMyShip']);
        Route::post('join', [GalaxyController::class, 'join']);

        // Map summaries (lightweight data for rendering)
        Route::get('map-summaries', [MapSummaryController::class, 'index']);

        // Sector map (aggregate stats per sector, no individual stars)
        Route::get('sector-map', [SectorMapController::class, 'index']);

        // Galaxy settings (owner only)
        Route::patch('settings', [GalaxySettingsController::class, 'update']);
    });

    // NPC management routes
    Route::prefix('npcs')->group(function () {
        Route::get('archetypes', [GalaxyCreationController::class, 'getArchetypes']);
        Route::get('{uuid}', [GalaxyCreationController::class, 'showNpc']);
        Route::delete('{uuid}', [GalaxyCreationController::class, 'destroyNpc']);
    });

    // Location information routes
    Route::prefix('location')->group(function () {
        Route::post('current/{uuid?}', [LocationController::class, 'current'])
            ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    });

    // Player management routes
    Route::prefix('players')->group(function () {
        Route::get('/', [PlayerController::class, 'index']);
        Route::post('/', [PlayerController::class, 'store']);

        // Routes that track player access
        Route::middleware('player.access')->group(function () {
            Route::get('{uuid}', [PlayerController::class, 'show']);
            Route::patch('{uuid}', [PlayerController::class, 'update']);
            Route::delete('{uuid}', [PlayerController::class, 'destroy']);
            Route::get('{uuid}/status', [PlayerStatusController::class, 'status']);
            Route::get('{uuid}/stats', [PlayerStatusController::class, 'stats']);
            Route::post('{uuid}/set-active', [PlayerController::class, 'setActive']);

            // Player settings (owner only)
            Route::patch('{uuid}/settings', [PlayerSettingsController::class, 'update']);
        });
    });

    // Ship management routes
    Route::prefix('ships')->group(function () {
        Route::get('{uuid}/status', [ShipStatusController::class, 'status']);
        Route::get('{uuid}/fuel', [ShipStatusController::class, 'fuel']);
        Route::post('{uuid}/regenerate-fuel', [ShipController::class, 'regenerateFuel']);
        Route::get('{uuid}/upgrades', [ShipStatusController::class, 'upgrades']);
        Route::get('{uuid}/damage', [ShipStatusController::class, 'damage']);
        Route::patch('{uuid}/name', [ShipController::class, 'rename']);
    });

    // Player's active ship endpoint
    Route::get('players/{playerUuid}/ship', [ShipController::class, 'getActiveShip']);

    // Navigation routes
    Route::prefix('players')->group(function () {
        Route::get('{uuid}/location', [NavigationController::class, 'getLocation']);
        Route::get('{uuid}/nearby-systems', [NavigationController::class, 'getNearbySystems']);
        Route::get('{uuid}/scan-local', [NavigationController::class, 'scanLocal']);
        Route::get('{uuid}/local-bodies', [NavigationController::class, 'getLocalBodies']);
    });

    // Knowledge Map (fog-of-war)
    Route::get('players/{playerUuid}/knowledge-map', [PlayerKnowledgeMapController::class, 'index']);

    // Star System routes
    // Comprehensive star system data with visibility based on inhabited status + scan level
    Route::prefix('players/{playerUuid}')->group(function () {
        Route::get('star-systems', [StarSystemController::class, 'index']);
        Route::get('star-systems/{systemUuid}', [StarSystemController::class, 'show']);
        Route::get('star-systems/{systemUuid}/status', [StarSystemController::class, 'status']);
        Route::get('current-system', [StarSystemController::class, 'current']);
    });

    // Facilities routes
    // Unified view of all facilities in the current star system
    Route::prefix('players/{playerUuid}')->group(function () {
        Route::get('facilities', [FacilitiesController::class, 'index']);
        Route::get('facilities/bar', [FacilitiesController::class, 'bar']);
    });

    // Travel routes
    Route::get('warp-gates/{locationUuid}', [TravelController::class, 'listWarpGates']);

    Route::prefix('players/{uuid}/travel')->group(function () {
        Route::post('warp-gate', [TravelController::class, 'travelViaWarpGate']);
        Route::post('coordinate', [TravelController::class, 'jumpToCoordinates']);
        Route::post('direct-jump', [TravelController::class, 'directJumpToHub']);
    });

    // Travel calculation routes
    Route::prefix('travel')->group(function () {
        Route::get('xp-preview', [TravelCalculationController::class, 'previewXP']);
        Route::get('fuel-cost', [TravelCalculationController::class, 'calculateFuelCost']);
    });

    // Trading routes
    Route::get('trading-hubs', [TradingController::class, 'listNearbyHubs']);
    Route::get('trading-hubs/{uuid}', [TradingController::class, 'getHubDetails']);
    Route::get('trading-hubs/{uuid}/inventory', [TradingController::class, 'getHubInventory']);
    Route::get('minerals', [TradingController::class, 'listMinerals']);

    // Trading transactions
    Route::post('trading-hubs/{uuid}/buy', [TradingTransactionController::class, 'buyMinerals']);
    Route::post('trading-hubs/{uuid}/sell', [TradingTransactionController::class, 'sellMinerals']);
    Route::get('players/{uuid}/cargo', [TradingTransactionController::class, 'getCargo']);
    Route::get('trading/affordability', [TradingTransactionController::class, 'calculateAffordability']);

    // Upgrade routes
    Route::get('ships/{uuid}/upgrade-options', [UpgradeController::class, 'listUpgradeOptions']);
    Route::get('ships/{uuid}/upgrade/{component}', [UpgradeController::class, 'getComponentUpgradeDetails']);
    Route::post('ships/{uuid}/upgrade/{component}', [UpgradeController::class, 'executeUpgrade']);
    Route::get('players/{uuid}/plans', [UpgradeController::class, 'getOwnedPlans']);
    Route::get('upgrade-costs', [UpgradeController::class, 'getUpgradeCostFormulas']);
    Route::get('upgrade-limits', [UpgradeController::class, 'getUpgradeLimits']);

    // Combat routes
    Route::get('warp-gates/{warpGateUuid}/pirates', [CombatController::class, 'checkPiratePresence']);
    Route::get('pirate-encounters/{encounterUuid}', [CombatController::class, 'getEncounterDetails']);
    Route::get('players/{uuid}/combat/preview', [CombatController::class, 'getCombatPreview']);
    Route::post('players/{uuid}/combat/escape', [CombatController::class, 'attemptEscape']);
    Route::post('players/{uuid}/combat/surrender', [CombatController::class, 'surrender']);
    Route::post('players/{uuid}/combat/engage', [CombatController::class, 'engageCombat']);
    Route::post('players/{uuid}/combat/salvage', [CombatController::class, 'collectSalvage']);

    // PvP Combat routes
    Route::post('players/{uuid}/pvp/challenge', [PvPCombatController::class, 'issueChallenge']);
    Route::get('players/{uuid}/pvp/challenges', [PvPCombatController::class, 'listChallenges']);
    Route::post('players/{uuid}/pvp/challenge/{challengeUuid}/accept', [PvPCombatController::class, 'acceptChallenge']);
    Route::post('players/{uuid}/pvp/challenge/{challengeUuid}/decline', [PvPCombatController::class, 'declineChallenge']);
    Route::delete('players/{uuid}/pvp/challenge/{challengeUuid}', [PvPCombatController::class, 'cancelChallenge']);
    Route::get('combat-sessions/{uuid}', [PvPCombatController::class, 'getCombatSession']);

    // Team Combat routes
    Route::post('players/{uuid}/pvp/challenge/{challengeUuid}/invite', [TeamCombatController::class, 'inviteAlly']);
    Route::get('players/{uuid}/team-invitations', [TeamCombatController::class, 'listInvitations']);
    Route::post('players/{uuid}/team-invitations/{invitationId}/accept', [TeamCombatController::class, 'acceptInvitation']);
    Route::post('players/{uuid}/team-invitations/{invitationId}/decline', [TeamCombatController::class, 'declineInvitation']);
    Route::get('pvp/challenge/{challengeUuid}/teams', [TeamCombatController::class, 'getTeamComposition']);
    Route::post('players/{uuid}/pvp/challenge/{challengeUuid}/accept-team', [TeamCombatController::class, 'acceptTeamChallenge']);

    // Ship repair & maintenance routes
    Route::get('ships/{uuid}/repair-estimate', [ShipServiceController::class, 'getRepairEstimate']);
    Route::get('ships/{uuid}/maintenance', [ShipServiceController::class, 'getMaintenanceStatus']);
    Route::post('ships/{uuid}/repair/hull', [ShipServiceController::class, 'repairHull']);
    Route::post('ships/{uuid}/repair/components', [ShipServiceController::class, 'repairComponents']);
    Route::post('ships/{uuid}/repair/all', [ShipServiceController::class, 'repairAll']);

    // Ship shopping routes
    Route::get('trading-hubs/{uuid}/shipyard', [ShipShopController::class, 'getShipyard']);
    Route::get('ships/catalog', [ShipShopController::class, 'getCatalog']);
    Route::post('players/{uuid}/ships/purchase', [ShipShopController::class, 'purchaseShip']);
    Route::post('players/{uuid}/ships/switch', [ShipShopController::class, 'switchShip']);
    Route::get('players/{uuid}/ships/fleet', [ShipShopController::class, 'getFleet']);

    // Plans shop routes
    Route::get('trading-hubs/{uuid}/plans-shop', [PlansShopController::class, 'getPlansShop']);
    Route::get('plans/catalog', [PlansShopController::class, 'getCatalog']);
    Route::post('players/{uuid}/plans/purchase', [PlansShopController::class, 'purchasePlan']);

    // Cartography & star charts routes
    Route::get('players/{uuid}/star-charts', [CartographyController::class, 'getPlayerCharts']);
    Route::get('trading-hubs/{uuid}/cartographer', [CartographyController::class, 'getCartographer']);
    Route::get('star-charts/preview', [CartographyController::class, 'previewCoverage']);
    Route::get('star-charts/pricing', [CartographyController::class, 'getPricing']);
    Route::post('players/{uuid}/star-charts/purchase', [CartographyController::class, 'purchaseChart']);
    Route::get('star-charts/system/{poiUuid}', [CartographyController::class, 'getSystemInfo']);

    // Colony management routes
    Route::get('players/{uuid}/colonies', [ColonyController::class, 'listColonies']);
    Route::post('players/{uuid}/colonies', [ColonyController::class, 'establishColony']);
    Route::get('colonies/{uuid}', [ColonyController::class, 'getColony']);
    Route::put('colonies/{uuid}', [ColonyController::class, 'updateColony']);
    Route::delete('colonies/{uuid}', [ColonyController::class, 'abandonColony']);
    Route::get('colonies/{uuid}/production', [ColonyController::class, 'getProduction']);
    Route::post('colonies/{uuid}/upgrade', [ColonyController::class, 'upgradeDevelopment']);
    Route::get('colonies/{uuid}/ship-production', [ColonyController::class, 'getShipProduction']);

    // Colony combat routes
    Route::get('colonies/{uuid}/defenses', [ColonyCombatController::class, 'getDefenses']);
    Route::post('players/{uuid}/attack-colony/{colonyUuid}', [ColonyCombatController::class, 'attackColony']);
    Route::post('colonies/{uuid}/fortify', [ColonyCombatController::class, 'fortifyColony']);

    // Colony building routes
    Route::get('colonies/{uuid}/buildings', [ColonyBuildingController::class, 'listBuildings']);
    Route::post('colonies/{uuid}/buildings', [ColonyBuildingController::class, 'constructBuilding']);
    Route::put('colonies/{uuid}/buildings/{buildingUuid}', [ColonyBuildingController::class, 'upgradeBuilding']);
    Route::delete('colonies/{uuid}/buildings/{buildingUuid}', [ColonyBuildingController::class, 'demolishBuilding']);

    // Mining routes
    Route::get('poi/{uuid}/mining-opportunities', [MiningController::class, 'getMiningOpportunities']);
    Route::post('colonies/{uuid}/mining/start', [MiningController::class, 'startAutomatedMining']);
    Route::post('ships/{uuid}/mining/extract', [MiningController::class, 'extractResources']);

    // Player statistics & rankings routes
    Route::get('players/{uuid}/ranking', [LeaderboardController::class, 'playerRanking']);
    Route::get('players/{uuid}/statistics', [LeaderboardController::class, 'playerStatistics']);
    Route::get('players/{uuid}/victory-progress', [VictoryController::class, 'playerProgress']);

    // Pirate faction reputation routes
    Route::get('players/{uuid}/pirate-reputation', [PirateFactionController::class, 'playerReputation']);

    // Market events routes (protected)
    Route::get('trading-hubs/{uuid}/active-events', [MarketEventController::class, 'hubEvents']);

    // Notification routes
    Route::prefix('players/{uuid}/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread', [NotificationController::class, 'unreadCount']);
        Route::post('{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('{notificationId}', [NotificationController::class, 'destroy']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('clear-read', [NotificationController::class, 'clearRead']);
    });

    // Mirror Universe routes
    Route::get('players/{uuid}/mirror-access', [MirrorUniverseController::class, 'checkAccess']);
    Route::post('players/{uuid}/mirror/enter', [MirrorUniverseController::class, 'enter']);
    Route::get('galaxies/{uuid}/mirror-gate', [MirrorUniverseController::class, 'getMirrorGate']);

    // System Scanning routes
    Route::prefix('players/{uuid}')->group(function () {
        Route::post('scan-system', [ScanController::class, 'scanSystem']);
        Route::get('scan-results/{poiUuid}', [ScanController::class, 'getScanResults']);
        Route::get('exploration-log', [ScanController::class, 'explorationLog']);
        Route::post('bulk-scan-levels', [ScanController::class, 'bulkScanLevels']);
        Route::get('system-data/{poiUuid}', [ScanController::class, 'getSystemData']);
    });

    // Precursor Ship Rumor routes
    // The legendary Precursor ship is hidden somewhere in each galaxy.
    // Ship yard owners think they know where it is. They're all wrong.
    // But their rumors might help narrow down the real location...
    Route::prefix('players/{uuid}/precursor')->group(function () {
        Route::get('check', [PrecursorRumorController::class, 'checkForRumor']);
        Route::get('gossip', [PrecursorRumorController::class, 'getGossip']);
        Route::post('bribe', [PrecursorRumorController::class, 'bribeForRumor']);
        Route::get('rumors', [PrecursorRumorController::class, 'getCollectedRumors']);
    });

    // Shipyard routes (unique pre-rolled ships)
    Route::get('systems/{uuid}/shipyard', [ShipyardController::class, 'index']);
    Route::get('shipyard-inventory/{uuid}', [ShipyardController::class, 'show']);
    Route::post('players/{uuid}/shipyard/purchase', [ShipyardController::class, 'purchase']);

    // Salvage Yard routes
    // Salvage yards sell ship components: weapons, shield regenerators, hull patches
    Route::get('systems/{uuid}/salvage-yard', [SalvageYardController::class, 'indexBySystem']);
    Route::prefix('players/{uuid}')->group(function () {
        Route::get('salvage-yard', [SalvageYardController::class, 'index']);
        Route::get('ship-components', [SalvageYardController::class, 'shipComponents']);
        Route::post('salvage-yard/purchase', [SalvageYardController::class, 'purchase']);
        Route::post('salvage-yard/sell-ship', [SalvageYardController::class, 'sellShip']);
        Route::post('ship-components/{componentId}/uninstall', [SalvageYardController::class, 'uninstall']);
    });

    // Orbital Structure routes
    Route::get('poi/{uuid}/orbital-structures', [OrbitalStructureController::class, 'listAtBody']);
    Route::get('players/{uuid}/orbital-structures', [OrbitalStructureController::class, 'listPlayerStructures']);
    Route::post('players/{uuid}/orbital-structures/build', [OrbitalStructureController::class, 'build']);
    Route::get('orbital-structures/{uuid}', [OrbitalStructureController::class, 'show']);
    Route::put('orbital-structures/{uuid}/upgrade', [OrbitalStructureController::class, 'upgrade']);
    Route::delete('orbital-structures/{uuid}', [OrbitalStructureController::class, 'demolish']);
    Route::post('orbital-structures/{uuid}/collect', [OrbitalStructureController::class, 'collect']);
});

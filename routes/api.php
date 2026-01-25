<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\ColonyCombatController;
use App\Http\Controllers\Api\GalaxyController;
use App\Http\Controllers\Api\GalaxyCreationController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\MarketEventController;
use App\Http\Controllers\Api\MirrorUniverseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PirateFactionController;
use App\Http\Controllers\Api\PvPCombatController;
use App\Http\Controllers\Api\TeamCombatController;
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

// Galaxy information routes (public)
// Note: {uuid} uses regex to avoid catching static routes like 'size-tiers'
Route::prefix('galaxies')->group(function () {
    Route::get('/', [GalaxyController::class, 'index']);
    Route::get('list', [GalaxyController::class, 'list']);  // Dehydrated list for game selection
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

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    // Galaxy creation routes
    // Note: Static routes MUST come before dynamic {uuid} routes to avoid 404s
    Route::prefix('galaxies')->group(function () {
        Route::post('create', [GalaxyCreationController::class, 'create']);
        Route::post('create-tiered', [GalaxyCreationController::class, 'createTiered']);
        Route::get('size-tiers', [GalaxyCreationController::class, 'getSizeTiers']);
    });

    // Galaxy routes with dynamic UUID parameter (must come after static routes)
    Route::prefix('galaxies/{uuid}')->group(function () {
        Route::get('creation-status', [GalaxyCreationController::class, 'creationStatus']);
        Route::post('npcs', [GalaxyCreationController::class, 'addNpcs']);
        Route::get('npcs', [GalaxyCreationController::class, 'listNpcs']);
    });

    // NPC management routes
    Route::prefix('npcs')->group(function () {
        Route::get('archetypes', [GalaxyCreationController::class, 'getArchetypes']);
        Route::get('{uuid}', [GalaxyCreationController::class, 'showNpc']);
        Route::delete('{uuid}', [GalaxyCreationController::class, 'destroyNpc']);
    });

    // Player management routes
    Route::prefix('players')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PlayerController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\PlayerController::class, 'store']);
        Route::get('{uuid}', [\App\Http\Controllers\Api\PlayerController::class, 'show']);
        Route::patch('{uuid}', [\App\Http\Controllers\Api\PlayerController::class, 'update']);
        Route::delete('{uuid}', [\App\Http\Controllers\Api\PlayerController::class, 'destroy']);
        Route::get('{uuid}/status', [\App\Http\Controllers\Api\PlayerStatusController::class, 'status']);
        Route::get('{uuid}/stats', [\App\Http\Controllers\Api\PlayerStatusController::class, 'stats']);
        Route::post('{uuid}/set-active', [\App\Http\Controllers\Api\PlayerController::class, 'setActive']);
    });

    // Ship management routes
    Route::prefix('ships')->group(function () {
        Route::get('{uuid}/status', [\App\Http\Controllers\Api\ShipStatusController::class, 'status']);
        Route::get('{uuid}/fuel', [\App\Http\Controllers\Api\ShipStatusController::class, 'fuel']);
        Route::post('{uuid}/regenerate-fuel', [\App\Http\Controllers\Api\ShipController::class, 'regenerateFuel']);
        Route::get('{uuid}/upgrades', [\App\Http\Controllers\Api\ShipStatusController::class, 'upgrades']);
        Route::get('{uuid}/damage', [\App\Http\Controllers\Api\ShipStatusController::class, 'damage']);
        Route::patch('{uuid}/name', [\App\Http\Controllers\Api\ShipController::class, 'rename']);
    });

    // Player's active ship endpoint
    Route::get('players/{playerUuid}/ship', [\App\Http\Controllers\Api\ShipController::class, 'getActiveShip']);

    // Navigation routes
    Route::prefix('players')->group(function () {
        Route::get('{uuid}/location', [\App\Http\Controllers\Api\NavigationController::class, 'getLocation']);
        Route::get('{uuid}/nearby-systems', [\App\Http\Controllers\Api\NavigationController::class, 'getNearbySystems']);
        Route::get('{uuid}/scan-local', [\App\Http\Controllers\Api\NavigationController::class, 'scanLocal']);
    });

    // Travel routes
    Route::get('warp-gates/{locationUuid}', [\App\Http\Controllers\Api\TravelController::class, 'listWarpGates']);

    Route::prefix('players/{uuid}/travel')->group(function () {
        Route::post('warp-gate', [\App\Http\Controllers\Api\TravelController::class, 'travelViaWarpGate']);
        Route::post('coordinate', [\App\Http\Controllers\Api\TravelController::class, 'jumpToCoordinates']);
        Route::post('direct-jump', [\App\Http\Controllers\Api\TravelController::class, 'directJumpToHub']);
    });

    // Travel calculation routes
    Route::prefix('travel')->group(function () {
        Route::get('xp-preview', [\App\Http\Controllers\Api\TravelCalculationController::class, 'previewXP']);
        Route::get('fuel-cost', [\App\Http\Controllers\Api\TravelCalculationController::class, 'calculateFuelCost']);
    });

    // Trading routes
    Route::get('trading-hubs', [\App\Http\Controllers\Api\TradingController::class, 'listNearbyHubs']);
    Route::get('trading-hubs/{uuid}', [\App\Http\Controllers\Api\TradingController::class, 'getHubDetails']);
    Route::get('trading-hubs/{uuid}/inventory', [\App\Http\Controllers\Api\TradingController::class, 'getHubInventory']);
    Route::get('minerals', [\App\Http\Controllers\Api\TradingController::class, 'listMinerals']);

    // Trading transactions
    Route::post('trading-hubs/{uuid}/buy', [\App\Http\Controllers\Api\TradingTransactionController::class, 'buyMinerals']);
    Route::post('trading-hubs/{uuid}/sell', [\App\Http\Controllers\Api\TradingTransactionController::class, 'sellMinerals']);
    Route::get('players/{uuid}/cargo', [\App\Http\Controllers\Api\TradingTransactionController::class, 'getCargo']);
    Route::get('trading/affordability', [\App\Http\Controllers\Api\TradingTransactionController::class, 'calculateAffordability']);

    // Upgrade routes
    Route::get('ships/{uuid}/upgrade-options', [\App\Http\Controllers\Api\UpgradeController::class, 'listUpgradeOptions']);
    Route::get('ships/{uuid}/upgrade/{component}', [\App\Http\Controllers\Api\UpgradeController::class, 'getComponentUpgradeDetails']);
    Route::post('ships/{uuid}/upgrade/{component}', [\App\Http\Controllers\Api\UpgradeController::class, 'executeUpgrade']);
    Route::get('players/{uuid}/plans', [\App\Http\Controllers\Api\UpgradeController::class, 'getOwnedPlans']);
    Route::get('upgrade-costs', [\App\Http\Controllers\Api\UpgradeController::class, 'getUpgradeCostFormulas']);
    Route::get('upgrade-limits', [\App\Http\Controllers\Api\UpgradeController::class, 'getUpgradeLimits']);

    // Combat routes
    Route::get('warp-gates/{warpGateUuid}/pirates', [\App\Http\Controllers\Api\CombatController::class, 'checkPiratePresence']);
    Route::get('pirate-encounters/{encounterUuid}', [\App\Http\Controllers\Api\CombatController::class, 'getEncounterDetails']);
    Route::get('players/{uuid}/combat/preview', [\App\Http\Controllers\Api\CombatController::class, 'getCombatPreview']);
    Route::post('players/{uuid}/combat/escape', [\App\Http\Controllers\Api\CombatController::class, 'attemptEscape']);
    Route::post('players/{uuid}/combat/surrender', [\App\Http\Controllers\Api\CombatController::class, 'surrender']);
    Route::post('players/{uuid}/combat/engage', [\App\Http\Controllers\Api\CombatController::class, 'engageCombat']);
    Route::post('players/{uuid}/combat/salvage', [\App\Http\Controllers\Api\CombatController::class, 'collectSalvage']);

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
    Route::get('ships/{uuid}/repair-estimate', [\App\Http\Controllers\Api\ShipServiceController::class, 'getRepairEstimate']);
    Route::get('ships/{uuid}/maintenance', [\App\Http\Controllers\Api\ShipServiceController::class, 'getMaintenanceStatus']);
    Route::post('ships/{uuid}/repair/hull', [\App\Http\Controllers\Api\ShipServiceController::class, 'repairHull']);
    Route::post('ships/{uuid}/repair/components', [\App\Http\Controllers\Api\ShipServiceController::class, 'repairComponents']);
    Route::post('ships/{uuid}/repair/all', [\App\Http\Controllers\Api\ShipServiceController::class, 'repairAll']);

    // Ship shopping routes
    Route::get('trading-hubs/{uuid}/shipyard', [\App\Http\Controllers\Api\ShipShopController::class, 'getShipyard']);
    Route::get('ships/catalog', [\App\Http\Controllers\Api\ShipShopController::class, 'getCatalog']);
    Route::post('players/{uuid}/ships/purchase', [\App\Http\Controllers\Api\ShipShopController::class, 'purchaseShip']);
    Route::post('players/{uuid}/ships/switch', [\App\Http\Controllers\Api\ShipShopController::class, 'switchShip']);
    Route::get('players/{uuid}/ships/fleet', [\App\Http\Controllers\Api\ShipShopController::class, 'getFleet']);

    // Plans shop routes
    Route::get('trading-hubs/{uuid}/plans-shop', [\App\Http\Controllers\Api\PlansShopController::class, 'getPlansShop']);
    Route::get('plans/catalog', [\App\Http\Controllers\Api\PlansShopController::class, 'getCatalog']);
    Route::post('players/{uuid}/plans/purchase', [\App\Http\Controllers\Api\PlansShopController::class, 'purchasePlan']);

    // Cartography & star charts routes
    Route::get('players/{uuid}/star-charts', [\App\Http\Controllers\Api\CartographyController::class, 'getPlayerCharts']);
    Route::get('trading-hubs/{uuid}/cartographer', [\App\Http\Controllers\Api\CartographyController::class, 'getCartographer']);
    Route::get('star-charts/preview', [\App\Http\Controllers\Api\CartographyController::class, 'previewCoverage']);
    Route::get('star-charts/pricing', [\App\Http\Controllers\Api\CartographyController::class, 'getPricing']);
    Route::post('players/{uuid}/star-charts/purchase', [\App\Http\Controllers\Api\CartographyController::class, 'purchaseChart']);
    Route::get('star-charts/system/{poiUuid}', [\App\Http\Controllers\Api\CartographyController::class, 'getSystemInfo']);

    // Colony management routes
    Route::get('players/{uuid}/colonies', [\App\Http\Controllers\Api\ColonyController::class, 'listColonies']);
    Route::post('players/{uuid}/colonies', [\App\Http\Controllers\Api\ColonyController::class, 'establishColony']);
    Route::get('colonies/{uuid}', [\App\Http\Controllers\Api\ColonyController::class, 'getColony']);
    Route::put('colonies/{uuid}', [\App\Http\Controllers\Api\ColonyController::class, 'updateColony']);
    Route::delete('colonies/{uuid}', [\App\Http\Controllers\Api\ColonyController::class, 'abandonColony']);
    Route::get('colonies/{uuid}/production', [\App\Http\Controllers\Api\ColonyController::class, 'getProduction']);
    Route::post('colonies/{uuid}/upgrade', [\App\Http\Controllers\Api\ColonyController::class, 'upgradeDevelopment']);
    Route::get('colonies/{uuid}/ship-production', [\App\Http\Controllers\Api\ColonyController::class, 'getShipProduction']);

    // Colony combat routes
    Route::get('colonies/{uuid}/defenses', [ColonyCombatController::class, 'getDefenses']);
    Route::post('players/{uuid}/attack-colony/{colonyUuid}', [ColonyCombatController::class, 'attackColony']);
    Route::post('colonies/{uuid}/fortify', [ColonyCombatController::class, 'fortifyColony']);

    // Colony building routes
    Route::get('colonies/{uuid}/buildings', [\App\Http\Controllers\Api\ColonyBuildingController::class, 'listBuildings']);
    Route::post('colonies/{uuid}/buildings', [\App\Http\Controllers\Api\ColonyBuildingController::class, 'constructBuilding']);
    Route::put('colonies/{uuid}/buildings/{buildingUuid}', [\App\Http\Controllers\Api\ColonyBuildingController::class, 'upgradeBuilding']);
    Route::delete('colonies/{uuid}/buildings/{buildingUuid}', [\App\Http\Controllers\Api\ColonyBuildingController::class, 'demolishBuilding']);

    // Mining routes
    Route::get('poi/{uuid}/mining-opportunities', [\App\Http\Controllers\Api\MiningController::class, 'getMiningOpportunities']);
    Route::post('colonies/{uuid}/mining/start', [\App\Http\Controllers\Api\MiningController::class, 'startAutomatedMining']);
    Route::post('ships/{uuid}/mining/extract', [\App\Http\Controllers\Api\MiningController::class, 'extractResources']);

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
});

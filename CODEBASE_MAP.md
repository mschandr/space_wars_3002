# Space Wars 3002 - Complete Codebase Map

**Generated:** February 2026
**Total Classes:** 295+ PHP files across 10 major directories
**Total Lines of Code:** ~33,817 lines (Models: 7,613 | Services: 15,410 | Controllers: 10,794)

---

## Directory Overview

This document provides a comprehensive mapping of every user-created PHP class in the codebase, organized by directory with analysis of:
- Number of methods per class
- Constructor injection patterns
- Key dependencies
- Code smell indicators

---

## 1. MODELS (48 classes - 7,613 LOC)

The data layer containing domain entities and Eloquent relationships.

### Core Game Models

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Player.php** | 35 | N | Model | Player state, credits, level, location, ship management. **Has TODO for missing fillable 'mirror_universe_entry_time'** |
| **PlayerShip.php** | 35 | N | Model | Player's actual ship instance with fuel, upgrades, cargo. 35 methods make this a busy model. |
| **PointOfInterest.php** | 37 | N | Model | Generic spatial entity (stars, planets, stations). **Largest model by method count (37).** Hierarchical (parent/child). Polymorphic relations. |
| **Galaxy.php** | 22 | N | Model | Top-level galaxy container with snapshotted config. Relationships to POIs, Sectors, Warp Gates. |
| **WarpGate.php** | 23 | N | Model | Connections between POIs (from/to). Includes hidden, dead-end, mirror universe types. |

### Combat & Conflict

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **CombatSession.php** | 10 | N | Model | Tracks combat state between player and pirates/NPCs. |
| **CombatParticipant.php** | 9 | N | Model | Individual combatant in a session (player or NPC). |
| **PvPChallenge.php** | 13 | N | Model | Player vs Player challenges and rankings. |
| **PvPTeamInvitation.php** | 8 | N | Model | Team-based PvP invitations. |

### Pirate System

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **PirateFaction.php** | 4 | N | Model | Top-level pirate organization. |
| **PirateCaptain.php** | 6 | N | Model | Individual pirate leader. |
| **PirateBand.php** | 10 | N | Model | Active pirate group in sectors. |
| **PirateFleet.php** | 6 | N | Model | Fleet of pirate ships. |
| **PirateCargo.php** | 5 | N | Model | Cargo stolen by pirates. |
| **WarpLanePirate.php** | 3 | N | Model | Legacy lane-based pirate presence. |

### Trading & Commerce

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **TradingHub.php** | 12 | N | Model | Market for buying/selling minerals. Relationships to inventory, ships. |
| **TradingHubInventory.php** | 6 | N | Model | Mineral stock at a trading hub. |
| **TradingHubShip.php** | 5 | N | Model | Ships available for sale at hubs. |
| **Mineral.php** | 2 | N | Model | Tradable resource type with base value (not base_price - bug fix in memory). |
| **PlayerCargo.php** | 2 | N | Model | Minerals carried by player. |
| **NpcCargo.php** | 2 | N | Model | Cargo carried by NPCs. |
| **StellarCartographer.php** | 1 | N | Model | Star chart vendor at trading hubs. |

### Colonies & Infrastructure

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Colony.php** | 13 | N | Model | Player-controlled settlement with population, production, buildings. |
| **ColonyBuilding.php** | 6 | N | Model | Structures in colonies (factories, shields, etc.). |
| **ColonyMission.php** | 8 | N | Model | Tasks/quests given to colonies. |
| **ColonyShipProduction.php** | 8 | N | Model | Ship production queues at colonies. |
| **SystemDefense.php** | 12 | N | Model | Defense structures protecting systems. |
| **OrbitalStructure.php** | 7 | N | Model | Stations, platforms, orbital bodies. |

### NPC & Exploration

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Npc.php** | 12 | N | Model | Non-player character. |
| **NpcShip.php** | 11 | N | Model | NPC's ship instance. |
| **Sector.php** | 6 | N | Model | Grid-based region with danger levels. |
| **SystemScan.php** | 12+1enum | N | Model | Scanned system data including ScanLevel enum. |
| **PlayerSystemKnowledge.php** | 8 | N | Model | What player knows about a system. |
| **PilotLaneKnowledge.php** | 4 | N | Model | What pilots know about warp lanes. |

### Special Systems & Advanced Features

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **PrecursorShip.php** | 18+interface | Y | Model+Interface | Ancient alien ships. Complex relationships. |
| **PlayerPrecursorRumor.php** | 2 | N | Model | Clues to finding precursor technology. |
| **Plan.php** | 5 | N | Model | Upgrade blueprints for ships. |
| **PlayerPlan.php** | 0 | N | Model | Player's acquired plans. **Empty model - potential code smell.** |
| **MarketEvent.php** | 9 | N | Model | Dynamic price fluctuations. |
| **SalvageYardInventory.php** | 8 | N | Model | Salvageable components. |
| **ShipyardInventory.php** | 3 | N | Model | Ships for sale at shipyards. |

### Ship & Component Management

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Ship.php** | 3 | N | Model | Ship template/blueprint. |
| **ShipComponent.php** | 7 | N | Model | Individual ship component (weapons, hull, etc.). |
| **PlayerShipComponent.php** | 10 | N | Model | Installed component in player's ship. |
| **PlayerShipFighter.php** | 8 | N | Model | Fighter drones carried by ship. |
| **PoiType.php** | 6+enum | N | Model+Enum | POI type classifications. |

### Miscellaneous

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **User.php** | 2 | N | Model | Authentication user. |

### Model Code Smells Detected

1. **PointOfInterest (37 methods)** - God class. Handles stars, planets, stations, nebulae, anomalies. Needs decomposition with single-responsibility pattern.
2. **PlayerShip (35 methods)** - High complexity managing fuel, cargo, weapons, shields, colonists, hidden holds. May need refactoring into composed objects.
3. **Player (35 methods)** - Tracks state, relationships, combat stats, mirrors, knowledge. Could extract concerns.
4. **PlayerPlan (0 methods)** - Empty model. Likely a pivot table or needs proper implementation.
5. **StellarCartographer (1 method)** - Minimal implementation. Might be combined with service logic.
6. **TODO Comments** - Player model has unimplemented fillable property for mirror universe time tracking.

---

## 2. SERVICES (70+ classes - 15,410 LOC)

Business logic layer implementing game mechanics and system calculations.

### Top-Level Services (Core Game Loop)

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **TravelService.php** | 7 | Y | Service | Movement, fuel calculation (ceil(distance/efficiency)), XP rewards. Fuel cost formula: ceil(distance) / (1 + (warp_drive-1)*0.2). XP = max(10, distance*5). |
| **TradingService.php** | 4 | Y | Service | Buy/sell minerals. Price discovery and validation. |
| **MiningService.php** | 10 | Y | Service | Extract minerals from belts/planets. Resource availability checks. |
| **CombatResolutionService.php** | 2 | Y | Service | Combat mechanics between player and pirates. **Only 2 methods - might be too simple or needs expansion.** |
| **ColonyCycleService.php** | 6 | Y | Service | Population growth, resource production, building effects. |
| **MarketEventService.php** | 7 | Y | Service | Dynamic price fluctuations and market mechanics. |
| **MarketEventGenerator.php** | 2 | Y | Service | Generates random market events. **Minimal - might be combined with MarketEventService.** |

### Fuel & Movement

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **FuelRegenerationService.php** | 3 | Y | Service | Time-based fuel regen. Formula: BASE_RATE * (1 + (warp_drive-1)*0.3) units/hour. |
| **TravelNotificationService.php** | 3+interface | Y | Service+Interface | Notifications for travel events. |
| **TravelCalculationController.php** | 3 | Y | Controller | Wraps travel calculations for API. (Should be service, not controller). |

### Combat & Encounters

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **PirateEncounterService.php** | 14 | Y | Service | Sector-based pirate encounters. Probability calculations. Enemy generation. **Complex service with 6 dependencies.** |
| **CombatResolutionService.php** | 2 | Y | Service | Damage calculation, victory/defeat. **Suspiciously minimal - 2 methods.** |
| **EscapeCalculationService.php** | 3 | Y | Service | Calculate odds of escaping pirates. |
| **SurrenderService.php** | 2 | Y | Service | Handle surrendering in combat. |
| **SalvageService.php** | 5 | Y | Service | Extract salvage from defeated enemies. |
| **PlayerDeathService.php** | 2 | Y | Service | Handle player death mechanics. |
| **PvPCombatService.php** | 4 | Y | Service | Player vs Player combat. |
| **TeamCombatService.php** | 5 | Y | Service | Team-based combat resolution. |
| **ColonyCombatService.php** | 2 | Y | Service | Combat at colonies. |

### NPC & Exploration

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **PirateFleetGenerator.py** | 2 | Y | Service | Creates pirate fleets with composition. |
| **NpcGenerationService.py** | 4 | Y | Service | Creates NPCs with personalities/factions. |
| **SystemScanService.php** | 10 | Y | Service | Scan systems for information. Accuracy based on sensors. |
| **LaneKnowledgeService.php** | 10 | Y | Service | Track what pilots know about warp lanes. |
| **PrecursorShipDetectionService.php** | 3+interface | Y | Service+Interface | Detect rare precursor technology. |
| **PrecursorRumorService.php** | 6 | Y | Service | Generate and track precursor clues. |

### Ship Management

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **ShipUpgradeService.php** | 6 | Y | Service | Upgrade ship components (weapons, hull, sensors, etc.). Cost/availability checks. |
| **ShipRepairService.php** | 4 | Y | Service | Repair hull damage. |
| **ShipPurchaseService.php** | 6 | Y | Service | Buy new ships. Credits/availability validation. |
| **ShipVariationService.php** | 5+trait | Y | Service+Trait | Create ship variations with random traits. |
| **ShipRarityService.php** | 4 | Y | Service | Determine ship rarity and attributes. |
| **ShipyardInventoryService.php** | 5 | Y | Service | Manage shipyard stock. |

### Colonies & Development

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **OrbitalStructureService.php** | 9 | Y | Service | Manage orbital structures and stations. |
| **SystemDefenseFactory.php** | 5 | Y | Service | Create defense installations. Factory pattern. |
| **SystemPopulationService.php** | 3 | Y | Service | Population calculations and tracking. |
| **PlayerSpawnService.php** | 2 | Y | Service | Initialize player in galaxy. |

### Knowledge & Learning

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **PlayerKnowledgeService.php** | 9 | Y | Service | Track player discoveries and knowledge state. |
| **StarChartService.php** | 7 | Y | Service | Star chart purchasing and coverage calculation (BFS 2-hop). Exponential pricing. |
| **BarRumorService.php** | 1 | Y | Service | Generate rumors in bars. **Minimal service - 1 method.** |
| **NotificationService.php** | 16 | Y | Service | Create and manage player notifications. **Large service with wide responsibilities.** |

### Mirror Universe

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **MirrorUniverseService.php** | 7 | Y | Service | High-risk parallel dimension mechanics. 2x resources, 3x pirate difficulty, 24h cooldown. |

### Salvage & Trading

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **SalvageYardService.php** | 10 | Y | Service | Salvage yard operations and inventory. |
| **ResourceValidatorService.php** | 8 | Y | Service | Validate resource availability and legality. |

### Galaxy Generation Services

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **GalaxyCreationService.php** | 4 | Y | Service | Wrapper around orchestrator pattern. |
| **TieredGalaxyCreationService.php** | 3 | Y | Service | Core vs frontier region creation. |
| **InhabitedSystemGenerator.php** | 3 | Y | Service | Distribute inhabited systems (40% default, 33-50% range) with min spacing. |
| **CoreSystemGenerator.php** | 4 | Y | Service | Generate core region with high civilization density. |
| **OuterSystemGenerator.php** | 4 | Y | Service | Generate frontier region with fewer inhabitants. |

### Galaxy Generation Orchestrator & Components

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **GalaxyGenerationOrchestrator.php** | ? | Y | Service | Pipeline orchestrator. Coordinates all generation phases with metrics. Multi-stage: stars → sectors → gates → pirates → trading → defense → minerals → mirror → precursor. |
| **StarFieldGenerator.php** | ? | Y | Service | POI point distribution (Poisson, Halton, etc.). |
| **SectorGridGenerator.php** | ? | Y | Service | Create overlaid sector grid. |
| **WarpGateNetworkGenerator.php** | ? | Y | Service | Build warp gate connections between inhabited systems. Adjacency auto-calc. |
| **WarpGate/IncrementalWarpGateGenerator.php** | ? | Y | Service | Progressive gate generation. |
| **TradingInfrastructureGenerator.php** | ? | Y | Service | Place trading hubs (50-80% of inhabited). Cartographers (30% of hubs). |
| **DefenseNetworkGenerator.php** | ? | Y | Service | Create defense structures. |
| **MineralDepositGenerator.php** | ? | Y | Service | Assign mineral resources to POIs. Scarcity and biases. |
| **MirrorUniverseGenerator.php** | ? | Y | Service | Create rare mirror gate (1 per galaxy, requires sensor 5). |
| **PlanetarySystemGenerator.php** | ? | Y | Service | Generate orbital hierarchies (stars with planets/moons). |
| **PrecursorContentGenerator.php** | ? | Y | Service | Create ancient tech and relics. |

### Galaxy Generation Support Classes

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **GalaxyGeneration/Data/GenerationConfig.php** | ? | Y | ValueObject | Configuration parameters for generation run. |
| **GalaxyGeneration/Data/GenerationMetrics.php** | ? | Y | ValueObject | Performance metrics and statistics. |
| **GalaxyGeneration/Data/GenerationResult.php** | ? | Y | ValueObject | Result data from each generator. |
| **GalaxyGeneration/Support/BulkInserter.php** | ? | Y | Service | Batch database inserts for performance. |
| **GalaxyGeneration/Support/Profiler.php** | ? | Y | Service | Performance profiling utility. |
| **GalaxyGeneration/Support/SpatialIndex.php** | ? | Y | Service | O(1) neighbor lookups for proximity queries. |
| **GalaxyGeneration/Contracts/GeneratorInterface.php** | ? | N | Interface | Contract for all generators. |

### Stellar System Generation (Subservice)

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **StellarSystem/StarSystemGenerator.php** | ? | Y | Service | Generate individual star systems with orbital bodies. |
| **StellarSystem/PlanetTypeSelector.php** | ? | Y | Service | Classify planets (terrestrial, gas giant, ice, etc.). |
| **StellarSystem/MoonGenerator.php** | ? | Y | Service | Create moons around planets. |

### Trading Subservices

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Trading/TradingHubGenerator.php** | ? | Y | Service | Create trading hub infrastructure. |
| **Trading/MineralSourceMapper.php** | ? | Y | Service | Map mineral sources across galaxy. |
| **Trading/ProximityPricingService.php** | ? | Y | Service | Calculate mineral prices based on proximity to source. |

### Utility & Support

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **NotificationService.php** | 16 | Y | Service | **Largest service - 16 methods.** Create notifications for: travel, combat, low quantium, discoveries, market events, etc. |
| **PlayerDeathService.php** | 2 | Y | Service | **Minimal - 2 methods.** Handle player elimination. |

### Service Code Smells Detected

1. **NotificationService (16 methods)** - Fat class creating notifications for many event types. Could split into domain-specific notification builders.
2. **PirateEncounterService (14 methods, 6 dependencies)** - Complex service with high coupling. Too many responsibilities: encounter checking, band selection, probability, generation, combat.
3. **CombatResolutionService (2 methods)** - Suspiciously minimal. Either incomplete or logic is scattered elsewhere.
4. **GalaxyGenerationOrchestrator** - Not examined in detail but orchestrators often become god classes. Need to verify method count.
5. **BarRumorService (1 method)** - Single-method service. Should be inlined or expanded.
6. **MarketEventGenerator (2 methods)** - Could be merged with MarketEventService for clarity.
7. **Multiple "thin wrapper" services** - Some services (TieredGalaxyCreationService, PlayerSpawnService) have 2-3 methods. Consider consolidating.

---

## 3. HTTP CONTROLLERS - API LAYER (50+ controllers - 10,794 LOC)

API endpoints wrapping service logic. Controllers should delegate to services, not implement business logic.

### Core Gameplay Controllers

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **TravelController.php** | 5 | Y | Controller | Travel, warp gate listing, coordinate jumps. Uses ResolvesPlayer trait. |
| **TravelCalculationController.php** | 3 | Y | Controller | Pre-flight calculations (fuel cost, duration). **Should be service, not controller.** |
| **LocationController.php** | 2 | Y | Controller | Get current location. Minimal. |
| **NavigationController.php** | 5 | Y | Controller | Navigation calculations and pathfinding. |

### Galaxy Management

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **GalaxyController.php** | 8 | Y | Controller | List, view, details galaxies. |
| **GalaxyCreationController.php** | 9 | Y | Controller | Create galaxies. Calls orchestrator for optimized generation. **9 methods - largest galaxy controller.** |
| **GalaxySettingsController.php** | 1 | Y | Controller | Get galaxy configuration. Minimal. |
| **SectorMapController.php** | 1 | Y | Controller | View sector grid map. **Recently added, minimal.** |
| **StarSystemController.php** | 5 | Y | Controller | Star system details and relationships. |
| **MapSummaryController.php** | 1 | Y | Controller | Leaderboard/summary data. **Very minimal.** |

### Trading & Commerce

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **TradingController.php** | 4 | Y | Controller | Trading hub operations. |
| **TradingTransactionController.php** | 5 | Y | Controller | Log/audit trading transactions. |
| **PlansShopController.php** | 3 | Y | Controller | Purchase upgrade plans. |
| **ShipShopController.php** | 5 | Y | Controller | Buy/sell ships at shipyards. |
| **CartographyController.php** | 7 | Y | Controller | Star chart shopping and purchasing. |

### Combat & Conflict

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **CombatController.php** | 8 | Y | Controller | Combat mechanics (attack, flee, surrender). **8 methods.** |
| **PvPCombatController.php** | 7 | Y | Controller | Player vs Player combat. |
| **TeamCombatController.php** | 7 | Y | Controller | Team-based combat. |
| **ColonyCombatController.php** | 4 | Y | Controller | Combat at colonies. |

### Ship Management

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **ShipController.php** | 4 | Y | Controller | Ship operations and status. |
| **ShipStatusController.php** | 4 | Y | Controller | Detailed ship stats and components. |
| **ShipServiceController.php** | 6 | Y | Controller | Repair, upgrades, maintenance. |
| **ShipyardController.php** | 4 | Y | Controller | Shipyard inventory and purchasing. |
| **UpgradeController.php** | 7 | Y | Controller | Ship upgrades (weapons, hull, sensors, etc.). |

### Mining & Resources

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **MiningController.php** | 4 | Y | Controller | Mining operations. |
| **SalvageYardController.php** | 7 | Y | Controller | Salvage yard interaction. |
| **ScanController.php** | 6 | Y | Controller | Scan systems for resources and threats. |

### Colony Management

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **ColonyController.php** | 8 | Y | Controller | Colony status, operations, production. **8 methods.** |
| **ColonyBuildingController.php** | 4 | Y | Controller | Colony building construction and effects. |

### Player & Status

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **PlayerController.php** | 6 | Y | Controller | Player state, profile, stats. |
| **PlayerStatusController.php** | 2 | Y | Controller | Current player status snapshot. Minimal. |
| **PlayerSettingsController.php** | 1 | Y | Controller | Player preferences and settings. **Very minimal.** |
| **PlayerKnowledgeMapController.php** | 2 | Y | Controller | Discovered systems and knowledge. |

### Special Systems

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **MirrorUniverseController.php** | 4 | Y | Controller | Enter/exit mirror universe, cooldown checks. |
| **PrecursorRumorController.php** | 5 | Y | Controller | Precursor ship rumors and clues. |
| **PrecursorShipDetectionService.php** | 3 | Y | Service | Detect precursor technology. |

### Notifications & Market

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **NotificationController.php** | 6 | Y | Controller | Fetch and manage notifications. |
| **MarketEventController.php** | 3 | Y | Controller | View market events. |
| **OrbitalStructureController.php** | 8 | Y | Controller | Orbital structures (stations, platforms). |
| **FacilitiesController.php** | 3 | Y | Controller | Space facilities access and upgrades. |

### Factions & NPCs

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **PirateFactionController.php** | 4 | Y | Controller | Pirate faction info and reputation. |
| **PoiTypeController.php** | 5 | Y | Controller | POI type enumeration and details. |
| **LeaderboardController.php** | 6 | Y | Controller | Rankings and leaderboards (merchants, conquerors, etc.). |
| **VictoryController.php** | 3 | Y | Controller | Victory condition progress. |

### Authentication

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Auth/AuthController.php** | ? | Y | Controller | Login/register via Laravel Sanctum. |

### Base & Traits (API Helpers)

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **BaseApiController.php** | 0 | N | Base | Parent class for all API controllers. Provides trait support. |
| **Traits/FindsByUuid.php** | ? | N | Trait | Lookup models by UUID. Shared by multiple controllers. |
| **Traits/ResolvesPlayer.php** | ? | N | Trait | Get authenticated player from request. Used everywhere. |
| **Traits/ResolvesShip.php** | ? | N | Trait | Get player's active ship. Used in combat/travel. |

### Helper Classes (Builders)

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Builders/StarSystemResponseBuilder.php** | ? | Y | Helper | Build API response for star systems. |
| **Builders/PoiCategorizer.php** | ? | Y | Helper | Classify POIs (star, planet, station, nebula, etc.). |
| **Builders/SystemGenerationHandler.php** | ? | Y | Helper | Generate missing systems on-demand. |
| **Builders/SystemNameGenerator.php** | ? | Y | Helper | Generate system names procedurally. |
| **Builders/BarNameGenerator.php** | ? | Y | Helper | Generate cantina/bar names. |
| **Builders/ParentStarResolver.php** | ? | Y | Helper | Find parent star for orbital bodies. |

### Legacy Controllers

| File | Methods | Constructor | Type | Summary |
|------|---------|-------------|------|---------|
| **Controller.php** | ? | N | Base | Laravel base controller. Not custom. |
| **GalaxyController.php** (non-API) | ? | Y | Controller | Non-API galaxy management (older pattern). |
| **GalaxyDebugController.php** | ? | Y | Controller | Debug endpoints for development. |

### Controller Code Smells Detected

1. **GalaxyCreationController (9 methods)** - Longest controller. Could split into separate endpoints for each step.
2. **TravelCalculationController** - Calculation endpoints should be services, not controllers. Violates single responsibility.
3. **Multiple minimal 1-method controllers** (MapSummaryController, PlayerSettingsController, GalaxySettingsController, SectorMapController) - Consider consolidation into fewer, larger endpoints.
4. **Traits overuse** - ResolvesPlayer used everywhere. Good pattern but indicates tight coupling to authentication.
5. **Builder classes duplication** - Multiple builders for naming/generation. Could use factory pattern.

---

## 4. CONSOLE COMMANDS (29 commands - Thin wrappers)

Artisan CLI commands for operations and orchestration. All commands call services; they don't implement logic.

### Galaxy Management Commands

| File | Methods | Summary |
|------|---------|---------|
| **GalaxyInitialize.php** | 1 | Initialize complete galaxy in one command. All-in-one orchestrator. |
| **GalaxyGeneratePoints.php** | 1 | Distribute POIs using configured generator (Poisson, Halton, etc.). |
| **GalaxyGenerateGates.php** | 1 | Create warp gate network. Adjacency auto-calculated. |
| **GalaxyGenerateSectors.php** | 1 | Create sector grid overlay. |
| **GalaxyDesignateInhabitedCommand.php** | 1 | Mark 33-50% of stars as inhabited with min spacing. |
| **GalaxyDistributePirates.php** | 1 | Place pirate bands on warp lanes. |
| **GalaxyDistributePirateBands.php** | 1 | Alternative pirate distribution. |
| **GalaxyCreateMirror.php** | 1 | Create 1 rare mirror gate per galaxy. |
| **GalaxyExpandCommand.php** | 1 | Add more POIs to existing galaxy. |
| **GalaxyFlushCommand.php** | 1 | Delete all data in galaxy. |
| **GalaxyViewCommand.php** | 1 | Visualize galaxy in terminal. |

### Trading & Economy Commands

| File | Methods | Summary |
|------|---------|---------|
| **TradingHubGenerateCommand.php** | 1 | Create trading hubs at 50-80% of inhabited systems. |
| **TradingHubPopulateInventory.php** | 1 | Stock hubs with initial mineral inventory. |
| **CartographyGenerateShopsCommand.php** | 1 | Place star chart vendors at ~30% of trading hubs. |
| **AssignMineralProductionCommand.php** | 1 | Assign mineral resources to systems. |

### Player & Game Management Commands

| File | Methods | Summary |
|------|---------|---------|
| **InitializePlayerCommand.php** | 1 | Create player in galaxy with starting ship/location. |
| **PlayerInterfaceCommand.php** | 1 | Launch console-based player interface (TUI). Commands: [v]iew map, [j]ump, [w]arp, [t]rade, [s]hip, [c]argo. |
| **PlayerCommand.php** | 1 | Legacy player operations. |
| **RegenerateFuelCommand.php** | 2 | Process passive fuel regeneration for all players. |

### Game Loop Commands

| File | Methods | Summary |
|------|---------|---------|
| **ProcessMarketEventsCommand.php** | 2 | Run market event generator (price fluctuations). |
| **ProcessColonyCycles.php** | 2 | Process colony production, population, resource cycles. |
| **PiratesMoveCommand.php** | 1 | Move pirate bands to new sectors. |
| **KnowledgeHydrateCommand.php** | 1 | Update player knowledge base (discoveries, charts). |

### Ship Management Commands

| File | Methods | Summary |
|------|---------|---------|
| **UpgradeShipCommand.php** | 1 | Upgrade ship component from CLI. |

### Benchmark Commands (Development)

| File | Methods | Summary |
|------|---------|---------|
| **BenchmarkGalaxyGeneration.php** | 1 | Performance test POI generation. |
| **BenchmarkGalaxyInitCommand.php** | 1 | Performance test full galaxy initialization. |
| **BenchmarkLeaderboardCommand.php** | 1 | Performance test leaderboard queries. |
| **BenchmarkNavigationCommand.php** | 1 | Performance test pathfinding. |

### Utility Commands

| File | Methods | Summary |
|------|---------|---------|
| **ClassifyIceGiants.php** | 1 | Classify ice giant planets. |

### Console Command Code Smells

1. **All commands are thin wrappers (1-2 methods)** - This is actually good architecture. Commands orchestrate services.
2. **No business logic in commands** - Correct pattern.
3. **Console interface (PlayerInterfaceCommand)** - Complex interactive TUI. May need its own service layer.

---

## 5. CONSOLE SHOP HANDLERS (6 handlers - Interactive TUI)

Interactive console interfaces for player actions within PlayerInterfaceCommand.

| File | Methods | Type | Summary |
|------|---------|------|---------|
| **MineralTradingHandler.php** | 2 | Interface | Buy/sell minerals at trading hubs. Terminal prompts. |
| **ComponentShopHandler.php** | 2 | Interface | Purchase ship upgrades. Terminal menus. |
| **RepairShopHandler.php** | 2 | Interface | Repair hull damage. Interactive repair selection. |
| **ShipShopHandler.php** | 2 | Interface | Buy/switch ships. Terminal shop interface. |
| **PlansShopHandler.php** | 2 | Interface | Buy upgrade plans. Terminal interface. |
| **PirateEncounterHandler.php** | 2 | No interface | Handle combat encounters during travel. Separate flow. |

### Shop Handler Code Smells

1. **All handlers are interfaces with exactly 2 methods** - Likely handle() and a helper. Thin abstraction.
2. **Tight coupling to console I/O** - Cannot be reused by API. Could extract logic to services.

### Console Traits (TUI Infrastructure)

| File | Methods | Type | Summary |
|------|---------|------|---------|
| **Traits/ConsoleBoxRenderer.php** | ? | Trait | Draw ANSI boxes in terminal. Shared UI utility. |
| **Traits/ConsoleColorizer.php** | ? | Trait | Apply ANSI colors. Shared UI utility. |
| **Traits/ModalDisplay.php** | ? | Trait | Display modal dialogs in terminal. |
| **Traits/TerminalInputHandler.php** | ? | Trait | Handle keyboard input (non-blocking). |

### Console Service

| File | Methods | Type | Summary |
|------|---------|------|---------|
| **Console/Services/LocationValidator.php** | ? | Service | Validate location coordinates and POI availability. |

---

## 6. ENUMS (15 enums across 7 files)

Type-safe enumerations for game constants and classifications.

### Main Enums (Root Level)

| File | Cases | Summary |
|------|-------|---------|
| **ComponentType.php** | ? | Ship component types (weapons, hull, sensors, warp drive, shields, cargo). |
| **MarketEventType.php** | ? | Market events (shortage, surplus, discovery, embargo, etc.). |
| **OrbitalStructureType.php** | ? | Space station types (refinery, shipyard, defense, research). |
| **RarityTier.php** | ? | Item rarity (common, uncommon, rare, epic, legendary). |

### Galaxy Enums

| File | Cases | Summary |
|------|-------|---------|
| **Galaxy/GalaxyStatus.php** | ? | Galaxy state (generating, active, dormant). |
| **Galaxy/GalaxySizeTier.php** | ? | Size categories (small, medium, large, massive). Affects dimensions. |
| **Galaxy/GalaxyDistributionMethod.php** | ? | POI generation method selection. |
| **Galaxy/GalaxyRandomEngine.php** | ? | Random number generator selection (MT19937, PCG, etc.). |
| **Galaxy/RegionType.php** | ? | Core vs Frontier region classification. |

### POI Enums

| File | Cases | Summary |
|------|-------|---------|
| **PointsOfInterest/PointOfInterestType.php** | ? | Star, Planet, Nebula, Anomaly, Station, BlackHole, Asteroid Belt. |
| **PointsOfInterest/PointOfInterestStatus.php** | ? | Status (active, destroyed, hidden, charted). |
| **PointsOfInterest/StellarClassification.php** | ? | Star types (O, B, A, F, G, K, M - real astronomy). |

### Specialized Enums

| File | Cases | Summary |
|------|-------|---------|
| **Exploration/ScanLevel.php** | ? | Scan depth (1-5, higher = more detail). |
| **Exploration/KnowledgeLevel.php** | ? | Knowledge state (unknown, rumored, charted, mapped). |
| **Trading/MineralRarity.php** | ? | Mineral scarcity levels (common, uncommon, rare, exotic). |
| **Defense/SystemDefenseType.php** | ? | Defense structure types (shield, cannon, fighter bay). |
| **WarpGate/GateType.php** | ? | Gate types (normal, hidden, dead-end, mirror, jackpot). |

### Enum Code Smells

1. **Mixed concerns in enums** - GalaxyDistributionMethod and GalaxyRandomEngine are technically config, not enums.
2. **No documented cases** - Don't know exact cases without reading files.

---

## 7. TRAITS (4 traits - Shared Behavior)

Reusable behavior composed into classes.

| File | Type | Location | Methods | Summary |
|------|------|----------|---------|---------|
| **HasUuidAndVersion.php** | Trait | app/Traits/ | 2 | Add UUID and version fields to models. Used by Galaxy, Player, etc. |
| **HasUuid.php** | Trait | app/Models/Traits/ | ? | Add UUID field to models. Variant of above. |
| **ShipVariationService.php** | Trait | app/Services/ | 5 | Generate ship variation traits (modifiers, bonuses). Included in service. |
| **Traits/FindsByUuid.php** | Trait | app/Http/Controllers/Api/Traits/ | ? | Lookup by UUID across controllers. |
| **Traits/ResolvesPlayer.php** | Trait | app/Http/Controllers/Api/Traits/ | ? | Extract authenticated player from request. |
| **Traits/ResolvesShip.php** | Trait | app/Http/Controllers/Api/Traits/ | ? | Get player's active ship. |

### Traits in Console

| File | Type | Location | Summary |
|------|------|----------|---------|
| **ConsoleBoxRenderer.php** | Trait | app/Console/Traits/ | Draw ANSI boxes. Terminal UI utility. |
| **ConsoleColorizer.php** | Trait | app/Console/Traits/ | Apply ANSI colors. Terminal UI utility. |
| **ModalDisplay.php** | Trait | app/Console/Traits/ | Display modal dialogs. Terminal UI component. |
| **TerminalInputHandler.php** | Trait | app/Console/Traits/ | Handle keyboard input (non-blocking). Terminal I/O. |

### Trait Code Smells

1. **Duplicate UUID traits** - HasUuid and HasUuidAndVersion do similar things. Could consolidate.
2. **Console traits are very focused** - Good separation of concerns for TUI rendering.
3. **ResolvesPlayer trait used everywhere** - Indicates tight coupling to auth system. Consider service.

---

## 8. GENERATORS (9 point generators - Spatial Distribution)

Pluggable system for distributing POIs across galaxy. Instantiated via PointGeneratorFactory using reflection.

All located in `/home/mdhas/workspace/space-wars-3002/app/Generators/Points/`

| File | Type | Inheritance | Summary |
|------|------|-------------|---------|
| **AbstractPointGenerator.php** | Abstract | None | Base class with utilities (density checks, bound validation, profiling). |
| **PoissonDisk.php** | Class | Extends AbstractPointGenerator | Blue noise distribution with minimum spacing. High quality. |
| **RandomScatter.php** | Class | Extends AbstractPointGenerator | Pure random (Uniform) distribution. Fast but uneven. |
| **HaltonSequence.php** | Class | Extends AbstractPointGenerator | Low-discrepancy quasi-random. Good for determinism. |
| **VogelsSpiral.php** | Class | Extends AbstractPointGenerator | Sunflower spiral pattern. Visually interesting. |
| **StratifiedGrid.php** | Class | Extends AbstractPointGenerator | Grid-based with jitter. Balanced coverage. |
| **LatinHypercube.php** | Class | Extends AbstractPointGenerator | Stratified random sampling. Variance reduction. |
| **R2Sequence.php** | Class | Extends AbstractPointGenerator | R2 low-discrepancy sequence. Fast convergence. |
| **UniformRandom.php** | Class | Extends AbstractPointGenerator | Baseline uniform random. Reference implementation. |

All implement `PointGeneratorInterface` with method `sample(Galaxy): array`.

### Point Generators Code Smells

1. **All generators extend AbstractPointGenerator** - Good inheritance hierarchy.
2. **No composition** - All use inheritance, not composition. OK for this small set.
3. **Determinism tests important** - Generators produce different results with same seed. Must test carefully.
4. **MAX_DENSITY = 0.65 hard limit** - DensityGuard prevents impossible configurations.

---

## 9. VALUE OBJECTS (1 file)

Immutable data structures representing configuration or calculations.

| File | Methods | Summary |
|------|---------|---------|
| **GalaxyConfig.php** | 2 | Snapshot of game_config.php at galaxy creation time. Allows different galaxies to have different rules. |

### ValueObject Code Smells

1. **Only 1 value object file** - Underutilized pattern. Could extract more (Point, Distance, Coordinate, etc.). |
2. **Limited functionality** - GalaxyConfig likely just holds properties. Could be more sophisticated.

---

## 10. FAKER PROVIDERS (10+ providers - Procedural Naming)

Custom Faker providers for generating realistic space/game names.

### Name Generators (Data)

| File | Type | Purpose |
|------|------|---------|
| **Common/GalaxyNames.php** | Data | List of galaxy name templates (Andromeda, Centaurus, etc.). |
| **Common/GalaxySuffixes.php** | Data | Suffixes for composite names (Prime, Major, Minor). |
| **Common/GalaxyVerbs.php** | Data | Action verbs for names (Shattered, Burning, Lost). |
| **Common/GreekLetters.php** | Data | Greek letters for star naming (Alpha, Beta, Gamma). |
| **Common/MythologicalNames.php** | Data | Mythological references (Zeus, Athena, Odin). |
| **Common/RomanNumerals.php** | Data | Roman numeral formatting (I, II, III, IV). |
| **Common/StarCatalog.php** | Data | Star catalog names and conventions. |

### Faker Providers (Generators)

| File | Methods | Purpose |
|------|---------|---------|
| **Providers/GalaxyNameProvider.php** | ? | Generate galaxy names from templates. |
| **Providers/StarNameProvider.php** | ? | Generate star names (Betelgeuse-style). |
| **Providers/PlanetNameProvider.php** | ? | Generate planet names (Earth-style or exotic). |
| **Providers/NebulaNameProvider.php** | ? | Generate nebula names. |
| **Providers/BlackHoleNameProvider.php** | ? | Generate black hole designations. |
| **Providers/AnomalyNameProvider.php** | ? | Generate anomaly descriptions. |
| **SpaceProvider.php** | 0 | Main provider registering all sub-providers. |

### Faker Code Smells

1. **SpaceProvider has 0 methods** - Likely just registers others via `provider()` calls.
2. **No grammar-based generation** - All providers probably use data lists. Could use Tracery-style grammars for more variety.
3. **Hard to extend** - Faker providers require subclassing or method registration.

---

## ARCHITECTURAL PATTERNS & OBSERVATIONS

### 1. Service Locator Pattern (Anti-pattern)
Most services use constructor injection (good). Some controllers/commands might resolve services from container without explicit dependencies (should avoid).

### 2. God Objects Detected

**Models:**
- **PointOfInterest** (37 methods) - Represents stars, planets, stations, nebulae, anomalies, black holes, asteroid belts, anomalies. Needs decomposition.
- **PlayerShip** (35 methods) - Fuel, cargo, weapons, shields, colonists, hidden holds, fighters. Too many concerns.
- **Player** (35 methods) - State, relationships, combat stats, knowledge, mirror universe. Could extract concerns.

**Services:**
- **NotificationService** (16 methods) - Creates notifications for: travel, combat, mining, discovery, market, colonies, defenses, low quantium, etc. Could split into domain-specific builders.
- **PirateEncounterService** (14 methods, 6 dependencies) - Encounter checking, band selection, probability, generation, combat. High coupling.

### 3. Circular Dependencies

Potential issues:
- PlayerShip model depends on services (e.g., FuelRegenerationService)
- Commands call services; services call other services
- Controllers inject multiple services
- Traits resolve dependencies

**Risk:** Difficult to test in isolation. Need to verify circular deps don't exist.

### 4. Thin Wrapper Classes (Code Smell)

Services with 1-2 methods:
- BarRumorService (1 method)
- MarketEventGenerator (2 methods)
- SurrenderService (2 methods)
- PlayerDeathService (2 methods)
- ColonyCombatService (2 methods)
- CombatResolutionService (2 methods)
- CombatParticipant (9 methods but might be thin)

**Recommendation:** Consolidate into larger services or expand responsibilities.

### 5. Controllers with Business Logic (Anti-pattern)

- **TravelCalculationController** - Pre-flight calculations should be in TravelService, not controller. Calculation logic shouldn't live in HTTP layer.
- Some builders (SystemGenerationHandler) blur line between request handling and business logic.

### 6. Missing Abstraction Layers

- **Notifications** - No abstraction. All routes through NotificationService. Could use observer pattern or event system.
- **Combat** - CombatResolutionService is suspiciously minimal. Combat logic may be scattered across controllers/models.
- **Galaxy Generation** - Large orchestrator with many generators. Could benefit from more granular abstractions.

### 7. Trait Overuse

**Good traits:**
- ConsoleBoxRenderer, ConsoleColorizer, ModalDisplay - Focused UI utilities
- HasUuid, HasUuidAndVersion - Cross-cutting concern

**Problematic traits:**
- ResolvesPlayer - Used everywhere, indicates tight coupling to auth
- FindsByUuid - Generic lookup that could be a service/repository

### 8. Configuration Management

**Good:**
- Single config/game_config.php source of truth
- Galaxy snapshots config at creation time
- Env-based overrides

**Potential issues:**
- No validation that enum cases match config values
- Config loading could fail silently
- No schema validation

### 9. Database Query Patterns

**Observations:**
- Heavy use of Eloquent relationships (good ORM usage)
- Polymorphic relations on PointOfInterest (flexible but can be slow)
- No visible query optimization (need to check N+1 issues)
- Bulk operations via BulkInserter (good for generation)

### 10. Testing Surface Area

**Concerns:**
- 295+ classes = large codebase to test
- 70 services with multiple dependencies = complex mock setup
- 50+ controllers = many endpoint integrations to test
- Generators produce different results each run (determinism critical for tests)

---

## RECOMMENDED REFACTORING PRIORITIES

### High Priority

1. **Split PointOfInterest** - 37 methods handling too many POI types. Create:
   - StarSystem class
   - Planet class
   - Nebula class
   - AsteroidBelt class
   - Station class

2. **Extract Notification Concerns** - Split NotificationService (16 methods):
   - TravelNotificationBuilder
   - CombatNotificationBuilder
   - ResourceNotificationBuilder
   - DiscoveryNotificationBuilder

3. **Fix CombatResolutionService** - Only 2 methods. Either expand or consolidate with related services.

4. **Consolidate Thin Services**:
   - Merge BarRumorService into PrecursorRumorService
   - Merge MarketEventGenerator into MarketEventService
   - Merge PlayerDeathService into PirateEncounterService or SurrenderService

5. **Move TravelCalculationController** - Calculations should be service methods, not controller actions.

### Medium Priority

6. **Reduce PirateEncounterService** - 14 methods, 6 dependencies. Split into:
   - PirateDetectionService (encounter probability)
   - PirateFleetCompositionService (enemy generation)
   - PirateEncounterHandler (orchestration)

7. **Reduce Controller Methods** - GalaxyCreationController (9 methods) could split:
   - POST /galaxies → create
   - POST /galaxies/{id}/generate-stars
   - POST /galaxies/{id}/generate-sectors
   - etc.

8. **Create Repository Pattern** - Controllers directly query models. Could extract:
   - PlayerRepository
   - GalaxyRepository
   - PointOfInterestRepository

9. **Introduce Value Objects**:
   - Point(x, y)
   - Distance(value, unit)
   - Coordinate(x, y, z)
   - Fuel(current, max)
   - Cargo(items)

10. **Extract Response Building** - Builders like StarSystemResponseBuilder suggest need for:
    - Response DTO layer
    - Resource/Transformer pattern
    - API response standardization

### Low Priority

11. **Trait Consolidation** - Merge duplicate UUID traits.

12. **Command Consistency** - All commands are thin wrappers (good), but could standardize output formatting.

13. **Error Handling** - Need consistent error responses across API.

14. **Logging** - Services should log important operations for debugging.

---

## SUMMARY TABLE

| Category | Count | LOC | Avg Methods | Concerns |
|----------|-------|-----|-------------|----------|
| Models | 48 | 7,613 | 8.4 | God objects (POI, PlayerShip, Player), empty models |
| Services | 70+ | 15,410 | 6.2 | Thin wrappers, god services (Notification, PirateEncounter) |
| Controllers | 50+ | 10,794 | 4.8 | Business logic in controllers, minimal endpoints |
| Commands | 29 | - | 1.2 | Thin wrappers (good pattern) |
| Shop Handlers | 6 | - | 2.0 | Interface compliance, console coupling |
| Enums | 15 | - | - | Good type safety, limited usage |
| Traits | 10+ | - | 2-5 | Good reusability, some consolidation opportunities |
| Generators | 9 | - | - | Clean hierarchy, determinism critical |
| ValueObjects | 1 | - | 2 | Underutilized pattern |
| Faker | 10+ | - | ? | Data-driven, limited generation |
| **TOTAL** | **~295** | **~33,817** | **5.8** | **Moderate-to-high complexity, refactoring needed** |

---

## CONCLUSION

Space Wars 3002's codebase exhibits solid architecture fundamentals:
- ✓ Clear service/controller/model separation
- ✓ Dependency injection throughout
- ✓ Pluggable generators for extensibility
- ✓ Configuration-driven game rules
- ✓ Comprehensive model relationships

However, there are opportunities for improvement:
- Split god objects (PointOfInterest, PlayerShip, Player)
- Consolidate thin services and controllers
- Extract more value objects
- Introduce response/DTO layer
- Reduce service coupling
- Add observable pattern for notifications

The codebase is maintainable at current size but approaching the threshold where refactoring becomes critical for further growth.


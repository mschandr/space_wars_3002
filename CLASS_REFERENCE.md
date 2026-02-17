# Space Wars 3002 - Quick Class Reference

**Lookup tables for every class in the codebase**

## Models Directory (/app/Models/)

### Game State Models
- **Player.php** - Player entity, credits, level, location, stats. (35 methods)
- **Galaxy.php** - Galaxy container with snapshotted config. (22 methods)
- **PointOfInterest.php** - POI entity (stars, planets, stations, nebulae, etc.). (37 methods)
- **Sector.php** - Grid-based region with danger level. (6 methods)

### Ship & Equipment
- **Ship.php** - Ship blueprint/template. (3 methods)
- **PlayerShip.php** - Player's actual ship instance. (35 methods)
- **ShipComponent.php** - Individual component type. (7 methods)
- **PlayerShipComponent.php** - Installed component. (10 methods)
- **PlayerShipFighter.php** - Fighter drones. (8 methods)

### Combat & Conflict
- **CombatSession.php** - Combat state. (10 methods)
- **CombatParticipant.php** - Combatant in session. (9 methods)
- **PvPChallenge.php** - Player vs player challenge. (13 methods)
- **PvPTeamInvitation.php** - Team PvP invitation. (8 methods)

### Pirate System
- **PirateFaction.php** - Pirate organization. (4 methods)
- **PirateCaptain.php** - Pirate leader. (6 methods)
- **PirateBand.php** - Active pirate group. (10 methods)
- **PirateFleet.php** - Fleet of ships. (6 methods)
- **PirateCargo.php** - Stolen cargo. (5 methods)
- **WarpLanePirate.php** - Legacy lane-based pirates. (3 methods)

### Warp & Navigation
- **WarpGate.php** - Gate connection between POIs. (23 methods)
- **WarpGate** connects from_poi_id → to_poi_id with types (normal, hidden, dead-end, mirror)

### Trading & Commerce
- **TradingHub.php** - Market for buying/selling minerals. (12 methods)
- **TradingHubInventory.php** - Mineral stock at hub. (6 methods)
- **TradingHubShip.php** - Ships for sale. (5 methods)
- **Mineral.php** - Tradable resource type. (2 methods)
- **PlayerCargo.php** - Player's cargo. (2 methods)
- **NpcCargo.php** - NPC cargo. (2 methods)
- **StellarCartographer.php** - Star chart vendor. (1 method)

### Colonies & Production
- **Colony.php** - Player settlement. (13 methods)
- **ColonyBuilding.php** - Building structure. (6 methods)
- **ColonyMission.php** - Colony task. (8 methods)
- **ColonyShipProduction.php** - Ship production queue. (8 methods)

### Infrastructure & Defense
- **SystemDefense.php** - Defense structure. (12 methods)
- **OrbitalStructure.php** - Orbital station. (7 methods)
- **SalvageYardInventory.php** - Salvageable items. (8 methods)
- **ShipyardInventory.php** - Ships for sale. (3 methods)

### Knowledge & Scanning
- **SystemScan.php** - Scanned system data with ScanLevel enum. (12 methods)
- **PlayerSystemKnowledge.php** - What player knows about system. (8 methods)
- **PilotLaneKnowledge.php** - Pilot knowledge of lanes. (4 methods)

### Special Systems
- **PrecursorShip.php** - Ancient alien tech. (18 methods + interface)
- **PlayerPrecursorRumor.php** - Precursor clues. (2 methods)
- **Plan.php** - Upgrade blueprint. (5 methods)
- **PlayerPlan.php** - Player's plan. (0 methods - EMPTY)
- **MarketEvent.php** - Price fluctuation event. (9 methods)
- **PoiType.php** - POI type classification. (6 methods + enum)

### Authentication
- **User.php** - Auth user. (2 methods)

### Traits
- **Traits/HasUuid.php** - Add UUID to models
- **Models/Traits/** - Model-specific traits

---

## Services Directory (/app/Services/)

### Core Game Loop (7 services)
1. **TravelService.php** - Movement, fuel calc (ceil(dist/efficiency)), XP rewards
2. **TradingService.php** - Buy/sell minerals
3. **MiningService.php** - Extract minerals
4. **CombatResolutionService.php** - Combat mechanics (2 methods - minimal)
5. **ColonyCycleService.php** - Population, production cycles
6. **MarketEventService.php** - Price fluctuations
7. **MarketEventGenerator.php** - Generate events (2 methods)

### Fuel & Movement (3 services)
- **FuelRegenerationService.php** - Time-based regen (BASE * (1 + (warp_drive-1)*0.3))
- **TravelNotificationService.php** - Travel event notifications
- **TravelCalculationController.php** - Pre-flight calculations (in Controllers, should be service)

### Combat & Encounters (8 services)
- **PirateEncounterService.php** - Sector encounter, probability, generation (14 methods, 6 deps)
- **EscapeCalculationService.php** - Escape probability
- **SurrenderService.php** - Surrender handling
- **SalvageService.php** - Extract salvage
- **PlayerDeathService.php** - Death mechanics
- **PvPCombatService.php** - Player vs player
- **TeamCombatService.php** - Team combat
- **ColonyCombatService.php** - Colony combat

### NPC & Exploration (6 services)
- **PirateFleetGenerator.php** - Create fleets
- **NpcGenerationService.php** - Create NPCs
- **SystemScanService.php** - Scan systems
- **LaneKnowledgeService.php** - Warp lane knowledge
- **PrecursorShipDetectionService.php** - Detect precursor ships
- **PrecursorRumorService.php** - Generate rumors

### Ship Management (6 services)
- **ShipUpgradeService.php** - Upgrade components
- **ShipRepairService.php** - Repair hull
- **ShipPurchaseService.php** - Buy ships
- **ShipVariationService.php** - Create variations with traits
- **ShipRarityService.php** - Determine rarity
- **ShipyardInventoryService.php** - Manage inventory

### Colonies & Development (4 services)
- **OrbitalStructureService.php** - Manage structures
- **SystemDefenseFactory.php** - Create defenses
- **SystemPopulationService.php** - Population calc
- **PlayerSpawnService.php** - Initialize player

### Knowledge & Learning (4 services)
- **PlayerKnowledgeService.php** - Track discoveries
- **StarChartService.php** - Star charts (BFS 2-hop, exponential pricing)
- **BarRumorService.php** - Bar rumors (1 method - minimal)
- **NotificationService.php** - All notifications (16 methods - god service)

### Mirror Universe (1 service)
- **MirrorUniverseService.php** - Parallel dimension mechanics

### Salvage & Trading (2 services)
- **SalvageYardService.php** - Salvage operations
- **ResourceValidatorService.php** - Resource validation

### Galaxy Generation (60+ services)

**Orchestrator:**
- **GalaxyGenerationOrchestrator.php** - Pipeline coordinator

**Main Generators:**
- **StarFieldGenerator.php** - POI distribution
- **SectorGridGenerator.php** - Sector grid
- **WarpGateNetworkGenerator.php** - Gate connections
- **TradingInfrastructureGenerator.php** - Hubs and cartographers
- **DefenseNetworkGenerator.php** - Defense structures
- **MineralDepositGenerator.php** - Mineral assignment
- **MirrorUniverseGenerator.php** - Mirror gate (1 per galaxy)
- **PlanetarySystemGenerator.php** - Orbital hierarchies
- **PrecursorContentGenerator.php** - Ancient tech

**Support Generators:**
- **StarFieldGenerator.php** - Point generation
- **SectorGridGenerator.php** - Region overlay
- **WarpGate/IncrementalWarpGateGenerator.php** - Progressive gates
- **WarpGate/WarpGateGenerator.php** - Gate network

**Specialized Generators:**
- **Generators/StellarSystem/StarSystemGenerator.php** - Star systems
- **Generators/StellarSystem/PlanetTypeSelector.php** - Planet classification
- **Generators/StellarSystem/MoonGenerator.php** - Moon generation
- **Generators/Trading/TradingHubGenerator.php** - Trading hubs
- **Generators/Trading/MineralSourceMapper.php** - Mineral sources
- **Generators/Trading/ProximityPricingService.php** - Proximity-based pricing

**High-Level Wrappers:**
- **GalaxyCreationService.php** - Wrapper
- **TieredGalaxyCreationService.php** - Core + frontier
- **InhabitedSystemGenerator.php** - 40% distribution
- **CoreSystemGenerator.php** - Civilized core
- **OuterSystemGenerator.php** - Frontier region

**Support Classes:**
- **GalaxyGeneration/Data/GenerationConfig.php** - Config VO
- **GalaxyGeneration/Data/GenerationMetrics.php** - Metrics VO
- **GalaxyGeneration/Data/GenerationResult.php** - Result VO
- **GalaxyGeneration/Support/BulkInserter.php** - Batch inserts
- **GalaxyGeneration/Support/Profiler.php** - Performance profiling
- **GalaxyGeneration/Support/SpatialIndex.php** - O(1) neighbor lookups
- **GalaxyGeneration/Contracts/GeneratorInterface.php** - Generator interface

---

## Controllers Directory (/app/Http/Controllers/Api/)

### Travel & Navigation
- **TravelController.php** - Travel, warp gates, coordinate jumps
- **TravelCalculationController.php** - Pre-flight calcs (should be service)
- **NavigationController.php** - Navigation and pathfinding
- **LocationController.php** - Current location

### Galaxy Management
- **GalaxyController.php** - List, view, details galaxies
- **GalaxyCreationController.php** - Create galaxies (9 methods - largest)
- **GalaxySettingsController.php** - Galaxy config
- **SectorMapController.php** - Sector grid visualization
- **StarSystemController.php** - System details
- **MapSummaryController.php** - Summary data (1 method - minimal)

### Trading & Commerce
- **TradingController.php** - Trading hub operations
- **TradingTransactionController.php** - Transaction logs
- **PlansShopController.php** - Plan shop
- **ShipShopController.php** - Ship shop
- **CartographyController.php** - Star chart shop

### Combat
- **CombatController.php** - Combat operations
- **PvPCombatController.php** - Player vs player
- **TeamCombatController.php** - Team combat
- **ColonyCombatController.php** - Colony combat

### Ships & Equipment
- **ShipController.php** - Ship operations
- **ShipStatusController.php** - Ship stats
- **ShipServiceController.php** - Repair/upgrades
- **ShipyardController.php** - Shipyard
- **UpgradeController.php** - Ship upgrades

### Resources & Mining
- **MiningController.php** - Mining operations
- **SalvageYardController.php** - Salvage
- **ScanController.php** - System scanning

### Colonies
- **ColonyController.php** - Colony operations (8 methods)
- **ColonyBuildingController.php** - Colony buildings

### Player Management
- **PlayerController.php** - Player profile
- **PlayerStatusController.php** - Player status (2 methods - minimal)
- **PlayerSettingsController.php** - Settings (1 method - minimal)
- **PlayerKnowledgeMapController.php** - Discoveries

### Special Systems
- **MirrorUniverseController.php** - Mirror mechanics
- **PrecursorRumorController.php** - Precursor rumors
- **NotificationController.php** - Notifications
- **MarketEventController.php** - Market events
- **OrbitalStructureController.php** - Orbital structures
- **FacilitiesController.php** - Space facilities
- **PirateFactionController.php** - Pirate info
- **PoiTypeController.php** - POI types
- **LeaderboardController.php** - Rankings
- **VictoryController.php** - Victory progress

### Authentication
- **Auth/AuthController.php** - Login/register (Laravel Sanctum)

### Base & Traits
- **BaseApiController.php** - Parent class
- **Traits/FindsByUuid.php** - UUID lookup trait
- **Traits/ResolvesPlayer.php** - Get auth player
- **Traits/ResolvesShip.php** - Get active ship

### Builders (Response Helpers)
- **Builders/StarSystemResponseBuilder.php** - Build system responses
- **Builders/PoiCategorizer.php** - Classify POIs
- **Builders/SystemGenerationHandler.php** - Generate missing systems
- **Builders/SystemNameGenerator.php** - Generate names
- **Builders/BarNameGenerator.php** - Generate bar names
- **Builders/ParentStarResolver.php** - Find parent star

---

## Commands Directory (/app/Console/Commands/)

### Galaxy Management (11 commands)
- **GalaxyInitialize.php** - All-in-one galaxy setup
- **GalaxyGeneratePoints.php** - POI distribution
- **GalaxyGenerateGates.php** - Warp gate network
- **GalaxyGenerateSectors.php** - Sector grid
- **GalaxyDesignateInhabitedCommand.php** - Mark inhabited (40%, 33-50% range)
- **GalaxyDistributePirates.php** - Place pirates
- **GalaxyDistributePirateBands.php** - Alternative pirate placement
- **GalaxyCreateMirror.php** - Mirror gate (1 per galaxy)
- **GalaxyExpandCommand.php** - Add to existing galaxy
- **GalaxyFlushCommand.php** - Delete galaxy data
- **GalaxyViewCommand.php** - Terminal visualization

### Trading & Economy (4 commands)
- **TradingHubGenerateCommand.php** - Create hubs (50-80% of inhabited)
- **TradingHubPopulateInventory.php** - Stock hubs
- **CartographyGenerateShopsCommand.php** - Place cartographers (30% of hubs)
- **AssignMineralProductionCommand.php** - Assign minerals

### Player Management (3 commands)
- **InitializePlayerCommand.php** - Create player in galaxy
- **PlayerInterfaceCommand.php** - Console player interface
- **PlayerCommand.php** - Legacy player ops

### Game Loop (4 commands)
- **RegenerateFuelCommand.php** - Process fuel regen
- **ProcessMarketEventsCommand.php** - Market events
- **ProcessColonyCycles.php** - Colony cycles
- **PiratesMoveCommand.php** - Move pirates
- **KnowledgeHydrateCommand.php** - Update knowledge

### Ship Management (1 command)
- **UpgradeShipCommand.php** - Upgrade ship

### Benchmarking (4 commands, dev only)
- **BenchmarkGalaxyGeneration.php** - POI generation perf
- **BenchmarkGalaxyInitCommand.php** - Full init perf
- **BenchmarkLeaderboardCommand.php** - Leaderboard queries
- **BenchmarkNavigationCommand.php** - Pathfinding perf

### Utility (1 command)
- **ClassifyIceGiants.php** - Classify ice giants

---

## Shop Handlers Directory (/app/Console/Shops/)

Interactive console interfaces (ANSI TUI) for PlayerInterfaceCommand:

- **MineralTradingHandler.php** - Buy/sell minerals (2 methods)
- **ComponentShopHandler.php** - Buy upgrades (2 methods)
- **RepairShopHandler.php** - Repair hull (2 methods)
- **ShipShopHandler.php** - Buy/switch ships (2 methods)
- **PlansShopHandler.php** - Buy plans (2 methods)
- **PirateEncounterHandler.php** - Combat encounters (2 methods)

All use console traits for UI:
- **Traits/ConsoleBoxRenderer.php** - Draw boxes
- **Traits/ConsoleColorizer.php** - Apply colors
- **Traits/ModalDisplay.php** - Modal dialogs
- **Traits/TerminalInputHandler.php** - Keyboard input

Support service:
- **Console/Services/LocationValidator.php** - Validate locations

---

## Enums Directory (/app/Enums/)

### Root Level (4 enums)
- **ComponentType.php** - weapons, hull, sensors, warp_drive, shields, cargo
- **MarketEventType.php** - shortage, surplus, discovery, embargo, etc.
- **OrbitalStructureType.php** - refinery, shipyard, defense, research
- **RarityTier.php** - common, uncommon, rare, epic, legendary

### Galaxy (5 enums)
- **Galaxy/GalaxyStatus.php** - generating, active, dormant
- **Galaxy/GalaxySizeTier.php** - small, medium, large, massive
- **Galaxy/GalaxyDistributionMethod.php** - Generation method selection
- **Galaxy/GalaxyRandomEngine.php** - Random engine selection
- **Galaxy/RegionType.php** - core, frontier

### POI (3 enums)
- **PointsOfInterest/PointOfInterestType.php** - star, planet, nebula, anomaly, station, black_hole, asteroid_belt
- **PointsOfInterest/PointOfInterestStatus.php** - active, destroyed, hidden, charted
- **PointsOfInterest/StellarClassification.php** - O, B, A, F, G, K, M (astronomy)

### Specialized (3 enums)
- **Exploration/ScanLevel.php** - 1-5 scan depth levels
- **Exploration/KnowledgeLevel.php** - unknown, rumored, charted, mapped
- **Trading/MineralRarity.php** - common, uncommon, rare, exotic
- **Defense/SystemDefenseType.php** - shield, cannon, fighter_bay
- **WarpGate/GateType.php** - normal, hidden, dead_end, mirror, jackpot

---

## Generators Directory (/app/Generators/Points/)

Point distribution algorithms (pluggable via PointGeneratorFactory):

All implement `PointGeneratorInterface` with `sample(Galaxy): array` method.

**Base:**
- **AbstractPointGenerator.php** - Base class with utilities

**Implementations (9):**
1. **PoissonDisk.php** - Blue noise (high quality, min spacing)
2. **RandomScatter.php** - Pure random (fast, uneven)
3. **HaltonSequence.php** - Low-discrepancy quasi-random
4. **VogelsSpiral.php** - Sunflower spiral pattern
5. **StratifiedGrid.php** - Grid with jitter
6. **LatinHypercube.php** - Stratified sampling
7. **R2Sequence.php** - R2 low-discrepancy
8. **UniformRandom.php** - Baseline uniform random

Selected via `config/game_config.php` → `galaxy.generator`

---

## Traits Directory (/app/Traits/)

- **HasUuidAndVersion.php** - UUID + version fields (2 methods)
- **HasUuid.php** - UUID only (duplicate of above variant)

---

## ValueObjects Directory (/app/ValueObjects/)

- **GalaxyConfig.php** - Snapshot of game_config at galaxy creation (2 methods)

**Underutilized pattern. Could create:**
- Point(x, y)
- Distance(value, unit)
- Coordinate(x, y, z)
- Fuel(current, max)
- Cargo(items)
- etc.

---

## Faker Directory (/app/Faker/)

Procedural naming system using Faker library.

**Data Providers (lists):**
- **Common/GalaxyNames.php** - Galaxy name templates
- **Common/GalaxySuffixes.php** - Suffixes
- **Common/GalaxyVerbs.php** - Action verbs
- **Common/GreekLetters.php** - Greek letters (Alpha, Beta, etc.)
- **Common/MythologicalNames.php** - Mythological references
- **Common/RomanNumerals.php** - Roman numerals
- **Common/StarCatalog.php** - Catalog names

**Name Generators (use data):**
- **Providers/GalaxyNameProvider.php** - Galaxy names
- **Providers/StarNameProvider.php** - Star names
- **Providers/PlanetNameProvider.php** - Planet names
- **Providers/NebulaNameProvider.php** - Nebula names
- **Providers/BlackHoleNameProvider.php** - Black hole names
- **Providers/AnomalyNameProvider.php** - Anomaly descriptions

**Main Provider:**
- **SpaceProvider.php** - Registers all providers (0 methods)

---

## Quick Lookup by Feature

### Player Creation & Management
- InitializePlayerCommand.php
- PlayerController.php
- PlayerSpawnService.php
- PlayerKnowledgeService.php

### Travel & Navigation
- TravelService.php (fuel calc, XP)
- TravelController.php
- NavigationController.php
- StarChartService.php (charts, pricing)

### Trading
- TradingService.php
- TradingController.php
- TradingHubGenerator.php
- ProximityPricingService.php
- MineralSourceMapper.php

### Combat
- CombatResolutionService.php
- PirateEncounterService.php
- CombatController.php
- PvPCombatService.php

### Mining & Resources
- MiningService.php
- MiningController.php
- SalvageService.php
- SalvageYardService.php

### Galaxy Generation
- GalaxyGenerationOrchestrator.php (coordinator)
- StarFieldGenerator.php (POIs)
- SectorGridGenerator.php (grid)
- WarpGateNetworkGenerator.php (gates)
- TradingInfrastructureGenerator.php (hubs)
- GalaxyInitialize.php (command)

### Colonies
- ColonyCycleService.php (cycles)
- ColonyController.php (API)
- ColonyBuilding.php (buildings)

### Ships
- ShipUpgradeService.php
- ShipRepairService.php
- ShipPurchaseService.php
- PlayerShip.php (model)
- Ship.php (template)

### Knowledge & Scanning
- PlayerKnowledgeService.php
- SystemScanService.php
- StarChartService.php

### Mirror Universe
- MirrorUniverseService.php
- MirrorUniverseController.php
- MirrorUniverseGenerator.php

### Notifications
- NotificationService.php (16 methods - god service)
- NotificationController.php
- PlayerNotification.php (model)

### NPCs & Pirates
- NpcGenerationService.php
- PirateEncounterService.php
- PirateFleetGenerator.php
- PirateFaction.php
- PirateCaptain.php
- PirateBand.php

---

**Total Classes:** 295+
**Critical Files:** CODEBASE_MAP.md (full analysis)
**Quick Summary:** CODEBASE_ANALYSIS_SUMMARY.md

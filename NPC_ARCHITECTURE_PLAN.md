# NPC Architecture Implementation Plan
## Space Wars 3002 - AI-Driven NPC System

**Document Version**: 1.0
**Created**: 2026-01-14
**Status**: Planning Phase

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Implementation Phases](#implementation-phases)
3. [Detailed Architecture](#detailed-architecture)
4. [Implementation Order](#implementation-order)
5. [Database Schema Changes](#database-schema-changes)
6. [API Design](#api-design)
7. [Testing Strategy](#testing-strategy)
8. [Performance Considerations](#performance-considerations)

---

## Executive Summary

This document outlines a comprehensive architecture for implementing AI-driven NPCs in Space Wars 3002. The system is designed to be:

- **Deterministic**: NPC behavior can be reproduced and debugged
- **Fair**: NPCs follow the same rules as players
- **Scalable**: Can handle hundreds of NPCs without performance degradation
- **Debuggable**: Full traces and logs for all NPC decisions
- **Extensible**: Easy to add new NPC types and behaviors

### Key Principles

1. **Separation of Concerns**: Queries (read) vs Commands (write)
2. **Event Sourcing**: All state changes emit events
3. **Perception-Based**: NPCs only know what they've perceived
4. **Tool-Based**: NPCs interact through a limited set of capabilities
5. **Fail-Safe**: System degrades gracefully when AI fails

---

## Implementation Phases

### Phase 1: Foundation (Weeks 1-2)
**Goal**: Establish core infrastructure
**Deliverables**:
- Query/Command split
- ActionResult pattern with error codes
- Event logging system
- Basic NPC tick scheduler

### Phase 2: State Management (Weeks 3-4)
**Goal**: Define and manage game state for NPCs
**Deliverables**:
- GameSnapshot DTO
- Perception/Knowledge system
- Plan/Commit pattern for actions
- NPC database schema

### Phase 3: Tool Layer (Weeks 5-6)
**Goal**: Create NPC-safe interaction layer
**Deliverables**:
- Tool capability system
- Rate limiting and quotas
- Action traces
- Safe defaults

### Phase 4: Intelligence (Weeks 7-8)
**Goal**: Add AI decision-making
**Deliverables**:
- First AI-driven NPC (Trader archetype)
- Policy layer for rule-based decisions
- Personality system
- Skills and limits

### Phase 5: Scale & Polish (Weeks 9-10)
**Goal**: Production readiness
**Deliverables**:
- Bot harness for testing
- Exploit prevention
- Performance optimization
- Documentation

---

## Detailed Architecture

### 1. Query vs Command Split

**Purpose**: Separate read operations (queries) from state-changing operations (commands)

#### Query Services

```php
namespace App\Services\Queries;

interface QueryInterface
{
    public function execute(array $params): QueryResult;
}

class MarketQuery implements QueryInterface
{
    public function getSnapshot(int $sectorId, ?int $actorId = null): MarketSnapshot
    {
        // Pure read - no side effects
        // Filter by actor's perception if provided
    }

    public function getPriceHistory(int $mineralId, int $sectorId, int $days = 7): array
    {
        // Historical data for decision-making
    }
}

class NavigationQuery implements QueryInterface
{
    public function calculateRoute(int $fromPoiId, int $toPoiId, array $constraints): RouteCalculation
    {
        // Deterministic route calculation
        // Returns: distance, fuel cost, estimated time, risk factors
    }

    public function getVisiblePois(int $actorId): Collection
    {
        // Returns POIs based on sensor range and star charts
    }
}

class SectorQuery implements QueryInterface
{
    public function getSectorInfo(int $sectorId, ?int $actorId = null): SectorSnapshot
    {
        // Sector details visible to actor
    }

    public function detectThreats(int $sectorId, int $actorId): array
    {
        // Pirate fleets, hostile players based on sensor level
    }
}
```

#### Command Services

```php
namespace App\Services\Commands;

interface CommandInterface
{
    public function execute(array $params): ActionResult;
}

class TradeCommand implements CommandInterface
{
    public function buyCommodity(
        int $actorId,
        int $portId,
        int $mineralId,
        int $quantity
    ): ActionResult {
        // 1. Validate inputs
        // 2. Authorize actor
        // 3. Check invariants (funds, cargo space, availability)
        // 4. Apply state changes (atomic transaction)
        // 5. Emit events
        // 6. Return ActionResult
    }

    public function sellCommodity(
        int $actorId,
        int $portId,
        int $mineralId,
        int $quantity
    ): ActionResult {
        // Same pattern as buy
    }
}

class TravelCommand implements CommandInterface
{
    public function queueTravel(int $actorId, int $destinationPoiId): ActionResult
    {
        // Queue travel action
    }

    public function executeTravelTick(int $actorId): ActionResult
    {
        // Execute one step of travel
    }

    public function cancelTravel(int $actorId): ActionResult
    {
        // Cancel queued travel
    }
}
```

---

### 2. ActionResult Pattern

**Purpose**: Standardized response for all actions with comprehensive error handling

```php
namespace App\DataTransferObjects;

class ActionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $stateDelta = null,
        public readonly array $events = [],
        public readonly ?array $metadata = null
    ) {}

    public static function success(array $stateDelta = [], array $events = []): self
    {
        return new self(
            success: true,
            stateDelta: $stateDelta,
            events: $events
        );
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(
            success: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage
        );
    }
}
```

#### Error Codes Taxonomy

```php
namespace App\Enums;

enum ActionErrorCode: string
{
    // Resource constraints
    case INSUFFICIENT_FUEL = 'insufficient_fuel';
    case INSUFFICIENT_FUNDS = 'insufficient_funds';
    case CARGO_FULL = 'cargo_full';
    case CARGO_EMPTY = 'cargo_empty';

    // Authorization
    case UNAUTHORIZED = 'unauthorized';
    case FORBIDDEN = 'forbidden';
    case HOSTILE_FACTION = 'hostile_faction';

    // State violations
    case COOLDOWN_ACTIVE = 'cooldown_active';
    case ALREADY_DOCKED = 'already_docked';
    case NOT_DOCKED = 'not_docked';
    case IN_COMBAT = 'in_combat';

    // Market
    case PORT_CLOSED = 'port_closed';
    case COMMODITY_UNAVAILABLE = 'commodity_unavailable';
    case INSUFFICIENT_INVENTORY = 'insufficient_inventory';
    case ILLEGAL_GOODS_DETECTED = 'illegal_goods_detected';

    // Navigation
    case SECTOR_NOT_VISIBLE = 'sector_not_visible';
    case GATE_LOCKED = 'gate_locked';
    case DESTINATION_UNREACHABLE = 'destination_unreachable';
    case PATH_BLOCKED = 'path_blocked';

    // System
    case RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    case INVALID_INPUT = 'invalid_input';
    case CONCURRENT_MODIFICATION = 'concurrent_modification';
}
```

---

### 3. Event Log System

**Purpose**: Append-only log of all state changes for debugging, auditing, and potential training data

#### Database Schema

```php
// Migration: create_game_events_table
Schema::create('game_events', function (Blueprint $table) {
    $table->id();
    $table->uuid('event_id')->unique();
    $table->string('event_type')->index(); // TravelQueued, TradeExecuted, etc.
    $table->string('actor_type')->index(); // 'player', 'npc'
    $table->unsignedBigInteger('actor_id')->index();
    $table->unsignedBigInteger('tick')->index(); // Game tick number
    $table->timestamp('occurred_at')->index();
    $table->uuid('correlation_id')->nullable()->index(); // Link related events
    $table->json('payload'); // Event-specific data
    $table->json('snapshot_hash')->nullable(); // Hash of game state before event
    $table->timestamps();

    $table->index(['actor_type', 'actor_id', 'occurred_at']);
    $table->index(['event_type', 'occurred_at']);
});
```

#### Event Types

```php
namespace App\Events\Game;

abstract class GameEvent
{
    public string $eventId;
    public string $eventType;
    public string $actorType;
    public int $actorId;
    public int $tick;
    public Carbon $occurredAt;
    public ?string $correlationId;

    abstract public function toPayload(): array;
}

class TravelQueued extends GameEvent
{
    public function __construct(
        public int $actorId,
        public string $actorType,
        public int $fromPoiId,
        public int $toPoiId,
        public int $estimatedFuelCost,
        public int $estimatedTime,
        public ?string $correlationId = null
    ) {
        $this->eventId = Str::uuid();
        $this->eventType = 'TravelQueued';
        $this->tick = GameTick::current();
        $this->occurredAt = now();
    }

    public function toPayload(): array
    {
        return [
            'from_poi_id' => $this->fromPoiId,
            'to_poi_id' => $this->toPoiId,
            'estimated_fuel_cost' => $this->estimatedFuelCost,
            'estimated_time' => $this->estimatedTime,
        ];
    }
}

class TradeExecuted extends GameEvent
{
    public function __construct(
        public int $actorId,
        public string $actorType,
        public string $action, // 'buy' or 'sell'
        public int $portId,
        public int $mineralId,
        public int $quantity,
        public int $unitPrice,
        public int $totalCost,
        public ?string $correlationId = null
    ) {
        $this->eventId = Str::uuid();
        $this->eventType = 'TradeExecuted';
        $this->tick = GameTick::current();
        $this->occurredAt = now();
    }

    public function toPayload(): array
    {
        return [
            'action' => $this->action,
            'port_id' => $this->portId,
            'mineral_id' => $this->mineralId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'total_cost' => $this->totalCost,
        ];
    }
}

// Additional event types:
// - JumpExecuted
// - FuelConsumed
// - CargoUpdated
// - PirateEncountered
// - ScanCompleted
// - CombatInitiated
// - CombatResolved
// - DockRequested
// - DockGranted
// - DockDenied
// - MarketScanned
```

---

### 4. NPC Tick Scheduler

**Purpose**: Manage NPC execution with budgets to prevent runaway behavior

#### Database Schema

```php
// Migration: create_npcs_table
Schema::create('npcs', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->string('name')->index();
    $table->string('archetype')->index(); // trader, pirate, explorer, etc.
    $table->foreignId('faction_id')->nullable()->constrained('pirate_factions');
    $table->foreignId('galaxy_id')->constrained('galaxies');
    $table->foreignId('current_poi_id')->constrained('points_of_interest');
    $table->foreignId('active_ship_id')->nullable()->constrained('npc_ships');
    $table->decimal('credits', 20, 2)->default(0);
    $table->string('status')->default('active'); // active, docked, traveling, dead
    $table->unsignedBigInteger('last_tick')->default(0);
    $table->unsignedInteger('actions_this_tick')->default(0);
    $table->json('personality'); // risk_tolerance, greed, etc.
    $table->json('skills'); // trading_skill, combat_skill, etc.
    $table->timestamps();

    $table->index(['galaxy_id', 'status']);
    $table->index('last_tick');
});

// Migration: create_npc_ships_table
Schema::create('npc_ships', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
    $table->foreignId('ship_id')->constrained('ships'); // Blueprint
    $table->string('name');
    $table->integer('hull')->default(100);
    $table->integer('max_hull')->default(100);
    $table->integer('weapons')->default(1);
    $table->integer('warp_drive')->default(1);
    $table->integer('cargo_capacity')->default(100);
    $table->integer('sensors')->default(1);
    $table->integer('shields')->default(1);
    $table->integer('current_fuel')->default(100);
    $table->integer('max_fuel')->default(100);
    $table->timestamp('fuel_last_updated_at')->nullable();
    $table->timestamps();
});

// Migration: create_npc_knowledge_table
Schema::create('npc_knowledge', function (Blueprint $table) {
    $table->id();
    $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
    $table->foreignId('poi_id')->constrained('points_of_interest');
    $table->string('knowledge_type'); // 'scanned', 'visited', 'market_data', 'rumor'
    $table->json('data');
    $table->timestamp('discovered_at');
    $table->timestamp('expires_at')->nullable();
    $table->float('confidence')->default(1.0); // 0-1
    $table->timestamps();

    $table->unique(['npc_id', 'poi_id', 'knowledge_type']);
    $table->index(['npc_id', 'expires_at']);
});
```

#### Tick Scheduler

```php
namespace App\Services\NPC;

class NpcTickScheduler
{
    private const TICK_INTERVAL_SECONDS = 30;
    private const MAX_ACTIONS_PER_TICK = 5;
    private const MAX_TOOL_CALLS_PER_TICK = 10;
    private const MAX_CPU_MS_PER_NPC = 1000;

    public function processTick(): void
    {
        $currentTick = GameTick::increment();

        // Get all active NPCs that haven't processed this tick
        $npcs = Npc::where('status', 'active')
            ->where('last_tick', '<', $currentTick)
            ->get();

        foreach ($npcs as $npc) {
            try {
                $this->processNpcTick($npc, $currentTick);
            } catch (\Throwable $e) {
                Log::error('NPC tick failed', [
                    'npc_id' => $npc->id,
                    'tick' => $currentTick,
                    'error' => $e->getMessage(),
                ]);

                // Safe default: do nothing
                $npc->update([
                    'last_tick' => $currentTick,
                    'actions_this_tick' => 0,
                ]);
            }
        }
    }

    private function processNpcTick(Npc $npc, int $tick): void
    {
        $startTime = microtime(true);

        // Reset action counter
        $npc->update(['actions_this_tick' => 0]);

        // Get NPC decision maker
        $decisionMaker = app(NpcDecisionMaker::class);

        // Process NPC decisions with budget
        $actionsRemaining = self::MAX_ACTIONS_PER_TICK;
        $toolCallsRemaining = self::MAX_TOOL_CALLS_PER_TICK;

        while ($actionsRemaining > 0 && $toolCallsRemaining > 0) {
            // Check CPU budget
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            if ($elapsedMs > self::MAX_CPU_MS_PER_NPC) {
                Log::warning('NPC exceeded CPU budget', [
                    'npc_id' => $npc->id,
                    'elapsed_ms' => $elapsedMs,
                ]);
                break;
            }

            // Let NPC make a decision
            $result = $decisionMaker->decide($npc, $toolCallsRemaining);

            if (!$result->shouldContinue) {
                break;
            }

            $actionsRemaining--;
            $toolCallsRemaining -= $result->toolCallsUsed;

            $npc->increment('actions_this_tick');
        }

        // Mark tick complete
        $npc->update(['last_tick' => $tick]);
    }
}
```

---

### 5. GameSnapshot DTO

**Purpose**: Canonical, machine-readable representation of game state for NPC decision-making

```php
namespace App\DataTransferObjects;

class GameSnapshot
{
    public function __construct(
        // NPC Identity
        public readonly int $npcId,
        public readonly string $npcUuid,
        public readonly string $npcName,
        public readonly string $archetype,
        public readonly ?int $factionId,
        public readonly array $personality,
        public readonly array $skills,

        // Location
        public readonly int $currentPoiId,
        public readonly string $currentPoiName,
        public readonly string $currentPoiType,
        public readonly int $sectorId,
        public readonly string $sectorName,
        public readonly bool $isDocked,
        public readonly ?array $coordinates,

        // Ship
        public readonly int $shipId,
        public readonly string $shipName,
        public readonly int $hull,
        public readonly int $maxHull,
        public readonly int $weapons,
        public readonly int $warpDrive,
        public readonly int $cargoCapacity,
        public readonly int $cargoUsed,
        public readonly int $sensors,
        public readonly int $shields,
        public readonly int $currentFuel,
        public readonly int $maxFuel,
        public readonly array $damage,
        public readonly array $cooldowns,

        // Inventory
        public readonly array $cargo, // [{mineral_id, quantity, illegal}]
        public readonly int $cargoValue,

        // Money
        public readonly float $credits,
        public readonly float $debt,

        // Visibility
        public readonly int $sensorRange,
        public readonly array $knownSectors,
        public readonly array $marketDataTimestamps,
        public readonly array $visiblePois,

        // Constraints
        public readonly int $maxActionsPerTick,
        public readonly int $actionsRemainingThisTick,
        public readonly array $activeConstraints, // ["cannot_dock" => "hostile_sector"]

        // Context
        public readonly int $currentTick,
        public readonly Carbon $timestamp
    ) {}

    public static function forNpc(Npc $npc): self
    {
        $ship = $npc->activeShip;
        $cargo = $npc->cargo()->with('mineral')->get();
        $knownPois = $npc->knowledge()
            ->where('knowledge_type', 'scanned')
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->pluck('poi_id')
            ->toArray();

        return new self(
            npcId: $npc->id,
            npcUuid: $npc->uuid,
            npcName: $npc->name,
            archetype: $npc->archetype,
            factionId: $npc->faction_id,
            personality: $npc->personality,
            skills: $npc->skills,
            currentPoiId: $npc->current_poi_id,
            currentPoiName: $npc->currentPoi->name,
            currentPoiType: $npc->currentPoi->type,
            sectorId: $npc->currentPoi->sector_id,
            sectorName: $npc->currentPoi->sector->name,
            isDocked: $npc->status === 'docked',
            coordinates: ['x' => $npc->currentPoi->x, 'y' => $npc->currentPoi->y],
            shipId: $ship->id,
            shipName: $ship->name,
            hull: $ship->hull,
            maxHull: $ship->max_hull,
            weapons: $ship->weapons,
            warpDrive: $ship->warp_drive,
            cargoCapacity: $ship->cargo_capacity,
            cargoUsed: $cargo->sum('quantity'),
            sensors: $ship->sensors,
            shields: $ship->shields,
            currentFuel: $ship->current_fuel,
            maxFuel: $ship->max_fuel,
            damage: self::calculateDamage($ship),
            cooldowns: self::getCooldowns($npc),
            cargo: $cargo->map(fn($c) => [
                'mineral_id' => $c->mineral_id,
                'mineral_name' => $c->mineral->name,
                'quantity' => $c->quantity,
                'illegal' => $c->mineral->is_illegal ?? false,
            ])->toArray(),
            cargoValue: self::calculateCargoValue($cargo),
            credits: $npc->credits,
            debt: 0, // TODO: Implement debt system
            sensorRange: $ship->sensors * 100,
            knownSectors: array_unique(array_map(
                fn($poiId) => PointOfInterest::find($poiId)?->sector_id,
                $knownPois
            )),
            marketDataTimestamps: self::getMarketDataTimestamps($npc),
            visiblePois: $knownPois,
            maxActionsPerTick: 5,
            actionsRemainingThisTick: 5 - $npc->actions_this_tick,
            activeConstraints: self::getActiveConstraints($npc),
            currentTick: GameTick::current(),
            timestamp: now()
        );
    }

    private static function calculateDamage(NpcShip $ship): array
    {
        return [
            'hull_damage_percent' => round((1 - $ship->hull / $ship->max_hull) * 100, 2),
            'critical' => $ship->hull < ($ship->max_hull * 0.25),
        ];
    }

    private static function getCooldowns(Npc $npc): array
    {
        // TODO: Implement cooldown system
        return [];
    }

    private static function calculateCargoValue(Collection $cargo): float
    {
        return $cargo->sum(function($item) {
            return $item->quantity * ($item->mineral->base_price ?? 0);
        });
    }

    private static function getMarketDataTimestamps(Npc $npc): array
    {
        return $npc->knowledge()
            ->where('knowledge_type', 'market_data')
            ->get()
            ->mapWithKeys(fn($k) => [$k->poi_id => $k->discovered_at->timestamp])
            ->toArray();
    }

    private static function getActiveConstraints(Npc $npc): array
    {
        $constraints = [];

        // Check if in hostile territory
        $currentSector = $npc->currentPoi->sector;
        if ($currentSector->isHostileTo($npc->faction_id)) {
            $constraints['cannot_dock'] = 'hostile_sector';
        }

        // Check if has illegal cargo
        $hasIllegalCargo = $npc->cargo()
            ->whereHas('mineral', fn($q) => $q->where('is_illegal', true))
            ->exists();
        if ($hasIllegalCargo && $currentSector->law_level > 5) {
            $constraints['illegal_cargo'] = 'high_law_sector';
        }

        // Check fuel
        if ($npc->activeShip->current_fuel < ($npc->activeShip->max_fuel * 0.2)) {
            $constraints['low_fuel'] = 'must_refuel_soon';
        }

        return $constraints;
    }

    public function toArray(): array
    {
        return (array) $this;
    }

    public function hash(): string
    {
        return md5(json_encode($this->toArray()));
    }
}
```

---

### 6. Plan/Commit Pattern

**Purpose**: Allow inspection and validation before executing high-impact actions

```php
namespace App\Services\NPC;

class TradePlanService
{
    public function planTradeRun(
        Npc $npc,
        int $fromPortId,
        int $toPortId,
        int $mineralId
    ): TradePlan {
        // Calculate optimal quantity based on cargo space and funds
        $port = TradingHub::find($fromPortId);
        $mineral = Mineral::find($mineralId);
        $ship = $npc->activeShip;

        $maxByFunds = floor($npc->credits / $mineral->base_price);
        $maxByCargo = $ship->cargo_capacity - $ship->cargos()->sum('quantity');
        $maxByAvailability = $port->inventories()
            ->where('mineral_id', $mineralId)
            ->value('quantity') ?? 0;

        $quantity = min($maxByFunds, $maxByCargo, $maxByAvailability);

        // Calculate route
        $route = app(NavigationQuery::class)->calculateRoute(
            $fromPortId,
            $toPortId,
            ['fuel_limit' => $ship->current_fuel]
        );

        // Estimate profit
        $buyPrice = $mineral->base_price;
        $sellPort = TradingHub::find($toPortId);
        $sellPrice = $mineral->base_price * 1.2; // Simplified

        $profit = ($sellPrice - $buyPrice) * $quantity;
        $profitAfterFuel = $profit - ($route->fuelCost * 10); // Fuel cost estimate

        return new TradePlan(
            planId: Str::uuid(),
            npcId: $npc->id,
            fromPortId: $fromPortId,
            toPortId: $toPortId,
            mineralId: $mineralId,
            quantity: $quantity,
            buyPrice: $buyPrice,
            sellPrice: $sellPrice,
            estimatedProfit: $profitAfterFuel,
            route: $route,
            risks: $this->assessRisks($npc, $route),
            createdAt: now(),
            expiresAt: now()->addMinutes(5)
        );
    }

    public function commitTradePlan(string $planId): ActionResult
    {
        $plan = Cache::get("trade_plan:{$planId}");

        if (!$plan) {
            return ActionResult::failure(
                'PLAN_EXPIRED',
                'Trade plan has expired or does not exist'
            );
        }

        // Revalidate plan is still viable
        $npc = Npc::find($plan->npcId);
        if (!$this->isPlanStillViable($plan, $npc)) {
            return ActionResult::failure(
                'PLAN_INVALID',
                'Market conditions have changed, plan is no longer viable'
            );
        }

        // Execute buy
        $buyResult = app(TradeCommand::class)->buyCommodity(
            actorId: $npc->id,
            portId: $plan->fromPortId,
            mineralId: $plan->mineralId,
            quantity: $plan->quantity
        );

        if (!$buyResult->success) {
            return $buyResult;
        }

        // Queue travel
        $travelResult = app(TravelCommand::class)->queueTravel(
            actorId: $npc->id,
            destinationPoiId: $plan->toPortId
        );

        if (!$travelResult->success) {
            // Rollback buy? Or NPC is now stuck with cargo
            // For now, accept the cargo and mark NPC for manual intervention
        }

        return ActionResult::success([
            'trade_plan' => $plan,
            'buy_result' => $buyResult,
            'travel_result' => $travelResult,
        ]);
    }

    private function assessRisks(Npc $npc, RouteCalculation $route): array
    {
        $risks = [];

        // Pirate encounters
        $pirateRisk = $this->calculatePirateRisk($route->sectors);
        if ($pirateRisk > 0.3) {
            $risks[] = [
                'type' => 'pirate_encounter',
                'probability' => $pirateRisk,
                'severity' => 'high',
            ];
        }

        // Fuel shortage
        if ($route->fuelCost > $npc->activeShip->current_fuel * 0.9) {
            $risks[] = [
                'type' => 'insufficient_fuel',
                'probability' => 0.8,
                'severity' => 'critical',
            ];
        }

        return $risks;
    }
}
```

---

### 7. Tool Layer (NPC-Safe Capabilities)

**Purpose**: Limited, rate-limited set of capabilities NPCs can use

```php
namespace App\Services\NPC\Tools;

interface NpcToolInterface
{
    public function execute(Npc $npc, array $params): ToolResult;
    public function getRateLimit(): int; // Calls per tick
    public function getCost(): int; // CPU cost estimate
}

class GetNpcSnapshotTool implements NpcToolInterface
{
    public function execute(Npc $npc, array $params): ToolResult
    {
        $snapshot = GameSnapshot::forNpc($npc);

        return ToolResult::success($snapshot->toArray());
    }

    public function getRateLimit(): int { return 1; }
    public function getCost(): int { return 10; }
}

class GetVisibleMarketDataTool implements NpcToolInterface
{
    public function execute(Npc $npc, array $params): ToolResult
    {
        $poiId = $params['poi_id'] ?? null;

        if (!$poiId) {
            return ToolResult::failure('INVALID_INPUT', 'poi_id required');
        }

        // Check if NPC can see this POI
        $canSee = $npc->knowledge()
            ->where('poi_id', $poiId)
            ->where('knowledge_type', 'scanned')
            ->exists();

        if (!$canSee) {
            return ToolResult::failure('SECTOR_NOT_VISIBLE', 'POI not in sensor range');
        }

        // Get market data
        $marketData = app(MarketQuery::class)->getSnapshot($poiId, $npc->id);

        return ToolResult::success($marketData);
    }

    public function getRateLimit(): int { return 5; }
    public function getCost(): int { return 20; }
}

class PlanRouteToolimplements NpcToolInterface
{
    public function execute(Npc $npc, array $params): ToolResult
    {
        $fromPoiId = $params['from_poi_id'] ?? $npc->current_poi_id;
        $toPoiId = $params['to_poi_id'] ?? null;

        if (!$toPoiId) {
            return ToolResult::failure('INVALID_INPUT', 'to_poi_id required');
        }

        $route = app(NavigationQuery::class)->calculateRoute(
            $fromPoiId,
            $toPoiId,
            [
                'fuel_limit' => $npc->activeShip->current_fuel,
                'avoid_hostile' => true,
            ]
        );

        return ToolResult::success($route->toArray());
    }

    public function getRateLimit(): int { return 3; }
    public function getCost(): int { return 50; }
}

class PlanTradeRunTool implements NpcToolInterface
{
    public function execute(Npc $npc, array $params): ToolResult
    {
        $plan = app(TradePlanService::class)->planTradeRun(
            npc: $npc,
            fromPortId: $params['from_port_id'],
            toPortId: $params['to_port_id'],
            mineralId: $params['mineral_id']
        );

        // Cache plan for commit
        Cache::put("trade_plan:{$plan->planId}", $plan, now()->addMinutes(5));

        return ToolResult::success($plan->toArray());
    }

    public function getRateLimit(): int { return 2; }
    public function getCost(): int { return 100; }
}

class ExecuteTradeTool implements NpcToolInterface
{
    public function execute(Npc $npc, array $params): ToolResult
    {
        $planId = $params['plan_id'] ?? null;

        if (!$planId) {
            return ToolResult::failure('INVALID_INPUT', 'plan_id required');
        }

        $result = app(TradePlanService::class)->commitTradePlan($planId);

        return ToolResult::fromActionResult($result);
    }

    public function getRateLimit(): int { return 1; }
    public function getCost(): int { return 200; }
}
```

---

## Implementation Order

### Week 1: Foundation
- [ ] Create ActionResult DTO
- [ ] Define ActionErrorCode enum
- [ ] Create Query and Command base interfaces
- [ ] Implement basic event logging
- [ ] Set up game tick counter

### Week 2: Core Infrastructure
- [ ] Build MarketQuery service
- [ ] Build NavigationQuery service
- [ ] Build TradeCommand service
- [ ] Build TravelCommand service
- [ ] Implement event emission in commands

### Week 3: NPC Foundation
- [ ] Create NPC database tables
- [ ] Create NPC, NpcShip, NpcKnowledge models
- [ ] Implement GameSnapshot DTO
- [ ] Build NpcTickScheduler

### Week 4: Perception System
- [ ] Implement NPC knowledge base
- [ ] Create scan mechanics
- [ ] Add market data caching with timestamps
- [ ] Implement sensor range calculations

### Week 5: Planning System
- [ ] Create TradePlan DTO
- [ ] Implement TradePlanService
- [ ] Add plan caching and expiration
- [ ] Build risk assessment

### Week 6: Tool Layer
- [ ] Create NpcToolInterface
- [ ] Implement core tools (GetSnapshot, GetMarketData, PlanRoute, etc.)
- [ ] Add rate limiting per NPC
- [ ] Create ToolResult wrapper

### Week 7: AI Integration
- [ ] Set up Claude API integration (or local LLM)
- [ ] Create NpcDecisionMaker with one archetype (Trader)
- [ ] Implement tool calling in AI loop
- [ ] Add action trace logging

### Week 8: Safety & Polish
- [ ] Implement safe defaults for AI failures
- [ ] Add policy layer for rule-based decisions
- [ ] Create personality system
- [ ] Implement skills and limits

### Week 9: Testing & Tuning
- [ ] Build bot harness
- [ ] Run 100-NPC simulations
- [ ] Tune parameters (tick interval, budgets, costs)
- [ ] Fix exploit loops

### Week 10: Production
- [ ] Performance optimization
- [ ] Add monitoring and alerts
- [ ] Write documentation
- [ ] Deploy first NPCs to production

---

## Database Schema Changes Summary

### New Tables
1. `npcs` - NPC entities
2. `npc_ships` - NPC ship instances
3. `npc_knowledge` - NPC perception/knowledge base
4. `npc_cargo` - NPC inventory
5. `game_events` - Event log
6. `game_ticks` - Tick counter and metadata
7. `trade_plans` - Cached trade plans
8. `npc_action_traces` - Decision traces for debugging

### Modified Tables
1. `pirate_factions` - Add `galaxy_id` ✅ (Done)
2. `players` - Add `last_mirror_travel_at` (for cooldown)
3. `points_of_interest` - Add `sector_id` index

---

## API Design

### Query Endpoints (Read-Only)

```
GET /api/npc/queries/market/{poi_id}
GET /api/npc/queries/route?from={poi}&to={poi}
GET /api/npc/queries/sector/{sector_id}
GET /api/npc/queries/visible-pois/{npc_id}
```

### Command Endpoints (Write)

```
POST /api/npc/commands/trade/buy
POST /api/npc/commands/trade/sell
POST /api/npc/commands/travel/queue
POST /api/npc/commands/travel/cancel
POST /api/npc/commands/scan
POST /api/npc/commands/dock
```

### Planning Endpoints

```
POST /api/npc/plans/trade-run
POST /api/npc/plans/trade-run/{plan_id}/commit
GET /api/npc/plans/{plan_id}
DELETE /api/npc/plans/{plan_id}
```

### Tool Endpoints (For AI)

```
POST /api/npc/tools/execute
  Body: {
    "tool": "get_snapshot",
    "npc_id": 123,
    "params": {}
  }
```

---

## Testing Strategy

### Unit Tests
- ActionResult pattern
- GameSnapshot creation
- Query services (pure functions)
- Command services (with mocked events)
- Tool execution and rate limiting

### Integration Tests
- Full NPC decision loop
- Plan/commit workflow
- Event emission and storage
- Tick scheduler with multiple NPCs

### Bot Harness Tests
- 100 NPCs, 10,000 ticks
- Metrics: avg profit, bankruptcies, stuck rate, CPU per tick
- Exploit detection (oscillation, loops)

### Performance Tests
- 1000 NPCs per tick
- Query response times < 100ms
- Command execution < 200ms
- Event logging throughput

---

## Performance Considerations

### Database Indexes

```sql
-- game_events
CREATE INDEX idx_events_actor ON game_events(actor_type, actor_id, occurred_at);
CREATE INDEX idx_events_type_time ON game_events(event_type, occurred_at);
CREATE INDEX idx_events_correlation ON game_events(correlation_id);

-- npc_knowledge
CREATE INDEX idx_knowledge_npc_poi ON npc_knowledge(npc_id, poi_id);
CREATE INDEX idx_knowledge_expiry ON npc_knowledge(npc_id, expires_at);

-- npcs
CREATE INDEX idx_npcs_galaxy_status ON npcs(galaxy_id, status);
CREATE INDEX idx_npcs_tick ON npcs(last_tick);
```

### Caching Strategy

- **Trade plans**: 5-minute TTL in Redis
- **Market snapshots**: 1-minute TTL
- **Route calculations**: 5-minute TTL
- **NPC snapshots**: No cache (always fresh)

### Rate Limiting

- **Per NPC**: 10 tool calls per tick
- **Per galaxy**: 1000 NPC ticks per second
- **API endpoints**: 100 req/min per IP

### Optimization Techniques

1. **Batch processing**: Process NPCs in chunks of 10
2. **Lazy loading**: Only load relationships when needed
3. **Query optimization**: Use select() to limit columns
4. **Event batching**: Flush events every 100 records
5. **Concurrent processing**: Use Laravel queues for NPC ticks

---

## Next Steps

1. **Review and approve this plan** with stakeholders
2. **Create tickets** for each week's work
3. **Set up development environment** for NPC testing
4. **Begin Week 1 implementation**
5. **Schedule weekly reviews** to adjust course as needed

---

**End of Plan Document**

*This is a living document and will be updated as implementation progresses.*

# Space Wars 3002

_A modern re-imagining of the classic BBS door game **Trade Wars 2002** — built with Laravel, Vue 3, and a whole lot of nostalgia._

---

## Nostalgia

Before MMOs and always-on games, there were **BBS door games**: text adventures you dialed into with a modem. Among the most legendary was **Trade Wars 2002**, the space trading and conquest simulator that defined the early 1990s BBS era.

Players explored a galaxy of numbered sectors, traded ores across spaceports, upgraded ships, colonized planets, and fought rivals — all under a strict daily turn cap. That mix of capitalism, colonization, combat, and constraint made it unforgettable.

**Space Wars 3002** keeps the spirit alive while expanding it with procedurally generated galaxies, configurable rules, richer mechanics, and a proper REST API — the game we wish we had back then.

---

## Table of Contents

- [Installation](#installation)
- [Gameplay](#gameplay)
- [Trading](#trading)
- [Ships](#ships)
- [Planets & Habitation](#planets--habitation)
- [Tech Stack](#tech-stack)
- [Planned Features](#planned-features)

---

## Installation

### Requirements

- PHP 8.3+
- Composer
- Node.js & npm
- MySQL / MariaDB

### Setup

```bash
# Clone the repository
git clone https://github.com/youruser/space-wars-3002.git
cd space-wars-3002

# Install PHP dependencies
composer install

# Install frontend dependencies
npm install

# Environment configuration
cp .env.example .env
php artisan key:generate

# Configure your database credentials in .env, then:
php artisan migrate
php artisan db:seed --class=ShipTypesSeeder

# Start development servers
php artisan serve      # Backend on :8000
npm run dev            # Vite dev server for frontend
```

### Galaxy Setup

A galaxy needs to be generated before players can join. The all-in-one command handles everything:

```bash
# Generate a complete galaxy (stars, warp gates, trading hubs, pirates)
php artisan galaxy:initialize "Alpha Centauri" --width=300 --height=300 --stars=3000

# Or use size tiers for quick setup
# Small (500x500), Medium (1500x1500), Large (2500x2500), Massive (5000x5000)
```

Warp gate adjacency is auto-calculated from galaxy dimensions. Individual generation steps can be run separately or skipped with `--skip-gates`, `--skip-pirates`, `--skip-mirror`.

### Player Initialization

```bash
# Console-based player setup
php artisan player:initialize {galaxy_id} {user_id} --call-sign="PlayerName"

# Launch the console player interface
php artisan player:interface {player_id}
```

Players can also be created via the REST API (see API section below).

---

## Gameplay

Space Wars 3002 is a **turn-based** space trading and conquest game. Players spend a limited number of turns per day exploring, trading, mining, fighting, and colonizing their way to victory.

### Core Loop

1. **Explore** — Discover star systems, scan for resources, and chart unknown space.
2. **Trade** — Buy low, sell high across trading hubs with dynamic supply and demand.
3. **Mine** — Extract minerals from asteroid belts and mineral-rich planets.
4. **Fight** — Encounter pirates on warp lanes, engage in combat, and collect salvage.
5. **Colonize** — Settle habitable worlds and build an empire.

### Galaxy Structure

Galaxies are procedurally generated with two distinct regions:

| Region | Inhabited | Warp Gates | Mineral Richness | Description |
|--------|-----------|------------|------------------|-------------|
| **Core** | ~81% | Active, dense network | Standard | Civilized space with trading hubs, shipyards, and defenses |
| **Outer** | ~2% | Dormant (sensor 3+ to activate) | 2x multiplier | Frontier wilderness — rich deposits, no services, colonization targets |

Star systems are distributed using pluggable point generators (Poisson Disk, Vogel's Spiral, Halton Sequence, and five others) selected via configuration.

### Navigation

- **Warp Gates** — Fast and fuel-efficient travel between inhabited systems. Gates form the backbone of civilized space.
- **Coordinate Jumps** — Travel anywhere by coordinates. Costs 4x the fuel of gate travel, but lets you reach uninhabited systems that gates don't connect to.
- **Fuel** — Regenerates passively over time. Higher warp drive levels increase both efficiency and regen rate.

### Scanning & Fog of War

Ship sensors determine what you can see. There are 9 progressive scan levels — higher sensors reveal more detail:

| Level | Reveals |
|-------|---------|
| 1-2 | Geography, planet count, gate presence |
| 3-4 | Basic and rare mineral deposits |
| 5-6 | Hidden moons, anomalies, derelict ships |
| 7-8 | Deep subsurface scans, pirate hideouts |
| 9 | Precursor gates and ancient secrets |

Core systems start at scan level 3 (well-documented). Uncharted outer systems start at 0 (complete fog).

### Star Charts

Separate from scanning — charts tell you **where** things are, scanning tells you **what's there**. Purchased from Stellar Cartographer shops (~30% of trading hubs). New players receive 3 free charts to nearby inhabited systems.

### Victory Paths

Four distinct ways to win, each requiring a completely different play style:

| Path | Condition | Play Style |
|------|-----------|------------|
| **Merchant Empire** | Accumulate 1 billion credits | Trading and market manipulation |
| **Colonization** | Control >50% of galactic population | Colony management and expansion |
| **Conquest** | Control >60% of star systems | Military dominance |
| **Pirate King** | Seize >70% of outlaw network | Combat and piracy |

### Mirror Universe

An ultra-rare parallel dimension — one gate per galaxy, requires sensor level 5 to detect. Inside: 2x resource spawns, 1.5x trading prices, 3x rare mineral spawn rates. The catch: 2x pirate difficulty and a 24-hour cooldown before you can return.

---

## Trading

The economy is the beating heart of Space Wars 3002, inspired by the Drug Wars-style pricing that made Trade Wars 2002 so addictive.

### How It Works

Each trading hub maintains independent supply and demand levels for every mineral it stocks. Prices fluctuate based on:

- **Demand level** (20-80 range) — higher demand = higher prices
- **Supply level** (20-80 range) — higher supply = lower prices
- **Hub spread** — 8% margin on each side (16% round-trip cost at the same hub)

The profit is in **arbitrage**: buying where supply is high (cheap) and selling where demand is high (expensive) at a different hub.

### Market Spikes

8% of hub/mineral combinations experience Drug Wars-style spike events:

| Event | Price Effect | Opportunity |
|-------|-------------|-------------|
| **Surplus/Crash** | Price drops to 30-50% of normal | Buy cheap, haul to a normal-priced hub |
| **Shortage/Surge** | Price jumps to 200-400% of normal | Sell here for massive profit |

Spikes are generated per-hub when inventory is first populated and can shift through market events over time.

### Trading Statistics

| Metric | Value |
|--------|-------|
| Hub spread | 8% per side (16% round-trip) |
| Demand/supply range | 20-80 (0.70x to 1.30x multiplier) |
| Spike chance | 8% of hub/mineral combos |
| Crash multiplier | 0.30x - 0.50x |
| Surge multiplier | 2.00x - 4.00x |
| Trading hub coverage | 65% of inhabited systems |
| XP from buying | 1 XP per 10 units (min 5) |
| XP from selling | 1 XP per 100 credits revenue (min 10) |

### Tutorial Trades

A player's very first mineral buy and first mineral sell are **free** — zero credits spent, zero credits earned. The full flow runs (cargo moves, XP is awarded, transaction is logged) so new players can learn the mechanics without consequences. All subsequent trades operate normally.

### Mineral Rarity

| Rarity | Hub Stock Range | Examples |
|--------|----------------|---------|
| Common | 5,000 - 15,000 | Iron, basic ores |
| Uncommon | 2,000 - 8,000 | Titanium, Deuterium |
| Rare | 500 - 3,000 | Dilithium |
| Very Rare | 100 - 1,000 | Exotic compounds |
| Legendary | 10 - 200 | Quantum crystals |

---

## Ships

New players start with **no ship** and 10,000 credits. Your first stop is the shipyard at your spawn location, where a free Sparrow-class starter ship is waiting. From there, you earn credits through trading, mining, and combat to work your way up the ship ladder.

### Ship Classes

| Class | Ship Name | Price | Cargo | Speed | Hull | Shields | Level Req | Role |
|-------|-----------|-------|-------|-------|------|---------|-----------|------|
| **Starter** | Sparrow-class Light Freighter | Free | 50 | 100 | 80 | 40 | -- | Balanced entry ship |
| **Fighter** | Viper-class Fighter | 18,000 | 20 | 180 | 100 | 80 | -- | Fast combat, 20% evasion |
| **Smuggler** | Wraith-class Runner | 35,000 | 40 (+80 hidden) | 140 | 90 | 70 | 5 | Hidden cargo, stealth |
| **Explorer** | Nomad-class Explorer | 45,000 | 100 | 120 | 90 | 70 | 6 | Long-range, sensors 5, warp 3 |
| **Mining** | Prospector-class Mining Vessel | 55,000 | 200 | 80 | 150 | 80 | 7 | 40% mining bonus, ore processing |
| **Battleship** | Leviathan-class Dreadnought | 150,000 | 80 | 70 | 350 | 280 | 12 | 8 weapon slots, 25% combat bonus |
| **Cargo** | Titan-class Supertanker | 200,000 | 5,000 | 40 | 200 | 100 | 10 | 100x starter cargo, slow |
| **Carrier** | Sovereign-class Command Ship | 250,000 | 150 | 60 | 400 | 300 | 18 | 12 fighter bays |
| **Colony Ship** | Exodus-class Ark | 500,000 | 500 | 50 | 250 | 150 | 15 | Carries 10,000 colonists |
| **Precursor** | Void Strider | _Found_ | Infinite | 10,000 | 1,000,000 | 1,000,000 | _Hidden_ | 1 per galaxy, must be discovered |

### Components & Upgrades

Ships have modular slot systems for customization:

- **Weapon Slots** — Increase combat damage
- **Engine Slots** — Improve speed and fuel efficiency
- **Reactor Slots** — Power more systems
- **Hull Plating Slots** — Boost effective max hull
- **Shield Slots** — Increase shield capacity
- **Sensor Slots** — See farther and reveal more detail
- **Cargo Module Slots** — Expand effective cargo hold
- **Utility Slots** — Special-purpose equipment

Components come in 6 rarity tiers that affect stats and pricing:

| Rarity | Stat Multiplier | Price Multiplier | Drop Weight |
|--------|----------------|-----------------|-------------|
| Common | 1.0x | 1.0x | 60 |
| Uncommon | 1.1x | 1.5x | 30 |
| Rare | 1.25x | 3.0x | 5 |
| Epic | 1.5x | 6.0x | 3 |
| Unique | 1.8x | 12.0x | 2 |
| Exotic | 2.2x | 30.0x | 1 |

Components are bought at Salvage Yards and installed into available slots. Effects stack with base ship stats — `getEffectiveCargoHold()`, `getEffectiveMaxHull()`, etc. account for all installed bonuses.

### Fuel System

Fuel regenerates passively over time. Better warp drives mean faster regen and more efficient travel:

- **Fuel cost**: `ceil(distance / efficiency)` where efficiency = `1 + (warp_drive - 1) * 0.2`
- **Regen rate**: Scales with warp drive level and installed fuel components
- **Coordinate jumps**: 4x fuel cost vs. gate travel

### Shipyard & Salvage Yard

- **Shipyards** exist at inhabited trading hubs. Inventory is lazily generated on first visit with rarity-tiered unique ships. Each ship is pre-rolled with stat jitter for uniqueness.
- **Salvage Yards** buy whole ships for 35% of value and sell components (50% of value). Browse components, buy & install in one transaction.

### The Precursor Ship

The **Void Strider** is a legendary Precursor vessel — one hidden in each galaxy. It cannot be purchased. Finding it requires sensor level 12+, being within 10 coordinate units, and using a coordinate jump. Rumors from NPCs at trading hubs can help narrow down its location, but all coordinates given are intentionally wrong by 50+ units. Triangulate from multiple rumors to find the truth.

---

## Planets & Habitation

> **Note:** The colony and habitation system is currently in early development. The models, database schema, and core service logic exist, but the full gameplay loop for planetary management is not yet complete. What follows describes the design and what has been partially implemented.

### Habitation Zones

- **Inhabited Systems** (~40% of stars, configurable 33-50%) — Civilized space with trading hubs, shipyards, repair docks, and warp gate connections. Distributed with minimum spacing (50 units) to prevent clustering.
- **Uninhabited Systems** (the rest) — No services, no warp gates. Must be reached by coordinate jump. Rich in minerals (1.5x deposit multiplier). Prime targets for colonization.

### Colonies (Partially Implemented)

Colonies require an **Exodus-class Ark** loaded with 10,000 colonists and a habitable planet. Once established:

- **Buildings**: Housing, Mines, Factories, Research Labs
- **Growth**: Population grows at a configurable rate per cycle
- **Production**: Passive income from mining, manufacturing, and trade
- **Defenses**: Orbital structures protect against raids

### Orbital Structures (Schema Ready)

Player-buildable structures that orbit planets and moons:

- **Defense Platforms** — Orbital weapons to deter attackers
- **Magnetic Mines** — Hidden space mines with sensor-based detection (30% base, +10% per sensor level)
- **Mining Platforms** — Passive mineral extraction (50 units/cycle base, 500 storage)
- **Orbital Bases** — Command centers for fleet operations

Construction is progressive (10% per cycle) with upgrades to level 5.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3, Laravel 12, Laravel Sanctum |
| Frontend | Vue 3, Inertia.js, Vite ([separate repo](https://github.com/mschandr/space-wars-3002-fe/)) |
| Database | MySQL / MariaDB |
| Auth | Laravel Sanctum (token-based) |
| Config | `config/game_config.php` (snapshotted per galaxy) |
| Testing | PHPUnit, Laravel feature + unit tests |
| Linting | Laravel Pint |
| Static Analysis | PHPStan |

### API

The game exposes a full REST API (50+ endpoints) covering authentication, galaxy management, player actions, trading, combat, navigation, scanning, shipyards, salvage yards, colonies, market events, and leaderboards. All responses follow a consistent `{ success, message, data }` format. Auth via Bearer tokens (Laravel Sanctum).

### Configuration

All game balance lives in `config/game_config.php` — a single source of truth for galaxy generation, trading economy, ship stats, victory conditions, scanning levels, and more. Each galaxy snapshots this config at creation time, so different galaxies can run under different rules.

---

## Planned Features

- **Full Colony Gameplay Loop** — Complete the planetary management system with building construction, population management, resource production chains, and inter-colony trade routes.
- **Species Asymmetry** — Humans (balanced), Lizards (warlike, combat bonuses), Hive Mind (expansionist, colony bonuses). Each species with unique ships, abilities, and victory modifiers.
- **Frontend UI** — The Vue 3 + Inertia.js frontend lives in a [separate repository](https://github.com/mschandr/space-wars-3002-fe/). Work continues on the browser UI: ship management, star map, trading interface, colony dashboard.
- **NPC & Pirate AI** — Adaptive pirate factions that respond to player behavior. Trade convoys, pirate raids on colonies, and NPC merchants that affect market prices.
- **Fleet Operations** — Multi-ship fleet management. Assign escort ships to cargo haulers, coordinate carrier fighter deployments, and organize colony defense fleets.
- **Diplomacy & Alliances** — Player-to-player trade agreements, non-aggression pacts, and alliance-based victory conditions.
- **Event System** — Galaxy-wide events (solar storms, trade embargoes, pirate uprisings, mineral discoveries) that dynamically shift the game state.
- **Job Board** — Accept missions and contracts from trading hubs or other players. NPC jobs include cargo deliveries, bounty hunts, and exploration surveys. Players can also post jobs for grunt work that's not worth their time — hauling cargo between systems, scouting routes, delivering supplies to colonies. Rewards scale with difficulty and distance.
- **Ship Roster & Personnel** — Hire crew members for your ship. Engineers, gunners, navigators, and medics each provide passive bonuses and unlock new capabilities. Manage your roster, pay salaries, and build a crew that complements your play style.
- **Leaderboard & Seasons** — Seasonal galaxy resets with persistent pilot rankings and achievement tracking across games.

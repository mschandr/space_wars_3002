# Space Wars 3002 🚀

_A modern re-imagining of the classic BBS door game **Trade Wars 2002** — built in PHP (Laravel) with a touch of Vue for visualization._

---

## Nostalgia

Before MMOs and always-online games, there were **BBS door games**: text adventures you dialed into with a modem. Among the most legendary was **Trade Wars 2002**, the space trading and conquest simulator that defined the early 1990s BBS era.

Players explored a galaxy of numbered sectors, traded ores across spaceports, upgraded ships, colonized planets, and fought rivals — all under a strict daily turn cap. That mix of capitalism, colonization, combat, and constraint made it unforgettable.

**Space Wars 3002** is our homage: keeping the spirit of trading, piracy, and galactic conquest alive, while expanding it with procedural galaxies, configurable rules, and richer mechanics — the game we wish we had back then.

---

## Game Overview

- **Turn-based play** — players act within a fixed number of daily turns.
- **Exploration** — discover a procedurally generated galaxy of star systems and warp lanes.
- **Trading** — arbitrage ores across markets with dynamic supply/demand.
- **Colonies** — settle planets, build defenses, and manage populations.
- **Conflict** — fight pirates, NPCs, and rivals across configurable victory paths.
- **Win Conditions:**
  - **Colonization** — sustain >50% of the galactic population.
  - **Merchant Empire** — amass credits or dominate ore trade.
  - **Conquest** — capture the majority of star systems.
  - **Pirate King** — take over the outlaw network.

---

## Current Progress

- **Schema & Models:**
  - Galaxies, star systems, celestial bodies (generalized: stars, planets, belts, comets, nebulae, black holes), warps, markets, ores.

- **Galaxy Generator:**
  - Artisan command builds galaxies from `config/game_config.php` and snapshots configs.
  - Points of Interest distributed via pluggable generators.

- **Point Distribution Methods:**
  - Implemented **Poisson disk sampling** (`PoissonDisk`).
  - Output visualized with `GalaxyDebug.vue` + `GalaxyMap.vue`.
  - Identified Poisson quirks (origin bias, thinning near Y-axis).
  - Planned alternatives: **RandomScatter** and **HaltonSequence** (low-discrepancy).

- **Architecture:**
  - `PointGeneratorInterface` with `sample()` method.
  - `PoissonDisk`, `RandomScatter`, `HaltonSequence` implement the interface.
  - `PointGeneratorFactory` selects via config (reflection).
  - `DensityGuard` enforces `MAX_DENSITY = 0.65` (fail-fast if overpopulated).

- **Testing:**
  - Unit tests: count, bounds, min spacing, deterministic seeds.
  - Feature test: collision-free generation, density guard blocks impossible configs.

---

## Planned Features

- **Procedural galaxies** (~3000 systems on 300×300 grid, 2–4 warp links per system).
- **Species asymmetry** (Humans = balanced, Lizards = warlike, Hive Mind = expansionist).
- **20+ ores** with origin biases + fluctuating market prices.
- **Ships & upgrades** (Scout → Carrier; engines, weapons, defenses, cargo upgrades).
- **Colonies & orbital defenses** (phase 2).
- **Backend monitoring** for live galaxies, maps, and NPC/pirate AI.

---

## Tech Stack

- **Backend:** PHP 8.x, Laravel 10/11
- **Frontend:** Vue (debug + UI), Inertia planned
- **Database:** MariaDB/MySQL
- **Config:** `config/game_config.php` (snapshotted per galaxy)

---

## Installation

```bash
git clone https://github.com/youruser/space-wars-3002.git
cd space-wars-3002

composer install
cp .env.example .env
php artisan key:generate

# configure DB in .env
php artisan migrate
```

---

Roadmap

 - [ ] Implement `RandomScatter` + `HaltonSequence` point generators.
 - [ ] Wire all generators into `PointGeneratorFactory` (config-driven).
 - [ ] Expand visualization tools for debugging galaxy maps.
 - [ ] Add colonies, defenses, and population scaling.
 - [ ] Introduce pirates/NPCs with adaptive AI.
 - [ ] Full frontend UI (beyond debug tools).

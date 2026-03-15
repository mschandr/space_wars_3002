# Space Wars 3002

A modern, API-first re-imagining of the classic BBS space trading and conquest formula. The project blends deterministic simulation, procedural galaxy generation, dynamic economy systems, and AI-assisted content generation.

## What This Project Is

Space Wars 3002 is not just a nostalgia remake. It is a larger simulation platform built around:

- procedural galaxy generation
- turn-based exploration and travel
- dynamic trading and economic shocks
- ship progression, services, and combat
- colonies, factions, contracts, and flotillas
- vendor personality and dialogue systems
- AI-assisted offline flavour generation for runtime-safe dialogue

## Current State

The backend is substantially ahead of the player-facing gameplay loop.

### Strongly implemented

- galaxy generation and initialization pipeline
- navigation and travel APIs
- core economy and commodity pricing systems
- ship services and world-data APIs
- authentication, players, notifications, and leaderboard foundations
- flotilla mechanics
- job board contract system
- large documentation surface for implemented and planned systems

### Partially implemented or still maturing

- combat depth and flotilla combat edge cases
- pirate faction reputation depth
- vendor dialogue runtime integration
- NPC trader behavioural loops
- colony depth and long-tail management mechanics
- some front-end/API integration work

### Still needed for a cleaner playable MVP

- tighter documentation structure
- one canonical checklist for shipped vs pending features
- clearer contributor entry points
- consolidated architecture docs for runtime flow, simulation, dialogue, and content generation

## Architecture Overview

The system currently reads best as three cooperating subsystems:

1. **Simulation Engine**  
   Resolves mechanics, state changes, pricing, travel, combat outcomes, inventory, and world rules.

2. **Dialogue Engine**  
   Selects context-appropriate flavour lines and combines them with runtime facts.

3. **Content Generator**  
   Generates dialogue pools offline so runtime interactions remain deterministic, fast, and safe.

### Runtime interaction pattern

```text
player action
→ simulation engine resolves mechanics
→ dialogue engine selects flavour line
→ runtime builder attaches factual data
→ response returned to client
```

This design avoids runtime hallucination, latency spikes, and brittle dialogue trees.

## Repository Structure

```text
app/                    Laravel application code
app/Console/Commands/   World generation, economy, navigation, maintenance commands
app/Http/Controllers/   API surface
app/Models/             Core domain models
app/Services/           Business logic and subsystems
config/                 Laravel and game configuration
database/               Migrations, factories, seeders
docs/                   In-repo documentation (currently mixed depth)
routes/                 API and web routes
tests/                  Unit, feature, and performance tests
```

## Documentation

The external docs repository is currently richer than the in-repo docs. It should become the canonical long-form documentation set.

### Proposed documentation entry points

- `docs/architecture/system-overview.md`
- `docs/architecture/runtime-flow.md`
- `docs/architecture/simulation-engine.md`
- `docs/architecture/dialogue-engine.md`
- `docs/architecture/content-generation.md`
- `docs/development/checklist.md`
- `docs/development/roadmap.md`

## Recommended Documentation Reorganization

```text
docs/
  README.md
  architecture/
    system-overview.md
    runtime-flow.md
    simulation-engine.md
    dialogue-engine.md
    content-generation.md
  systems/
    economy.md
    trading.md
    navigation.md
    ships.md
    combat.md
    colonies.md
    vendors.md
    factions.md
    flotillas.md
    contracts.md
  gameplay/
    player-loop.md
    inspections.md
    negotiation.md
    exploration.md
  development/
    checklist.md
    roadmap.md
    technical-debt.md
  api/
    ...existing endpoint docs...
  archive/
    ...older notes and superseded docs...
```

## Development Status

See the proposed canonical checklist:

- `docs/development/checklist.md`

## Getting Started

### Requirements

- PHP 8.3+
- Composer
- Node.js / npm
- MySQL or MariaDB

### Basic setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### Initialize a galaxy

```bash
php artisan galaxy:initialize "Alpha Centauri" --width=300 --height=300 --stars=3000
```

### Initialize a player

```bash
php artisan player:initialize {galaxy_id} {user_id} --call-sign="PlayerName"
```

## Contributor Notes

The project currently has strong simulation depth and broad API coverage. The next leverage point is not blindly adding features; it is making the system easier to understand:

- consolidate docs
- establish one canonical status checklist
- reduce duplicate documentation
- make runtime architecture obvious to future contributors

## License

See `LICENSE.md`.

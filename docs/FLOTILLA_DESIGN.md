# Flotilla System Design Spec

> **Status**: Future Feature — Document Only
> **Version**: 1.0
> **Date**: 2026-02-21

## Overview

A **flotilla** is a temporary formation of 2–4 ships owned by the same player that move and operate together. Unlike a fleet (all ships a player owns), a flotilla is a deliberate grouping with mechanical implications for movement, combat, and cargo management.

---

## Database Schema

### New Table: `flotillas`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint | PK, auto-increment |
| `uuid` | uuid | unique |
| `player_id` | FK → players | not null |
| `name` | string | nullable |
| `flagship_ship_id` | FK → player_ships | not null |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Modified Table: `player_ships`

| Column | Type | Constraints |
|--------|------|-------------|
| `flotilla_id` | FK → flotillas | nullable, new column |

No pivot table needed. A ship belongs to at most one flotilla. Simple one-to-many.

---

## Configuration

```php
// config/game_config.php → 'flotilla'
'max_ships' => 4,
'fuel_penalty_per_ship' => 0.10,  // +10% per additional ship
'form_turn_cost' => 1,
'cargo_recovery_rate' => 0.70,    // 70% cargo recoverable on win
'pirate_loot_recovery_rate' => 0.50, // random % of pirate cargo
```

---

## Movement

- **Slowest ship sets the pace**: minimum warp drive in the flotilla determines speed.
- Each ship burns fuel independently, calculated per ship's own `warp_drive`.
- **Fuel penalty**: +10% per additional ship (convoy overhead).
  - 2 ships = 1.1× fuel cost
  - 3 ships = 1.2× fuel cost
  - 4 ships = 1.3× fuel cost
- If **any** ship lacks fuel, the whole flotilla cannot move — must refuel or remove that ship.
- All ships move atomically to the same destination.

---

## Cargo Model: Per-Ship with Salvage

**Cargo stays on its ship at all times.** No shared pool.

- `player_cargos.player_ship_id` already enforces this — no schema changes needed.
- When a ship leaves a flotilla, its cargo goes with it.
- On dissolution: cargo stays on each ship. Default overflow rule — ship with most available space absorbs any orphaned cargo.

---

## Combat: Escort Pattern

### Attack Phase

All ships fire together. Total damage = sum of all ships' weapons (random variance per ship). Targets weakest pirate (existing behavior).

### Defense Phase

Pirates target the ship with the **lowest hull** first (pick off the weak). Creates a natural escort dynamic — combat ships absorb hits while cargo ships carry goods.

### Ship Destruction Mid-Combat

Weapons removed from combined total, combat continues with remaining ships. If flagship destroyed, next largest ship becomes flagship.

---

## Salvage: Per-Battle XOR Choice

When a battle is **won**, the player makes **one** choice for the entire engagement:

### Option A — Recover CARGO from your wrecks

- 70% of destroyed ships' cargo is recoverable
- Distributed to surviving ships by available hold space
- If insufficient hold space, excess is lost

### Option B — Recover COMPONENTS from your wrecks

- Escalating efficiency loss per component recovered:
  - 1st component: 10% loss
  - 2nd component: 20% loss
  - 3rd component: 30% loss
  - ...and so on
- Example: A level 10 weapon recovered first → effective level 9
- Example: A level 8 shield recovered second → effective level ~6.4

**Cannot choose both.** Time pressure in the wreckage — grab cargo or grab parts.

### Pirate Loot (Always, On Win)

- Random percentage of whatever pirates were carrying (loot table roll)
- Pirate components recoverable at 50%+ loss rate — battle-damaged, incompatible tech
- This is separate from the cargo/component XOR choice above

---

## Loss Conditions

### Flee

ALL cargo on destroyed ships is lost. You left the wreckage behind. Surviving ships keep their cargo.

### Full Wipe (All Ships Destroyed)

Normal `PlayerDeathService` flow — respawn, minimum credits, plans detached, flotilla entity deleted.

---

## Balance Constraints

1. **Size cap**: Max 4 ships per flotilla (configurable)
2. **Fuel penalty**: +10% per extra ship (2 ships = 1.1×, 3 ships = 1.2×, 4 ships = 1.3×)
3. **Speed penalty**: Slowest ship governs the whole flotilla
4. **Location requirement**: All ships must be at the same POI to form a flotilla
5. **Flagship gates actions**: Only the flagship can trade, mine, dock. Other ships are escorts. To trade with a specific ship, make it flagship or remove it from the flotilla.
6. **Pirate smart targeting**: Pirates target the ship with highest cargo value, not the toughest ship. Rewards escort composition.

---

## API Endpoints

```
POST   /api/players/{uuid}/flotilla                — Create flotilla (designate flagship)
GET    /api/players/{uuid}/flotilla                — Get flotilla status
POST   /api/players/{uuid}/flotilla/add-ship       — Add ship (must be at same POI)
POST   /api/players/{uuid}/flotilla/remove-ship    — Remove ship
POST   /api/players/{uuid}/flotilla/set-flagship   — Change flagship
DELETE /api/players/{uuid}/flotilla                — Dissolve flotilla
```

---

## Design Decisions

### Multi-player flotillas?

**No.** Too complex for any version right now.

### NPC escort hire?

**Yes, V1+.** Hire mercs at trading hubs, but mercs have trustability ratings. Low-trust mercs might lead you into pirate traps. High sensor levels let you detect untrustworthy mercs before hiring. Adds a risk/reward layer to the bar recruitment system.

### Mining with flotillas?

**Only the mining ship mines.** A flotilla is an escort/transport formation, not a mining operation. Escorts protect the mining ship but don't participate in extraction. Mined minerals go into the mining ship's hold only.

A future "transfer cargo" action (move minerals between ships while docked at the same POI) is a separate feature — not tied to flotillas or mining specifically.

### Colony ships in flotillas?

**Yes, V1+.** Flotillas will be necessary to escort colony ships (too vulnerable solo). Not implementing colony ships yet.

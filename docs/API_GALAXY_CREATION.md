# Galaxy Creation API Documentation

This document describes the API endpoints for creating galaxies and managing NPC players.

## Overview

The Galaxy Creation API allows you to:
- Create fully-playable galaxies via a single API call
- Choose between multiplayer, single-player (with NPCs), or mixed game modes
- Add NPC players to existing galaxies
- Manage and query NPC players

## Authentication

All endpoints require authentication via Laravel Sanctum. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

---

## Endpoints

### 1. Create Galaxy

Creates a complete, playable galaxy with all necessary components.

**Endpoint:** `POST /api/galaxies/create`

**Request Body:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `name` | string | No | Auto-generated | Galaxy name (max 100 chars) |
| `width` | integer | Yes | - | Galaxy width (100-2000) |
| `height` | integer | Yes | - | Galaxy height (100-2000) |
| `stars` | integer | Yes | - | Number of stars (50-10000) |
| `grid_size` | integer | No | 10 | Sector grid size (5-50) |
| `game_mode` | string | Yes | - | One of: `multiplayer`, `single_player`, `mixed` |
| `npc_count` | integer | No | 0 (5 for single_player) | Number of NPCs to generate (0-100) |
| `npc_difficulty` | string | No | `medium` | One of: `easy`, `medium`, `hard`, `expert` |
| `skip_mirror` | boolean | No | false | Skip mirror universe creation |
| `skip_pirates` | boolean | No | false | Skip pirate distribution |
| `skip_precursors` | boolean | No | false | Skip precursor ship spawning |

**Game Modes:**
- `multiplayer` - Public galaxy for real players only. No NPCs allowed.
- `single_player` - Private galaxy owned by the creating user. NPCs required (defaults to 5 if not specified).
- `mixed` - Public galaxy that supports both real players and NPCs.

**Example Request:**

```json
{
  "name": "Alpha Centauri",
  "width": 300,
  "height": 300,
  "stars": 1000,
  "grid_size": 10,
  "game_mode": "single_player",
  "npc_count": 10,
  "npc_difficulty": "medium"
}
```

**Example Response:**

```json
{
  "success": true,
  "data": {
    "success": true,
    "galaxy": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Centauri",
      "width": 300,
      "height": 300,
      "game_mode": "single_player",
      "status": "active"
    },
    "mirror_galaxy": {
      "id": 2,
      "uuid": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Alpha Centauri (Mirror)"
    },
    "npcs": [
      {
        "uuid": "660e8400-e29b-41d4-a716-446655440000",
        "call_sign": "Captain Nova",
        "archetype": "trader",
        "difficulty": "medium"
      }
    ],
    "statistics": {
      "stars": {
        "total": 1000,
        "inhabited": 400,
        "uninhabited": 600
      },
      "points_of_interest": 1500,
      "sectors": 100,
      "warp_gates": 850,
      "trading_hubs": 260,
      "pirate_encounters": 125,
      "market_events": 4,
      "npcs": 10
    },
    "steps": [
      {"step": 1, "name": "Creating Galaxy", "status": "completed"},
      {"step": 2, "name": "Verifying Prerequisites", "status": "completed"}
    ],
    "execution_time_seconds": 45.23
  },
  "message": "Galaxy created successfully",
  "meta": {
    "timestamp": "2026-01-18T12:00:00+00:00",
    "request_id": "abc123"
  }
}
```

**Error Responses:**

| Code | Error Code | Description |
|------|------------|-------------|
| 422 | VALIDATION_ERROR | Invalid request parameters |
| 500 | GALAXY_CREATION_FAILED | Galaxy creation failed (see message for details) |

---

### 2. Add NPCs to Galaxy

Add NPC players to an existing galaxy.

**Endpoint:** `POST /api/galaxies/{uuid}/npcs`

**URL Parameters:**
- `uuid` - Galaxy UUID

**Request Body:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `count` | integer | Yes | - | Number of NPCs to create (1-50) |
| `difficulty` | string | No | `medium` | Difficulty level |
| `archetype_distribution` | object | No | null | Custom archetype weights |

**Archetype Distribution:**

```json
{
  "archetype_distribution": {
    "trader": 35,
    "merchant": 20,
    "explorer": 15,
    "miner": 20,
    "pirate_hunter": 10
  }
}
```

**Example Request:**

```json
{
  "count": 5,
  "difficulty": "hard",
  "archetype_distribution": {
    "trader": 50,
    "pirate_hunter": 50
  }
}
```

**Example Response:**

```json
{
  "success": true,
  "data": {
    "success": true,
    "npcs_created": 5,
    "npcs": [
      {
        "uuid": "770e8400-e29b-41d4-a716-446655440000",
        "call_sign": "Trader Alpha",
        "archetype": "trader",
        "difficulty": "hard",
        "credits": 22500.00,
        "location": "Proxima Station"
      }
    ],
    "statistics": {
      "total": 15,
      "by_archetype": {"trader": 8, "pirate_hunter": 7},
      "by_difficulty": {"medium": 10, "hard": 5},
      "average_credits": 18500.50
    }
  },
  "message": "Successfully created 5 NPCs"
}
```

**Error Responses:**

| Code | Error Code | Description |
|------|------------|-------------|
| 404 | NOT_FOUND | Galaxy not found |
| 422 | NPC_NOT_ALLOWED | Galaxy game_mode doesn't allow NPCs |
| 403 | FORBIDDEN | User doesn't own this single_player galaxy |

---

### 3. List NPCs in Galaxy

Get all NPCs in a galaxy with optional filtering.

**Endpoint:** `GET /api/galaxies/{uuid}/npcs`

**URL Parameters:**
- `uuid` - Galaxy UUID

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `archetype` | string | Filter by archetype |
| `status` | string | Filter by status (active, inactive, destroyed) |
| `difficulty` | string | Filter by difficulty |

**Example Request:**

```
GET /api/galaxies/550e8400.../npcs?archetype=trader&difficulty=medium
```

**Example Response:**

```json
{
  "success": true,
  "data": {
    "npcs": [
      {
        "uuid": "770e8400-e29b-41d4-a716-446655440000",
        "call_sign": "Captain Nova",
        "archetype": "trader",
        "difficulty": "medium",
        "level": 3,
        "credits": 45000.00,
        "status": "active",
        "current_activity": "trading",
        "location": {
          "id": 123,
          "name": "Proxima Station",
          "x": 150.5,
          "y": 200.3
        },
        "ship": {
          "uuid": "880e8400...",
          "name": "Captain Nova's Freighter",
          "class": "freighter",
          "hull": 100,
          "max_hull": 100
        }
      }
    ],
    "total": 1,
    "statistics": {
      "total": 10,
      "by_archetype": {"trader": 4, "explorer": 3, "miner": 3},
      "average_credits": 32500.00
    }
  }
}
```

---

### 4. Get NPC Details

Get detailed information about a specific NPC.

**Endpoint:** `GET /api/npcs/{uuid}`

**URL Parameters:**
- `uuid` - NPC UUID

**Example Response:**

```json
{
  "success": true,
  "data": {
    "uuid": "770e8400-e29b-41d4-a716-446655440000",
    "call_sign": "Captain Nova",
    "archetype": "trader",
    "archetype_description": "Focus on profitable trade routes",
    "difficulty": "medium",
    "level": 5,
    "experience": 2400,
    "credits": 75000.00,
    "status": "active",
    "current_activity": "trading",
    "personality": {
      "aggression": 0.12,
      "risk_tolerance": 0.28,
      "trade_focus": 0.92
    },
    "combat_stats": {
      "ships_destroyed": 2,
      "combats_won": 5,
      "combats_lost": 1
    },
    "economy_stats": {
      "total_trade_volume": 250000.00
    },
    "galaxy": {
      "id": 1,
      "uuid": "550e8400...",
      "name": "Alpha Centauri"
    },
    "location": {
      "id": 123,
      "name": "Proxima Station",
      "x": 150.5,
      "y": 200.3,
      "is_inhabited": true
    },
    "ship": {
      "uuid": "880e8400...",
      "name": "Captain Nova's Freighter",
      "class": "freighter",
      "status": "operational",
      "stats": {
        "hull": 100,
        "max_hull": 100,
        "weapons": 15,
        "cargo_hold": 200,
        "current_cargo": 75,
        "sensors": 2,
        "warp_drive": 2,
        "current_fuel": 85,
        "max_fuel": 150
      },
      "cargo": [
        {"mineral": "Iron Ore", "quantity": 50},
        {"mineral": "Copper", "quantity": 25}
      ]
    },
    "last_action_at": "2026-01-18T11:45:00+00:00"
  }
}
```

---

### 5. Delete NPC

Delete an NPC from a galaxy.

**Endpoint:** `DELETE /api/npcs/{uuid}`

**URL Parameters:**
- `uuid` - NPC UUID

**Example Response:**

```json
{
  "success": true,
  "data": {
    "deleted": "Captain Nova"
  },
  "message": "NPC 'Captain Nova' has been deleted"
}
```

---

### 6. Get NPC Archetypes

Get available NPC archetypes and difficulty configurations.

**Endpoint:** `GET /api/npcs/archetypes`

**Example Response:**

```json
{
  "success": true,
  "data": {
    "archetypes": [
      {
        "name": "trader",
        "description": "Focus on profitable trade routes",
        "default_aggression": 0.1,
        "default_risk_tolerance": 0.3,
        "default_trade_focus": 0.9
      },
      {
        "name": "explorer",
        "description": "Seek out uncharted systems",
        "default_aggression": 0.2,
        "default_risk_tolerance": 0.7,
        "default_trade_focus": 0.4
      },
      {
        "name": "pirate_hunter",
        "description": "Hunt down pirates for bounties",
        "default_aggression": 0.8,
        "default_risk_tolerance": 0.6,
        "default_trade_focus": 0.2
      },
      {
        "name": "miner",
        "description": "Extract and sell minerals",
        "default_aggression": 0.1,
        "default_risk_tolerance": 0.4,
        "default_trade_focus": 0.6
      },
      {
        "name": "merchant",
        "description": "Wealthy trade magnate",
        "default_aggression": 0.05,
        "default_risk_tolerance": 0.2,
        "default_trade_focus": 0.95
      }
    ],
    "difficulties": [
      {
        "name": "easy",
        "credits_multiplier": 0.5,
        "combat_skill_multiplier": 0.6,
        "decision_quality": 0.5
      },
      {
        "name": "medium",
        "credits_multiplier": 1.0,
        "combat_skill_multiplier": 1.0,
        "decision_quality": 0.75
      },
      {
        "name": "hard",
        "credits_multiplier": 1.5,
        "combat_skill_multiplier": 1.3,
        "decision_quality": 0.9
      },
      {
        "name": "expert",
        "credits_multiplier": 2.0,
        "combat_skill_multiplier": 1.6,
        "decision_quality": 1.0
      }
    ]
  }
}
```

---

## Quick Start Examples

### Create a Single-Player Galaxy with NPCs

```bash
curl -X POST https://your-api.com/api/galaxies/create \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "width": 300,
    "height": 300,
    "stars": 1000,
    "game_mode": "single_player",
    "npc_count": 10,
    "npc_difficulty": "medium"
  }'
```

### Create a Multiplayer Galaxy (No NPCs)

```bash
curl -X POST https://your-api.com/api/galaxies/create \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Competitive Arena",
    "width": 500,
    "height": 500,
    "stars": 2000,
    "game_mode": "multiplayer"
  }'
```

### Create a Mixed Galaxy (Players + NPCs)

```bash
curl -X POST https://your-api.com/api/galaxies/create \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Open Universe",
    "width": 400,
    "height": 400,
    "stars": 1500,
    "game_mode": "mixed",
    "npc_count": 20,
    "npc_difficulty": "hard"
  }'
```

### Add More NPCs Later

```bash
curl -X POST https://your-api.com/api/galaxies/{galaxy_uuid}/npcs \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "count": 5,
    "difficulty": "expert",
    "archetype_distribution": {
      "pirate_hunter": 100
    }
  }'
```

---

## NPC Archetypes Explained

| Archetype | Description | Behavior |
|-----------|-------------|----------|
| `trader` | Focus on profitable trade routes | Low aggression, medium risk, high trade focus |
| `merchant` | Wealthy trade magnate | Very low aggression, low risk, very high trade focus |
| `explorer` | Seek out uncharted systems | Low aggression, high risk tolerance, medium trade focus |
| `miner` | Extract and sell minerals | Low aggression, medium risk, medium trade focus |
| `pirate_hunter` | Hunt down pirates for bounties | High aggression, medium risk, low trade focus |

## Difficulty Levels

| Difficulty | Credits Multiplier | Combat Skill | Description |
|------------|-------------------|--------------|-------------|
| `easy` | 0.5x | 0.6x | For new players |
| `medium` | 1.0x | 1.0x | Balanced challenge |
| `hard` | 1.5x | 1.3x | Experienced players |
| `expert` | 2.0x | 1.6x | Maximum challenge |

---

## Workflow: Setting Up a New Game

### Single-Player Game Flow

1. **Create Galaxy** - `POST /api/galaxies/create` with `game_mode: "single_player"`
2. **Create Player** - `POST /api/players` with the returned `galaxy.id`
3. **Start Playing** - Use existing game APIs for travel, trading, combat

### Multiplayer Game Flow

1. **Create Galaxy** - `POST /api/galaxies/create` with `game_mode: "multiplayer"`
2. **Share Galaxy UUID** - Give other players the galaxy UUID
3. **Each Player Joins** - `POST /api/players` with the galaxy ID
4. **Start Playing** - Use existing game APIs

### Mixed Game Flow

1. **Create Galaxy** - `POST /api/galaxies/create` with `game_mode: "mixed"`
2. **NPCs populate the galaxy** - Specified via `npc_count`
3. **Real Players Join** - `POST /api/players` with the galaxy ID
4. **Add More NPCs** - `POST /api/galaxies/{uuid}/npcs` as needed

# Galaxy Management API Documentation

This document provides comprehensive documentation for all Galaxy Management endpoints in Space Wars 3002.

## Table of Contents

- [Response Format](#response-format)
- [Galaxy List & Discovery](#galaxy-list--discovery)
  - [List User's Galaxies](#list-users-galaxies)
  - [List User's Galaxies (Cached)](#list-users-galaxies-cached)
- [Galaxy Details](#galaxy-details)
  - [Get Galaxy Details](#get-galaxy-details)
  - [Get Galaxy Map](#get-galaxy-map)
  - [Get Galaxy Statistics](#get-galaxy-statistics)
- [Sector Information](#sector-information)
  - [Get Sector Details](#get-sector-details)
  - [Get Sector Map](#get-sector-map)
- [Galaxy Creation](#galaxy-creation)
  - [Create Galaxy](#create-galaxy)
  - [Get Size Tiers](#get-size-tiers)
  - [Get Creation Status](#get-creation-status)
- [Galaxy Settings](#galaxy-settings)
  - [Update Galaxy Settings](#update-galaxy-settings)
- [Player Membership](#player-membership)
  - [Get My Player](#get-my-player)
  - [Join Galaxy](#join-galaxy)
- [NPC Management](#npc-management)
  - [Add NPCs to Galaxy](#add-npcs-to-galaxy)
  - [List NPCs in Galaxy](#list-npcs-in-galaxy)
  - [Get NPC Details](#get-npc-details)
  - [Delete NPC](#delete-npc)
  - [Get Archetype List](#get-archetype-list)
- [Map Data](#map-data)
  - [Get Map Summaries](#get-map-summaries)

---

## Response Format

All API responses follow a standardized format with success/error wrapper.

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "message": "Description of operation",
  "meta": {
    "timestamp": "2026-02-16T12:34:56Z",
    "request_id": "uuid-v4"
  }
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": { ... }
  },
  "meta": {
    "timestamp": "2026-02-16T12:34:56Z",
    "request_id": "uuid-v4"
  }
}
```

### Validation Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "field_name": ["Error message 1", "Error message 2"]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T12:34:56Z",
    "request_id": "uuid-v4"
  }
}
```

---

## Galaxy List & Discovery

### List User's Galaxies

Retrieves galaxies for the authenticated user, divided into games they're part of and open games they can join.

**Endpoint:** `GET /api/galaxies`

**Authentication:** Required (Sanctum token)

**Parameters:** None

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "my_games": [
      {
        "uuid": "string",
        "name": "string",
        "width": "integer",
        "height": "integer",
        "status": "string (creating|active|completed|archived)",
        "game_mode": "string (single_player|multiplayer|mixed)",
        "size_tier": "string (small|medium|large|massive)",
        "max_players": "integer|null",
        "player_count": "integer"
      }
    ],
    "open_games": [
      {
        "uuid": "string",
        "name": "string",
        "width": "integer",
        "height": "integer",
        "status": "string",
        "game_mode": "string",
        "size_tier": "string",
        "max_players": "integer|null",
        "player_count": "integer"
      }
    ]
  },
  "message": "Galaxies retrieved successfully"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `my_games` | array | Galaxies where user has a player OR is the owner, ordered by last access (descending) |
| `open_games` | array | Active galaxies accepting new players (not at capacity, not single-player), ordered by player count (ascending) |
| `uuid` | string | Galaxy unique identifier (UUID v4) |
| `name` | string | Galaxy display name |
| `width` | integer | Galaxy width in coordinate units |
| `height` | integer | Galaxy height in coordinate units |
| `status` | string | Galaxy status: `creating`, `active`, `completed`, `archived` |
| `game_mode` | string | Game mode: `single_player`, `multiplayer`, `mixed` |
| `size_tier` | string | Size tier: `small`, `medium`, `large`, `massive` |
| `max_players` | integer\|null | Maximum player capacity (default: 100 if null) |
| `player_count` | integer | Number of active players in galaxy |

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |

**Notes:**

- Mirror universe galaxies are excluded from both lists (accessed via warp gates, not direct selection)
- `my_games` includes galaxies where user is owner even if they don't have a player
- `open_games` filters out galaxies at capacity and single-player games (unless user is owner)
- Default max players is 100 if galaxy doesn't specify a limit
- Ordering: `my_games` by `last_accessed_at DESC`, `open_games` by `player_count ASC`

---

### List User's Galaxies (Cached)

Same as above but with 5-minute caching for open games list (faster for game selection UI).

**Endpoint:** `GET /api/galaxies/list`

**Authentication:** Required (Sanctum token)

**Parameters:** None

**Response Structure:** Same as `GET /api/galaxies`

**Caching:**

- `my_games`: Real-time query (not cached)
- `open_games`: Cached for 300 seconds (5 minutes)
- Cache key: `galaxies:open_games`

**Error Responses:** Same as `GET /api/galaxies`

**Notes:**

- Use this endpoint for game selection UIs where slight staleness is acceptable
- User's galaxies are always fresh (no caching)
- Open games list may show slightly outdated player counts

---

## Galaxy Details

### Get Galaxy Details

Retrieves full hydrated details for a specific galaxy (public endpoint).

**Endpoint:** `GET /api/galaxies/{uuid}`

**Authentication:** Not required (public)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "uuid": "string",
    "name": "string",
    "width": "integer",
    "height": "integer",
    "status": "string",
    "game_mode": "string",
    "size_tier": "string",
    "max_players": "integer|null",
    "is_public": "boolean",
    "description": "string|null",
    "total_players": "integer",
    "active_player_count": "integer",
    "total_systems": "integer",
    "sectors": "integer",
    "warp_gates": "integer",
    "trading_hubs": "integer",
    "owner": {
      "id": "integer",
      "name": "string"
    },
    "players": [
      {
        "uuid": "string",
        "call_sign": "string",
        "level": "integer",
        "status": "string"
      }
    ]
  },
  "message": "Galaxy details retrieved successfully"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `total_players` | integer | Total players (all statuses) |
| `active_player_count` | integer | Active players only |
| `total_systems` | integer | Count of all POIs (stars, planets, etc.) |
| `sectors` | integer | Number of sectors in galaxy |
| `warp_gates` | integer | Number of warp gate connections |
| `trading_hubs` | integer | Number of active trading hubs |
| `owner` | object\|null | Galaxy owner information (if exists) |
| `players` | array | First 20 active players (preview) |

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Galaxy not found |

---

### Get Galaxy Map

Retrieves optimized map data for rendering (systems, warp gates, sectors).

**Endpoint:** `GET /api/galaxies/{uuid}/map`

**Authentication:** Not required (public, but visibility depends on authentication)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "galaxy": {
      "uuid": "string",
      "name": "string",
      "width": "integer",
      "height": "integer"
    },
    "systems": [
      {
        "uuid": "string",
        "name": "string",
        "type": "string",
        "x": "float",
        "y": "float",
        "is_inhabited": "boolean",
        "is_current_location": "boolean",
        "scan": {
          "level": "integer",
          "label": "string",
          "color": "string",
          "opacity": "float"
        }
      }
    ],
    "warp_gates": [
      {
        "uuid": "string",
        "from": {
          "x": "float",
          "y": "float"
        },
        "to": {
          "x": "float",
          "y": "float"
        },
        "is_mirror": "boolean"
      }
    ],
    "sectors": [
      {
        "uuid": "string",
        "name": "string",
        "x_min": "float",
        "x_max": "float",
        "y_min": "float",
        "y_max": "float",
        "danger_level": "integer"
      }
    ],
    "player_location": {
      "x": "float",
      "y": "float"
    }
  },
  "message": "Galaxy map retrieved successfully"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `systems` | array | Visible systems (based on star charts if authenticated) |
| `is_current_location` | boolean | True if this is the player's current location |
| `scan.level` | integer | Scan level (0-5, determines visibility detail) |
| `scan.label` | string | Human-readable scan level (e.g., "Unknown", "Basic", "Detailed") |
| `scan.color` | string | CSS color for rendering scan level |
| `scan.opacity` | float | Opacity value for rendering (0.0-1.0) |
| `warp_gates` | array | Visible warp gate connections (excludes hidden gates) |
| `is_mirror` | boolean | True if gate leads to mirror universe |
| `sectors` | array | All sectors in galaxy |
| `player_location` | object\|null | Player's current coordinates (if authenticated) |

**Visibility Rules:**

- **Unauthenticated:** Only inhabited systems visible
- **Authenticated with star charts:** Only charted systems visible
- **Authenticated without charts:** All inhabited systems visible
- **Warp gates:** Only non-hidden gates shown

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Galaxy not found |

---

### Get Galaxy Statistics

Retrieves comprehensive galaxy-wide statistics (public endpoint).

**Endpoint:** `GET /api/galaxies/{uuid}/statistics`

**Authentication:** Not required (public)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "galaxy": {
      "uuid": "string",
      "name": "string",
      "dimensions": {
        "width": "integer",
        "height": "integer"
      },
      "total_systems": "integer",
      "inhabited_systems": "integer"
    },
    "players": {
      "total": "integer",
      "active": "integer",
      "destroyed": "integer"
    },
    "economy": {
      "total_credits_in_circulation": "float",
      "average_player_credits": "float",
      "trading_hubs": "integer"
    },
    "colonies": {
      "total": "integer",
      "total_population": "integer",
      "average_development": "float"
    },
    "combat": {
      "total_pvp_challenges": "integer",
      "completed_battles": "integer"
    },
    "infrastructure": {
      "warp_gates": "integer",
      "sectors": "integer",
      "pirate_fleets": "integer"
    }
  },
  "message": "Galaxy statistics retrieved successfully"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `total_systems` | integer | Total POIs in galaxy |
| `inhabited_systems` | integer | Systems with civilization (33-50% of stars) |
| `total_credits_in_circulation` | float | Sum of all player credits |
| `average_player_credits` | float | Mean credits per player |
| `total_population` | integer | Sum of all colony populations |
| `average_development` | float | Mean colony development level |
| `total_pvp_challenges` | integer | All PvP challenge records |
| `completed_battles` | integer | Finished combat sessions |
| `pirate_fleets` | integer | Active pirate fleets on warp lanes |

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Galaxy not found |

---

## Sector Information

### Get Sector Details

Retrieves detailed information about a specific sector.

**Endpoint:** `GET /api/sectors/{uuid}`

**Authentication:** Not required (public)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Sector UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "uuid": "string",
    "name": "string",
    "galaxy": {
      "uuid": "string",
      "name": "string"
    },
    "bounds": {
      "x_min": "float",
      "x_max": "float",
      "y_min": "float",
      "y_max": "float"
    },
    "danger_level": "integer",
    "statistics": {
      "total_systems": "integer",
      "inhabited_systems": "integer",
      "active_players": "integer",
      "pirate_fleets": "integer"
    },
    "systems": [
      {
        "uuid": "string",
        "name": "string",
        "type": "string",
        "x": "float",
        "y": "float",
        "is_inhabited": "boolean"
      }
    ]
  },
  "message": "Sector information retrieved successfully"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `bounds` | object | Sector boundary coordinates |
| `danger_level` | integer | Sector danger rating (affects pirate encounters) |
| `total_systems` | integer | Number of systems in this sector |
| `inhabited_systems` | integer | Number of civilized systems |
| `active_players` | integer | Players currently in this sector |
| `pirate_fleets` | integer | Pirate fleets on warp lanes in this sector |
| `systems` | array | All POIs within sector boundaries |

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Sector not found |

---

### Get Sector Map

Retrieves lightweight sector-grid map with aggregate stats (optimized for large galaxies).

**Endpoint:** `GET /api/galaxies/{uuid}/sector-map`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "galaxy": {
      "uuid": "string",
      "name": "string",
      "width": "integer",
      "height": "integer"
    },
    "grid_size": {
      "cols": "integer",
      "rows": "integer"
    },
    "sectors": [
      {
        "uuid": "string",
        "name": "string",
        "grid_x": "integer",
        "grid_y": "integer",
        "bounds": {
          "x_min": "float",
          "x_max": "float",
          "y_min": "float",
          "y_max": "float"
        },
        "danger_level": "integer",
        "total_systems": "integer",
        "inhabited_systems": "integer",
        "has_trading": "boolean",
        "player_count": "integer"
      }
    ],
    "player_sector_uuid": "string|null",
    "player_location": {
      "x": "integer",
      "y": "integer"
    }
  },
  "message": "Sector map retrieved successfully"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `grid_size` | object | Sector grid dimensions (cols × rows) |
| `grid_x` | integer | Sector column in grid (0-indexed) |
| `grid_y` | integer | Sector row in grid (0-indexed) |
| `has_trading` | boolean | True if sector contains at least one active trading hub |
| `player_count` | integer | Active players in this sector |
| `player_sector_uuid` | string\|null | UUID of sector containing player's current location |

**Performance:**

- Reduces 3000-star galaxy from 30k+ lines to ~400 sector entries
- Uses single round-trip with correlated subqueries
- Optimized for map overview rendering

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 404 | NOT_FOUND | Galaxy not found |
| 404 | NO_PLAYER_IN_GALAXY | User has no player in this galaxy |

---

## Galaxy Creation

### Create Galaxy

Creates a new galaxy using the optimized generation pipeline.

**Endpoint:** `POST /api/galaxies/create`

**Authentication:** Required (Sanctum token)

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | No | Galaxy name (unique, 2-100 chars). Auto-generated if omitted |
| `size_tier` | string | Yes | Size tier: `small`, `medium`, `large`, `massive` |
| `game_mode` | string | No | Game mode: `single_player`, `multiplayer`, `mixed` (default: `single_player`) |
| `description` | string | No | Galaxy description (max 500 chars) |
| `is_public` | boolean | No | Public visibility (default: false) |
| `max_players` | integer | No | Max player capacity (10-1000, default: 100) |

**Size Tier Specifications:**

| Size Tier | Dimensions | Stars | Grid Size | NPCs | Difficulty |
|-----------|------------|-------|-----------|------|------------|
| `small` | 500×500 | ~500 | 10×10 | 5 | easy |
| `medium` | 1500×1500 | ~1500 | 15×15 | 10 | medium |
| `large` | 2500×2500 | ~2500 | 20×20 | 15 | hard |
| `massive` | 5000×5000 | ~5000 | 25×25 | 25 | expert |

**Request Body Example:**

```json
{
  "name": "Nova Sector",
  "size_tier": "medium",
  "game_mode": "multiplayer",
  "description": "A balanced galaxy for competitive play",
  "is_public": true,
  "max_players": 150
}
```

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "galaxy": {
      "uuid": "string",
      "name": "string",
      "width": "integer",
      "height": "integer",
      "status": "string",
      "size_tier": "string",
      "game_mode": "string"
    },
    "statistics": {
      "total_systems": "integer",
      "inhabited_systems": "integer",
      "sectors": "integer",
      "warp_gates": "integer",
      "trading_hubs": "integer"
    },
    "config": {
      "core_region": {
        "center_x": "float",
        "center_y": "float",
        "radius": "float",
        "inhabited_percentage": "integer"
      },
      "outer_region": {
        "inhabited_percentage": "integer",
        "mineral_richness_multiplier": "float"
      }
    }
  },
  "message": "Galaxy created successfully using optimized pipeline"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `statistics` | object | Generation statistics (counts) |
| `config.core_region` | object | Civilized core configuration (100% inhabited) |
| `config.outer_region` | object | Frontier region configuration (0% inhabited, 2× minerals) |
| `mineral_richness_multiplier` | float | Mineral spawn rate multiplier for outer region |

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 400 | NPC_CONFIG_DISABLED | NPC parameters provided (NPCs auto-configured by size tier) |
| 422 | MISSING_SIZE_TIER | size_tier parameter missing |
| 422 | VALIDATION_ERROR | Invalid parameters (see errors object) |
| 500 | OPTIMIZED_GALAXY_CREATION_FAILED | Generation pipeline failed |

**Notes:**

- NPC count and difficulty are automatically determined by size tier
- Warp gate adjacency auto-calculated: `max(width, height) / 15`
- Core region: 100% inhabited, active warp gates, trading hubs
- Outer region: 0% inhabited (colonization targets), dormant gates, 2× mineral richness
- Generation timeout: 5 minutes maximum
- Admin users (user_id=1, email=mark.dhas@gmail.com) receive detailed metrics in response

---

### Get Size Tiers

Retrieves available galaxy size tiers and their configurations.

**Endpoint:** `GET /api/galaxies/size-tiers`

**Authentication:** Required (Sanctum token)

**Parameters:** None

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "tiers": [
      {
        "value": "small",
        "label": "Small (500×500, ~500 stars)",
        "dimensions": {
          "width": 500,
          "height": 500
        },
        "star_count": 500,
        "grid_size": 10
      },
      {
        "value": "medium",
        "label": "Medium (1500×1500, ~1500 stars)",
        "dimensions": {
          "width": 1500,
          "height": 1500
        },
        "star_count": 1500,
        "grid_size": 15
      }
    ]
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |

---

### Get Creation Status

Retrieves galaxy creation progress status (useful for async creation monitoring).

**Endpoint:** `GET /api/galaxies/{uuid}/creation-status`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "galaxy_id": "integer",
    "galaxy_uuid": "string",
    "galaxy_name": "string",
    "status": "string",
    "size_tier": "string",
    "current_progress": "integer",
    "is_complete": "boolean",
    "generation_started_at": "string|null",
    "generation_completed_at": "string|null",
    "steps": {
      "points_of_interest": {
        "completed": "boolean",
        "progress": "integer"
      },
      "sectors": {
        "completed": "boolean",
        "progress": "integer"
      },
      "warp_gates": {
        "completed": "boolean",
        "progress": "integer"
      }
    }
  }
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `current_progress` | integer | Overall progress percentage (0-100) |
| `is_complete` | boolean | True if generation complete or galaxy playable |
| `generation_started_at` | string\|null | ISO 8601 timestamp of generation start |
| `generation_completed_at` | string\|null | ISO 8601 timestamp of completion |
| `steps` | object | Individual step progress (if available) |

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 404 | NOT_FOUND | Galaxy not found |

---

## Galaxy Settings

### Update Galaxy Settings

Updates galaxy settings (owner only).

**Endpoint:** `PATCH /api/galaxies/{uuid}/settings`

**Authentication:** Required (Sanctum token)

**Authorization:** Galaxy owner only (owner_user_id must match authenticated user)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | No | Galaxy name (2-100 chars, must be unique) |
| `description` | string | No | Galaxy description (max 500 chars, nullable) |
| `is_public` | boolean | No | Public visibility flag |
| `max_players` | integer | No | Max player capacity (10-1000, cannot be less than current player count) |

**Request Body Example:**

```json
{
  "name": "Nova Sector Revised",
  "description": "A medium-sized galaxy perfect for new players",
  "is_public": true,
  "max_players": 150
}
```

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "uuid": "string",
    "name": "string",
    "description": "string|null",
    "is_public": "boolean",
    "max_players": "integer",
    "status": "string",
    "game_mode": "string",
    "total_players": "integer",
    "active_player_count": "integer"
  },
  "message": "Galaxy settings updated successfully"
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 403 | FORBIDDEN | User does not own this galaxy |
| 404 | NOT_FOUND | Galaxy not found |
| 422 | DUPLICATE_GALAXY_NAME | Galaxy name already exists |
| 422 | INVALID_MAX_PLAYERS | max_players less than current player count |
| 422 | VALIDATION_ERROR | Invalid parameters |

**Notes:**

- All fields are optional (partial updates supported)
- Name uniqueness validated across all galaxies
- max_players cannot be reduced below current active player count

---

## Player Membership

### Get My Player

Retrieves the authenticated user's player in a specific galaxy.

**Endpoint:** `GET /api/galaxies/{uuid}/my-player`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "player": {
      "uuid": "string",
      "call_sign": "string",
      "level": "integer",
      "experience": "integer",
      "credits": "float",
      "status": "string",
      "current_location": {
        "uuid": "string",
        "name": "string",
        "x": "float",
        "y": "float"
      },
      "active_ship": {
        "uuid": "string",
        "name": "string",
        "class": "string",
        "hull": "integer",
        "max_hull": "integer"
      }
    },
    "sector": {
      "uuid": "string",
      "name": "string",
      "grid": {
        "x": "integer",
        "y": "integer"
      }
    },
    "total_sectors": "integer"
  },
  "message": "Player found"
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 404 | NOT_FOUND | Galaxy not found |
| 404 | NO_PLAYER_IN_GALAXY | User has no player in this galaxy |

**Notes:**

- Use this endpoint to check if user has a player before showing game UI
- Returns full player resource with current location and ship

---

### Join Galaxy

Joins a galaxy (idempotent: returns existing player or creates new one).

**Endpoint:** `POST /api/galaxies/{uuid}/join`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `call_sign` | string | Conditional | Player name (max 50 chars, unique per galaxy). Required only if creating new player |

**Request Body Example:**

```json
{
  "call_sign": "StarLord"
}
```

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "player": {
      "uuid": "string",
      "call_sign": "string",
      "level": "integer",
      "experience": "integer",
      "credits": "float",
      "status": "string",
      "current_location": {
        "uuid": "string",
        "name": "string",
        "x": "float",
        "y": "float"
      },
      "active_ship": {
        "uuid": "string",
        "name": "string",
        "class": "string"
      }
    },
    "created": "boolean",
    "sector": {
      "uuid": "string",
      "name": "string",
      "grid": {
        "x": "integer",
        "y": "integer"
      }
    },
    "total_sectors": "integer"
  },
  "message": "Successfully joined galaxy"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `created` | boolean | True if new player created, false if existing player returned |
| `credits` | float | Starting credits (from game_config.ships.starting_credits) |
| `active_ship` | object | Starter ship (Sparrow Light Freighter) |

**New Player Initialization:**

When creating a new player, the following occurs:

1. Random inhabited starting location selected
2. Starting credits assigned (config: `game_config.ships.starting_credits`)
3. Starter ship created (Sparrow Light Freighter with default stats)
4. Spawn system named and scanned (level 1)
5. Outgoing warp gates discovered
6. Free star chart granted for spawn location
7. Starting charts granted for 3 nearest inhabited systems (2-hop BFS)

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 400 | GALAXY_NOT_ACTIVE | Galaxy not accepting new players (status != active) |
| 400 | GALAXY_FULL | Galaxy at maximum player capacity |
| 403 | SINGLE_PLAYER_GALAXY | Single-player galaxy and user is not owner |
| 404 | NOT_FOUND | Galaxy not found |
| 422 | DUPLICATE_CALL_SIGN | Call sign already exists in this galaxy |
| 422 | VALIDATION_ERROR | Invalid call_sign parameter |
| 500 | JOIN_FAILED | Player creation failed |
| 500 | NO_STARTING_LOCATION | No inhabited starting location found |

**Notes:**

- Idempotent operation: safe to call multiple times
- If user already has player, returns it with `created: false`
- Call sign uniqueness enforced per galaxy (not globally)
- Starting location is random inhabited system
- Single-player galaxies only accept owner as player

---

## NPC Management

### Add NPCs to Galaxy

Adds NPC players to an existing galaxy.

**Endpoint:** `POST /api/galaxies/{uuid}/npcs`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `count` | integer | Yes | Number of NPCs to create (1-50) |
| `difficulty` | string | No | NPC difficulty: `easy`, `medium`, `hard`, `expert` (default: `medium`) |
| `archetype_distribution` | object | No | Distribution percentages per archetype (must sum to 100) |

**Request Body Example:**

```json
{
  "count": 10,
  "difficulty": "medium",
  "archetype_distribution": {
    "merchant": 30,
    "explorer": 20,
    "pirate": 15,
    "colonizer": 20,
    "warrior": 15
  }
}
```

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "npcs_created": "integer",
    "archetypes": {
      "merchant": "integer",
      "explorer": "integer",
      "pirate": "integer",
      "colonizer": "integer",
      "warrior": "integer"
    },
    "difficulty": "string"
  },
  "message": "Successfully created X NPCs"
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 404 | NOT_FOUND | Galaxy not found |
| 422 | VALIDATION_ERROR | Invalid parameters |
| 500 | NPC_CREATION_FAILED | NPC generation failed |

---

### List NPCs in Galaxy

Retrieves all NPCs in a galaxy with optional filtering.

**Endpoint:** `GET /api/galaxies/{uuid}/npcs`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |
| `archetype` | string | query | No | Filter by archetype |
| `status` | string | query | No | Filter by status |
| `difficulty` | string | query | No | Filter by difficulty |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "npcs": [
      {
        "uuid": "string",
        "call_sign": "string",
        "archetype": "string",
        "difficulty": "string",
        "level": "integer",
        "credits": "float",
        "status": "string",
        "current_activity": "string|null",
        "location": {
          "id": "integer",
          "name": "string",
          "x": "float",
          "y": "float"
        },
        "ship": {
          "uuid": "string",
          "name": "string",
          "class": "string",
          "hull": "integer",
          "max_hull": "integer"
        }
      }
    ],
    "total": "integer",
    "statistics": {
      "total": "integer",
      "by_archetype": {},
      "by_difficulty": {},
      "active": "integer"
    }
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 404 | NOT_FOUND | Galaxy not found |

---

### Get NPC Details

Retrieves detailed information about a specific NPC.

**Endpoint:** `GET /api/npcs/{uuid}`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | NPC UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "uuid": "string",
    "call_sign": "string",
    "archetype": "string",
    "archetype_description": "string",
    "difficulty": "string",
    "level": "integer",
    "experience": "integer",
    "credits": "float",
    "status": "string",
    "current_activity": "string|null",
    "personality": {
      "aggression": "float",
      "risk_tolerance": "float",
      "trade_focus": "float"
    },
    "combat_stats": {
      "ships_destroyed": "integer",
      "combats_won": "integer",
      "combats_lost": "integer"
    },
    "economy_stats": {
      "total_trade_volume": "float"
    },
    "galaxy": {
      "id": "integer",
      "uuid": "string",
      "name": "string"
    },
    "location": {
      "id": "integer",
      "name": "string",
      "x": "float",
      "y": "float",
      "is_inhabited": "boolean"
    },
    "ship": {
      "uuid": "string",
      "name": "string",
      "class": "string",
      "status": "string",
      "stats": {
        "hull": "integer",
        "max_hull": "integer",
        "weapons": "integer",
        "cargo_hold": "integer",
        "current_cargo": "integer",
        "sensors": "integer",
        "warp_drive": "integer",
        "current_fuel": "integer",
        "max_fuel": "integer"
      },
      "cargo": [
        {
          "mineral": "string",
          "quantity": "integer"
        }
      ]
    },
    "last_action_at": "string|null"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 404 | NOT_FOUND | NPC not found |

---

### Delete NPC

Deletes an NPC from a galaxy (owner only).

**Endpoint:** `DELETE /api/npcs/{uuid}`

**Authentication:** Required (Sanctum token)

**Authorization:** Galaxy owner only

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | NPC UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "deleted": "string"
  },
  "message": "NPC 'CallSign' has been deleted"
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 403 | FORBIDDEN | User does not own the galaxy |
| 404 | NOT_FOUND | NPC not found |

---

### Get Archetype List

Retrieves available NPC archetypes and difficulty multipliers.

**Endpoint:** `GET /api/npcs/archetypes`

**Authentication:** Required (Sanctum token)

**Parameters:** None

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "archetypes": [
      {
        "name": "merchant",
        "description": "Focuses on trading and economic growth",
        "default_aggression": "float",
        "default_risk_tolerance": "float",
        "default_trade_focus": "float"
      }
    ],
    "difficulties": [
      {
        "name": "easy",
        "credits_multiplier": "float",
        "combat_skill_multiplier": "float",
        "decision_quality": "float"
      }
    ]
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |

---

## Map Data

### Get Map Summaries

Retrieves lightweight map data for known systems (optimized for tooltips and quick rendering).

**Endpoint:** `GET /api/galaxies/{uuid}/map-summaries`

**Authentication:** Required (Sanctum token)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | path | Yes | Galaxy UUID |

**Response Structure:**

```json
{
  "success": true,
  "data": {
    "systems": [
      {
        "uuid": "string",
        "name": "string",
        "x": "integer",
        "y": "integer",
        "type": "string",
        "is_inhabited": "boolean",
        "has_trading": "boolean",
        "gate_count": "integer",
        "is_current_location": "boolean"
      }
    ],
    "total": "integer",
    "player_location": {
      "uuid": "string",
      "x": "integer",
      "y": "integer"
    }
  },
  "message": "Map summaries retrieved successfully"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `systems` | array | Known systems (star charts + scans + current location) |
| `has_trading` | boolean | True if system has active trading hub |
| `gate_count` | integer | Number of outgoing warp gates (non-hidden) |
| `is_current_location` | boolean | True if player is currently here |

**Known Systems Include:**

- Systems with star charts
- Systems with scan records
- Current location (always included)

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | User not authenticated |
| 404 | NOT_FOUND | Galaxy not found |
| 404 | NO_PLAYER_IN_GALAXY | User has no player in this galaxy |

**Notes:**

- Returns minimal data for performance (hover tooltips, quick rendering)
- Only includes systems player has discovered
- Gate count excludes hidden gates

---

## Common HTTP Status Codes

| Status Code | Meaning |
|-------------|---------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid request parameters |
| 401 | Unauthorized - Authentication required or failed |
| 403 | Forbidden - Authenticated but not authorized |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Internal Server Error - Server error occurred |

## Common Error Codes

| Error Code | Description |
|------------|-------------|
| `UNAUTHENTICATED` | User not authenticated (missing or invalid token) |
| `FORBIDDEN` | User authenticated but lacks permission |
| `NOT_FOUND` | Requested resource does not exist |
| `VALIDATION_ERROR` | Request validation failed (see errors object) |
| `NO_PLAYER_IN_GALAXY` | User has no player in specified galaxy |
| `GALAXY_NOT_ACTIVE` | Galaxy not accepting new players |
| `GALAXY_FULL` | Galaxy at maximum player capacity |
| `DUPLICATE_CALL_SIGN` | Call sign already exists in galaxy |
| `DUPLICATE_GALAXY_NAME` | Galaxy name already exists |

## Authentication

All authenticated endpoints require a Sanctum token in the `Authorization` header:

```
Authorization: Bearer {token}
```

Tokens are obtained via the `/api/auth/login` endpoint.

## Rate Limiting

API rate limits apply per user:

- Authenticated requests: 60 requests/minute
- Unauthenticated requests: 30 requests/minute

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1645027200
```

## Caching

Several endpoints use caching for performance:

| Endpoint | Cache Duration | Cache Key |
|----------|----------------|-----------|
| `GET /api/galaxies/list` (open_games) | 5 minutes | `galaxies:open_games` |

Cache is automatically invalidated on relevant mutations.

---

**Documentation Version:** 2026.02.16.002
**API Version:** Compatible with Space Wars 3002 v2026.02.10.001+

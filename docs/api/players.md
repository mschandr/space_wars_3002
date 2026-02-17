# Players API

This document describes all player-related API endpoints for Space Wars 3002.

## Table of Contents
- [Authentication](#authentication)
- [Response Format](#response-format)
- [Endpoints](#endpoints)
  - [GET /api/players](#get-apiplayers)
  - [POST /api/players](#post-apiplayers)
  - [GET /api/players/{uuid}](#get-apiplayersuuid)
  - [PATCH /api/players/{uuid}](#patch-apiplayersuuid)
  - [DELETE /api/players/{uuid}](#delete-apiplayersuuid)
  - [POST /api/players/{uuid}/set-active](#post-apiplayersuuidset-active)
  - [GET /api/players/{uuid}/status](#get-apiplayersuuidstatus)
  - [GET /api/players/{uuid}/stats](#get-apiplayersuuidstats)
  - [PATCH /api/players/{uuid}/settings](#patch-apiplayersuuidsettings)
  - [GET /api/players/{playerUuid}/knowledge-map](#get-apiplayersplayeruuidknowledge-map)

---

## Authentication

All endpoints require authentication via Laravel Sanctum. Include a valid bearer token in the request header:

```
Authorization: Bearer {token}
```

All endpoints enforce ownership validation - authenticated users can only access their own players.

---

## Response Format

All responses follow a standardized format:

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message",
  "meta": {
    "timestamp": "2026-02-16T12:34:56.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:34:56.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Validation Error Response:**
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
    "timestamp": "2026-02-16T12:34:56.000000Z",
    "request_id": "uuid-v4"
  }
}
```

---

## Endpoints

### GET /api/players

List all players belonging to the authenticated user.

**Authentication Required:** Yes

**Request Parameters:**

None

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "player-uuid-v4",
      "call_sign": "SpaceTrader",
      "credits": 10000.0,
      "experience": 250,
      "level": 2,
      "status": "active",
      "current_location": {
        "id": 42,
        "uuid": "poi-uuid-v4",
        "name": "Sol",
        "type": "star",
        "x": 150.5,
        "y": 200.3,
        "is_inhabited": true,
        ...
      },
      "active_ship": {
        "id": 15,
        "uuid": "ship-uuid-v4",
        "name": "SpaceTrader's Scout",
        "current_fuel": 85,
        "max_fuel": 100,
        "hull": 100,
        "max_hull": 100,
        "status": "operational",
        ...
      },
      "galaxy": {
        "id": 7,
        "uuid": "galaxy-uuid-v4",
        "name": "Milky Way Prime"
      },
      "created_at": "2026-02-10T14:30:00.000000Z",
      "updated_at": "2026-02-16T10:20:15.000000Z"
    }
  ],
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:34:56.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Field Descriptions:**
- `id`: Internal database ID (integer)
- `uuid`: Public UUID identifier (use this for API calls)
- `call_sign`: Player's chosen name/handle (max 50 characters, unique per galaxy)
- `credits`: Player's current currency balance (float)
- `experience`: Total XP accumulated
- `level`: Current player level (calculated from XP)
- `status`: Player account status (e.g., "active", "inactive")
- `current_location`: Full POI resource where player is currently located (if loaded)
- `active_ship`: Full ship resource for player's currently active ship (if loaded)
- `galaxy`: Basic galaxy info (id, uuid, name)
- `created_at`: ISO 8601 timestamp of player creation
- `updated_at`: ISO 8601 timestamp of last update

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |

**Warnings & Caveats:**
- Returns all players for the authenticated user across all galaxies
- Always includes `galaxy`, `currentLocation`, and `activeShip` relations
- Returns an empty array if user has no players

---

### POST /api/players

Create a new player in a specified galaxy.

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxy_id | integer | Yes | Database ID of the galaxy (not UUID) |
| call_sign | string | Yes | Player's name/handle (max 50 characters) |

**Request Body Example:**
```json
{
  "galaxy_id": 7,
  "call_sign": "SpaceTrader"
}
```

**Success Response:**

**Status Code:** `201 Created`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "player-uuid-v4",
    "call_sign": "SpaceTrader",
    "credits": 10000.0,
    "experience": 0,
    "level": 1,
    "status": "active",
    "current_location": {
      "id": 42,
      "uuid": "poi-uuid-v4",
      "name": "Alpha Centauri",
      "type": "star",
      "x": 150.5,
      "y": 200.3,
      "is_inhabited": true,
      ...
    },
    "active_ship": {
      "id": 15,
      "uuid": "ship-uuid-v4",
      "name": "SpaceTrader's Scout",
      "current_fuel": 100,
      "max_fuel": 100,
      "hull": 100,
      "max_hull": 100,
      "weapons": 10,
      "cargo_hold": 100,
      "sensors": 1,
      "warp_drive": 1,
      "is_active": true,
      "status": "operational",
      ...
    },
    "galaxy": {
      "id": 7,
      "uuid": "galaxy-uuid-v4",
      "name": "Milky Way Prime"
    },
    "created_at": "2026-02-16T12:34:56.000000Z",
    "updated_at": "2026-02-16T12:34:56.000000Z"
  },
  "message": "Player created successfully",
  "meta": {
    "timestamp": "2026-02-16T12:34:56.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Field Descriptions:**
- Starting credits determined by `config('game_config.ships.starting_credits')`, defaults to 10,000
- Starting location is randomly selected from inhabited star systems in the galaxy
- Player automatically receives a Scout-class ship as starting vessel
- Initial ship is named "{call_sign}'s Scout"
- Ship stats are copied from the Scout blueprint's base values
- Player starts at level 1 with 0 XP
- Status is set to "active" by default

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 422 | VALIDATION_ERROR | Invalid input (see error.errors for details) |
| 422 | DUPLICATE_CALL_SIGN | Call sign already exists in this galaxy |
| 400 | NO_STARTING_LOCATION | Galaxy has no inhabited systems for spawning |
| 400 | PLAYER_CREATION_FAILED | Database transaction failed (see message for details) |

**Validation Errors:**
- `galaxy_id`: Must be a valid galaxy database ID
- `call_sign`: Required, string, max 50 characters

**Warnings & Caveats:**
- **KNOWN ISSUE:** This endpoint accepts `galaxy_id` as an integer database ID, inconsistent with other endpoints that use UUIDs. Future versions may deprecate this in favor of `galaxy_uuid`.
- **KNOWN ISSUE:** Call sign validation lacks minimum length, character set restrictions (alphanumeric + underscore), and proper uniqueness validation at the database level.
- **TODO:** Players should receive 3 free star charts to nearest inhabited systems upon creation (not yet implemented).
- Uses database transactions to ensure atomic creation (player + ship + future star charts).
- If Scout ship blueprint is missing from database, player will be created without a ship.
- Creation fails if galaxy has zero inhabited systems.

---

### GET /api/players/{uuid}

Get detailed information about a specific player.

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string | Yes | Player's UUID (in URL path) |

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "player-uuid-v4",
    "call_sign": "SpaceTrader",
    "credits": 15750.5,
    "experience": 450,
    "level": 3,
    "status": "active",
    "current_location": {
      "id": 42,
      "uuid": "poi-uuid-v4",
      "name": "Sol",
      "type": "star",
      "x": 150.5,
      "y": 200.3,
      "is_inhabited": true,
      ...
    },
    "active_ship": {
      "id": 15,
      "uuid": "ship-uuid-v4",
      "name": "SpaceTrader's Scout",
      "ship": {
        "id": 2,
        "name": "Scout",
        "class": "scout",
        "description": "Fast and agile exploration vessel",
        ...
      },
      "current_fuel": 85,
      "max_fuel": 100,
      "hull": 95,
      "max_hull": 100,
      "weapons": 10,
      "cargo_hold": 100,
      "sensors": 2,
      "warp_drive": 1,
      "is_active": true,
      "status": "operational",
      ...
    },
    "galaxy": {
      "id": 7,
      "uuid": "galaxy-uuid-v4",
      "name": "Milky Way Prime"
    },
    "created_at": "2026-02-10T14:30:00.000000Z",
    "updated_at": "2026-02-16T10:20:15.000000Z"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:34:56.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Field Descriptions:**
- Includes all standard player fields (same as index endpoint)
- `active_ship.ship`: Includes the full ship blueprint/template relationship showing the base ship class

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 404 | NOT_FOUND | Player not found or doesn't belong to authenticated user |

**Warnings & Caveats:**
- Ownership is enforced: users can only view their own players
- Returns 404 if player exists but belongs to another user (security by obscurity)
- Always loads `galaxy`, `currentLocation`, `activeShip.ship` relations

---

### PATCH /api/players/{uuid}

Update player details (currently only call sign).

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string | Yes | Player's UUID (in URL path) |

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| call_sign | string | No | New call sign (max 50 characters) |

**Request Body Example:**
```json
{
  "call_sign": "NewName"
}
```

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "player-uuid-v4",
    "call_sign": "NewName",
    "credits": 15750.5,
    "experience": 450,
    "level": 3,
    "status": "active",
    "current_location": { ... },
    "active_ship": { ... },
    "galaxy": { ... },
    "created_at": "2026-02-10T14:30:00.000000Z",
    "updated_at": "2026-02-16T12:40:00.000000Z"
  },
  "message": "Player updated successfully",
  "meta": {
    "timestamp": "2026-02-16T12:40:00.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 404 | NOT_FOUND | Player not found or doesn't belong to authenticated user |
| 422 | VALIDATION_ERROR | Invalid input (see error.errors for details) |
| 422 | DUPLICATE_CALL_SIGN | Call sign already exists in this galaxy |

**Warnings & Caveats:**
- Only `call_sign` is currently updatable via this endpoint
- Call sign must be unique within the galaxy
- Uniqueness check excludes the current player
- Ownership is enforced: users can only update their own players
- No-op if call_sign is not provided (returns success without changes)

---

### DELETE /api/players/{uuid}

Permanently delete a player and all associated data.

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string | Yes | Player's UUID (in URL path) |

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": null,
  "message": "Player deleted successfully",
  "meta": {
    "timestamp": "2026-02-16T12:45:00.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 404 | NOT_FOUND | Player not found or doesn't belong to authenticated user |

**Warnings & Caveats:**
- **DESTRUCTIVE ACTION:** This permanently deletes the player
- Cascade deletion behavior depends on database foreign key constraints
- May also delete associated ships, cargo, colonies, star charts, etc.
- Ownership is enforced: users can only delete their own players
- No confirmation step - deletion is immediate

---

### POST /api/players/{uuid}/set-active

Set a player as the active player for the authenticated user.

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string | Yes | Player's UUID (in URL path) |

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "player-uuid-v4",
    "call_sign": "SpaceTrader",
    "credits": 15750.5,
    "experience": 450,
    "level": 3,
    "status": "active",
    "current_location": { ... },
    "active_ship": { ... },
    "galaxy": { ... },
    "created_at": "2026-02-10T14:30:00.000000Z",
    "updated_at": "2026-02-16T10:20:15.000000Z"
  },
  "message": "Player set as active",
  "meta": {
    "timestamp": "2026-02-16T12:50:00.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 404 | NOT_FOUND | Player not found or doesn't belong to authenticated user |

**Warnings & Caveats:**
- **STUB IMPLEMENTATION:** Currently this endpoint does not actually track active player state
- No `is_active` field exists on the players table yet
- Future implementation may add user-level preference tracking
- Currently just returns the player data with a success message
- Ownership is enforced: users can only set their own players as active

---

### GET /api/players/{uuid}/status

Get real-time player status snapshot (location, ship, basic stats).

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string | Yes | Player's UUID (in URL path) |

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "player": {
      "uuid": "player-uuid-v4",
      "call_sign": "SpaceTrader",
      "level": 3,
      "credits": 15750.5,
      "experience": 450,
      "status": "active"
    },
    "location": {
      "name": "Sol",
      "type": "star",
      "coordinates": {
        "x": 150.5,
        "y": 200.3
      }
    },
    "ship": {
      "name": "SpaceTrader's Scout",
      "fuel": 85,
      "max_fuel": 100,
      "hull": 95,
      "max_hull": 100,
      "status": "operational"
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:55:00.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Field Descriptions:**

**player:**
- `uuid`: Player's unique identifier
- `call_sign`: Player's name/handle
- `level`: Current player level
- `credits`: Currency balance (float)
- `experience`: Total XP
- `status`: Player account status

**location:**
- `name`: Name of current POI (star, planet, station, etc.)
- `type`: POI type enum value (e.g., "star", "planet", "station")
- `coordinates.x`: X coordinate in galaxy (float)
- `coordinates.y`: Y coordinate in galaxy (float)
- Null if player has no current location

**ship:**
- `name`: Custom ship name
- `fuel`: Current fuel units (integer)
- `max_fuel`: Maximum fuel capacity (integer)
- `hull`: Current hull integrity (integer)
- `max_hull`: Maximum hull strength (integer)
- `status`: Ship operational status (e.g., "operational", "damaged")
- Null if player has no active ship

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 404 | NOT_FOUND | Player not found or doesn't belong to authenticated user |

**Warnings & Caveats:**
- Optimized for quick status checks (smaller payload than full player resource)
- Fuel values represent current state (may need regeneration via `FuelRegenerationService`)
- Location and ship can be null if not yet initialized
- Does not include detailed ship stats (weapons, cargo, sensors, etc.)
- Ownership is enforced

---

### GET /api/players/{uuid}/stats

Get comprehensive player statistics (progression, economy, exploration, mirror universe).

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string | Yes | Player's UUID (in URL path) |

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "player_info": {
      "uuid": "player-uuid-v4",
      "call_sign": "SpaceTrader",
      "level": 3,
      "experience": 450,
      "next_level_xp": 900,
      "progress_to_next_level": 50.0
    },
    "economy": {
      "credits": 15750.5,
      "total_ships_owned": 2,
      "total_plans_owned": 5
    },
    "exploration": {
      "systems_charted": 27
    },
    "mirror_universe": {
      "is_in_mirror": false,
      "can_return": false,
      "cooldown_remaining": null
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T13:00:00.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Field Descriptions:**

**player_info:**
- `uuid`: Player's unique identifier
- `call_sign`: Player's name/handle
- `level`: Current player level
- `experience`: Total XP accumulated
- `next_level_xp`: XP required for next level (formula: `level^2 * 100`)
- `progress_to_next_level`: Percentage progress to next level (0-100, rounded to 2 decimals)

**economy:**
- `credits`: Current currency balance (float)
- `total_ships_owned`: Count of all ships owned (including inactive)
- `total_plans_owned`: Count of upgrade plans/blueprints owned

**exploration:**
- `systems_charted`: Count of star charts owned (revealed systems via BFS traversal)

**mirror_universe:**
- `is_in_mirror`: Boolean indicating if player is currently in mirror universe
- `can_return`: Boolean indicating if player can return from mirror universe (respects 24-hour cooldown)
- `cooldown_remaining`: Human-readable time remaining before next mirror travel (e.g., "3 hours from now", null if no cooldown)

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 404 | NOT_FOUND | Player not found or doesn't belong to authenticated user |

**Warnings & Caveats:**
- Loads multiple relationships (`ships`, `plans`, `starCharts`) - may be slower for players with large inventories
- XP calculation: Level requirement grows quadratically (`level^2 * 100`)
- Progress percentage is based on XP within current level band, not total XP
- Mirror universe methods (`isInMirrorUniverse()`, `canReturnFromMirror()`, `getMirrorCooldownRemaining()`) are called on the Player model
- Ownership is enforced

---

### PATCH /api/players/{uuid}/settings

Update player settings (call sign and/or client preferences).

**Authentication Required:** Yes

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string | Yes | Player's UUID (in URL path) |

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| call_sign | string | No | New call sign (min 2, max 50 characters) |
| settings | object | No | JSON object for client-side preferences (merged with existing) |

**Request Body Example:**
```json
{
  "call_sign": "NewName",
  "settings": {
    "ui_theme": "dark",
    "sound_enabled": true,
    "auto_refuel": false,
    "notification_preferences": {
      "combat": true,
      "trading": false
    }
  }
}
```

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "player-uuid-v4",
    "call_sign": "NewName",
    "credits": 15750.5,
    "experience": 450,
    "level": 3,
    "status": "active",
    "current_location": { ... },
    "active_ship": { ... },
    "galaxy": { ... },
    "created_at": "2026-02-10T14:30:00.000000Z",
    "updated_at": "2026-02-16T13:10:00.000000Z"
  },
  "message": "Player settings updated successfully",
  "meta": {
    "timestamp": "2026-02-16T13:10:00.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player not found |
| 422 | VALIDATION_ERROR | Invalid input (see error.errors for details) |
| 422 | DUPLICATE_CALL_SIGN | Call sign already exists in this galaxy |

**Validation Rules:**
- `call_sign`: Optional, string, minimum 2 characters, maximum 50 characters
- `settings`: Optional, must be a valid JSON object/array

**Field Descriptions:**
- `call_sign`: Updated name/handle (must be unique within galaxy)
- `settings`: Client-side preferences stored as JSON blob
  - Settings are **merged** with existing settings (not replaced entirely)
  - Allows incremental updates without losing previous settings
  - No schema validation on settings object (freeform JSON)
  - Useful for UI themes, sound preferences, gameplay options, etc.

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | UNAUTHENTICATED | No valid authentication token provided |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player not found |
| 422 | VALIDATION_ERROR | Invalid input (see error.errors for details) |
| 422 | DUPLICATE_CALL_SIGN | Call sign already exists in this galaxy |

**Warnings & Caveats:**
- **Settings merge behavior:** Existing settings are merged with new settings, not replaced
- Example: If existing settings are `{"theme": "light", "sound": true}` and you send `{"theme": "dark"}`, result will be `{"theme": "dark", "sound": true}`
- No schema validation on settings object - client is responsible for data structure
- Call sign validation includes minimum length (2 chars) unlike the basic PATCH endpoint
- Ownership is strictly enforced via explicit check (returns 403 FORBIDDEN instead of 404)
- Returns full PlayerResource after update (includes all relations)

---

### GET /api/players/{playerUuid}/knowledge-map

Get the player's complete knowledge map (fog of war system) including known systems, lanes, and danger zones.

**Authentication Required:** Optional (but ownership is enforced if authenticated)

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| playerUuid | string | Yes | Player's UUID (in URL path) |
| sector_uuid | string | No | Optional sector UUID to filter results (query parameter) |

**Success Response:**

**Status Code:** `200 OK`

**Data Structure:**
```json
{
  "success": true,
  "data": {
    "galaxy": {
      "uuid": "galaxy-uuid-v4",
      "name": "Milky Way Prime",
      "width": 300,
      "height": 300
    },
    "player": {
      "uuid": "player-uuid-v4",
      "location": {
        "x": 150.5,
        "y": 200.3,
        "poi_uuid": "poi-uuid-v4"
      },
      "sensor_range_ly": 200,
      "sensor_level": 2
    },
    "known_systems": [
      {
        "poi_uuid": "poi-uuid-v4",
        "x": 150.5,
        "y": 200.3,
        "knowledge_level": 3,
        "knowledge_label": "Surveyed",
        "freshness": 1.0,
        "source": "current_location",
        "star": {
          "type": "Star",
          "stellar_class": "G2",
          "stellar_description": "G2-class star",
          "temperature_range_k": {
            "min": 5200,
            "max": 6000
          },
          "temperature_k": 5778,
          "luminosity": 1.0,
          "goldilocks_zone": {
            "inner": 0.95,
            "outer": 1.37
          }
        },
        "name": "Sol",
        "is_inhabited": true,
        "planet_count": 8,
        "services": {
          "trading_hub": true,
          "shipyard": true,
          "salvage_yard": false,
          "cartographer": true
        },
        "pirate_warning": {
          "active": true,
          "danger_radius_ly": 5,
          "confidence": "Medium"
        },
        "scan_level": 2,
        "has_scan_data": true
      },
      {
        "poi_uuid": "poi-uuid-v4-2",
        "x": 175.2,
        "y": 210.8,
        "knowledge_level": 2,
        "knowledge_label": "Basic",
        "freshness": 0.85,
        "source": "star_chart",
        "star": {
          "type": "Star",
          "stellar_class": "M5",
          "stellar_description": "M5-class star",
          "temperature_range_k": {
            "min": 2400,
            "max": 3700
          }
        },
        "name": "Proxima Centauri",
        "is_inhabited": false,
        "planet_count": 2
      },
      {
        "poi_uuid": "poi-uuid-v4-3",
        "x": 200.0,
        "y": 250.5,
        "knowledge_level": 1,
        "knowledge_label": "Detected",
        "freshness": 0.95,
        "source": "sensor_sweep",
        "star": {
          "type": "Star",
          "stellar_class": "K7",
          "stellar_description": "K7-class star",
          "temperature_range_k": {
            "min": 3700,
            "max": 5200
          }
        }
      }
    ],
    "known_lanes": [
      {
        "gate_uuid": "gate-uuid-v4",
        "from_poi_uuid": "poi-uuid-v4",
        "to_poi_uuid": "poi-uuid-v4-2",
        "from": {
          "x": 150.5,
          "y": 200.3
        },
        "to": {
          "x": 175.2,
          "y": 210.8
        },
        "has_pirate": false,
        "pirate_freshness": 0.92,
        "discovery_method": "traversal"
      }
    ],
    "danger_zones": [
      {
        "center": {
          "x": 150.5,
          "y": 200.3
        },
        "radius_ly": 5,
        "source": "pirate_warning",
        "confidence": "Medium"
      }
    ],
    "statistics": {
      "total_known": 3,
      "by_level": {
        "1": 1,
        "2": 1,
        "3": 1
      },
      "known_lanes": 1,
      "pirate_warnings": 1
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T13:20:00.000000Z",
    "request_id": "uuid-v4"
  }
}
```

**Field Descriptions:**

**galaxy:**
- `uuid`: Galaxy's unique identifier
- `name`: Galaxy name
- `width`: Galaxy width in light years
- `height`: Galaxy height in light years

**player:**
- `uuid`: Player's unique identifier
- `location.x`: Player's current X coordinate
- `location.y`: Player's current Y coordinate
- `location.poi_uuid`: UUID of player's current POI
- `sensor_range_ly`: Detection range in light years (calculated: `sensors * 100`)
- `sensor_level`: Current sensor upgrade level (from active ship)

**known_systems (array of systems):**

*All knowledge levels (1+):*
- `poi_uuid`: System's unique identifier
- `x`: X coordinate in galaxy (float)
- `y`: Y coordinate in galaxy (float)
- `knowledge_level`: Integer knowledge level (1-4)
  - 1 = Detected (position + stellar class only)
  - 2 = Basic (+ name, inhabited status, planet count)
  - 3 = Surveyed (+ services for inhabited systems)
  - 4 = Comprehensive (future: full planetary data)
- `knowledge_label`: Human-readable level ("Detected", "Basic", "Surveyed", "Comprehensive")
- `freshness`: Knowledge staleness factor (0.0-1.0, 1.0 = completely fresh)
  - Degrades over time based on last update
  - Formula: `max(0.1, 1.0 - (hours_since_update / 168))`
- `source`: How knowledge was acquired ("current_location", "star_chart", "sensor_sweep", "travel", etc.)
- `star.type`: POI type label (e.g., "Star")
- `star.stellar_class`: Star classification (e.g., "G2", "M5", "K7")
- `star.stellar_description`: Human-readable description
- `star.temperature_range_k`: Min/max temperature range for this stellar class (Kelvin)

*Basic knowledge (2+):*
- `name`: System name
- `is_inhabited`: Boolean indicating civilized system
- `planet_count`: Number of child POIs (planets, belts, etc.)

*Surveyed knowledge (3+, inhabited systems only):*
- `services.trading_hub`: Boolean, active trading hub present
- `services.shipyard`: Boolean, ship purchasing/switching available
- `services.salvage_yard`: Boolean, ship scrapping available
- `services.cartographer`: Boolean, star chart vendor present
- `star.temperature_k`: Precise stellar temperature in Kelvin
- `star.luminosity`: Stellar luminosity relative to Sol (1.0 = Sol)
- `star.goldilocks_zone`: Habitable zone boundaries
  - `inner`: Inner boundary in AU
  - `outer`: Outer boundary in AU

*Optional fields (any level):*
- `pirate_warning`: Present if pirates recently encountered nearby
  - `active`: Always true if present
  - `danger_radius_ly`: Danger zone radius (default 5 LY)
  - `confidence`: Detection confidence based on sensors ("Low", "Medium", "High")
    - Low: sensors 1-2
    - Medium: sensors 3-4
    - High: sensors 5+
- `scan_level`: Player's scan depth at this system (if scanned)
- `has_scan_data`: Boolean indicating detailed scan exists

**known_lanes (array of warp gates):**
- `gate_uuid`: Warp gate's unique identifier
- `from_poi_uuid`: Source POI UUID (can be null for hidden gates)
- `to_poi_uuid`: Destination POI UUID (can be null for hidden gates)
- `from.x`: Source X coordinate (uses gate's stored coords or POI coords)
- `from.y`: Source Y coordinate
- `to.x`: Destination X coordinate
- `to.y`: Destination Y coordinate
- `has_pirate`: Boolean indicating pirate presence is known
- `pirate_freshness`: Staleness of pirate intel (0.0-1.0, null if never checked)
  - Formula: `max(0.1, 1.0 - (hours_since_check / 168))`
- `discovery_method`: How lane was discovered ("traversal", "star_chart", "sensor_detect", etc.)

**danger_zones (array of zones):**
- `center.x`: Zone center X coordinate
- `center.y`: Zone center Y coordinate
- `radius_ly`: Danger radius in light years
- `source`: Zone source ("pirate_warning", "combat_log", etc.)
- `confidence`: Detection confidence ("Low", "Medium", "High")

**statistics:**
- `total_known`: Total count of known systems
- `by_level`: Breakdown of systems by knowledge level (object with level as key)
- `known_lanes`: Total count of known warp gates
- `pirate_warnings`: Count of active pirate warnings

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 403 | FORBIDDEN | Authenticated user does not own this player |
| 404 | NOT_FOUND | Player not found |
| 400 | NO_LOCATION | Player has no current location |

**Warnings & Caveats:**
- **Fog of War:** Only returns information the player actually knows - unknown systems/lanes are completely hidden
- Sector filtering is optional - use `?sector_uuid=xxx` to limit to one sector
- Knowledge freshness degrades over time (1 week = 0.1 freshness minimum)
- Services data only available for **inhabited systems at Surveyed level (3+)**
- Uninhabited systems never show services (no trading hubs exist there)
- Pirate warnings have 5 LY danger radius by default (configurable via `game_config.knowledge.pirate_danger_radius_ly`)
- Sensor range calculation: `sensors * 100` LY (e.g., sensor level 2 = 200 LY range)
- Pirate confidence depends on sensor level:
  - Sensors 5+: High (95% accuracy)
  - Sensors 3-4: Medium (85% accuracy)
  - Sensors 1-2: Low (70% accuracy)
- Lane coordinates fall back to POI coordinates if gate doesn't store explicit source/dest coords
- Lane pirate freshness is null if never checked (player hasn't traveled that lane recently)
- Stellar class is observable from any distance (even "Detected" level 1)
- Temperature ranges come from stellar classification enum
- Precise temperature/luminosity/goldilocks zone only available at Surveyed level (3+)
- Knowledge is aggregated from multiple sources: current location (freshest), star charts, sensor sweeps, travel history, scans
- Uses `PlayerKnowledgeService` and `LaneKnowledgeService` to merge knowledge from multiple tables
- Can be called without authentication for public galaxy views, but ownership is enforced if user is authenticated
- System scan data (`scan_level`) is separate from general knowledge level

**Knowledge Level System:**

The game uses a tiered fog-of-war system:

1. **Detected (1):** Position + stellar classification only (sensor detection range)
2. **Basic (2):** + Name, inhabited status, planet count (1-hop from known systems via star charts)
3. **Surveyed (3):** + Services, precise stellar data (visited, charted, or scanned)
4. **Comprehensive (4):** Future: Full planetary composition, resource distribution, detailed POI data

Knowledge sources ranked by freshness:
1. Current location (always 1.0)
2. Recent scans (decay over hours)
3. Travel history (decay over days)
4. Star charts (static, no decay)
5. Sensor sweeps (decay over days)

**Performance Considerations:**
- Loads multiple relationships for known POIs (`tradingHub`, `sector`)
- Loads all lane knowledge for galaxy (filtered by `LaneKnowledgeService`)
- Loads system scan levels for all known POIs
- Consider pagination or sector filtering for galaxies with thousands of known systems
- Response size scales with exploration progress (new players: small, veteran explorers: large)

---

## Common Error Codes

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| UNAUTHENTICATED | 401 | Authentication token missing or invalid |
| FORBIDDEN | 403 | User does not own the requested resource |
| NOT_FOUND | 404 | Resource not found or user lacks access |
| VALIDATION_ERROR | 422 | Request validation failed (see error.errors) |
| DUPLICATE_CALL_SIGN | 422 | Call sign already exists in galaxy |
| NO_STARTING_LOCATION | 400 | Galaxy has no suitable spawn points |
| PLAYER_CREATION_FAILED | 400 | Database error during player creation |
| NO_LOCATION | 400 | Player has no current location set |

---

## Notes

### Ownership & Security
- All endpoints enforce ownership validation
- Users can only access their own players
- 404 is returned for both "not found" and "forbidden" cases (security by obscurity)
- Exception: `/settings` endpoint returns explicit 403 FORBIDDEN for clarity

### UUIDs vs Database IDs
- All URL paths use UUIDs (not database IDs)
- Exception: `POST /api/players` accepts `galaxy_id` as integer (known inconsistency, may be deprecated)
- Internal database IDs are exposed in responses for debugging but should not be used in API calls

### Response Consistency
- All responses include `success` boolean
- All responses include `meta` object with timestamp and request_id
- Success responses wrap data in `data` field
- Error responses wrap errors in `error` object
- Validation errors include field-specific messages in `error.errors`

### Known Issues & TODOs
- `POST /api/players` uses integer `galaxy_id` instead of UUID (inconsistent with other endpoints)
- `POST /api/players` lacks proper call_sign validation (no min length, no regex, no DB-level uniqueness)
- `POST /api/players/{uuid}/set-active` is a stub (doesn't actually track active player state)
- `POST /api/players` doesn't grant starter star charts (mentioned in TODO comment)
- Settings field has no schema validation (completely freeform JSON)

---

## Examples

### Creating a New Player

```bash
curl -X POST https://api.spacewars3002.com/api/players \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "galaxy_id": 1,
    "call_sign": "SpaceCowboy"
  }'
```

### Updating Player Settings

```bash
curl -X PATCH https://api.spacewars3002.com/api/players/player-uuid-here/settings \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": {
      "ui_theme": "dark",
      "auto_save": true,
      "notification_preferences": {
        "combat": true,
        "trading": false,
        "exploration": true
      }
    }
  }'
```

### Getting Knowledge Map for Specific Sector

```bash
curl -X GET "https://api.spacewars3002.com/api/players/player-uuid-here/knowledge-map?sector_uuid=sector-uuid-here" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

**Version:** 2026.02.10.001
**Last Updated:** 2026-02-16

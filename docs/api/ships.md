# Ship API Documentation

This document describes all ship-related API endpoints in Space Wars 3002. Ships are the player's primary vehicle for exploration, trading, and combat.

## Table of Contents
- [Get My Ship in Galaxy](#get-my-ship-in-galaxy)
- [Get Player's Active Ship](#get-players-active-ship)
- [Rename Ship](#rename-ship)
- [Regenerate Fuel](#regenerate-fuel)
- [Get Ship Status](#get-ship-status)
- [Get Fuel Status](#get-fuel-status)
- [Get Ship Damage](#get-ship-damage)
- [Get Ship Upgrades](#get-ship-upgrades)

---

## Get My Ship in Galaxy

Get the authenticated user's active ship in a specific galaxy.

**Endpoint:** `GET /api/galaxies/{uuid}/my-ship`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Galaxy UUID |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 123,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "USS Enterprise",
    "current_fuel": 850,
    "max_fuel": 1000,
    "fuel_regen_rate": 13.0,
    "time_to_full_fuel": 415,
    "hull": 450,
    "max_hull": 500,
    "shields": 200,
    "max_shields": 250,
    "weapons": 5,
    "cargo_hold": 15,
    "sensors": 8,
    "warp_drive": 7,
    "current_cargo": 450,
    "status": "operational",
    "ship_class": {
      "id": 3,
      "name": "Voyager-class",
      "class": "explorer"
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `data.id` | integer | Internal database ID of the PlayerShip |
| `data.uuid` | string | Unique identifier for the player's ship |
| `data.name` | string | Custom ship name (player-assigned) |
| `data.current_fuel` | integer | Current fuel units |
| `data.max_fuel` | integer | Maximum fuel capacity |
| `data.fuel_regen_rate` | float | Fuel regeneration rate (units per hour) |
| `data.time_to_full_fuel` | integer | Seconds until fuel is fully regenerated (null if already full) |
| `data.hull` | integer | Current hull integrity points |
| `data.max_hull` | integer | Maximum hull integrity |
| `data.shields` | integer | Current shield strength |
| `data.max_shields` | integer | Maximum shield capacity |
| `data.weapons` | integer | Weapon system level (1-20+) |
| `data.cargo_hold` | integer | Total cargo capacity in units |
| `data.sensors` | integer | Sensor array level (1-20+) |
| `data.warp_drive` | integer | Warp drive level (1-20+) |
| `data.current_cargo` | integer | Currently used cargo space (only included if cargos relation loaded) |
| `data.status` | string | Ship operational status (operational, damaged, destroyed, docked) |
| `data.ship_class.id` | integer | Ship blueprint/template ID |
| `data.ship_class.name` | string | Ship class name (Voyager-class, Battlecruiser, etc.) |
| `data.ship_class.class` | string | Ship category (scout, freighter, battleship, explorer) |

### Error Responses

**Galaxy Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Galaxy not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Not In Galaxy (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_IN_GALAXY",
    "message": "You are not in this galaxy",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**No Active Ship (404)**
```json
{
  "success": false,
  "error": {
    "code": "NO_SHIP",
    "message": "No active ship found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- The `ship_class` object is only included if the `ship` relationship is loaded (which it is in this endpoint via `->with('ship')`)
- The `current_cargo` field is only included if the `cargos` relationship is loaded (not loaded by default in this endpoint)
- Fuel regeneration is **not** automatically applied when fetching ship data; use `/regenerate-fuel` or `/status` endpoints for up-to-date fuel values
- The user must have a player record in the specified galaxy, even if they have ships in other galaxies

---

## Get Player's Active Ship

Get the active ship for a specific player (must be owned by authenticated user).

**Endpoint:** `GET /api/players/{playerUuid}/ship`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| playerUuid | string | Yes | Path | Player UUID |

### Success Response (200 OK)

Same structure as [Get My Ship in Galaxy](#get-my-ship-in-galaxy).

### Error Responses

**Player Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Player not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**No Active Ship (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "No active ship found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- Only returns ships owned by the authenticated user
- The player UUID must belong to the authenticated user, otherwise a 404 is returned (player appears as "not found" rather than "unauthorized")
- Players can have multiple ships but only one is "active" at a time

---

## Rename Ship

Rename a player's ship.

**Endpoint:** `PATCH /api/ships/{uuid}/name`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

### Request Body

```json
{
  "name": "Millennium Falcon"
}
```

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| name | string | Yes | Max 100 characters | New name for the ship |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 123,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Millennium Falcon",
    "current_fuel": 850,
    "max_fuel": 1000,
    "fuel_regen_rate": 13.0,
    "time_to_full_fuel": 415,
    "hull": 450,
    "max_hull": 500,
    "shields": 200,
    "max_shields": 250,
    "weapons": 5,
    "cargo_hold": 15,
    "sensors": 8,
    "warp_drive": 7,
    "status": "operational",
    "ship_class": {
      "id": 3,
      "name": "Voyager-class",
      "class": "explorer"
    }
  },
  "message": "Ship renamed successfully",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

### Error Responses

**Validation Error (422)**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "name": [
        "The name field is required."
      ]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Ship Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- Only the ship owner can rename their ship
- Ship names are limited to 100 characters
- Empty strings or whitespace-only names are rejected
- There are no uniqueness constraints on ship names (multiple ships can have the same name)

---

## Regenerate Fuel

Manually trigger fuel regeneration calculation based on time elapsed since last update.

**Endpoint:** `POST /api/ships/{uuid}/regenerate-fuel`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

### Request Body

None required.

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "fuel_before": 450,
    "fuel_after": 850,
    "fuel_regenerated": 400,
    "max_fuel": 1000,
    "is_full": false,
    "time_to_full": 415
  },
  "message": "Fuel regenerated successfully",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `data.fuel_before` | integer | Fuel level before regeneration |
| `data.fuel_after` | integer | Fuel level after regeneration (capped at max_fuel) |
| `data.fuel_regenerated` | integer | Amount of fuel regenerated |
| `data.max_fuel` | integer | Maximum fuel capacity |
| `data.is_full` | boolean | Whether fuel tank is at maximum capacity |
| `data.time_to_full` | integer | Seconds until fuel is fully regenerated (null if already full) |

### Error Responses

**Ship Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- Fuel regeneration is **time-based** and passive; it happens automatically based on elapsed time since `fuel_last_updated_at`
- Base regeneration rate: 10 units/hour, scaled by warp drive level: `BASE_RATE * (1 + (warp_drive - 1) * 0.3)`
- This endpoint does **not** consume any resources; it simply recalculates current fuel based on time elapsed
- If fuel is already full, `fuel_regenerated` will be 0 and `time_to_full` will be null
- The `fuel_last_updated_at` timestamp is updated after regeneration to "now"
- Fuel cannot exceed `max_fuel` capacity

---

## Get Ship Status

Get comprehensive ship status including hull, fuel, cargo, and components.

**Endpoint:** `GET /api/ships/{uuid}/status`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "USS Enterprise",
    "ship_class": "Voyager-class",
    "status": "operational",
    "hull": {
      "current": 450,
      "max": 500,
      "percentage": 90.00,
      "is_damaged": false
    },
    "fuel": {
      "current": 850,
      "max": 1000,
      "percentage": 85.00,
      "time_to_full": 415
    },
    "cargo": {
      "current": 450,
      "capacity": 1500,
      "available_space": 1050
    },
    "components": {
      "weapons": 5,
      "sensors": 8,
      "warp_drive": 7
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `data.uuid` | string | Ship UUID |
| `data.name` | string | Ship name |
| `data.ship_class` | string | Ship class name (from blueprint) |
| `data.status` | string | Operational status (operational, damaged, destroyed, docked) |
| `data.hull.current` | integer | Current hull integrity points |
| `data.hull.max` | integer | Maximum hull integrity |
| `data.hull.percentage` | float | Hull percentage (0-100, rounded to 2 decimals) |
| `data.hull.is_damaged` | boolean | True if hull is below 30% of maximum |
| `data.fuel.current` | integer | Current fuel units (auto-regenerated before response) |
| `data.fuel.max` | integer | Maximum fuel capacity |
| `data.fuel.percentage` | float | Fuel percentage (0-100, rounded to 2 decimals) |
| `data.fuel.time_to_full` | integer | Seconds until full (null if already full) |
| `data.cargo.current` | integer | Currently used cargo space |
| `data.cargo.capacity` | integer | Total cargo hold capacity |
| `data.cargo.available_space` | integer | Remaining available cargo space |
| `data.components.weapons` | integer | Weapon system level |
| `data.components.sensors` | integer | Sensor array level |
| `data.components.warp_drive` | integer | Warp drive level |

### Error Responses

**Ship Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- **Fuel is automatically regenerated** before status is returned (calls `regenerateFuel()` internally)
- The `is_damaged` flag indicates critical hull damage (<30%), not just any damage
- The `current_cargo` value is calculated from the sum of all cargo items (no cached value)
- This endpoint loads the `ship` relationship to access the ship class name
- Hull and shields are separate systems; shields regenerate faster but hull damage is permanent until repaired

---

## Get Fuel Status

Get detailed fuel status and regeneration information.

**Endpoint:** `GET /api/ships/{uuid}/fuel`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "current_fuel": 850,
    "max_fuel": 1000,
    "fuel_percentage": 85.00,
    "regen_rate_per_hour": 13.0,
    "seconds_to_full": 415,
    "last_updated": "2026-02-16T14:17:10Z"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `data.current_fuel` | integer | Current fuel units (auto-regenerated before response) |
| `data.max_fuel` | integer | Maximum fuel capacity |
| `data.fuel_percentage` | float | Fuel percentage (0-100, rounded to 2 decimals) |
| `data.regen_rate_per_hour` | float | Fuel regeneration rate in units per hour |
| `data.seconds_to_full` | integer | Seconds until fuel tank is full (null if already full) |
| `data.last_updated` | string | ISO 8601 timestamp of last fuel update |

### Error Responses

**Ship Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- **Fuel is automatically regenerated** before returning status (calls `regenerateFuel()` internally)
- The `regen_rate_per_hour` is calculated as `3600 / PlayerShip::fuelRegenRate()` where `fuelRegenRate()` returns seconds per fuel unit
- Regeneration formula: `BASE_RATE * (1 + (warp_drive - 1) * 0.3)` units/hour (base rate = 10 units/hour)
- Higher warp drive levels = faster fuel regeneration
- The `last_updated` timestamp is updated to "now" after regeneration
- If fuel is already at max, `seconds_to_full` will be null

---

## Get Ship Damage

Get detailed ship damage assessment and repair cost estimate.

**Endpoint:** `GET /api/ships/{uuid}/damage`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "ship_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "hull": {
      "current": 350,
      "max": 500,
      "damage": 150,
      "percentage": 70.00
    },
    "status": "operational",
    "assessment": "good",
    "needs_repair": true,
    "repair_cost_estimate": 1500
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `data.ship_uuid` | string | Ship UUID |
| `data.hull.current` | integer | Current hull integrity points |
| `data.hull.max` | integer | Maximum hull integrity |
| `data.hull.damage` | integer | Total hull damage (max - current) |
| `data.hull.percentage` | float | Hull percentage (0-100, rounded to 2 decimals) |
| `data.status` | string | Ship status (operational, damaged, destroyed, docked) |
| `data.assessment` | string | Damage assessment level (see below) |
| `data.needs_repair` | boolean | True if ship has any hull damage |
| `data.repair_cost_estimate` | integer | Estimated repair cost in credits |

#### Assessment Levels

| Percentage | Assessment |
|------------|------------|
| 90-100% | excellent |
| 70-89% | good |
| 50-69% | moderate |
| 30-49% | damaged |
| 10-29% | critical |
| 0-9% | destroyed |

### Error Responses

**Ship Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- The `repair_cost_estimate` uses a **placeholder formula**: `damage * 10` credits
- The actual repair cost may vary based on repair shop location, player reputation, or other game mechanics
- Ships with 0 hull are considered "destroyed" but the endpoint will still return data (check `assessment` field)
- The `status` field from the database may differ from the `assessment` calculation (status is set during combat/events)
- This endpoint does **not** regenerate fuel (unlike `/status` and `/fuel` endpoints)

---

## Get Ship Upgrades

Get detailed information about ship component upgrade levels and potential.

**Endpoint:** `GET /api/ships/{uuid}/upgrades`

**Authentication:** Required (Laravel Sanctum)

### Request Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "ship_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "ship_name": "USS Enterprise",
    "upgrades": {
      "max_fuel": {
        "current_value": 1000,
        "max_level": 25,
        "bonus_from_plans": 5,
        "can_upgrade": true
      },
      "max_hull": {
        "current_value": 500,
        "max_level": 22,
        "bonus_from_plans": 2,
        "can_upgrade": true
      },
      "weapons": {
        "current_value": 5,
        "max_level": 20,
        "bonus_from_plans": 0,
        "can_upgrade": true
      },
      "cargo_hold": {
        "current_value": 1500,
        "max_level": 30,
        "bonus_from_plans": 10,
        "can_upgrade": true
      },
      "sensors": {
        "current_value": 8,
        "max_level": 20,
        "bonus_from_plans": 0,
        "can_upgrade": true
      },
      "warp_drive": {
        "current_value": 7,
        "max_level": 20,
        "bonus_from_plans": 0,
        "can_upgrade": true
      }
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `data.ship_uuid` | string | Ship UUID |
| `data.ship_name` | string | Ship name |
| `data.upgrades.{component}.current_value` | integer | Current level/value of the component |
| `data.upgrades.{component}.max_level` | integer | Maximum possible level (base + bonus from plans) |
| `data.upgrades.{component}.bonus_from_plans` | integer | Additional levels unlocked via rare plans |
| `data.upgrades.{component}.can_upgrade` | boolean | Whether component can be upgraded further |

#### Upgradeable Components

- **max_fuel**: Fuel tank capacity
- **max_hull**: Hull integrity capacity
- **weapons**: Weapon system level
- **cargo_hold**: Cargo hold capacity
- **sensors**: Sensor array level
- **warp_drive**: Warp drive level

### Error Responses

**Ship Not Found (404)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Unauthenticated (401)**
```json
{
  "message": "Unauthenticated."
}
```

### Warnings & Caveats

- The `max_level` is dynamic and includes bonuses from rare upgrade plans purchased by the player
- Base max level per component is typically 20 (configurable via `config('game_config.upgrades.max_level_per_component')`)
- Rare plans add permanent bonuses to max levels (e.g., "Advanced Sensor Plan +5" adds 5 to sensor max level)
- The `can_upgrade` field is simply `current_value < max_level`; it does **not** check if the player has credits or access to a component shop
- This endpoint loads both the `ship` relationship and the `player.plans` relationship to calculate bonuses
- Plans are stored in the `player_plans` pivot table and accessed via `$player->getAdditionalLevelsForComponent()`
- Upgrading components requires visiting a Component Shop at a trading hub (see Component Shop API)

---

## Common Response Patterns

### Standard Success Response

All successful responses follow this structure:

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message",
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

### Standard Error Response

All error responses follow this structure:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T14:23:45Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

### HTTP Status Codes

- **200 OK**: Successful request
- **401 Unauthorized**: Missing or invalid authentication token
- **404 Not Found**: Resource not found or user doesn't own the resource
- **422 Unprocessable Entity**: Validation error

---

## Authentication

All endpoints require authentication via **Laravel Sanctum**. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your-api-token}
```

Tokens are obtained via the authentication endpoints (see Auth API documentation).

---

## Rate Limiting

Ship API endpoints are subject to standard API rate limiting:
- **60 requests per minute** for authenticated users
- Rate limit headers are included in all responses

---

## Related Documentation

- **Travel API**: Ship movement and warp gate travel
- **Trading API**: Buying/selling minerals, cargo management
- **Combat API**: Ship combat and damage mechanics
- **Upgrade API**: Component shops and ship upgrades
- **Auth API**: Authentication and token management

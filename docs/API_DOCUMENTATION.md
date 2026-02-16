# Space Wars 3002 - API Documentation

**Version:** 1.0
**Base URL:** `/api`
**Authentication:** Bearer Token (Laravel Sanctum)

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [Galaxies](#2-galaxies)
3. [Players](#3-players)
4. [Ships](#4-ships)
5. [Navigation & Travel](#5-navigation--travel)
6. [Trading](#6-trading)
7. [Salvage Yard](#7-salvage-yard)
8. [Combat](#8-combat)
9. [Colonies](#9-colonies)
10. [Star Charts & Cartography](#10-star-charts--cartography)
11. [Scanning & Exploration](#11-scanning--exploration)
12. [Precursor Ship](#12-precursor-ship)
13. [Mirror Universe](#13-mirror-universe)
14. [Leaderboards & Statistics](#14-leaderboards--statistics)
15. [Notifications](#15-notifications)
16. [NPCs](#16-npcs)

---

## Response Format

All responses follow this structure:

```json
{
  "success": true,
  "message": "Description of result",
  "data": { ... }
}
```

Error responses:

```json
{
  "success": false,
  "message": "Error description",
  "error_code": "ERROR_CODE",
  "errors": { ... }
}
```

---

## 1. Authentication

### 1.1 Register User

Create a new user account.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/auth/register` |
| **Authentication** | No |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `name` | string | Yes | User's display name (max 255 chars) |
| `email` | string | Yes | Unique email address |
| `password` | string | Yes | Password (min 8 chars) |
| `password_confirmation` | string | Yes | Must match password |

**Request:**

```json
{
  "name": "Captain Kirk",
  "email": "kirk@starfleet.com",
  "password": "Enterprise1701",
  "password_confirmation": "Enterprise1701"
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "Captain Kirk",
      "email": "kirk@starfleet.com",
      "email_verified_at": null,
      "created_at": "2026-02-05T10:30:00.000000Z"
    },
    "access_token": "1|abc123def456ghi789jkl012mno345pqr678stu901",
    "token_type": "Bearer"
  }
}
```

---

### 1.2 Login

Authenticate and receive an access token.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/auth/login` |
| **Authentication** | No |
| **Expected Response Time** | ~150ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `email` | string | Yes | Registered email address |
| `password` | string | Yes | User password |

**Request:**

```json
{
  "email": "kirk@starfleet.com",
  "password": "Enterprise1701"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Captain Kirk",
      "email": "kirk@starfleet.com",
      "email_verified_at": null
    },
    "access_token": "2|xyz789abc123def456ghi012jkl345mno678pqr901",
    "token_type": "Bearer"
  }
}
```

---

### 1.3 Logout

Revoke the current access token.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/auth/logout` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Headers:**

```
Authorization: Bearer {access_token}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": null
}
```

---

### 1.4 Refresh Token

Get a new access token (revokes current token).

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/auth/refresh` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "access_token": "3|new123token456here789abc012def345ghi678jkl",
    "token_type": "Bearer"
  }
}
```

---

### 1.5 Get Current User

Retrieve authenticated user information.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/auth/me` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "name": "Captain Kirk",
    "email": "kirk@starfleet.com",
    "email_verified_at": null,
    "created_at": "2026-02-05T10:30:00.000000Z",
    "updated_at": "2026-02-05T10:30:00.000000Z"
  }
}
```

---

## 2. Galaxies

### 2.1 List Galaxies

Get user's games and available open games.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxies retrieved successfully",
  "data": {
    "my_games": [
      {
        "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "name": "Alpha Sector",
        "width": 500,
        "height": 500,
        "status": "active",
        "game_mode": "multiplayer",
        "size_tier": "medium",
        "player_count": 12,
        "max_players": 100
      }
    ],
    "open_games": [
      {
        "uuid": "b2c3d4e5-f6a7-8901-bcde-f23456789012",
        "name": "Beta Quadrant",
        "width": 300,
        "height": 300,
        "status": "active",
        "game_mode": "multiplayer",
        "size_tier": "small",
        "player_count": 3,
        "max_players": 50
      }
    ]
  }
}
```

---

### 2.2 Get Galaxy Details

Get detailed information about a specific galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{uuid}` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~150ms |

**Path Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `uuid` | string | Yes | Galaxy UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxy details retrieved successfully",
  "data": {
    "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "name": "Alpha Sector",
    "width": 500,
    "height": 500,
    "status": "active",
    "game_mode": "multiplayer",
    "size_tier": "medium",
    "max_players": 100,
    "total_players": 15,
    "active_player_count": 12,
    "total_systems": 3000,
    "sectors": 100,
    "warp_gates": 450,
    "trading_hubs": 85
  }
}
```

---

### 2.3 Get Galaxy Statistics

Get detailed statistics for a galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{uuid}/statistics` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~300ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxy statistics retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "name": "Alpha Sector",
      "dimensions": { "width": 500, "height": 500 },
      "total_systems": 3000,
      "inhabited_systems": 1200
    },
    "players": {
      "total": 15,
      "active": 12,
      "destroyed": 3
    },
    "economy": {
      "total_credits_in_circulation": 15000000,
      "average_player_credits": 1000000,
      "trading_hubs": 85
    },
    "colonies": {
      "total": 45,
      "total_population": 4500000,
      "average_development": 3.2
    },
    "combat": {
      "total_pvp_challenges": 28,
      "completed_battles": 15
    },
    "infrastructure": {
      "warp_gates": 450,
      "sectors": 100,
      "pirate_fleets": 35
    }
  }
}
```

---

### 2.4 Get Galaxy Map

Get map data for rendering (systems, warp gates, sectors).

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{uuid}/map` |
| **Authentication** | No (public, but enhanced for authenticated users) |
| **Expected Response Time** | ~500ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxy map retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "name": "Alpha Sector",
      "width": 500,
      "height": 500
    },
    "systems": [
      {
        "uuid": "sys-123-456",
        "name": "Sol Prime",
        "type": "star",
        "x": 250,
        "y": 250,
        "is_inhabited": true,
        "is_current_location": true,
        "scan": {
          "level": 3,
          "label": "Surveyed",
          "color": "#4ade80",
          "opacity": 0.9
        }
      }
    ],
    "warp_gates": [
      {
        "uuid": "gate-789",
        "from": { "x": 250, "y": 250 },
        "to": { "x": 280, "y": 230 },
        "is_mirror": false
      }
    ],
    "sectors": [
      {
        "uuid": "sector-001",
        "name": "Core Alpha",
        "x_min": 200,
        "x_max": 300,
        "y_min": 200,
        "y_max": 300,
        "danger_level": 2
      }
    ],
    "player_location": { "x": 250, "y": 250 }
  }
}
```

---

### 2.5 Get Available Size Tiers

Get configuration for galaxy creation options.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/size-tiers` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "tiers": [
      {
        "value": "small",
        "label": "Small",
        "description": "300x300, ~1000 systems",
        "width": 300,
        "height": 300,
        "star_count": 1000
      },
      {
        "value": "medium",
        "label": "Medium",
        "description": "500x500, ~3000 systems",
        "width": 500,
        "height": 500,
        "star_count": 3000
      },
      {
        "value": "large",
        "label": "Large",
        "description": "750x750, ~6000 systems",
        "width": 750,
        "height": 750,
        "star_count": 6000
      },
      {
        "value": "massive",
        "label": "Massive",
        "description": "1000x1000, ~10000 systems",
        "width": 1000,
        "height": 1000,
        "star_count": 10000
      }
    ]
  }
}
```

---

### 2.6 Create Galaxy

Create a new galaxy with the optimized generation pipeline.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/galaxies/create` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | 10-60 seconds (depending on size) |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `size_tier` | string | Yes | One of: `small`, `medium`, `large`, `massive` |
| `game_mode` | string | Yes | One of: `single_player`, `multiplayer`, `mixed` |
| `name` | string | No | Custom galaxy name |
| `skip_mirror` | boolean | No | Skip mirror universe generation |
| `skip_precursors` | boolean | No | Skip precursor ship generation |

**Request:**

```json
{
  "size_tier": "medium",
  "game_mode": "multiplayer",
  "name": "My Galaxy"
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "message": "Galaxy created successfully using optimized pipeline",
  "data": {
    "galaxy": {
      "uuid": "c3d4e5f6-a7b8-9012-cdef-345678901234",
      "name": "My Galaxy",
      "width": 500,
      "height": 500,
      "status": "active",
      "game_mode": "multiplayer",
      "size_tier": "medium"
    },
    "statistics": {
      "total_systems": 3000,
      "inhabited_systems": 1200,
      "warp_gates": 450,
      "trading_hubs": 85,
      "sectors": 100
    },
    "config": {
      "size_tier": "medium",
      "game_mode": "multiplayer",
      "npc_count": 10,
      "npc_difficulty": "medium"
    }
  }
}
```

---

### 2.7 Join Galaxy

Join an existing galaxy (creates a player) or retrieve existing player.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/galaxies/{uuid}/join` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~300ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `call_sign` | string | Yes (new players) | Unique name in galaxy (max 50 chars) |

**Request:**

```json
{
  "call_sign": "StarCommander"
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "message": "Successfully joined galaxy",
  "data": {
    "player": {
      "uuid": "player-123-456",
      "call_sign": "StarCommander",
      "credits": 10000,
      "level": 1,
      "experience": 0,
      "status": "active",
      "current_location": {
        "uuid": "poi-789",
        "name": "Alrataris",
        "type": "star",
        "x": 250,
        "y": 260
      }
    },
    "created": true,
    "sector": {
      "uuid": "sector-001",
      "name": "Core Alpha",
      "grid": { "x": 5, "y": 5 }
    },
    "total_sectors": 100
  }
}
```

---

### 2.8 Get My Player in Galaxy

Check if authenticated user has a player in a specific galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{uuid}/my-player` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Player found",
  "data": {
    "player": {
      "uuid": "player-123-456",
      "call_sign": "StarCommander",
      "credits": 15000,
      "level": 3,
      "experience": 2500
    },
    "sector": {
      "uuid": "sector-001",
      "name": "Core Alpha",
      "grid": { "x": 5, "y": 5 }
    },
    "total_sectors": 100
  }
}
```

---

### 2.9 Get Sector Details

Get information about a specific sector.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/sectors/{uuid}` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Sector information retrieved successfully",
  "data": {
    "uuid": "sector-001",
    "name": "Core Alpha",
    "galaxy": {
      "uuid": "galaxy-123",
      "name": "Alpha Sector"
    },
    "bounds": {
      "x_min": 200,
      "x_max": 300,
      "y_min": 200,
      "y_max": 300
    },
    "danger_level": 2,
    "statistics": {
      "total_systems": 30,
      "inhabited_systems": 12,
      "active_players": 3,
      "pirate_fleets": 2
    },
    "systems": [
      {
        "uuid": "sys-001",
        "name": "Sol Prime",
        "type": "star",
        "x": 250,
        "y": 250,
        "is_inhabited": true
      }
    ]
  }
}
```

---

## 3. Players

### 3.1 List My Players

Get all players belonging to the authenticated user.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uuid": "player-123",
      "call_sign": "StarCommander",
      "credits": 15000,
      "level": 3,
      "experience": 2500,
      "status": "active",
      "galaxy": {
        "uuid": "galaxy-456",
        "name": "Alpha Sector"
      },
      "current_location": {
        "uuid": "poi-789",
        "name": "Sol Prime"
      },
      "active_ship": {
        "uuid": "ship-101",
        "name": "Swift Star"
      }
    }
  ]
}
```

---

### 3.2 Get Player Details

Get detailed information about a specific player.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "uuid": "player-123",
    "call_sign": "StarCommander",
    "credits": 15000,
    "level": 3,
    "experience": 2500,
    "status": "active",
    "galaxy": {
      "uuid": "galaxy-456",
      "name": "Alpha Sector"
    },
    "current_location": {
      "uuid": "poi-789",
      "name": "Sol Prime",
      "type": "star",
      "x": 250,
      "y": 250,
      "is_inhabited": true
    },
    "active_ship": {
      "uuid": "ship-101",
      "name": "Swift Star",
      "class": "scout",
      "hull": 100,
      "max_hull": 100,
      "current_fuel": 85,
      "max_fuel": 100
    }
  }
}
```

---

### 3.3 Get Player Status

Get current status including location and ship details.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/status` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "player": {
      "uuid": "player-123",
      "call_sign": "StarCommander",
      "credits": 15000,
      "level": 3,
      "experience": 2500,
      "xp_to_next_level": 500
    },
    "location": {
      "uuid": "poi-789",
      "name": "Sol Prime",
      "type": "star",
      "coordinates": { "x": 250, "y": 250 },
      "is_inhabited": true,
      "has_trading_hub": true
    },
    "ship": {
      "uuid": "ship-101",
      "name": "Swift Star",
      "class": "scout",
      "status": "operational",
      "hull": 100,
      "max_hull": 100,
      "fuel": 85,
      "max_fuel": 100,
      "cargo": 15,
      "cargo_capacity": 100
    }
  }
}
```

---

### 3.4 Update Player

Update player information (e.g., call sign).

| Property | Value |
|----------|-------|
| **Endpoint** | `PATCH /api/players/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `call_sign` | string | No | New call sign (must be unique in galaxy) |

**Request:**

```json
{
  "call_sign": "AdmiralKirk"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Player updated successfully",
  "data": {
    "uuid": "player-123",
    "call_sign": "AdmiralKirk",
    "credits": 15000
  }
}
```

---

### 3.5 Update Player Settings

Update player-specific settings.

| Property | Value |
|----------|-------|
| **Endpoint** | `PATCH /api/players/{uuid}/settings` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `call_sign` | string | No | New call sign |
| `settings` | object | No | JSON settings object |

**Request:**

```json
{
  "call_sign": "NewCallSign",
  "settings": {
    "notifications_enabled": true,
    "auto_repair": false
  }
}
```

---

### 3.6 Delete Player

Delete a player from a galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `DELETE /api/players/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Player deleted successfully",
  "data": null
}
```

---

### 3.7 Get Player Cargo

Get cargo manifest for player's active ship.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/cargo` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "cargo_hold": 100,
    "current_cargo": 45,
    "available_space": 55,
    "items": [
      {
        "mineral": {
          "uuid": "min-001",
          "name": "Titanium",
          "rarity": "common"
        },
        "quantity": 25
      },
      {
        "mineral": {
          "uuid": "min-002",
          "name": "Deuterium",
          "rarity": "rare"
        },
        "quantity": 20
      }
    ]
  }
}
```

---

## 4. Ships

### 4.1 Get Ship Status

Get detailed status of a player's ship.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/ships/{uuid}/status` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "uuid": "ship-101",
    "name": "Swift Star",
    "class": "scout",
    "status": "operational",
    "stats": {
      "hull": 100,
      "max_hull": 100,
      "weapons": 15,
      "cargo_hold": 100,
      "current_cargo": 45,
      "sensors": 2,
      "warp_drive": 3,
      "weapon_slots": 2,
      "utility_slots": 2,
      "shield_strength": 50
    },
    "fuel": {
      "current": 85,
      "max": 100,
      "regen_rate": 1.0,
      "time_to_full": 450
    },
    "variation_traits": ["efficient_injectors", "reinforced_plating"]
  }
}
```

---

### 4.2 Get Ship Fuel Status

Get current fuel and regeneration info.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/ships/{uuid}/fuel` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "current_fuel": 85,
    "max_fuel": 100,
    "fuel_percentage": 85,
    "regen_rate_modifier": 1.2,
    "seconds_per_fuel_point": 25,
    "time_to_full_seconds": 375
  }
}
```

---

### 4.3 Regenerate Fuel

Trigger fuel regeneration calculation.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/ships/{uuid}/regenerate-fuel` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Fuel regenerated",
  "data": {
    "previous_fuel": 85,
    "current_fuel": 92,
    "fuel_regenerated": 7,
    "max_fuel": 100
  }
}
```

---

### 4.4 Rename Ship

Change ship name.

| Property | Value |
|----------|-------|
| **Endpoint** | `PATCH /api/ships/{uuid}/name` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `name` | string | Yes | New ship name |

**Request:**

```json
{
  "name": "Enterprise"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Ship renamed successfully",
  "data": {
    "uuid": "ship-101",
    "name": "Enterprise"
  }
}
```

---

### 4.5 Get Active Ship

Get player's active ship details.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{playerUuid}/ship` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "uuid": "ship-101",
    "name": "Swift Star",
    "class": "scout",
    "status": "operational",
    "is_active": true,
    "hull": 100,
    "max_hull": 100,
    "weapons": 15,
    "cargo_hold": 100,
    "sensors": 2,
    "warp_drive": 3
  }
}
```

---

### 4.6 Get Ship Catalog

Browse all available ship blueprints.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/ships/catalog` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Query Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `rarity` | string | No | Filter by rarity |
| `class` | string | No | Filter by ship class |
| `min_price` | number | No | Minimum price filter |
| `max_price` | number | No | Maximum price filter |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "ships": [
      {
        "uuid": "blueprint-001",
        "name": "Sparrow",
        "class": "starter",
        "description": "A reliable starter vessel",
        "base_price": 0,
        "cargo_capacity": 50,
        "hull_strength": 100,
        "shield_strength": 50,
        "weapon_slots": 1,
        "utility_slots": 2,
        "speed": 100,
        "rarity": "common"
      },
      {
        "uuid": "blueprint-002",
        "name": "Wraith",
        "class": "smuggler",
        "description": "Fast smuggling vessel with hidden cargo holds",
        "base_price": 35000,
        "cargo_capacity": 75,
        "hull_strength": 80,
        "shield_strength": 40,
        "weapon_slots": 2,
        "utility_slots": 3,
        "speed": 150,
        "rarity": "uncommon",
        "special_features": ["Hidden cargo hold (50 units)", "+20% fuel regeneration"]
      }
    ],
    "total_count": 9
  }
}
```

---

### 4.7 Get Shipyard

Get ships available for purchase at a trading hub.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/trading-hubs/{uuid}/shipyard` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "has_shipyard": true,
    "trading_hub_name": "Sol Station",
    "available_ships": [
      {
        "ship": {
          "uuid": "blueprint-002",
          "name": "Wraith",
          "class": "smuggler"
        },
        "current_price": 35000,
        "quantity": 3
      }
    ]
  }
}
```

---

### 4.8 Purchase Ship

Purchase a new ship at a shipyard.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/ships/purchase` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `ship_id` | integer | Yes | Ship blueprint ID to purchase |
| `trading_hub_uuid` | string | Yes | UUID of trading hub with shipyard |
| `trade_in_current_ship` | boolean | No | Trade in current ship for credit |

**Request:**

```json
{
  "ship_id": 2,
  "trading_hub_uuid": "hub-123-456",
  "trade_in_current_ship": true
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Ship purchased successfully",
  "data": {
    "ship": {
      "uuid": "ship-new-789",
      "name": "Wraith",
      "class": "smuggler"
    },
    "cost_paid": 35000,
    "trade_in_value": 5000,
    "net_cost": 30000,
    "remaining_credits": 85000
  }
}
```

---

### 4.9 Switch Active Ship

Switch to a different ship in your fleet.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/ships/switch` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `ship_uuid` | string | Yes | UUID of ship to activate |

**Request:**

```json
{
  "ship_uuid": "ship-102"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Active ship switched successfully",
  "data": {
    "active_ship": {
      "uuid": "ship-102",
      "name": "Heavy Hauler",
      "class": "cargo"
    }
  }
}
```

---

### 4.10 Get Fleet

List all ships owned by player.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/ships/fleet` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "fleet": [
      {
        "uuid": "ship-101",
        "name": "Swift Star",
        "class": "scout",
        "is_active": true,
        "status": "operational"
      },
      {
        "uuid": "ship-102",
        "name": "Heavy Hauler",
        "class": "cargo",
        "is_active": false,
        "status": "operational"
      }
    ],
    "total_ships": 2,
    "active_ship_uuid": "ship-101"
  }
}
```

---

### 4.11 Get Upgrade Options

List available upgrades for a ship.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/ships/{uuid}/upgrade-options` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "upgrades": [
      {
        "component": "weapons",
        "current_level": 15,
        "max_level": 100,
        "next_level_cost": 1500,
        "can_upgrade": true
      },
      {
        "component": "sensors",
        "current_level": 2,
        "max_level": 20,
        "next_level_cost": 2500,
        "can_upgrade": true
      },
      {
        "component": "warp_drive",
        "current_level": 3,
        "max_level": 10,
        "next_level_cost": 5000,
        "can_upgrade": true
      },
      {
        "component": "cargo_hold",
        "current_level": 100,
        "max_level": 5000,
        "next_level_cost": 500,
        "can_upgrade": true
      },
      {
        "component": "hull",
        "current_level": 100,
        "max_level": 1000,
        "next_level_cost": 1000,
        "can_upgrade": true
      }
    ]
  }
}
```

---

### 4.12 Execute Upgrade

Upgrade a ship component.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/ships/{uuid}/upgrade/{component}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Path Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `uuid` | string | Yes | Ship UUID |
| `component` | string | Yes | Component: `weapons`, `sensors`, `warp_drive`, `cargo_hold`, `hull` |

**Request:**

```json
{
  "levels": 1
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Upgrade successful",
  "data": {
    "component": "sensors",
    "previous_level": 2,
    "new_level": 3,
    "cost_paid": 2500,
    "remaining_credits": 12500
  }
}
```

---

### 4.13 Get Repair Estimate

Get repair cost estimate for ship.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/ships/{uuid}/repair-estimate` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "hull_damage": 25,
    "hull_repair_cost": 500,
    "component_damage": 10,
    "component_repair_cost": 200,
    "total_cost": 700,
    "can_afford": true
  }
}
```

---

### 4.14 Repair Ship

Repair hull damage.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/ships/{uuid}/repair/hull` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Hull repaired",
  "data": {
    "hull_repaired": 25,
    "cost_paid": 500,
    "current_hull": 100,
    "max_hull": 100,
    "remaining_credits": 14500
  }
}
```

---

## 5. Navigation & Travel

### 5.1 Get Current Location

Get detailed information about player's current location.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/location` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "location": {
      "uuid": "poi-789",
      "name": "Sol Prime",
      "type": "star",
      "x": 250,
      "y": 250,
      "is_inhabited": true
    },
    "galaxy": {
      "uuid": "galaxy-123",
      "name": "Alpha Sector"
    },
    "warp_gates_available": 5,
    "trading_hub": {
      "uuid": "hub-456",
      "name": "Sol Station",
      "type": "major",
      "has_salvage_yard": true,
      "services": ["trading", "repair", "shipyard", "salvage"]
    },
    "is_inhabited": true
  }
}
```

---

### 5.2 Get Nearby Systems

Get systems within sensor range.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/nearby-systems` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "current_location": {
      "name": "Sol Prime",
      "coordinates": { "x": 250, "y": 250 }
    },
    "sensor_range": 200,
    "sensor_level": 2,
    "systems_detected": 15,
    "nearby_systems": [
      {
        "uuid": "sys-001",
        "name": "Alpha Centauri",
        "type": "Star",
        "distance": 45.5,
        "coordinates": { "x": 280, "y": 275 },
        "is_inhabited": true,
        "has_chart": true
      },
      {
        "uuid": "sys-002",
        "name": "Unknown System",
        "type": "Star",
        "distance": 78.2,
        "coordinates": null,
        "is_inhabited": false,
        "has_chart": false
      }
    ]
  }
}
```

---

### 5.3 Scan Local Area

Get detailed scan of all nearby POIs (not just stars).

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/scan-local` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~250ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "current_location": {
      "name": "Sol Prime",
      "type": "star",
      "coordinates": { "x": 250, "y": 250 }
    },
    "sensor_range": 200,
    "sensor_level": 2,
    "total_pois_detected": 25,
    "pois_by_type": {
      "star": [
        { "uuid": "sys-001", "name": "Alpha Centauri", "distance": 45.5, "is_inhabited": true }
      ],
      "planet": [
        { "uuid": "planet-001", "name": "Earth", "distance": 0.5, "is_inhabited": true }
      ],
      "asteroid_belt": [
        { "uuid": "belt-001", "name": "Main Belt", "distance": 2.3, "is_inhabited": false }
      ]
    }
  }
}
```

---

### 5.4 Get Local Bodies

Get orbital bodies at current location (planets, moons, asteroids). Includes orbital presence data for all bodies (always visible) and a detailed defensive capability breakdown when the player's ship sensors are level 5 or higher.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/local-bodies` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "system": {
      "uuid": "poi-789",
      "name": "Sol Prime",
      "type": "star",
      "coordinates": { "x": 250, "y": 250 },
      "is_inhabited": true
    },
    "sector": {
      "uuid": "sector-001",
      "name": "Core Alpha",
      "grid": { "x": 5, "y": 5 }
    },
    "bodies": {
      "planets": [
        {
          "uuid": "planet-001",
          "name": "Earth",
          "type": "terrestrial",
          "type_label": "Terrestrial Planet",
          "orbital_index": 3,
          "is_inhabited": true,
          "has_colony": true,
          "attributes": {
            "habitable": true,
            "in_goldilocks_zone": true,
            "temperature": 288
          },
          "orbital_presence": {
            "structures": [
              {
                "type": "orbital_defense",
                "name": "Orbital Defense Platform",
                "level": 2,
                "status": "operational",
                "owner": { "uuid": "player-uuid", "call_sign": "Captain Vex" }
              }
            ],
            "system_defenses": [
              { "type": "orbital_cannon", "quantity": 4, "level": 3 }
            ]
          },
          "defensive_capability": {
            "orbital_defense_platforms": 32,
            "system_defenses": 230,
            "fighter_squadrons": 500,
            "colony_garrison": 90,
            "colony_defense_buildings": 100,
            "magnetic_mines": 3,
            "planetary_shield_hp": 10000,
            "total_damage_per_round": 952,
            "threat_level": "fortress"
          },
          "moons": [
            {
              "uuid": "moon-001",
              "name": "Luna",
              "is_inhabited": false,
              "has_colony": false,
              "orbital_presence": { "structures": [], "system_defenses": [] },
              "defensive_capability": null
            }
          ]
        }
      ],
      "moons": [],
      "asteroid_belts": [
        {
          "uuid": "belt-001",
          "name": "Main Belt",
          "type": "asteroid_belt",
          "orbital_index": 4,
          "is_inhabited": false,
          "has_colony": false,
          "attributes": {},
          "orbital_presence": { "structures": [], "system_defenses": [] },
          "defensive_capability": null
        }
      ],
      "stations": [],
      "defense_platforms": [],
      "other": []
    },
    "summary": {
      "total_bodies": 5,
      "planets": 4,
      "moons": 1,
      "asteroid_belts": 1,
      "stations": 0
    }
  }
}
```

**Field Reference:**

| Field | Always Present | Description |
|-------|:-:|-------------|
| `orbital_presence` | Yes | Large structures and system defenses visibly orbiting the body |
| `orbital_presence.structures[]` | Yes | Player-built orbital structures (type, name, level, status, owner) |
| `orbital_presence.system_defenses[]` | Yes | Pre-built system defenses (type, quantity, level) |
| `defensive_capability` | No | Detailed defense breakdown; `null` when ship sensors < 5 |
| `defensive_capability.total_damage_per_round` | Sensor 5+ | Sum of all damage sources (key number for attack planning) |
| `defensive_capability.threat_level` | Sensor 5+ | `none` / `minimal` / `moderate` / `heavy` / `fortress` |
| `defensive_capability.magnetic_mines` | Sensor 5+ | Count only (mines detonate per-hit, not per-round) |
| `defensive_capability.planetary_shield_hp` | Sensor 5+ | Shield absorb HP (not damage output) |

**Threat Level Thresholds:**

| Damage/Round | Label |
|---|---|
| 0 | `none` |
| 1-50 | `minimal` |
| 51-150 | `moderate` |
| 151-400 | `heavy` |
| 401+ | `fortress` |

**CHANGELOG**
---
**2026-02-15**
- Added `orbital_presence` to every body and moon (always visible)
- Added `defensive_capability` to every body and moon (sensor-gated, requires level >= 5)
- Added `has_colony` to moon sub-items
- Added `defense_platforms` category to bodies response
- Response now requires active ship for sensor level (defaults to 1 if no active ship)

---

### 5.5 List Warp Gates

Get available warp gates at a location.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/warp-gates/{locationUuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "location": {
      "uuid": "poi-789",
      "name": "Sol Prime"
    },
    "gate_count": 5,
    "gates": [
      {
        "uuid": "gate-001",
        "destination": {
          "uuid": "poi-790",
          "name": "Alpha Centauri",
          "type": "star",
          "x": 280,
          "y": 275
        },
        "fuel_cost": 5,
        "distance": 45.5
      },
      {
        "uuid": "gate-002",
        "destination": {
          "uuid": "poi-791",
          "name": "Proxima",
          "type": "star",
          "x": 260,
          "y": 240
        },
        "fuel_cost": 3,
        "distance": 22.4
      }
    ]
  }
}
```

---

### 5.6 Travel via Warp Gate

Travel through a warp gate to destination.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/travel/warp-gate` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~300ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `gate_uuid` | string | Yes | UUID of warp gate to use |

**Request:**

```json
{
  "gate_uuid": "gate-001"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Travel successful",
  "data": {
    "fuel_consumed": 5,
    "xp_earned": 225,
    "new_location": {
      "uuid": "poi-790",
      "name": "Alpha Centauri",
      "type": "star",
      "x": 280,
      "y": 275,
      "is_inhabited": true
    },
    "level_up": false,
    "new_level": 3,
    "pirate_encounter": null
  }
}
```

**Pirate Encounter Response:**

```json
{
  "success": true,
  "message": "Travel successful",
  "data": {
    "fuel_consumed": 5,
    "xp_earned": 225,
    "new_location": { ... },
    "pirate_encounter": {
      "encounter_uuid": "enc-123",
      "pirate_fleet": {
        "name": "Black Marauders",
        "ship_count": 3,
        "total_weapons": 150
      },
      "options": ["fight", "flee", "surrender"]
    }
  }
}
```

---

### 5.7 Jump to Coordinates

Direct jump to specific coordinates (uses more fuel).

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/travel/coordinate` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~300ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `target_x` | number | Yes | X coordinate |
| `target_y` | number | Yes | Y coordinate |

**Request:**

```json
{
  "target_x": 350,
  "target_y": 400
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Jump successful",
  "data": {
    "fuel_consumed": 25,
    "xp_earned": 500,
    "new_location": {
      "uuid": "poi-850",
      "name": "Frontier Outpost",
      "type": "star",
      "x": 350,
      "y": 400
    },
    "level_up": true,
    "new_level": 4
  }
}
```

---

### 5.8 Direct Jump to POI

Jump directly to a known location by UUID.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/travel/direct-jump` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~300ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `target_poi_uuid` | string | Yes | UUID of destination POI |

**Request:**

```json
{
  "target_poi_uuid": "poi-850"
}
```

---

### 5.9 Preview XP Gain

Calculate XP that would be earned for travel.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/travel/xp-preview` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Query Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `distance` | number | Yes | Travel distance |
| `player_uuid` | string | Yes | Player UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "distance": 100,
    "base_xp": 500,
    "bonus_xp": 0,
    "total_xp": 500
  }
}
```

---

### 5.10 Calculate Fuel Cost

Calculate fuel cost for travel.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/travel/fuel-cost` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Query Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `ship_uuid` | string | Yes | Ship UUID |
| `poi_uuid` | string | Yes (unless x,y provided) | Destination POI UUID |
| `x` | number | Yes (unless poi_uuid provided) | Destination X coordinate |
| `y` | number | Yes (unless poi_uuid provided) | Destination Y coordinate |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "from": { "uuid": "...", "name": "Current System", "x": 100, "y": 100 },
    "to": { "uuid": "...", "name": "Destination System", "x": 200, "y": 200 },
    "distance": 141.42,
    "ship": { "current_fuel": 85, "max_fuel": 100, "warp_drive": 3 },
    "warp_gate": null,
    "direct_jump": { "distance": 141.42, "fuel_cost": 48, "can_afford": true, "in_range": true, "max_range": 500 },
    "cheapest_option": "direct_jump",
    "cheapest_fuel_cost": 48,
    "can_reach": true
  }
}
```

---

## 6. Trading

### 6.1 List Nearby Trading Hubs

Get trading hubs within sensor range.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/trading-hubs` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Query Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `player_uuid` | string | Yes | Player UUID |
| `radius` | number | No | Search radius (default: sensor range) |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "hubs": [
      {
        "uuid": "hub-001",
        "name": "Sol Station",
        "type": "major",
        "location": {
          "uuid": "poi-789",
          "name": "Sol Prime",
          "distance": 0
        }
      },
      {
        "uuid": "hub-002",
        "name": "Alpha Trade Post",
        "type": "minor",
        "location": {
          "uuid": "poi-790",
          "name": "Alpha Centauri",
          "distance": 45.5
        }
      }
    ],
    "search_radius": 200
  }
}
```

---

### 6.2 Get Trading Hub Details

Get detailed information about a trading hub.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/trading-hubs/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "uuid": "hub-001",
    "name": "Sol Station",
    "type": "major",
    "is_active": true,
    "has_salvage_yard": true,
    "has_shipyard": true,
    "has_cartographer": true,
    "services": ["trading", "repair", "shipyard", "salvage", "cartography"]
  }
}
```

---

### 6.3 Get Hub Inventory

Get minerals available for trade at a hub.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/trading-hubs/{uuid}/inventory` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "hub": {
      "uuid": "hub-001",
      "name": "Sol Station"
    },
    "inventory": [
      {
        "mineral": {
          "uuid": "min-001",
          "name": "Titanium",
          "rarity": "common",
          "base_price": 100
        },
        "quantity": 500,
        "buy_price": 110,
        "sell_price": 90
      },
      {
        "mineral": {
          "uuid": "min-002",
          "name": "Deuterium",
          "rarity": "rare",
          "base_price": 500
        },
        "quantity": 50,
        "buy_price": 550,
        "sell_price": 450
      }
    ]
  }
}
```

---

### 6.4 List All Minerals

Get all minerals in the game.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/minerals` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uuid": "min-001",
      "name": "Titanium",
      "description": "Common structural metal",
      "rarity": "common",
      "base_price": 100
    },
    {
      "uuid": "min-002",
      "name": "Deuterium",
      "description": "Fusion reactor fuel",
      "rarity": "rare",
      "base_price": 500
    },
    {
      "uuid": "min-003",
      "name": "Dilithium",
      "description": "Warp drive crystals",
      "rarity": "very_rare",
      "base_price": 2500
    }
  ]
}
```

---

### 6.5 Buy Minerals

Purchase minerals from a trading hub.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/trading-hubs/{uuid}/buy` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `player_uuid` | string | Yes | Player UUID |
| `mineral_uuid` | string | Yes | Mineral UUID to buy |
| `quantity` | integer | Yes | Amount to purchase |

**Request:**

```json
{
  "player_uuid": "player-123",
  "mineral_uuid": "min-001",
  "quantity": 50
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Purchase successful",
  "data": {
    "mineral": "Titanium",
    "quantity_bought": 50,
    "price_per_unit": 110,
    "total_cost": 5500,
    "remaining_credits": 9500,
    "cargo_used": 50,
    "cargo_remaining": 50
  }
}
```

---

### 6.6 Sell Minerals

Sell minerals to a trading hub.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/trading-hubs/{uuid}/sell` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `player_uuid` | string | Yes | Player UUID |
| `mineral_uuid` | string | Yes | Mineral UUID to sell |
| `quantity` | integer | Yes | Amount to sell |

**Request:**

```json
{
  "player_uuid": "player-123",
  "mineral_uuid": "min-002",
  "quantity": 20
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Sale successful",
  "data": {
    "mineral": "Deuterium",
    "quantity_sold": 20,
    "price_per_unit": 450,
    "total_earned": 9000,
    "new_credits": 18500,
    "cargo_freed": 20
  }
}
```

---

## 7. Salvage Yard

### 7.1 Get Salvage Yard Inventory

List components available at the salvage yard.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/salvage-yard` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "hub": {
      "id": 1,
      "name": "Sol Station",
      "tier": "major"
    },
    "inventory": {
      "weapons": [
        {
          "id": 1,
          "component": {
            "id": 1,
            "uuid": "comp-001",
            "name": "Mark I Pulse Laser",
            "type": "weapon",
            "slot_type": "weapon_slot",
            "description": "Standard pulse laser",
            "slots_required": 1,
            "rarity": "common",
            "rarity_color": "gray",
            "effects": { "damage": 25, "accuracy": 0.85, "fire_rate": 1.0 },
            "requirements": null
          },
          "quantity": 5,
          "price": 5500,
          "condition": 100,
          "condition_description": "Pristine",
          "source": "manufactured",
          "source_description": "Factory New",
          "is_new": true
        }
      ],
      "utilities": [
        {
          "id": 2,
          "component": {
            "uuid": "comp-010",
            "name": "Basic Shield Regenerator",
            "type": "shield",
            "slot_type": "utility_slot",
            "effects": { "shield_regen": 5 }
          },
          "quantity": 3,
          "price": 6000,
          "condition": 85,
          "condition_description": "Good",
          "source": "salvage",
          "source_description": "Salvaged"
        }
      ]
    }
  }
}
```

---

### 7.2 Get Ship Components

List components installed on player's ship.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/ship-components` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "ship": {
      "id": 1,
      "name": "Swift Star",
      "class": "scout"
    },
    "components": {
      "weapon_slots": {
        "1": {
          "id": 5,
          "component": {
            "uuid": "comp-001",
            "name": "Mark I Pulse Laser",
            "type": "weapon",
            "rarity": "common",
            "effects": { "damage": 25 }
          },
          "slot_index": 1,
          "condition": 95,
          "is_damaged": true,
          "is_broken": false,
          "ammo": null,
          "max_ammo": null,
          "needs_ammo": false,
          "is_active": true
        }
      },
      "utility_slots": {
        "1": {
          "id": 6,
          "component": {
            "uuid": "comp-010",
            "name": "Basic Shield Regenerator",
            "type": "shield",
            "effects": { "shield_regen": 5 }
          },
          "slot_index": 1,
          "condition": 100
        }
      },
      "total_weapon_slots": 2,
      "total_utility_slots": 2
    }
  }
}
```

---

### 7.3 Purchase Component

Buy and install a component from the salvage yard.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/salvage-yard/purchase` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `inventory_id` | integer | Yes | Salvage yard inventory item ID |
| `slot_index` | integer | Yes | Which slot to install in (1-based) |
| `ship_id` | integer | No | Ship ID (defaults to active ship) |

**Request:**

```json
{
  "inventory_id": 1,
  "slot_index": 2
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Mark I Pulse Laser installed in weapon slot slot 2.",
  "data": {
    "component_id": 7,
    "credits_remaining": 9500
  }
}
```

---

### 7.4 Uninstall Component

Remove a component from your ship (optionally sell it).

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/ship-components/{componentId}/uninstall` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `sell` | boolean | No | Whether to sell the component (default: false) |

**Request:**

```json
{
  "sell": true
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Mark I Pulse Laser sold for 2,500 credits.",
  "data": {
    "credits_received": 2500,
    "credits_total": 12000
  }
}
```

---

## 8. Combat

### 8.1 Check Pirate Presence

Check if pirates are present on a warp gate route.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/warp-gates/{warpGateUuid}/pirates` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "has_pirates": true,
    "pirate_fleet": {
      "uuid": "fleet-001",
      "name": "Black Marauders",
      "faction": "Crimson Raiders",
      "ship_count": 3,
      "threat_level": "medium",
      "estimated_weapons": 150
    },
    "detection_chance": 0.85
  }
}
```

---

### 8.2 Get Combat Preview

Preview combat outcome before engaging.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/combat/preview` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "player_strength": {
      "weapons": 50,
      "hull": 100,
      "shields": 50
    },
    "enemy_strength": {
      "weapons": 150,
      "hull": 300,
      "shields": 100
    },
    "win_probability": 0.35,
    "escape_chance": 0.65,
    "recommendation": "flee"
  }
}
```

---

### 8.3 Engage Combat

Initiate combat with pirates or enemies.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/combat/engage` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~500ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `encounter_uuid` | string | Yes | UUID of the encounter |

**Request:**

```json
{
  "encounter_uuid": "enc-123"
}
```

**Response (200 OK - Victory):**

```json
{
  "success": true,
  "message": "Combat victory!",
  "data": {
    "result": "victory",
    "rounds": 5,
    "damage_taken": 25,
    "enemy_destroyed": true,
    "xp_earned": 500,
    "level_up": false,
    "salvage_available": {
      "credits": 1500,
      "cargo": [
        { "mineral": "Titanium", "quantity": 20 }
      ]
    }
  }
}
```

---

### 8.4 Attempt Escape

Try to flee from combat.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/combat/escape` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Response (200 OK - Success):**

```json
{
  "success": true,
  "message": "Escape successful!",
  "data": {
    "escaped": true,
    "damage_taken": 10,
    "fuel_consumed": 15
  }
}
```

**Response (200 OK - Failed):**

```json
{
  "success": true,
  "message": "Escape failed!",
  "data": {
    "escaped": false,
    "damage_taken": 35,
    "combat_continues": true
  }
}
```

---

### 8.5 Surrender

Surrender to enemies (lose cargo, save ship).

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/combat/surrender` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "You surrendered to the pirates",
  "data": {
    "cargo_lost": [
      { "mineral": "Titanium", "quantity": 50 }
    ],
    "credits_lost": 5000,
    "remaining_credits": 10000,
    "ship_status": "operational"
  }
}
```

---

### 8.6 Collect Salvage

Collect salvage after combat victory.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/combat/salvage` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Salvage collected",
  "data": {
    "credits_collected": 1500,
    "cargo_collected": [
      { "mineral": "Titanium", "quantity": 20 }
    ],
    "total_credits": 16500
  }
}
```

---

### 8.7 Issue PvP Challenge

Challenge another player to combat.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/pvp/challenge` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `target_player_uuid` | string | Yes | UUID of player to challenge |
| `wager` | integer | No | Credit wager (both players must match) |

**Request:**

```json
{
  "target_player_uuid": "player-456",
  "wager": 5000
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "message": "Challenge issued",
  "data": {
    "challenge_uuid": "challenge-789",
    "challenger": "StarCommander",
    "target": "SpaceAce",
    "wager": 5000,
    "status": "pending",
    "expires_at": "2026-02-05T12:00:00Z"
  }
}
```

---

### 8.8 List PvP Challenges

Get pending challenges for a player.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/pvp/challenges` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "incoming": [
      {
        "uuid": "challenge-123",
        "challenger": { "uuid": "player-456", "call_sign": "SpaceAce" },
        "wager": 5000,
        "created_at": "2026-02-05T10:00:00Z"
      }
    ],
    "outgoing": [
      {
        "uuid": "challenge-789",
        "target": { "uuid": "player-789", "call_sign": "StarRider" },
        "wager": 10000,
        "created_at": "2026-02-05T09:30:00Z"
      }
    ]
  }
}
```

---

### 8.9 Accept/Decline Challenge

Accept or decline a PvP challenge.

| Property | Value |
|----------|-------|
| **Accept Endpoint** | `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept` |
| **Decline Endpoint** | `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/decline` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

---

## 9. Colonies

### 9.1 List Colonies

Get all colonies owned by a player.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/colonies` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "uuid": "colony-001",
      "name": "New Earth",
      "location": {
        "uuid": "poi-planet-001",
        "name": "Terra Nova",
        "type": "terrestrial"
      },
      "population": 50000,
      "development_level": 3,
      "defense_rating": 150
    }
  ]
}
```

---

### 9.2 Establish Colony

Create a new colony on a colonizable body.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/colonies` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~300ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `poi_uuid` | string | Yes | UUID of planet/moon to colonize |
| `name` | string | Yes | Colony name |

**Request:**

```json
{
  "poi_uuid": "poi-planet-002",
  "name": "Hope's Landing"
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "message": "Colony established",
  "data": {
    "uuid": "colony-002",
    "name": "Hope's Landing",
    "population": 10000,
    "development_level": 1,
    "colonists_disembarked": 10000
  }
}
```

---

### 9.3 Get Colony Details

Get detailed information about a colony.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/colonies/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "uuid": "colony-001",
    "name": "New Earth",
    "population": 50000,
    "max_population": 100000,
    "development_level": 3,
    "defense_rating": 150,
    "production": {
      "credits_per_cycle": 5000,
      "resources": [
        { "mineral": "Titanium", "amount_per_cycle": 100 }
      ]
    },
    "buildings": [
      { "type": "housing", "level": 3 },
      { "type": "mining_facility", "level": 2 },
      { "type": "defense_platform", "level": 1 }
    ]
  }
}
```

---

### 9.4 Get Colony Production

Get production statistics for a colony.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/colonies/{uuid}/production` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

---

### 9.5 Upgrade Colony Development

Upgrade colony development level.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/colonies/{uuid}/upgrade` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

---

### 9.6 List Colony Buildings

Get buildings in a colony.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/colonies/{uuid}/buildings` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

---

### 9.7 Construct Building

Build a new structure in a colony.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/colonies/{uuid}/buildings` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `building_type` | string | Yes | Type of building to construct |

---

### 9.8 Abandon Colony

Abandon a colony (irreversible).

| Property | Value |
|----------|-------|
| **Endpoint** | `DELETE /api/colonies/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

---

## 10. Star Charts & Cartography

### 10.1 Get Player Star Charts

Get systems the player has charts for.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/star-charts` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "total_charts": 15,
    "systems": [
      {
        "uuid": "poi-789",
        "name": "Sol Prime",
        "coordinates": { "x": 250, "y": 250 },
        "is_inhabited": true,
        "purchased_at": "2026-02-01T10:00:00Z"
      }
    ]
  }
}
```

---

### 10.2 Get Cartographer

Check if trading hub has a cartographer and get available charts.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/trading-hubs/{uuid}/cartographer` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "has_cartographer": true,
    "cartographer_name": "Stellar Charts Ltd",
    "available_charts": [
      {
        "poi_uuid": "poi-800",
        "name": "Frontier System",
        "distance": 150,
        "price": 2500,
        "systems_revealed": 3
      }
    ]
  }
}
```

---

### 10.3 Purchase Star Chart

Buy a star chart for a system.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/star-charts/purchase` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `poi_uuid` | string | Yes | UUID of system to purchase chart for |
| `trading_hub_uuid` | string | Yes | UUID of trading hub with cartographer |

---

## 11. Scanning & Exploration

### 11.1 Scan System

Perform a sensor scan of a system.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/scan-system` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `poi_uuid` | string | Yes | UUID of system to scan |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Scan complete",
  "data": {
    "scan_level": 3,
    "scan_label": "Surveyed",
    "new_discoveries": [
      { "type": "planet", "name": "Terra Nova" },
      { "type": "asteroid_belt", "name": "Main Belt" }
    ],
    "resource_indicators": {
      "mineral_richness": "high",
      "rare_minerals_detected": true
    }
  }
}
```

---

### 11.2 Get Scan Results

Get stored scan results for a system.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/scan-results/{poiUuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

---

### 11.3 Get Exploration Log

Get player's exploration history.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/exploration-log` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

---

## 12. Precursor Ship

The legendary Precursor ship is hidden somewhere in each galaxy. Ship yard owners have heard rumors about its location, but they're all wrong. Collecting multiple rumors might help triangulate the real location.

### 12.1 Check for Rumors

Check if current location has Precursor ship rumors.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/precursor/check` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "has_rumor": true,
    "shipyard_owner": "Viktor Petrov",
    "bribe_cost": 25000,
    "already_obtained": false
  }
}
```

---

### 12.2 Get Gossip (Free)

Get free gossip about the Precursor ship (no coordinates).

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/precursor/gossip` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "gossip": "You notice the dock workers whispering among themselves.\n\nWhen you ask about the Precursor ship, Viktor Petrov looks up from their work.\n\n\"The Void Strider? Yeah, I've heard the stories. Half-million year old ship, hidden somewhere in the deep black between stars. Tech beyond anything we can build today.\"\n\nThey pause, studying you carefully.\n\n\"I might know something about where to look. But information like that... it'll cost you. 25,000 credits, and I'll tell you what I know.\"\n\n\"Fair warning though - everyone thinks they know where it is. Not everyone's right.\""
  }
}
```

---

### 12.3 Bribe for Rumor

Pay for the shipyard owner's rumored location.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/precursor/bribe` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "rumor": {
      "x": 350,
      "y": 420,
      "confidence": 0.75,
      "owner_name": "Viktor Petrov",
      "story": "I got this from a dying smuggler who claimed he'd seen it with his own eyes."
    },
    "bribe_paid": 25000,
    "remaining_credits": 75000,
    "message": "Viktor Petrov pockets your credits and leans in close..."
  }
}
```

---

### 12.4 Get Collected Rumors

Get all rumors the player has obtained.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/precursor/rumors` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "rumors": [
      {
        "hub_name": "Sol Station",
        "hub_location": "Sol Prime",
        "rumor_x": 350,
        "rumor_y": 420,
        "bribe_paid": 25000,
        "obtained_at": "2026-02-05T10:30:00Z"
      },
      {
        "hub_name": "Alpha Trade Post",
        "hub_location": "Alpha Centauri",
        "rumor_x": 380,
        "rumor_y": 400,
        "bribe_paid": 15000,
        "obtained_at": "2026-02-05T11:00:00Z"
      }
    ],
    "total_spent": 40000
  }
}
```

---

## 13. Mirror Universe

The Mirror Universe is a dangerous parallel dimension with enhanced rewards but increased risks.

### 13.1 Check Mirror Access

Check if player can access the Mirror Universe.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/mirror-access` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "can_access": true,
    "sensor_level_required": 5,
    "current_sensor_level": 6,
    "cooldown_remaining": 0,
    "mirror_gate_detected": true
  }
}
```

---

### 13.2 Enter Mirror Universe

Travel to the Mirror Universe.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/mirror/enter` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~300ms |

---

### 13.3 Get Mirror Gate Location

Find the mirror gate in a galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{uuid}/mirror-gate` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~150ms |

---

## 14. Leaderboards & Statistics

### 14.1 Overall Leaderboard

Get overall rankings for a galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{galaxyUuid}/leaderboards/overall` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~200ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "leaderboard": [
      {
        "rank": 1,
        "player": { "uuid": "player-001", "call_sign": "StarCommander" },
        "score": 125000,
        "level": 15
      },
      {
        "rank": 2,
        "player": { "uuid": "player-002", "call_sign": "SpaceAce" },
        "score": 98000,
        "level": 12
      }
    ]
  }
}
```

---

### 14.2 Combat Leaderboard

Get combat rankings.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{galaxyUuid}/leaderboards/combat` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~200ms |

---

### 14.3 Economic Leaderboard

Get economic rankings (credits, trade volume).

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{galaxyUuid}/leaderboards/economic` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~200ms |

---

### 14.4 Colonial Leaderboard

Get colonial rankings (population, colonies).

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{galaxyUuid}/leaderboards/colonial` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~200ms |

---

### 14.5 Victory Conditions

Get victory conditions for a galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{galaxyUuid}/victory-conditions` |
| **Authentication** | No (public) |
| **Expected Response Time** | ~100ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "conditions": {
      "merchant_empire": {
        "description": "Accumulate 1 billion credits",
        "target": 1000000000
      },
      "colonization": {
        "description": "Control >50% of galactic population",
        "target_percentage": 50
      },
      "conquest": {
        "description": "Control >60% of star systems",
        "target_percentage": 60
      },
      "pirate_king": {
        "description": "Seize >70% of outlaw network",
        "target_percentage": 70
      }
    }
  }
}
```

---

### 14.6 Player Victory Progress

Get player's progress toward victory conditions.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/victory-progress` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

---

## 15. Notifications

### 15.1 List Notifications

Get player notifications.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/notifications` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

---

### 15.2 Get Unread Count

Get count of unread notifications.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/players/{uuid}/notifications/unread` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

---

### 15.3 Mark as Read

Mark a notification as read.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/notifications/{notificationId}/read` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

---

### 15.4 Mark All as Read

Mark all notifications as read.

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/players/{uuid}/notifications/mark-all-read` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

---

### 15.5 Delete Notification

Delete a notification.

| Property | Value |
|----------|-------|
| **Endpoint** | `DELETE /api/players/{uuid}/notifications/{notificationId}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

---

## 16. NPCs

### 16.1 List NPCs in Galaxy

Get NPCs in a galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/galaxies/{uuid}/npcs` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~200ms |

**Query Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `archetype` | string | No | Filter by archetype |
| `status` | string | No | Filter by status |
| `difficulty` | string | No | Filter by difficulty |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "npcs": [
      {
        "uuid": "npc-001",
        "call_sign": "TraderBot-Alpha",
        "archetype": "trader",
        "difficulty": "medium",
        "level": 5,
        "credits": 25000,
        "status": "active",
        "current_activity": "trading",
        "location": {
          "id": 123,
          "name": "Sol Prime",
          "x": 250,
          "y": 250
        },
        "ship": {
          "uuid": "ship-npc-001",
          "name": "Merchant Vessel",
          "class": "cargo"
        }
      }
    ],
    "total": 10,
    "statistics": {
      "by_archetype": { "trader": 5, "pirate": 3, "explorer": 2 },
      "by_difficulty": { "easy": 3, "medium": 5, "hard": 2 }
    }
  }
}
```

---

### 16.2 Get NPC Details

Get detailed information about an NPC.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/npcs/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

---

### 16.3 Get NPC Archetypes

Get available NPC archetypes and their configurations.

| Property | Value |
|----------|-------|
| **Endpoint** | `GET /api/npcs/archetypes` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~50ms |

**Response (200 OK):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "archetypes": [
      {
        "name": "trader",
        "description": "Focus on trading and economic activities",
        "default_aggression": 0.2,
        "default_risk_tolerance": 0.3,
        "default_trade_focus": 0.9
      },
      {
        "name": "pirate",
        "description": "Aggressive, attacks other ships",
        "default_aggression": 0.9,
        "default_risk_tolerance": 0.7,
        "default_trade_focus": 0.2
      },
      {
        "name": "explorer",
        "description": "Explores unknown systems",
        "default_aggression": 0.1,
        "default_risk_tolerance": 0.5,
        "default_trade_focus": 0.3
      }
    ],
    "difficulties": [
      {
        "name": "easy",
        "credits_multiplier": 0.5,
        "combat_skill_multiplier": 0.7,
        "decision_quality": 0.6
      },
      {
        "name": "medium",
        "credits_multiplier": 1.0,
        "combat_skill_multiplier": 1.0,
        "decision_quality": 0.8
      },
      {
        "name": "hard",
        "credits_multiplier": 1.5,
        "combat_skill_multiplier": 1.3,
        "decision_quality": 0.95
      }
    ]
  }
}
```

---

### 16.4 Add NPCs to Galaxy

Add NPCs to a galaxy (owner only for single-player).

| Property | Value |
|----------|-------|
| **Endpoint** | `POST /api/galaxies/{uuid}/npcs` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~500ms |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `count` | integer | Yes | Number of NPCs to add |
| `difficulty` | string | No | Difficulty level (default: medium) |
| `archetype_distribution` | object | No | Custom distribution of archetypes |

---

### 16.5 Delete NPC

Remove an NPC from a galaxy.

| Property | Value |
|----------|-------|
| **Endpoint** | `DELETE /api/npcs/{uuid}` |
| **Authentication** | Yes (Bearer Token) |
| **Expected Response Time** | ~100ms |

---

## Error Codes Reference

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_CREDENTIALS` | 401 | Login credentials are incorrect |
| `UNAUTHENTICATED` | 401 | No valid authentication token |
| `FORBIDDEN` | 403 | Not authorized to access resource |
| `NOT_FOUND` | 404 | Resource not found |
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `DUPLICATE_CALL_SIGN` | 422 | Call sign already exists in galaxy |
| `NO_ACTIVE_SHIP` | 400 | Player has no active ship |
| `NO_LOCATION` | 400 | Player has no current location |
| `INSUFFICIENT_CREDITS` | 400 | Not enough credits for transaction |
| `INSUFFICIENT_FUEL` | 400 | Not enough fuel for travel |
| `CARGO_FULL` | 400 | Cargo hold is full |
| `GATE_NOT_FOUND` | 400 | Warp gate not found at location |
| `NPC_CONFIG_DISABLED` | 400 | NPC configuration not available |
| `GALAXY_FULL` | 400 | Galaxy at maximum capacity |
| `GALAXY_NOT_ACTIVE` | 400 | Galaxy not accepting players |
| `SINGLE_PLAYER_GALAXY` | 403 | Cannot join single-player galaxy |

---

## Rate Limits

| Endpoint Type | Rate Limit |
|---------------|------------|
| Authentication | 10 requests/minute |
| Galaxy Creation | 3 requests/hour |
| Travel | 60 requests/minute |
| Trading | 120 requests/minute |
| All Other | 300 requests/minute |

---

## Changelog

### Version 1.0 (February 2026)
- Initial API documentation
- Full coverage of all 120+ endpoints
- Sample requests and responses for all major endpoints

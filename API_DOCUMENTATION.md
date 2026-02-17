# Space Wars 3002 - Complete API Reference

> **Comprehensive endpoint-by-endpoint documentation.**
> Base URL: `/api` | Authentication: Bearer Token (Laravel Sanctum)

---

## Table of Contents

1. [Common Patterns](#common-patterns)
2. [Authentication](#authentication)
3. [Galaxy Management](#galaxy-management)
4. [Galaxy Creation & NPCs](#galaxy-creation--npcs)
5. [Galaxy Settings](#galaxy-settings)
6. [Player Management](#player-management)
7. [Player Status & Stats](#player-status--stats)
8. [Player Settings](#player-settings)
9. [Navigation](#navigation)
10. [Travel](#travel)
11. [Travel Calculations](#travel-calculations)
12. [Star Systems](#star-systems)
13. [Knowledge Map](#knowledge-map)
14. [Facilities](#facilities)
15. [Location](#location)
16. [Ships](#ships)
17. [Ship Status](#ship-status)
18. [Ship Services (Repair & Maintenance)](#ship-services-repair--maintenance)
19. [Ship Shop (Legacy)](#ship-shop-legacy)
20. [Shipyard (Unique Ships)](#shipyard-unique-ships)
21. [Upgrades](#upgrades)
22. [Plans Shop](#plans-shop)
23. [Salvage Yard & Components](#salvage-yard--components)
24. [Trading](#trading)
25. [Trading Transactions](#trading-transactions)
26. [Mining](#mining)
27. [Combat (PvE)](#combat-pve)
28. [PvP Combat](#pvp-combat)
29. [Team Combat](#team-combat)
30. [Colonies](#colonies)
31. [Colony Buildings](#colony-buildings)
32. [Colony Combat](#colony-combat)
33. [Scanning & Exploration](#scanning--exploration)
34. [Cartography & Star Charts](#cartography--star-charts)
35. [Orbital Structures](#orbital-structures)
36. [Mirror Universe](#mirror-universe)
37. [Precursor Rumors](#precursor-rumors)
38. [Notifications](#notifications)
39. [Leaderboards](#leaderboards)
40. [Victory Conditions](#victory-conditions)
41. [Market Events](#market-events)
42. [Pirate Factions](#pirate-factions)
43. [World Data (POI Types)](#world-data-poi-types)
44. [Map Summaries](#map-summaries)
45. [Sector Map](#sector-map)

---

## Common Patterns

### Authentication Header
All endpoints except those marked **Public** require:
```
Authorization: Bearer {token}
```

### Standard Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "abc-123"
  }
}
```

### Standard Error Response
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": { ... }
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "abc-123"
  }
}
```

### UUID Format
All entity identifiers use UUID v4: `550e8400-e29b-41d4-a716-446655440000`

### Common HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 202 | Accepted (async operation in progress) |
| 400 | Bad request / validation error |
| 401 | Unauthorized (missing/invalid token) |
| 403 | Forbidden (not your resource) |
| 404 | Not found |
| 409 | Conflict (duplicate action) |
| 422 | Validation failed |
| 500 | Server error |

---

## Authentication

### `POST /api/auth/register` **Public**

Register a new user account.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | User display name |
| `email` | string | Yes | Unique email address |
| `password` | string | Yes | Minimum 8 characters |
| `password_confirmation` | string | Yes | Must match `password` |

**Success Response (201):**
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "PlayerOne",
      "email": "player@example.com"
    },
    "token": "1|abc123def456..."
  }
}
```

**Errors:**
- `422` — Validation failed (email taken, password too short, etc.)

**Caveats:**
- The `token` returned should be stored securely and used for all subsequent API calls.
- Tokens do not expire automatically but can be revoked via logout.

---

### `POST /api/auth/login` **Public**

Authenticate and receive a bearer token.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `email` | string | Yes | Registered email |
| `password` | string | Yes | Account password |

**Success Response (200):**
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "PlayerOne",
      "email": "player@example.com"
    },
    "token": "2|xyz789..."
  }
}
```

**Errors:**
- `401` — Invalid credentials

**Caveats:**
- Login **revokes all existing tokens** for the user. Any previously issued tokens become invalid.
- This is a security feature: only one active session at a time.

---

### `POST /api/auth/verify-email` **Public**

Verify a user's email address.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `token` | string | Yes | Verification token from email |

**Caveats:**
- This endpoint is currently a **TODO placeholder** and returns a stub response. Email verification is not yet implemented.

---

### `POST /api/auth/logout`

Revoke the current bearer token.

**Parameters:** None

**Success Response (200):**
```json
{
  "data": {
    "message": "Logged out successfully"
  }
}
```

---

### `POST /api/auth/refresh`

Revoke current token and issue a new one.

**Parameters:** None

**Success Response (200):**
```json
{
  "data": {
    "token": "3|newtoken..."
  }
}
```

**Caveats:**
- The old token is immediately invalidated. Use the new token for all subsequent requests.

---

### `GET /api/auth/me`

Get the authenticated user's profile.

**Parameters:** None

**Success Response (200):**
```json
{
  "data": {
    "id": 1,
    "name": "PlayerOne",
    "email": "player@example.com",
    "created_at": "2026-01-01T00:00:00Z"
  }
}
```

---

## Galaxy Management

### `GET /api/galaxies`

List galaxies available to the authenticated user (their own + public galaxies).

**Parameters:** None

**Success Response (200):**
```json
{
  "data": {
    "galaxies": [
      {
        "uuid": "...",
        "name": "Alpha Centauri",
        "width": 300,
        "height": 300,
        "is_public": true,
        "player_count": 12,
        "max_players": 100,
        "created_at": "2026-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

### `GET /api/galaxies/list`

Cached version of galaxy listing. Faster but may be slightly stale.

**Parameters:** None

**Success Response:** Same structure as `GET /api/galaxies`.

**Caveats:**
- Response is cached. New galaxies may not appear immediately.

---

### `GET /api/galaxies/{uuid}` **Public**

Get detailed information about a specific galaxy.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

**Success Response (200):**
```json
{
  "data": {
    "uuid": "...",
    "name": "Alpha Centauri",
    "width": 300,
    "height": 300,
    "star_count": 3000,
    "is_public": true,
    "max_players": 100,
    "player_count": 12,
    "configuration": { ... },
    "created_at": "2026-01-01T00:00:00Z"
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `configuration` | object | Snapshotted game config at galaxy creation time |

---

### `GET /api/galaxies/{uuid}/map` **Public**

Get the full galaxy map with all POI positions.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

**Success Response (200):**
```json
{
  "data": {
    "galaxy": { "uuid": "...", "name": "...", "width": 300, "height": 300 },
    "points": [
      {
        "uuid": "...",
        "name": "Sol",
        "x": 150.5,
        "y": 200.3,
        "type": "star",
        "is_inhabited": true
      }
    ]
  }
}
```

**Caveats:**
- This returns ALL points in the galaxy. For large galaxies (3000+ stars), this can be a very large response. Consider using `/sector-map` or `/map-summaries` for lighter alternatives.

---

### `GET /api/galaxies/{uuid}/statistics` **Public**

Get aggregate statistics for a galaxy.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

**Success Response (200):**
```json
{
  "data": {
    "total_systems": 3000,
    "inhabited_systems": 1200,
    "total_players": 12,
    "total_colonies": 45,
    "total_warp_gates": 800,
    "total_trading_hubs": 780
  }
}
```

---

### `GET /api/sectors/{uuid}` **Public**

Get details about a specific sector.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Sector UUID |

**Success Response (200):**
```json
{
  "data": {
    "uuid": "...",
    "name": "Sector Alpha-1",
    "grid_x": 0,
    "grid_y": 0,
    "danger_level": 3,
    "points_of_interest": [ ... ]
  }
}
```

---

### `GET /api/galaxies/{uuid}/my-player`

Get the authenticated user's player in a specific galaxy.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

**Success Response (200):**
```json
{
  "data": {
    "uuid": "...",
    "call_sign": "SpaceAce",
    "level": 5,
    "credits": 50000.00,
    "current_poi_id": 123
  }
}
```

**Errors:**
- `404` — No player found in this galaxy for the authenticated user.

---

### `POST /api/galaxies/{uuid}/join`

Join a galaxy. Creates a new player with a starter ship, initial star charts, and lane knowledge.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |
| `call_sign` | string | body | No | Player name (auto-generated if omitted) |

**Success Response (200/201):**
```json
{
  "data": {
    "player": {
      "uuid": "...",
      "call_sign": "SpaceAce",
      "credits": 10000.00,
      "level": 1
    },
    "ship": {
      "uuid": "...",
      "name": "Lucky Star",
      "class": "starter"
    },
    "starting_location": {
      "uuid": "...",
      "name": "Sol",
      "x": 150.0,
      "y": 200.0
    }
  }
}
```

**Caveats:**
- This endpoint is **idempotent**. If the user already has a player in this galaxy, it returns the existing player instead of creating a new one.
- New players receive: starter ship, initial credits (from config), 3 free star charts to nearest inhabited systems, and lane knowledge for nearby warp gates.

---

## Galaxy Creation & NPCs

### `POST /api/galaxies/create`

Create a new galaxy with full procedural generation.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Galaxy name |
| `size_tier` | string | No | One of: `small`, `medium`, `large`, `massive` |
| `width` | integer | No | Custom width (overrides size_tier) |
| `height` | integer | No | Custom height (overrides size_tier) |
| `star_count` | integer | No | Number of stars to generate |
| `is_public` | boolean | No | Whether other users can join (default: true) |
| `max_players` | integer | No | Maximum player count |

**Success Response (202):**
```json
{
  "data": {
    "galaxy_uuid": "...",
    "status": "generating",
    "message": "Galaxy creation started"
  }
}
```

**Caveats:**
- Galaxy creation is **asynchronous**. Use `GET /api/galaxies/{uuid}/creation-status` to poll for completion.
- Creation has a 5-minute timeout.
- The creating user becomes the galaxy owner.

---

### `GET /api/galaxies/{uuid}/creation-status`

Check the status of galaxy creation.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

**Success Response (200):**
```json
{
  "data": {
    "status": "completed",
    "progress": {
      "stars": "done",
      "sectors": "done",
      "warp_gates": "done",
      "trading_hubs": "done",
      "pirates": "done"
    }
  }
}
```

| Field | Values | Description |
|-------|--------|-------------|
| `status` | `generating`, `completed`, `failed` | Overall creation state |

---

### `GET /api/galaxies/size-tiers`

Get available galaxy size presets.

**Parameters:** None

**Success Response (200):**
```json
{
  "data": {
    "tiers": {
      "small": { "width": 500, "height": 500, "stars": 500 },
      "medium": { "width": 1500, "height": 1500, "stars": 1500 },
      "large": { "width": 2500, "height": 2500, "stars": 2500 },
      "massive": { "width": 5000, "height": 5000, "stars": 5000 }
    }
  }
}
```

---

### `POST /api/galaxies/{uuid}/npcs`

Add NPC players to a galaxy. Count auto-scales by galaxy size.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |
| `count` | integer | body | No | Number of NPCs (auto-scaled if omitted) |
| `archetype` | string | body | No | NPC archetype (e.g., `trader`, `pirate`, `explorer`) |

**Success Response (201):**
```json
{
  "data": {
    "npcs_created": 10,
    "archetypes_used": ["trader", "explorer", "pirate"]
  }
}
```

---

### `GET /api/galaxies/{uuid}/npcs`

List all NPCs in a galaxy.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

---

### `GET /api/npcs/archetypes`

List available NPC archetype templates.

**Parameters:** None

---

### `GET /api/npcs/{uuid}`

Get details of a specific NPC.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | NPC UUID |

---

### `DELETE /api/npcs/{uuid}`

Remove an NPC from the galaxy.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | NPC UUID |

---

## Galaxy Settings

### `PATCH /api/galaxies/{uuid}/settings`

Update galaxy settings. **Owner only.**

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | No | New galaxy name |
| `description` | string | No | Galaxy description |
| `is_public` | boolean | No | Public visibility |
| `max_players` | integer | No | Maximum players allowed |

**Errors:**
- `403` — Not the galaxy owner.
- `422` — `max_players` cannot be less than current player count.

---

## Player Management

### `GET /api/players`

List all players for the authenticated user across all galaxies.

**Parameters:** None

**Success Response (200):**
```json
{
  "data": {
    "players": [
      {
        "uuid": "...",
        "call_sign": "SpaceAce",
        "galaxy_id": 1,
        "level": 5,
        "credits": 50000.00,
        "status": "active"
      }
    ]
  }
}
```

---

### `POST /api/players`

Create a new player in a galaxy.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `galaxy_id` | integer | Yes | Galaxy internal ID |
| `call_sign` | string | No | Player name |

**Caveats:**
- Uses integer `galaxy_id`, not UUID. This is a **known tech debt item** — prefer using `POST /api/galaxies/{uuid}/join` instead.

---

### `GET /api/players/{uuid}`

Get player details.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "uuid": "...",
    "call_sign": "SpaceAce",
    "level": 5,
    "xp": 12500,
    "credits": 50000.00,
    "turns_remaining": 150,
    "current_poi_id": 123,
    "galaxy_id": 1,
    "status": "active",
    "settings": { ... }
  }
}
```

---

### `PATCH /api/players/{uuid}`

Update player information.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `call_sign` | string | No | New call sign |

**Caveats:**
- Only `call_sign` can be changed. Other fields like credits, level, XP cannot be modified via this endpoint.

---

### `DELETE /api/players/{uuid}`

Delete a player.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Caveats:**
- This is a **destructive, irreversible action**. All associated data (ships, cargo, colonies, charts) will be deleted.

---

### `POST /api/players/{uuid}/set-active`

Set this player as the user's active player (for multi-galaxy support).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

## Player Status & Stats

### `GET /api/players/{uuid}/status`

Get real-time player status including location and ship info.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "player": {
      "uuid": "...",
      "call_sign": "SpaceAce",
      "level": 5,
      "credits": 50000.00,
      "turns_remaining": 150
    },
    "location": {
      "uuid": "...",
      "name": "Sol",
      "x": 150.0,
      "y": 200.0,
      "type": "star",
      "is_inhabited": true,
      "sector": { "uuid": "...", "name": "Sector Alpha" }
    },
    "ship": {
      "uuid": "...",
      "name": "Lucky Star",
      "hull": 100,
      "max_hull": 100,
      "current_fuel": 80,
      "max_fuel": 100,
      "cargo_hold": 50,
      "current_cargo": 12
    }
  }
}
```

---

### `GET /api/players/{uuid}/stats`

Get detailed player statistics.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "xp_progress": {
      "current_level": 5,
      "current_xp": 12500,
      "xp_to_next_level": 2500,
      "progress_percent": 83.3
    },
    "economy": {
      "total_credits_earned": 250000.00,
      "total_trades": 45,
      "total_minerals_sold": 1200
    },
    "exploration": {
      "systems_visited": 120,
      "systems_scanned": 80,
      "star_charts_owned": 15
    },
    "combat": {
      "pirates_defeated": 30,
      "pvp_wins": 5,
      "pvp_losses": 2
    },
    "mirror_universe": {
      "visits": 3,
      "last_visit": "2026-02-15T10:00:00Z",
      "cooldown_expires": "2026-02-16T10:00:00Z"
    }
  }
}
```

---

## Player Settings

### `PATCH /api/players/{uuid}/settings`

Update player settings.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `call_sign` | string | No | New call sign |
| `settings` | object | No | Settings JSON blob (merged, not replaced) |

**Caveats:**
- The `settings` field is **merged** with existing settings, not replaced. To remove a setting, set its value to `null`.

---

## Navigation

### `GET /api/players/{uuid}/location`

Get the player's current location details.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "poi": {
      "uuid": "...",
      "name": "Sol",
      "x": 150.0,
      "y": 200.0,
      "type": "star",
      "is_inhabited": true
    },
    "sector": {
      "uuid": "...",
      "name": "Sector Alpha"
    },
    "warp_gates": [
      {
        "uuid": "...",
        "destination": { "uuid": "...", "name": "Proxima", "x": 155.0, "y": 205.0 },
        "distance": 7.07,
        "is_hidden": false
      }
    ],
    "has_trading_hub": true,
    "gate_count": 3
  }
}
```

---

### `GET /api/players/{uuid}/nearby-systems`

Find systems near the player's current location.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |
| `range` | float | query | No | Search radius in light-years (default: sensor range) |

**Success Response (200):**
```json
{
  "data": {
    "current_location": { "uuid": "...", "x": 150.0, "y": 200.0 },
    "sensor_range": 500,
    "nearby_systems": [
      {
        "uuid": "...",
        "name": "Proxima",
        "x": 155.0,
        "y": 205.0,
        "distance": 7.07,
        "is_inhabited": true,
        "has_trading_hub": true,
        "travel_options": {
          "warp_gate": { "available": true, "fuel_cost": 5 },
          "direct_jump": { "available": true, "fuel_cost": 8 }
        }
      }
    ]
  }
}
```

**Caveats:**
- Uses a **bounding box + circular distance filter** for performance. The range defaults to `sensor_level * 100` units.
- Travel options include both warp gate (if connected) and direct jump fuel costs.

---

### `GET /api/players/{uuid}/scan-local`

Perform a local area scan around the player's position.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "scan_range": 500,
    "systems_detected": [ ... ],
    "anomalies": [ ... ]
  }
}
```

---

### `GET /api/players/{uuid}/local-bodies`

Get orbital bodies (planets, moons, asteroids) at the current star system.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "system": { "uuid": "...", "name": "Sol" },
    "bodies": {
      "planets": [ ... ],
      "moons": [ ... ],
      "asteroid_belts": [ ... ],
      "stations": [ ... ]
    },
    "defensive_capabilities": {
      "defense_platforms": 2,
      "total_defense_power": 150,
      "assessment": "Well-defended system"
    }
  }
}
```

**Caveats:**
- Defensive capability analysis is only shown at **sensor level 5+**.
- Bodies are categorized by type for easy UI rendering.

---

## Travel

### `GET /api/warp-gates/{locationUuid}`

List all warp gates at a specific location.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `locationUuid` | UUID | path | Yes | POI UUID |

**Success Response (200):**
```json
{
  "data": {
    "gates": [
      {
        "uuid": "...",
        "destination": {
          "uuid": "...",
          "name": "Proxima",
          "x": 155.0,
          "y": 205.0,
          "is_inhabited": true
        },
        "distance": 7.07,
        "fuel_cost": 5,
        "status": "active",
        "is_hidden": false,
        "has_pirate": false
      }
    ]
  }
}
```

**Caveats:**
- Gates are **bidirectional**. If A connects to B, then B also connects to A.
- Hidden gates are excluded unless the player has sufficient sensor level to detect them.

---

### `POST /api/players/{uuid}/travel/warp-gate`

Travel through a warp gate to its destination.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `gate_uuid` | UUID | Yes | Warp gate to travel through |

**Success Response (200):**
```json
{
  "data": {
    "success": true,
    "destination": {
      "uuid": "...",
      "name": "Proxima",
      "x": 155.0,
      "y": 205.0
    },
    "fuel_consumed": 5,
    "fuel_remaining": 75,
    "xp_earned": 50,
    "pirate_encounter": null
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `pirate_encounter` | object/null | Non-null if pirates intercepted during travel. Contains encounter UUID and details. |

**Errors:**
- `400` — Insufficient fuel.
- `400` — Not at the gate's source location.
- `400` — Gate is inactive/dormant.

**Caveats:**
- May trigger a **pirate encounter** on warp lanes with pirate fleets. Check `pirate_encounter` in the response.
- Fuel cost formula: `ceil(distance / efficiency)` where `efficiency = 1 + (warp_drive - 1) * 0.2`
- XP earned: `max(10, distance * 5)`

---

### `POST /api/players/{uuid}/travel/coordinate`

Jump directly to specific coordinates (no warp gate needed).

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `x` | float | Yes | Target X coordinate |
| `y` | float | Yes | Target Y coordinate |

**Success Response (200):**
```json
{
  "data": {
    "success": true,
    "destination": {
      "uuid": "...",
      "name": "Unknown System",
      "x": 200.0,
      "y": 300.0
    },
    "fuel_consumed": 25,
    "fuel_remaining": 55,
    "xp_earned": 100
  }
}
```

**Errors:**
- `400` — Insufficient fuel.
- `404` — No point of interest at or near those coordinates.

**Caveats:**
- Direct jumps consume **more fuel** than warp gate travel.
- This is the only way to reach **uninhabited systems** (no warp gates).

---

### `POST /api/players/{uuid}/travel/direct-jump`

Jump directly to a specific trading hub.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hub_uuid` | UUID | Yes | Trading hub UUID to jump to |

**Caveats:**
- Internally delegates to `jumpToCoordinates` using the hub's POI coordinates.
- Convenience endpoint for UI "jump to hub" buttons.

---

## Travel Calculations

### `GET /api/travel/xp-preview`

Preview XP that would be earned from a hypothetical trip.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `from_x` | float | query | Yes | Origin X |
| `from_y` | float | query | Yes | Origin Y |
| `to_x` | float | query | Yes | Destination X |
| `to_y` | float | query | Yes | Destination Y |

**Success Response (200):**
```json
{
  "data": {
    "distance": 50.5,
    "xp_reward": 252
  }
}
```

---

### `GET /api/travel/fuel-cost`

Calculate fuel cost for travel between two points.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `ship_uuid` | UUID | query | Yes | Ship to calculate for |
| `poi_uuid` | UUID | query | No* | Destination POI UUID |
| `x` | float | query | No* | Destination X coordinate |
| `y` | float | query | No* | Destination Y coordinate |

*Either `poi_uuid` OR both `x` and `y` are required.

**Success Response (200):**
```json
{
  "data": {
    "warp_gate": {
      "available": true,
      "fuel_cost": 5,
      "gate_uuid": "..."
    },
    "direct_jump": {
      "fuel_cost": 12,
      "distance": 50.5
    },
    "cheapest": "warp_gate",
    "current_fuel": 80,
    "max_fuel": 100
  }
}
```

**Caveats:**
- Returns both warp gate and direct jump options with a `cheapest` recommendation.
- Warp gate option is only `available: true` if there's a gate connection between the two points.

---

## Star Systems

### `GET /api/players/{playerUuid}/star-systems`

List star systems the player knows about.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |
| `filter` | string | query | No | Filter: `known` (default), `inhabited`, `scanned`, `charted` |
| `limit` | integer | query | No | Results per page (default: 50, max: 200) |
| `offset` | integer | query | No | Pagination offset (default: 0) |

**Success Response (200):**
```json
{
  "data": {
    "systems": [
      {
        "uuid": "...",
        "name": "Sol",
        "coordinates": { "x": 150.0, "y": 200.0 },
        "is_inhabited": true,
        "has_chart": true,
        "scan_level": 3,
        "scan_level_label": "Moderate"
      }
    ],
    "pagination": {
      "total": 150,
      "limit": 50,
      "offset": 0,
      "has_more": true
    },
    "filter": "known"
  }
}
```

| Field | Description |
|-------|-------------|
| `scan_level` | 0=unknown, 1=basic, 2=moderate, 3=detailed, 4=complete |
| `name` | Shows "Unknown System" for uncharted uninhabited systems |
| `coordinates` | `null` for uncharted uninhabited systems |

**Caveats:**
- The `known` filter combines inhabited systems + charted + scanned systems.
- Uninhabited systems that haven't been charted or scanned will not appear.
- Coordinates and names are hidden for uncharted uninhabited systems (fog of war).

---

### `GET /api/players/{playerUuid}/star-systems/{systemUuid}`

Get comprehensive star system details.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |
| `systemUuid` | UUID | path | Yes | Star system POI UUID |

**Success Response (200):**

The response is a complex nested structure built by `StarSystemResponseBuilder`. Contents vary by visibility level:
- **Inhabited systems**: Full details always visible.
- **Uninhabited systems**: Filtered by sensor level + existing scans.

```json
{
  "data": {
    "system": {
      "uuid": "...",
      "name": "Sol",
      "x": 150.0,
      "y": 200.0,
      "type": "star",
      "is_inhabited": true,
      "stellar_class": "G",
      "sector": { "uuid": "...", "name": "..." }
    },
    "bodies": [ ... ],
    "warp_gates": [ ... ],
    "trading_hub": { ... },
    "visibility_level": 5
  }
}
```

**202 Response (generation in progress):**
If the system hasn't been populated yet (lazy generation), returns 202 with polling info:
```json
{
  "data": {
    "status": "generating",
    "polling": { "retry_after": 5 }
  }
}
```

---

### `GET /api/players/{playerUuid}/star-systems/{systemUuid}/status`

Check generation status for a star system.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |
| `systemUuid` | UUID | path | Yes | Star system POI UUID |

**Success Response (200):**
```json
{
  "data": {
    "status": "ready",
    "system_uuid": "...",
    "system_name": "Sol",
    "ready": true,
    "progress": "100%",
    "percent": 100,
    "polling": null
  }
}
```

| `status` | Description |
|----------|-------------|
| `pending` | Generation hasn't started yet |
| `generating` | Generation in progress |
| `ready` | System data is ready |
| `error` | Generation failed |

---

### `GET /api/players/{playerUuid}/current-system`

Get a summary of the player's current star system.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**

Same structure as the `show` endpoint but includes an additional `current_position` field:
```json
{
  "data": {
    "system": { ... },
    "bodies": [ ... ],
    "current_position": {
      "uuid": "...",
      "name": "Sol III",
      "type": "planet",
      "type_label": "Planet",
      "is_at_star": false
    }
  }
}
```

**Caveats:**
- Resolves the parent star system even if the player is at a planet or moon.
- `is_at_star` indicates whether the player is at the star itself or an orbital body.

---

## Knowledge Map

### `GET /api/players/{playerUuid}/knowledge-map`

Get the player's fog-of-war map — only systems they know about.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |
| `sector_uuid` | UUID | query | No | Filter to a specific sector |

**Success Response (200):**
```json
{
  "data": {
    "galaxy": {
      "uuid": "...",
      "name": "Alpha Centauri",
      "width": 300,
      "height": 300
    },
    "player": {
      "uuid": "...",
      "location": { "x": 150.0, "y": 200.0, "poi_uuid": "..." },
      "sensor_range_ly": 500,
      "sensor_level": 3
    },
    "known_systems": [
      {
        "poi_uuid": "...",
        "x": 150.0,
        "y": 200.0,
        "knowledge_level": 3,
        "knowledge_label": "Surveyed",
        "freshness": 0.95,
        "source": "chart",
        "star": {
          "type": "Star",
          "stellar_class": "G",
          "stellar_description": "G-class main sequence star",
          "temperature_range_k": { "min": 5200, "max": 6000 }
        },
        "name": "Sol",
        "is_inhabited": true,
        "planet_count": 8,
        "services": {
          "trading_hub": true,
          "shipyard": false,
          "salvage_yard": true,
          "cartographer": true
        },
        "pirate_warning": null,
        "scan_level": 3,
        "has_scan_data": true
      }
    ],
    "known_lanes": [
      {
        "gate_uuid": "...",
        "from_poi_uuid": "...",
        "to_poi_uuid": "...",
        "from": { "x": 150.0, "y": 200.0 },
        "to": { "x": 155.0, "y": 205.0 },
        "has_pirate": true,
        "pirate_freshness": 0.8,
        "discovery_method": "traversal"
      }
    ],
    "danger_zones": [
      {
        "center": { "x": 160.0, "y": 210.0 },
        "radius_ly": 5,
        "source": "pirate_warning",
        "confidence": "Medium"
      }
    ],
    "statistics": {
      "total_known": 120,
      "by_level": { "1": 30, "2": 50, "3": 30, "4": 10 },
      "known_lanes": 45,
      "pirate_warnings": 5
    }
  }
}
```

**Knowledge Levels:**

| Level | Label | Visible Data |
|-------|-------|-------------|
| 1 | Detected | Position, stellar class only |
| 2 | Basic | Name, inhabited status, planet count |
| 3 | Surveyed | Services (trading, shipyard, etc.) |
| 4+ | Detailed/Complete | Full scan data |

**Caveats:**
- This is the **primary endpoint for rendering the galaxy map** in the frontend.
- Data is filtered by what the player actually knows — everything else is fog of war.
- `freshness` decays over time (1.0 = just discovered, approaches 0.0 over ~7 days).
- `pirate_freshness` similarly decays; `null` if pirate status unknown.
- Pirate confidence varies by sensor level: Low (<3), Medium (3-4), High (5+).

---

## Facilities

### `GET /api/players/{playerUuid}/facilities`

List all facilities in the player's current star system.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "system": { "uuid": "...", "name": "Sol", "is_inhabited": true },
    "facilities": {
      "trading_hubs": [
        {
          "uuid": "...",
          "name": "Sol Central Market",
          "type": "trading_post",
          "location": "Main System Hub",
          "services": [],
          "has_cartographer": true,
          "has_salvage_yard": false,
          "actions": {
            "trade": "/api/players/{uuid}/trading",
            "inventory": "/api/players/{uuid}/trading/inventory"
          }
        }
      ],
      "shipyards": [ ... ],
      "salvage_yards": [ ... ],
      "cartographers": [ ... ],
      "bars": [
        {
          "id": 1,
          "name": "The Rusty Nebula",
          "location": "Main Trading Hub",
          "atmosphere": "Dim lighting, jazz music",
          "actions": { "visit": "/api/players/{uuid}/facilities/bar" }
        }
      ],
      "trading_stations": [ ... ],
      "defense_platforms": [ ... ],
      "summary": {
        "total_trading_hubs": 1,
        "total_shipyards": 1,
        "total_salvage_yards": 0,
        "total_cartographers": 1,
        "total_bars": 1,
        "total_defense_platforms": 2,
        "has_trading": true,
        "has_ship_services": true,
        "has_salvage": false,
        "has_cartography": true,
        "has_bar": true
      },
      "available_actions": [
        {
          "id": "trading",
          "label": "Trading Hub",
          "description": "Buy and sell commodities",
          "endpoint": "/api/players/{uuid}/trading",
          "icon": "trading"
        }
      ]
    }
  }
}
```

**Caveats:**
- This is a **unified facilities view** — use it to discover what actions are available in the current system.
- `available_actions` provides ready-to-use endpoint URLs for UI buttons.
- Every inhabited system has at least one bar.

---

### `GET /api/players/{playerUuid}/facilities/bar`

Visit the bar in the current system and hear rumors.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "system": { "uuid": "...", "name": "Sol" },
    "bar": {
      "name": "The Rusty Nebula",
      "atmosphere": "Crowded, smoke-filled",
      "patrons": 17
    },
    "rumors": [
      {
        "type": "trading",
        "text": "I heard mineral prices are spiking in Sector Gamma...",
        "reliability": "low"
      }
    ],
    "tip": "The reliability of rumors varies..."
  }
}
```

**Errors:**
- `400` — Not at an inhabited system (no bar available).

---

## Location

### `POST /api/location/current/{uuid?}`

Get comprehensive system information, optionally for a specific POI or coordinates.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | No | POI UUID to look up |
| `x` | float | body | No | X coordinate to look up |
| `y` | float | body | No | Y coordinate to look up |

If no UUID or coordinates provided, returns the authenticated user's current location.

**Success Response (200):**

Response varies by **scan level**:

| Scan Level | Data Shown |
|------------|-----------|
| Unknown (0) | Position only |
| Basic (1) | Name, type, inhabited status |
| Moderate (2) | Warp gates, trading hub presence |
| Detailed (3) | Full orbital bodies, facilities |
| Complete (4+) | Everything including hidden details |

**Caveats:**
- Pre-loads children and gates **once** to avoid N+1 queries.
- This is a POST despite being a read operation — this is for body parameters support.

---

## Ships

### `GET /api/galaxies/{uuid}/my-ship`

Get the authenticated user's active ship in a specific galaxy.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

---

### `GET /api/players/{playerUuid}/ship`

Get a player's active ship.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `playerUuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "uuid": "...",
    "name": "Lucky Star",
    "ship_class": "starter",
    "hull": 100,
    "max_hull": 100,
    "current_fuel": 80,
    "max_fuel": 100,
    "fuel_last_updated_at": "2026-02-16T10:00:00Z",
    "weapons": 10,
    "sensors": 1,
    "warp_drive": 1,
    "cargo_hold": 50,
    "current_cargo": 12,
    "shield_strength": 50,
    "weapon_slots": 2,
    "utility_slots": 2,
    "status": "operational",
    "is_active": true
  }
}
```

---

### `POST /api/ships/{uuid}/regenerate-fuel`

Manually trigger fuel regeneration calculation.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

**Success Response (200):**
```json
{
  "data": {
    "current_fuel": 85,
    "max_fuel": 100,
    "fuel_regenerated": 5,
    "regeneration_rate": 10.0,
    "next_regeneration_at": "2026-02-16T11:00:00Z"
  }
}
```

**Caveats:**
- Fuel regeneration is **passive and time-based** (10 units/hour base rate).
- Formula: `regen_rate = BASE_RATE * (1 + (warp_drive - 1) * 0.3)` units/hour
- Regeneration is also auto-calculated when reading ship status.

---

### `PATCH /api/ships/{uuid}/name`

Rename a ship.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | New ship name (max 100 characters) |

---

## Ship Status

### `GET /api/ships/{uuid}/status`

Get comprehensive ship status.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

**Caveats:**
- **Auto-regenerates fuel** before returning status, so fuel values are always current.

---

### `GET /api/ships/{uuid}/fuel`

Get fuel information only.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

**Success Response (200):**
```json
{
  "data": {
    "current_fuel": 85,
    "max_fuel": 100,
    "fuel_percentage": 85.0,
    "regeneration_rate": 10.0,
    "time_to_full": "1.5 hours"
  }
}
```

---

### `GET /api/ships/{uuid}/upgrades`

Get ship upgrade levels and capabilities.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

---

### `GET /api/ships/{uuid}/damage`

Get ship damage assessment.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

**Success Response (200):**
```json
{
  "data": {
    "hull": 75,
    "max_hull": 100,
    "hull_percentage": 75.0,
    "assessment": "moderate",
    "components": [
      { "name": "weapons", "current": 8, "max": 10, "status": "damaged" }
    ]
  }
}
```

| Assessment | Range |
|-----------|-------|
| `excellent` | 90-100% |
| `good` | 70-89% |
| `moderate` | 50-69% |
| `damaged` | 25-49% |
| `critical` | 1-24% |
| `destroyed` | 0% |

---

## Ship Services (Repair & Maintenance)

### `GET /api/ships/{uuid}/repair-estimate`

Get cost estimate for repairs.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

**Success Response (200):**
```json
{
  "data": {
    "hull_repair": {
      "current": 75,
      "max": 100,
      "cost": 2500.00
    },
    "component_repairs": [
      { "name": "weapons", "current": 8, "base": 10, "cost": 500.00 }
    ],
    "total_cost": 3000.00
  }
}
```

**Caveats:**
- Detects **downgraded components** — components below the ship blueprint's base values.

---

### `POST /api/ships/{uuid}/repair/hull`

Repair ship hull damage.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

**Errors:**
- `400` — Insufficient credits.
- `400` — Hull already at maximum.

---

### `POST /api/ships/{uuid}/repair/components`

Repair damaged ship components.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

---

### `POST /api/ships/{uuid}/repair/all`

Repair everything (hull + all components).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

---

### `GET /api/ships/{uuid}/maintenance`

Get maintenance status overview.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

---

## Ship Shop (Legacy)

> These endpoints use the `trading_hub_ships` inventory system. For the newer unique-ship system, see [Shipyard](#shipyard-unique-ships).

### `GET /api/trading-hubs/{uuid}/shipyard`

Check if a trading hub has a shipyard and list available ships.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Trading hub UUID or POI UUID |

---

### `GET /api/ships/catalog`

Browse the full ship catalog with optional filters.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `rarity` | string | query | No | Filter by rarity tier |
| `class` | string | query | No | Filter by ship class (starter, smuggler, etc.) |
| `min_price` | float | query | No | Minimum base price |
| `max_price` | float | query | No | Maximum base price |

**Success Response (200):**
```json
{
  "data": {
    "ships": [
      {
        "uuid": "...",
        "name": "Scout Mk I",
        "class": "starter",
        "description": "...",
        "base_price": 5000.00,
        "cargo_capacity": 50,
        "hull_strength": 100,
        "shield_strength": 50,
        "weapon_slots": 2,
        "speed": 10,
        "rarity": "common",
        "requirements": { "level": 1 },
        "can_afford": true,
        "meets_requirements": true,
        "special_features": ["Hidden cargo hold (20 units)"]
      }
    ]
  }
}
```

---

### `POST /api/players/{uuid}/ships/purchase`

Purchase a ship, optionally trading in the current one.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ship_uuid` | UUID | Yes | Ship blueprint UUID to purchase |
| `trade_in` | boolean | No | Trade in current ship for credit (default: false) |

**Success Response (200):**
```json
{
  "data": {
    "ship": { "uuid": "...", "name": "...", "class": "..." },
    "cost": 50000.00,
    "trade_in_value": 15000.00,
    "net_cost": 35000.00,
    "credits_remaining": 15000.00
  }
}
```

**Caveats:**
- Trade-in value = **50% of base price * condition multiplier** (minimum 50% condition).
- The traded-in ship and all its cargo are **permanently deleted**.

---

### `POST /api/players/{uuid}/ships/switch`

Switch active ship (if player owns multiple).

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ship_uuid` | UUID | Yes | Ship to make active |

---

### `GET /api/players/{uuid}/ships/fleet`

List all ships owned by the player.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

## Shipyard (Unique Ships)

> The Shipyard system sells **unique pre-rolled ships** with individual stats and rarity tiers. Each ship in inventory is one-of-a-kind.

### `GET /api/systems/{uuid}/shipyard`

List available ships at a system's shipyard. Triggers lazy inventory generation on first visit.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | System POI UUID |

**Success Response (200):**
```json
{
  "data": {
    "system": { "uuid": "...", "name": "Sol" },
    "ships": [
      {
        "uuid": "...",
        "name": "Defiant Chaser",
        "rarity": "rare",
        "rarity_label": "Rare",
        "rarity_color": "#0ea5e9",
        "price": 75000.00,
        "blueprint": { "name": "Battlecruiser Mk II", "class": "battleship" },
        "stats": {
          "hull_strength": 250,
          "shield_strength": 120,
          "cargo_capacity": 80,
          "speed": 12,
          "weapon_slots": 4,
          "utility_slots": 3,
          "max_fuel": 150,
          "sensors": 2,
          "warp_drive": 2,
          "weapons": 30
        },
        "is_sold": false
      }
    ]
  }
}
```

**Caveats:**
- Inventory is **lazily generated** on first visit and persists forever.
- Each ship has unique pre-rolled stats based on its rarity tier.
- Sold ships (`is_sold: true`) remain visible but cannot be purchased.

---

### `GET /api/shipyard-inventory/{uuid}`

Get detailed view of a specific shipyard inventory item.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Inventory item UUID |

**Success Response (200):**

Same as above but includes additional detail fields:
```json
{
  "data": {
    "uuid": "...",
    "name": "...",
    "variation_traits": ["reinforced_hull", "efficient_engines"],
    "attributes": { ... },
    ...
  }
}
```

---

### `POST /api/players/{uuid}/shipyard/purchase`

Purchase a unique ship from shipyard inventory.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `inventory_uuid` | UUID | Yes | Shipyard inventory item UUID |
| `custom_name` | string | No | Custom name for the ship (max 100 chars) |

**Success Response (200):**
```json
{
  "data": {
    "ship": {
      "uuid": "...",
      "name": "Defiant Chaser",
      "hull": 250,
      "max_hull": 250,
      "cargo_hold": 80,
      "weapons": 30,
      "sensors": 2,
      "warp_drive": 2
    },
    "credits_remaining": 25000.00
  }
}
```

**Errors:**
- `400` — Insufficient credits.
- `400` — Ship requirements not met.
- `404` — Inventory item not found.
- `422` — Invalid `inventory_uuid`.

**Caveats:**
- What you see in the shop is what you get — stats are **not re-rolled** on purchase.
- The inventory item is marked as sold after purchase.

---

## Upgrades

### `GET /api/ships/{uuid}/upgrade-options`

List available upgrade options for a ship.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

---

### `GET /api/ships/{uuid}/upgrade/{component}`

Get detailed info about upgrading a specific component.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |
| `component` | string | path | Yes | Component name: `weapons`, `sensors`, `warp_drive`, `hull`, `cargo`, `shields` |

**Success Response (200):**
```json
{
  "data": {
    "component": "sensors",
    "current_level": 2,
    "next_level": 3,
    "cost": 7500.00,
    "can_afford": true,
    "max_level": 10,
    "plan_bonus": 1,
    "effective_max_level": 11,
    "stat_changes": {
      "scan_range": { "current": 200, "next": 300 }
    }
  }
}
```

---

### `POST /api/ships/{uuid}/upgrade/{component}`

Execute an upgrade on a ship component.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |
| `component` | string | path | Yes | Component to upgrade |

**Success Response (200):**
```json
{
  "data": {
    "component": "sensors",
    "new_level": 3,
    "cost_paid": 7500.00,
    "credits_remaining": 42500.00
  }
}
```

**Caveats:**
- Cost formula: `base_cost * (1 + current_level * 0.5)`
- Plans grant bonus levels beyond the normal max.

---

### `GET /api/players/{uuid}/plans`

List upgrade plans owned by the player.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

### `GET /api/upgrade-costs`

Get upgrade cost formulas and base values.

**Parameters:** None

---

### `GET /api/upgrade-limits`

Get maximum upgrade levels for each component.

**Parameters:** None

---

## Plans Shop

### `GET /api/trading-hubs/{uuid}/plans-shop`

Check if a trading hub sells upgrade plans and list available ones.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Trading hub UUID or POI UUID |

**Success Response (200):**
```json
{
  "data": {
    "has_plans_shop": true,
    "trading_hub_name": "Sol Central Market",
    "available_plans": [
      {
        "plan": {
          "id": 1,
          "name": "Sensor Enhancement Mk II",
          "component": "sensors",
          "additional_levels": 1,
          "rarity": "uncommon",
          "price": 25000.00,
          "requirements": { "min_level": 5 }
        },
        "owned_count": 0,
        "current_bonus": 0,
        "projected_bonus": 1
      }
    ]
  }
}
```

| Field | Description |
|-------|-------------|
| `owned_count` | How many of this plan the player already owns |
| `current_bonus` | Total bonus levels from owned copies |
| `projected_bonus` | What bonus would be after purchasing one more |

**Caveats:**
- Plans are **stackable** — buying multiple copies of the same plan increases the bonus.
- Not all trading hubs sell plans. Check `has_plans_shop` first.

---

### `GET /api/plans/catalog`

Browse all upgrade plans in the game.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `component` | string | query | No | Filter by component type |
| `rarity` | string | query | No | Filter by rarity |
| `min_price` | float | query | No | Minimum price |
| `max_price` | float | query | No | Maximum price |

---

### `POST /api/players/{uuid}/plans/purchase`

Purchase an upgrade plan.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `plan_id` | integer | Yes | Plan ID |
| `trading_hub_uuid` | UUID | Yes | Hub where the plan is being purchased |

**Success Response (200):**
```json
{
  "data": {
    "plan": { ... },
    "cost_paid": 25000.00,
    "remaining_credits": 25000.00,
    "owned_count": 1,
    "total_bonus": 1
  }
}
```

**Errors:**
- `400` — Hub doesn't sell plans.
- `400` — Plan not available at this hub.
- `400` — Level requirement not met.
- `400` — Insufficient credits.

**Caveats:**
- Unlike most purchases, credits are deducted via direct assignment (`player->credits -= price`) — this is a known tech debt item (should use `deductCredits()`).

---

## Salvage Yard & Components

### `GET /api/players/{uuid}/salvage-yard`

List salvage yard inventory at the player's current trading hub.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "hub": {
      "id": 1,
      "name": "Sol Central Market",
      "tier": "major"
    },
    "inventory": {
      "weapons": [ ... ],
      "shields": [ ... ],
      "engines": [ ... ]
    }
  }
}
```

**Errors:**
- `400` — Not at a trading hub with a salvage yard (`NO_SALVAGE_YARD`).

---

### `GET /api/systems/{uuid}/salvage-yard`

Browse salvage yard inventory at a specific system POI. Triggers lazy generation on first visit.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | System POI UUID |

---

### `GET /api/players/{uuid}/ship-components`

Get components installed on the player's active ship.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "ship": {
      "id": 1,
      "name": "Lucky Star",
      "class": "Starter Scout"
    },
    "components": [
      {
        "id": 1,
        "slot_type": "weapon",
        "slot_index": 1,
        "component": { "name": "Pulse Laser", "rarity": "common", "stats": { ... } }
      }
    ]
  }
}
```

---

### `POST /api/players/{uuid}/salvage-yard/purchase`

Purchase a component from the salvage yard and install it.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `inventory_id` | integer | Yes | Salvage yard inventory item ID |
| `slot_index` | integer | Yes | Slot to install into (1-based) |
| `ship_id` | integer | No | Target ship ID (defaults to active ship) |

**Success Response (200):**
```json
{
  "data": {
    "component_id": 5,
    "credits_remaining": 42000.00
  }
}
```

**Errors:**
- `400` — Not at a salvage yard (`NO_SALVAGE_YARD`).
- `400` — Item not available at this salvage yard (`ITEM_NOT_AVAILABLE`).
- `400` — Ship not found (`SHIP_NOT_FOUND`).
- `400` — Purchase failed (insufficient credits, slot occupied, etc.).

---

### `POST /api/players/{uuid}/ship-components/{componentId}/uninstall`

Uninstall a component from the player's ship, optionally selling it.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |
| `componentId` | integer | path | Yes | Component ID |
| `sell` | boolean | body | No | Sell to salvage yard for credits (default: false) |

**Success Response (200):**
```json
{
  "data": {
    "credits_received": 500,
    "credits_total": 42500.00
  }
}
```

**Errors:**
- `403` — Component not on your ship.
- `404` — Component not found.

---

### `POST /api/players/{uuid}/salvage-yard/sell-ship`

Sell an entire ship to the salvage yard for lump-sum credits.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ship_uuid` | UUID | Yes | Ship UUID to sell |

**Success Response (200):**
```json
{
  "data": {
    "credits_received": 25000.00,
    "components_salvaged": 3,
    "credits_total": 67000.00
  }
}
```

**Errors:**
- `400` — Cannot sell your only ship.
- `400` — Not at a salvage yard.
- `404` — Ship not found.

**Caveats:**
- Selling a ship **permanently deletes** it and all its cargo.
- Installed components are extracted and added to the salvage yard's inventory.
- Lump sum formula: `blueprint.base_price * rarity_multiplier * sell_percentage * condition_percentage`

---

## Trading

### `GET /api/trading-hubs`

List trading hubs near the player's current location.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `player_uuid` | UUID | query | Yes | Player UUID for location context |
| `range` | float | query | No | Search radius |

**Success Response (200):**
```json
{
  "data": {
    "hubs": [
      {
        "uuid": "...",
        "name": "Sol Central Market",
        "poi_uuid": "...",
        "distance": 0.0,
        "is_active": true,
        "type": "trading_post"
      }
    ]
  }
}
```

**Caveats:**
- Uses a **spatial bounding box** for performance.

---

### `GET /api/trading-hubs/{uuid}`

Get detailed trading hub information.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Trading hub UUID or POI UUID |

**Caveats:**
- Accepts **both** TradingHub UUID and POI UUID — resolved internally.

---

### `GET /api/trading-hubs/{uuid}/inventory`

Get the mineral inventory at a trading hub.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Trading hub UUID or POI UUID |

**Success Response (200):**
```json
{
  "data": {
    "hub": { "uuid": "...", "name": "..." },
    "inventory": [
      {
        "mineral": { "id": 1, "name": "Iron Ore", "base_value": 100.00 },
        "quantity": 500,
        "buy_price": 120.00,
        "sell_price": 80.00
      }
    ]
  }
}
```

---

### `GET /api/minerals`

List all mineral types in the game.

**Parameters:** None

**Caveats:**
- Response is **cached for 1 hour**.

---

## Trading Transactions

### `POST /api/trading-hubs/{uuid}/buy`

Buy minerals from a trading hub.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `player_uuid` | UUID | Yes | Player UUID |
| `mineral_id` | integer | Yes | Mineral to buy |
| `quantity` | integer | Yes | Amount to buy |

**Success Response (200):**
```json
{
  "data": {
    "mineral": "Iron Ore",
    "quantity": 10,
    "total_cost": 1200.00,
    "credits_remaining": 48800.00,
    "cargo_used": 22,
    "cargo_capacity": 50,
    "xp_earned": 50
  }
}
```

**Errors:**
- `400` — Insufficient credits.
- `400` — Insufficient cargo space.
- `400` — Hub doesn't have enough stock.

---

### `POST /api/trading-hubs/{uuid}/sell`

Sell minerals to a trading hub.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `player_uuid` | UUID | Yes | Player UUID |
| `mineral_id` | integer | Yes | Mineral to sell |
| `quantity` | integer | Yes | Amount to sell |

**Success Response (200):**
```json
{
  "data": {
    "mineral": "Iron Ore",
    "quantity": 10,
    "total_revenue": 800.00,
    "credits_after": 49600.00,
    "cargo_freed": 10,
    "xp_earned": 40
  }
}
```

**Caveats:**
- Sell prices are always lower than buy prices (spread).
- Trading earns XP.

---

### `GET /api/players/{uuid}/cargo`

Get the player's cargo manifest.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "cargo": [
      { "mineral": "Iron Ore", "quantity": 12, "value": 960.00 }
    ],
    "current_cargo": 12,
    "cargo_capacity": 50,
    "total_value": 960.00
  }
}
```

---

### `GET /api/trading/affordability`

Calculate how much of a mineral the player can afford.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `player_uuid` | UUID | query | Yes | Player UUID |
| `hub_uuid` | UUID | query | Yes | Trading hub UUID |
| `mineral_id` | integer | query | Yes | Mineral ID |

**Success Response (200):**
```json
{
  "data": {
    "max_affordable": 41,
    "max_cargo_space": 38,
    "max_purchasable": 38,
    "limiting_factor": "cargo_space",
    "unit_price": 120.00,
    "total_cost": 4560.00
  }
}
```

---

## Mining

### `GET /api/poi/{uuid}/mining-opportunities`

Get available mining opportunities at a POI.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | POI UUID (asteroid belt, planet, etc.) |

---

### `POST /api/colonies/{uuid}/mining/start`

Start automated mining at a colony.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Colony UUID |

---

### `POST /api/ships/{uuid}/mining/extract`

Manually extract resources using the ship.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Ship UUID |

**Caveats:**
- Extraction efficiency depends on sensor level.
- Extracted resources are capped by available cargo space.

---

## Combat (PvE)

### `GET /api/warp-gates/{warpGateUuid}/pirates`

Check for pirate presence on a warp lane.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `warpGateUuid` | UUID | path | Yes | Warp gate UUID |

---

### `GET /api/pirate-encounters/{encounterUuid}`

Get details of a specific pirate encounter.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `encounterUuid` | UUID | path | Yes | Encounter UUID |

---

### `GET /api/players/{uuid}/combat/preview`

Preview combat odds before engaging.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

### `POST /api/players/{uuid}/combat/escape`

Attempt to flee from a pirate encounter.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

### `POST /api/players/{uuid}/combat/surrender`

Surrender to pirates (pay tribute/lose cargo).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

### `POST /api/players/{uuid}/combat/engage`

Engage in combat with pirates.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

### `POST /api/players/{uuid}/combat/salvage`

Collect salvage after winning combat.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

## PvP Combat

### `POST /api/players/{uuid}/pvp/challenge`

Issue a PvP challenge to another player.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `target_uuid` | UUID | Yes | UUID of player to challenge |
| `wager` | float | No | Optional credit wager |
| `team_size` | integer | No | Team size (for team battles) |

---

### `GET /api/players/{uuid}/pvp/challenges`

List pending PvP challenges (sent and received).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

### `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept`

Accept a PvP challenge.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |
| `challengeUuid` | UUID | path | Yes | Challenge UUID |

---

### `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/decline`

Decline a PvP challenge.

---

### `DELETE /api/players/{uuid}/pvp/challenge/{challengeUuid}`

Cancel a PvP challenge you issued.

---

### `GET /api/combat-sessions/{uuid}`

Get details of a combat session (in-progress or completed).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Combat session UUID |

---

## Team Combat

### `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/invite`

Invite an ally to join your team for a PvP challenge.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ally_uuid` | UUID | Yes | UUID of player to invite |

---

### `GET /api/players/{uuid}/team-invitations`

List pending team invitations.

---

### `POST /api/players/{uuid}/team-invitations/{invitationId}/accept`

Accept a team invitation.

---

### `POST /api/players/{uuid}/team-invitations/{invitationId}/decline`

Decline a team invitation.

---

### `GET /api/pvp/challenge/{challengeUuid}/teams`

Get team composition for a PvP challenge.

---

### `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept-team`

Accept a team PvP challenge (starts the battle).

---

## Colonies

### `GET /api/players/{uuid}/colonies`

List all colonies owned by the player.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

---

### `POST /api/players/{uuid}/colonies`

Establish a new colony on an uninhabited world.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `poi_uuid` | UUID | Yes | POI UUID of the world to colonize |
| `name` | string | No | Colony name |

**Errors:**
- `400` — POI is not habitable.
- `400` — POI already has a colony.
- `400` — Insufficient credits.

**Caveats:**
- Colony cost: `credits * development_level` (starts at level 1).
- Requires colonists on the ship.

---

### `GET /api/colonies/{uuid}`

Get colony details.

---

### `PUT /api/colonies/{uuid}`

Update colony settings.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | No | New colony name |

---

### `DELETE /api/colonies/{uuid}`

Abandon a colony.

**Caveats:**
- **Irreversible.** All buildings and population are lost.

---

### `GET /api/colonies/{uuid}/production`

Get colony resource production details.

---

### `POST /api/colonies/{uuid}/upgrade`

Upgrade colony development level.

**Caveats:**
- Max development level: **10**.
- Cost scales with level: `credits * dev_level` for credits, `minerals * dev_level` for minerals.

---

### `GET /api/colonies/{uuid}/ship-production`

Get ship production capabilities.

---

## Colony Buildings

### `GET /api/colonies/{uuid}/buildings`

List all buildings in a colony.

---

### `POST /api/colonies/{uuid}/buildings`

Construct a new building.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | Yes | Building type |

**Available building types:**

| Type | Description |
|------|-------------|
| `mine` | Mineral extraction |
| `factory` | Manufacturing |
| `barracks` | Military garrison |
| `shield_generator` | Colony defense |
| `research_lab` | Technology research |
| `shipyard` | Ship construction |
| `habitat` | Population housing |
| `trading_post` | Commerce facility |

---

### `PUT /api/colonies/{uuid}/buildings/{buildingUuid}`

Upgrade (or repair) a building.

---

### `DELETE /api/colonies/{uuid}/buildings/{buildingUuid}`

Demolish a building.

---

## Colony Combat

### `GET /api/colonies/{uuid}/defenses`

Get colony defensive capabilities.

---

### `POST /api/players/{uuid}/attack-colony/{colonyUuid}`

Attack another player's colony.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ally_uuids` | array | No | UUIDs of allied players joining the attack |

---

### `POST /api/colonies/{uuid}/fortify`

Spend credits to improve colony defenses and garrison.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `credits` | float | Yes | Amount of credits to invest in fortification |
| `target` | string | No | What to fortify: `defense` or `garrison` |

---

## Scanning & Exploration

### `POST /api/players/{uuid}/scan-system`

Scan a star system to reveal information.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `poi_uuid` | UUID | No | System to scan (defaults to current location) |

**Success Response (200):**
```json
{
  "data": {
    "scan_level": 3,
    "scan_level_label": "Moderate",
    "system": { ... },
    "bodies_discovered": 5,
    "anomalies_found": 1
  }
}
```

**Caveats:**
- Scan range is limited by **sensor level**.
- Higher sensor levels reveal more detailed information.

---

### `GET /api/players/{uuid}/scan-results/{poiUuid}`

Get existing scan results for a system.

---

### `GET /api/players/{uuid}/exploration-log`

Get the player's exploration history.

---

### `POST /api/players/{uuid}/bulk-scan-levels`

Get scan levels for multiple POIs at once.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `poi_ids` | array | Yes | Array of POI IDs (max 500) |

**Caveats:**
- Maximum **500 POIs** per request to prevent abuse.

---

### `GET /api/players/{uuid}/system-data/{poiUuid}`

Get combined system and scan data for a POI.

---

## Cartography & Star Charts

### `GET /api/players/{uuid}/star-charts`

Get all star charts owned by the player.

---

### `GET /api/trading-hubs/{uuid}/cartographer`

Get cartographer details at a trading hub.

**Caveats:**
- Only ~30% of trading hubs have a Stellar Cartographer.

---

### `GET /api/star-charts/preview`

Preview what systems a chart purchase would reveal.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `player_uuid` | UUID | query | Yes | Player UUID |
| `poi_uuid` | UUID | query | Yes | System to chart |

---

### `GET /api/star-charts/pricing`

Get chart pricing for a system.

**Caveats:**
- Pricing uses **exponential formula**: `base_price * (multiplier ^ (unknown_count - 1)) * markup`
- This means charts get progressively more expensive — discourages repeated small purchases.

---

### `POST /api/players/{uuid}/star-charts/purchase`

Purchase a star chart.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `poi_uuid` | UUID | Yes | System to purchase chart for |
| `hub_uuid` | UUID | Yes | Cartographer's trading hub |

---

### `GET /api/star-charts/system/{poiUuid}`

Get system info from star chart data.

---

## Orbital Structures

### `GET /api/poi/{uuid}/orbital-structures`

List all orbital structures at a body (planet, moon, etc.).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | POI UUID |

---

### `GET /api/players/{uuid}/orbital-structures`

List all orbital structures owned by the player.

---

### `POST /api/players/{uuid}/orbital-structures/build`

Build a new orbital structure.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `poi_uuid` | UUID | Yes | POI to build at |
| `type` | string | Yes | Structure type (e.g., `mining_platform`, `defense_station`) |

**Success Response (201):**

Returns the created structure via `OrbitalStructureResource`.

**Errors:**
- `400` — Build failed (insufficient resources, invalid location, etc.).

---

### `GET /api/orbital-structures/{uuid}`

Get details of a specific orbital structure.

---

### `PUT /api/orbital-structures/{uuid}/upgrade`

Upgrade an orbital structure.

**Errors:**
- `400` — Upgrade failed (insufficient resources, max level, etc.).
- `403` — Not the structure owner.

---

### `DELETE /api/orbital-structures/{uuid}`

Demolish an orbital structure.

**Errors:**
- `400` — Demolish failed.
- `403` — Not the structure owner.

---

### `POST /api/orbital-structures/{uuid}/collect`

Collect resources from a mining platform.

**Success Response (200):**
```json
{
  "data": {
    "extracted": [
      { "mineral": "Iron Ore", "quantity": 50 }
    ]
  }
}
```

**Errors:**
- `400` — Not a mining platform (`EXTRACTION_FAILED`).
- `403` — Not the structure owner.

---

## Mirror Universe

### `GET /api/players/{uuid}/mirror-access`

Check if the player can access the mirror universe.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "can_access": true,
    "sensor_level": 5,
    "required_sensor_level": 5,
    "cooldown_active": false,
    "cooldown_expires": null,
    "at_gate": true,
    "gate_uuid": "..."
  }
}
```

**Caveats:**
- Requires **sensor level 5+** to detect the mirror gate.
- 24-hour cooldown between visits.
- Only 1 mirror gate per galaxy.

---

### `GET /api/galaxies/{uuid}/mirror-gate`

Find the mirror universe gate in a galaxy.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

---

### `POST /api/players/{uuid}/mirror/enter`

Enter the mirror universe.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Errors:**
- `400` — Sensor level too low.
- `400` — Cooldown active.
- `400` — Not at the mirror gate location.

**Caveats:**
- Mirror universe has: **2x resources**, **1.5x trading prices**, **3x rare mineral spawns**, **2x pirate difficulty**, **1.5x pirate fleet sizes**.
- This is a high-risk, high-reward area.

---

## Precursor Rumors

> The legendary Precursor ship is hidden somewhere in each galaxy. Ship yard owners think they know where it is — they're all wrong. But comparing rumors might help narrow it down.

### `GET /api/players/{uuid}/precursor/check`

Check if the current location's ship yard has a rumor available.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "has_trading_hub": true,
    "trading_hub_name": "Sol Central Market",
    "has_shipyard": true,
    "has_rumor": true,
    "already_obtained": false,
    "bribe_cost": 5000.00,
    "owner_name": "Greasy Pete",
    "can_afford": true
  }
}
```

---

### `GET /api/players/{uuid}/precursor/gossip`

Get free gossip about the Precursor ship (no coordinates).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Errors:**
- `400` — Not at a trading hub.

---

### `POST /api/players/{uuid}/precursor/bribe`

Bribe the ship yard owner for their rumored location.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |

**Success Response (200):**
```json
{
  "data": {
    "rumor": {
      "location": { "x": 250.0, "y": 180.0 },
      "description": "The old mechanic swears he saw it near...",
      "confidence": "dubious"
    },
    "bribe_paid": 5000.00,
    "remaining_credits": 45000.00
  }
}
```

**Errors:**
- `400` — Not at a trading hub.
- `400` — Insufficient credits / no rumor available.
- `409` — Already obtained this rumor.

**Caveats:**
- **Every rumor is wrong.** The locations are incorrect, but collecting multiple rumors from different ship yards may help triangulate the real location.

---

### `GET /api/players/{uuid}/precursor/rumors`

Get all rumors the player has collected.

**Success Response (200):**
```json
{
  "data": {
    "rumors": [ ... ],
    "total_rumors": 5,
    "total_invested": 25000.00,
    "hint": "Each ship yard believes they know where the Precursor ship is hidden..."
  }
}
```

---

## Notifications

### `GET /api/players/{uuid}/notifications`

List player notifications with optional filters.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Player UUID |
| `read` | boolean | query | No | Filter by read status |
| `type` | string | query | No | Filter by notification type |

---

### `GET /api/players/{uuid}/notifications/unread`

Get unread notification count.

**Success Response (200):**
```json
{
  "data": {
    "unread_count": 5
  }
}
```

---

### `POST /api/players/{uuid}/notifications/{notificationId}/read`

Mark a notification as read.

---

### `DELETE /api/players/{uuid}/notifications/{notificationId}`

Delete a notification.

---

### `POST /api/players/{uuid}/notifications/mark-all-read`

Mark all notifications as read.

---

### `POST /api/players/{uuid}/notifications/clear-read`

Delete all read notifications.

---

## Leaderboards

### `GET /api/galaxies/{galaxyUuid}/leaderboards/overall` **Public**

Get the overall leaderboard for a galaxy.

---

### `GET /api/galaxies/{galaxyUuid}/leaderboards/combat` **Public**

Get the combat leaderboard.

---

### `GET /api/galaxies/{galaxyUuid}/leaderboards/economic` **Public**

Get the economic leaderboard.

---

### `GET /api/galaxies/{galaxyUuid}/leaderboards/colonial` **Public**

Get the colonial leaderboard.

---

### `GET /api/players/{uuid}/ranking`

Get the player's ranking across all leaderboards.

---

### `GET /api/players/{uuid}/statistics`

Get detailed player statistics for leaderboard context.

---

## Victory Conditions

### `GET /api/galaxies/{galaxyUuid}/victory-conditions` **Public**

Get the victory conditions for a galaxy.

**Success Response (200):**
```json
{
  "data": {
    "conditions": [
      {
        "type": "merchant_empire",
        "label": "Merchant Empire",
        "description": "Accumulate 1 billion credits",
        "target": 1000000000,
        "current_leader": { "call_sign": "SpaceAce", "progress": 0.05 }
      },
      {
        "type": "colonization",
        "label": "Galactic Colonizer",
        "description": "Control >50% of galactic population",
        "target_percent": 50
      },
      {
        "type": "conquest",
        "label": "Galactic Conqueror",
        "description": "Control >60% of star systems",
        "target_percent": 60
      },
      {
        "type": "pirate_king",
        "label": "Pirate King",
        "description": "Seize >70% of outlaw network",
        "target_percent": 70
      }
    ]
  }
}
```

---

### `GET /api/players/{uuid}/victory-progress`

Get the player's progress toward each victory condition.

---

### `GET /api/galaxies/{galaxyUuid}/victory-leaders` **Public**

Get leaders for each victory condition.

---

## Market Events

### `GET /api/galaxies/{galaxyUuid}/market-events` **Public**

Get active market events in a galaxy.

**Success Response (200):**
```json
{
  "data": {
    "events": [
      {
        "uuid": "...",
        "type": "price_surge",
        "mineral": "Gold",
        "price_multiplier": 1.5,
        "description": "Gold prices surging due to sector conflict",
        "expires_at": "2026-02-17T00:00:00Z",
        "affected_hubs": 5
      }
    ]
  }
}
```

---

### `GET /api/market-events/{eventUuid}` **Public**

Get details of a specific market event.

---

### `GET /api/trading-hubs/{uuid}/active-events`

Get active market events affecting a specific trading hub.

---

## Pirate Factions

### `GET /api/galaxies/{galaxyUuid}/pirate-factions` **Public**

List pirate factions in a galaxy.

---

### `GET /api/pirate-factions/{factionUuid}` **Public**

Get details of a specific pirate faction.

---

### `GET /api/pirate-factions/{factionUuid}/captains` **Public**

List captains in a pirate faction.

---

### `GET /api/players/{uuid}/pirate-reputation`

Get the player's reputation with all pirate factions.

**Success Response (200):**
```json
{
  "data": {
    "reputations": [
      {
        "faction": { "uuid": "...", "name": "Shadow Corsairs" },
        "reputation": -50,
        "standing": "Hostile",
        "kills": 5
      }
    ]
  }
}
```

**Caveats:**
- Reputation formula: `kills * -10`
- Standings: Allied, Friendly, Neutral, Unfriendly, Hostile, Hated

---

## World Data (POI Types)

### `GET /api/poi-types` **Public**

Get all POI types.

**Success Response (200):**
```json
{
  "data": {
    "types": [
      {
        "id": 1,
        "code": "star",
        "label": "Star",
        "description": "Main sequence star",
        "domain": "stellar",
        "category": "stellar",
        "capabilities": {
          "is_habitable": false,
          "is_mineable": false,
          "is_orbital": false,
          "is_dockable": true,
          "can_have_trading_hub": true,
          "can_have_warp_gate": true
        },
        "base_danger_level": 0,
        "icon": "star",
        "color": "#FFD700",
        "produces_minerals": false
      }
    ]
  }
}
```

---

### `GET /api/poi-types/{idOrCode}` **Public**

Get a specific POI type by numeric ID or string code.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `idOrCode` | string/int | path | Yes | POI type ID or code (e.g., `star`, `planet`, `1`) |

---

### `GET /api/poi-types/by-category` **Public**

Get POI types grouped by category.

---

### `GET /api/poi-types/habitable` **Public**

Get only habitable POI types.

---

### `GET /api/poi-types/mineable` **Public**

Get only mineable POI types (includes which minerals they produce).

---

## Map Summaries

### `GET /api/galaxies/{uuid}/map-summaries`

Get lightweight system data for known systems (optimized for map rendering/tooltips).

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

**Success Response (200):**
```json
{
  "data": {
    "systems": [
      {
        "uuid": "...",
        "name": "Sol",
        "x": 150,
        "y": 200,
        "type": "star",
        "is_inhabited": true,
        "has_trading": true,
        "gate_count": 5,
        "is_current_location": true
      }
    ],
    "total": 120,
    "player_location": {
      "uuid": "...",
      "x": 150,
      "y": 200
    }
  }
}
```

**Caveats:**
- Only returns systems the player has **discovered** (via star charts, scans, or current location).
- Minimal data per system — use this for rendering maps, not for detailed info.
- `has_trading` checks for active trading hubs.
- `gate_count` only counts non-hidden gates.

---

## Sector Map

### `GET /api/galaxies/{uuid}/sector-map`

Get aggregate stats per sector instead of individual stars.

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | UUID | path | Yes | Galaxy UUID |

**Success Response (200):**
```json
{
  "data": {
    "galaxy": {
      "uuid": "...",
      "name": "Alpha Centauri",
      "width": 300,
      "height": 300
    },
    "grid_size": { "cols": 10, "rows": 10 },
    "sectors": [
      {
        "uuid": "...",
        "name": "Sector Alpha-1",
        "grid_x": 0,
        "grid_y": 0,
        "bounds": {
          "x_min": 0.0,
          "x_max": 30.0,
          "y_min": 0.0,
          "y_max": 30.0
        },
        "danger_level": 3,
        "total_systems": 25,
        "inhabited_systems": 10,
        "has_trading": true,
        "player_count": 2
      }
    ],
    "player_sector_uuid": "...",
    "player_location": { "x": 150, "y": 200 }
  }
}
```

**Caveats:**
- Reduces a 3000-star galaxy from 30k+ lines to ~400 sector entries.
- Uses optimized correlated subqueries (2 database round-trips total).
- `player_count` shows active players per sector.
- Ideal for zoomed-out galaxy overview rendering.

---

## General Warnings & Caveats

1. **UUID vs Integer IDs**: Public-facing endpoints use UUIDs. Some internal endpoints (`POST /api/players` with `galaxy_id`) still use integer IDs — this is tech debt.

2. **Fog of War**: Many endpoints filter data based on what the player has discovered. Uninhabited systems are invisible until charted or scanned.

3. **Sensor-Gated Information**: Ship sensor level controls: scan range, scan detail, hidden gate detection, pirate detection confidence, and defensive capability analysis visibility.

4. **Fuel Regeneration**: Fuel regenerates passively over time. Ship status endpoints auto-calculate current fuel before responding, so values are always up-to-date.

5. **Trading Hub UUID Resolution**: Endpoints that accept a trading hub UUID also accept a POI UUID — the system resolves both transparently.

6. **Lazy Generation**: Shipyard and salvage yard inventories are generated on first visit and persist forever. First access may be slightly slower.

7. **Pirate Encounters**: Travel via warp gates may trigger pirate encounters. Always check the `pirate_encounter` field in travel responses.

8. **Exponential Pricing**: Star chart prices use exponential scaling. Buying many small charts is far more expensive than buying a few large coverage charts.

9. **Destructive Actions**: Deleting players, abandoning colonies, and selling ships are **irreversible**. All associated data is permanently removed.

10. **Rate Limiting**: No explicit rate limiting is documented, but bulk endpoints (like `bulk-scan-levels`) have built-in limits (max 500 items).

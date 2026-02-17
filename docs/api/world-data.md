# World Data API Reference

API endpoints for accessing Point of Interest (POI) types and managing orbital structures. All responses follow the standardized API response format with `success`, `data`, `message`, and `meta` fields.

---

## Table of Contents

- [POI Types](#poi-types)
  - [GET /api/poi-types](#get-apipoi-types)
  - [GET /api/poi-types/by-category](#get-apipoi-typesby-category)
  - [GET /api/poi-types/habitable](#get-apipoi-typeshabitable)
  - [GET /api/poi-types/mineable](#get-apipoi-typesmineable)
  - [GET /api/poi-types/{idOrCode}](#get-apipoi-typesidorcode)
- [Orbital Structures](#orbital-structures)
  - [GET /api/poi/{uuid}/orbital-structures](#get-apipoiuuidorbital-structures)
  - [GET /api/orbital-structures/{uuid}](#get-apiorbital-structuresuuid)
  - [GET /api/players/{uuid}/orbital-structures](#get-apiplayersuuidorbital-structures)
  - [POST /api/players/{uuid}/orbital-structures/build](#post-apiplayersuuidorbital-structuresbuild)
  - [PUT /api/orbital-structures/{uuid}/upgrade](#put-apiorbital-structuresuuidupgrade)
  - [POST /api/orbital-structures/{uuid}/collect](#post-apiorbital-structuresuuidcollect)
  - [DELETE /api/orbital-structures/{uuid}](#delete-apiorbital-structuresuuid)
- [Data Structures](#data-structures)
  - [POI Type Object](#poi-type-object)
  - [Orbital Structure Object](#orbital-structure-object)
  - [Structure Types](#structure-types)

---

## POI Types

### GET /api/poi-types

Retrieve all Point of Interest types in the game. POI types define the characteristics of stars, planets, moons, asteroid belts, nebulae, stations, and other spatial entities.

**Authentication:** Not required

**Request Parameters:** None

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "types": [
      {
        "id": 1,
        "code": "STAR",
        "label": "Star",
        "description": "A luminous sphere of plasma held together by its own gravity",
        "domain": "stellar",
        "category": "star",
        "capabilities": {
          "is_habitable": false,
          "is_mineable": false,
          "is_orbital": false,
          "is_dockable": false,
          "can_have_trading_hub": false,
          "can_have_warp_gate": true
        },
        "base_danger_level": 0,
        "icon": "star",
        "color": "#FFD700",
        "produces_minerals": []
      },
      {
        "id": 2,
        "code": "PLANET",
        "label": "Planet",
        "description": "A celestial body orbiting a star",
        "domain": "planetary",
        "category": "planet",
        "capabilities": {
          "is_habitable": true,
          "is_mineable": true,
          "is_orbital": true,
          "is_dockable": true,
          "can_have_trading_hub": true,
          "can_have_warp_gate": false
        },
        "base_danger_level": 0,
        "icon": "planet",
        "color": "#4169E1",
        "produces_minerals": ["fe", "ni", "cu"]
      }
      // ... more POI types
    ]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique identifier for the POI type |
| `code` | string | Machine-readable code (e.g., "STAR", "PLANET", "ASTEROID_BELT") |
| `label` | string | Human-readable display name |
| `description` | string | Detailed description of the POI type |
| `domain` | string | Broad classification domain (e.g., "stellar", "planetary", "special") |
| `category` | string | Specific category within domain (e.g., "star", "planet", "station") |
| `capabilities.is_habitable` | boolean | Whether this type can support colonies or habitation |
| `capabilities.is_mineable` | boolean | Whether this type can be mined for resources |
| `capabilities.is_orbital` | boolean | Whether this type orbits another body |
| `capabilities.is_dockable` | boolean | Whether ships can dock here |
| `capabilities.can_have_trading_hub` | boolean | Whether trading hubs can spawn here |
| `capabilities.can_have_warp_gate` | boolean | Whether warp gates can connect to this type |
| `base_danger_level` | integer | Base danger rating (0-10, affects pirate encounters) |
| `icon` | string | Icon identifier for UI rendering |
| `color` | string | Hex color code for visualization |
| `produces_minerals` | array | Array of mineral symbols this type produces (e.g., ["fe", "au", "pt"]) |

**Warnings & Caveats:**

- POI types are seeded into the database at installation and rarely change
- The `produces_minerals` array is empty for non-mineable types
- This endpoint returns ALL types; use filtered endpoints for specific subsets

---

### GET /api/poi-types/by-category

Retrieve POI types grouped by category for easier UI organization.

**Authentication:** Not required

**Request Parameters:** None

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "categories": {
      "star": [
        {
          "id": 1,
          "code": "STAR",
          "label": "Star",
          "color": "#FFD700",
          "icon": "star"
        },
        {
          "id": 10,
          "code": "RED_DWARF",
          "label": "Red Dwarf",
          "color": "#FF4500",
          "icon": "red-dwarf"
        }
      ],
      "planet": [
        {
          "id": 2,
          "code": "PLANET",
          "label": "Planet",
          "color": "#4169E1",
          "icon": "planet"
        },
        {
          "id": 3,
          "code": "TERRESTRIAL",
          "label": "Terrestrial Planet",
          "color": "#8B4513",
          "icon": "terrestrial"
        }
      ],
      "station": [
        {
          "id": 20,
          "code": "SPACE_STATION",
          "label": "Space Station",
          "color": "#C0C0C0",
          "icon": "station"
        }
      ]
      // ... more categories
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Response Fields:**

The `categories` object contains keys representing category names, each with an array of simplified POI type objects:

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | POI type ID |
| `code` | string | POI type code |
| `label` | string | Display name |
| `color` | string | Hex color code |
| `icon` | string | Icon identifier |

**Warnings & Caveats:**

- This endpoint returns a simplified version of POI types (no capabilities, description, etc.)
- Category keys are dynamic based on actual data in the database
- Common categories include: "star", "planet", "moon", "belt", "nebula", "station", "anomaly"

---

### GET /api/poi-types/habitable

Retrieve only POI types that support habitation/colonization.

**Authentication:** Not required

**Request Parameters:** None

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "types": [
      {
        "id": 2,
        "code": "PLANET",
        "label": "Planet",
        "description": "A celestial body orbiting a star"
      },
      {
        "id": 3,
        "code": "TERRESTRIAL",
        "label": "Terrestrial Planet",
        "description": "Rocky planet with solid surface"
      },
      {
        "id": 5,
        "code": "OCEAN",
        "label": "Ocean World",
        "description": "Planet covered entirely in water"
      }
      // ... more habitable types
    ]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | POI type ID |
| `code` | string | POI type code |
| `label` | string | Display name |
| `description` | string | Detailed description |

**Warnings & Caveats:**

- Returns minimal fields optimized for colony placement UI
- All returned types have `is_habitable: true`
- Typically includes terrestrial planets, ocean worlds, super-earths, and moons

---

### GET /api/poi-types/mineable

Retrieve only POI types that can be mined for resources.

**Authentication:** Not required

**Request Parameters:** None

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "types": [
      {
        "id": 15,
        "code": "ASTEROID_BELT",
        "label": "Asteroid Belt",
        "produces_minerals": ["fe", "ni", "pt", "au"]
      },
      {
        "id": 3,
        "code": "TERRESTRIAL",
        "label": "Terrestrial Planet",
        "produces_minerals": ["fe", "cu", "al"]
      },
      {
        "id": 11,
        "code": "ICE_GIANT",
        "label": "Ice Giant",
        "produces_minerals": ["h2o", "ch4", "nh3"]
      }
      // ... more mineable types
    ]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | POI type ID |
| `code` | string | POI type code |
| `label` | string | Display name |
| `produces_minerals` | array | Array of mineral symbols this type produces |

**Warnings & Caveats:**

- Returns minimal fields optimized for mining UI
- All returned types have `is_mineable: true`
- `produces_minerals` array indicates which resources can be extracted, but actual availability depends on individual POI instances

---

### GET /api/poi-types/{idOrCode}

Retrieve a specific POI type by its numeric ID or string code.

**Authentication:** Not required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `idOrCode` | string/integer | Yes | POI type ID (numeric) or code (string like "STAR", "PLANET") |

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "id": 15,
    "code": "ASTEROID_BELT",
    "label": "Asteroid Belt",
    "description": "A region of space filled with rocky debris",
    "domain": "planetary",
    "category": "belt",
    "capabilities": {
      "is_habitable": false,
      "is_mineable": true,
      "is_orbital": true,
      "is_dockable": false,
      "can_have_trading_hub": false,
      "can_have_warp_gate": false
    },
    "base_danger_level": 2,
    "icon": "asteroid-belt",
    "color": "#A0522D",
    "produces_minerals": ["fe", "ni", "pt", "au", "ir"]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | `NOT_FOUND` | POI type not found |

**Error Response Example:**

```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "POI type not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Warnings & Caveats:**

- The `idOrCode` parameter is case-insensitive for string codes
- Numeric IDs are checked first, then string codes
- Common codes: "STAR", "PLANET", "TERRESTRIAL", "GAS_GIANT", "ASTEROID_BELT", "NEBULA", "SPACE_STATION"

---

## Orbital Structures

Orbital structures are player-built installations that orbit planets and moons. They include mining platforms, defense platforms, magnetic mines, and orbital bases. All structure endpoints require authentication except for listing structures at a POI.

### GET /api/poi/{uuid}/orbital-structures

List all orbital structures at a specific celestial body (planet/moon).

**Authentication:** Not required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (UUID) | Yes | UUID of the Point of Interest (planet/moon) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "uuid": "8f7a1b2c-3d4e-5f6a-7b8c-9d0e1f2a3b4c",
      "structure_type": "mining_platform",
      "structure_label": "Mining Platform",
      "name": "Mining Platform",
      "level": 2,
      "status": "operational",
      "health": 390,
      "max_health": 390,
      "construction_progress": 100,
      "construction_started_at": "2026-02-10T14:30:00+00:00",
      "construction_completed_at": "2026-02-12T14:30:00+00:00",
      "attributes": {
        "extraction_rate": 50,
        "storage": 500
      },
      "operating_costs": {
        "credits_per_cycle": 50,
        "minerals_per_cycle": 0
      },
      "poi": {
        "uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
        "name": "Kepler-442 b"
      },
      "player": {
        "uuid": "6d5c4b3a-2f1e-0d9c-8b7a-6f5e4d3c2b1a",
        "call_sign": "StarHunter"
      },
      "created_at": "2026-02-10T14:30:00+00:00",
      "updated_at": "2026-02-12T14:30:00+00:00"
    },
    {
      "uuid": "9e8d7c6b-5a4f-3e2d-1c0b-9a8f7e6d5c4b",
      "structure_type": "orbital_defense",
      "structure_label": "Orbital Defense Platform",
      "name": "Orbital Defense Platform",
      "level": 1,
      "status": "operational",
      "health": 500,
      "max_health": 500,
      "construction_progress": 100,
      "construction_started_at": "2026-02-11T08:00:00+00:00",
      "construction_completed_at": "2026-02-13T08:00:00+00:00",
      "attributes": {
        "defense_rating": 100,
        "damage_per_round": 25
      },
      "operating_costs": {
        "credits_per_cycle": 100,
        "minerals_per_cycle": 5
      },
      "poi": {
        "uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
        "name": "Kepler-442 b"
      },
      "player": {
        "uuid": "5c4b3a2f-1e0d-9c8b-7a6f-5e4d3c2b1a0d",
        "call_sign": "DefenderPrime"
      },
      "created_at": "2026-02-11T08:00:00+00:00",
      "updated_at": "2026-02-13T08:00:00+00:00"
    }
  ],
  "message": "Orbital structures retrieved",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | `NOT_FOUND` | Point of interest not found |

**Warnings & Caveats:**

- Returns all structures at the POI regardless of owner (public visibility)
- Destroyed structures (status: "destroyed") are excluded from results
- Empty array returned if no structures exist at the location
- The `player` relationship is eagerly loaded for all structures

---

### GET /api/orbital-structures/{uuid}

Retrieve detailed information about a specific orbital structure.

**Authentication:** Not required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (UUID) | Yes | UUID of the orbital structure |

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "uuid": "8f7a1b2c-3d4e-5f6a-7b8c-9d0e1f2a3b4c",
    "structure_type": "mining_platform",
    "structure_label": "Mining Platform",
    "name": "Mining Platform",
    "level": 2,
    "status": "operational",
    "health": 390,
    "max_health": 390,
    "construction_progress": 100,
    "construction_started_at": "2026-02-10T14:30:00+00:00",
    "construction_completed_at": "2026-02-12T14:30:00+00:00",
    "attributes": {
      "extraction_rate": 50,
      "storage": 500
    },
    "operating_costs": {
      "credits_per_cycle": 50,
      "minerals_per_cycle": 0
    },
    "poi": {
      "uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
      "name": "Kepler-442 b"
    },
    "player": {
      "uuid": "6d5c4b3a-2f1e-0d9c-8b7a-6f5e4d3c2b1a",
      "call_sign": "StarHunter"
    },
    "created_at": "2026-02-10T14:30:00+00:00",
    "updated_at": "2026-02-12T14:30:00+00:00"
  },
  "message": "Orbital structure retrieved",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | `NOT_FOUND` | Orbital structure not found |

**Warnings & Caveats:**

- Returns structure regardless of ownership (public visibility)
- Includes both POI and player relationship data
- Status can be: "constructing", "operational", or "destroyed"

---

### GET /api/players/{uuid}/orbital-structures

List all orbital structures owned by a specific player.

**Authentication:** Required (Laravel Sanctum)

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (UUID) | Yes | UUID of the player |

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "uuid": "8f7a1b2c-3d4e-5f6a-7b8c-9d0e1f2a3b4c",
      "structure_type": "mining_platform",
      "structure_label": "Mining Platform",
      "name": "Mining Platform Alpha",
      "level": 2,
      "status": "operational",
      "health": 390,
      "max_health": 390,
      "construction_progress": 100,
      "construction_started_at": "2026-02-10T14:30:00+00:00",
      "construction_completed_at": "2026-02-12T14:30:00+00:00",
      "attributes": {
        "extraction_rate": 50,
        "storage": 500
      },
      "operating_costs": {
        "credits_per_cycle": 50,
        "minerals_per_cycle": 0
      },
      "poi": {
        "uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
        "name": "Kepler-442 b"
      },
      "player": {
        "uuid": "6d5c4b3a-2f1e-0d9c-8b7a-6f5e4d3c2b1a",
        "call_sign": "StarHunter"
      },
      "created_at": "2026-02-10T14:30:00+00:00",
      "updated_at": "2026-02-12T14:30:00+00:00"
    },
    {
      "uuid": "9e8d7c6b-5a4f-3e2d-1c0b-9a8f7e6d5c4b",
      "structure_type": "orbital_base",
      "structure_label": "Orbital Base",
      "name": "Trading Outpost Gamma",
      "level": 1,
      "status": "constructing",
      "health": 1000,
      "max_health": 1000,
      "construction_progress": 60,
      "construction_started_at": "2026-02-15T12:00:00+00:00",
      "construction_completed_at": null,
      "attributes": {
        "docking_slots": 4,
        "cargo_capacity": 2000,
        "repair": true
      },
      "operating_costs": {
        "credits_per_cycle": 200,
        "minerals_per_cycle": 10
      },
      "poi": {
        "uuid": "6c5b4a3f-2e1d-0c9b-8a7f-6e5d4c3b2a1f",
        "name": "Ross 128 c"
      },
      "player": {
        "uuid": "6d5c4b3a-2f1e-0d9c-8b7a-6f5e4d3c2b1a",
        "call_sign": "StarHunter"
      },
      "created_at": "2026-02-15T12:00:00+00:00",
      "updated_at": "2026-02-16T10:00:00+00:00"
    }
  ],
  "message": "Player orbital structures retrieved",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 401 | `UNAUTHORIZED` | User not authenticated |
| 403 | `FORBIDDEN` | Authenticated user does not own this player |
| 404 | `NOT_FOUND` | Player not found |

**Warnings & Caveats:**

- Requires authentication via Laravel Sanctum token
- Only returns structures owned by the authenticated player
- Destroyed structures are excluded from results
- The `poi` relationship is eagerly loaded to show location names

---

### POST /api/players/{uuid}/orbital-structures/build

Build a new orbital structure at a planet or moon.

**Authentication:** Required (Laravel Sanctum)

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (UUID) | Yes | UUID of the player building the structure |

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `poi_uuid` | string (UUID) | Yes | UUID of the planet/moon where structure will be built |
| `type` | string | Yes | Structure type code: "mining_platform", "orbital_defense", "magnetic_mine", or "orbital_base" |

**Request Body Example:**

```json
{
  "poi_uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
  "type": "mining_platform"
}
```

**Success Response (201 Created):**

```json
{
  "success": true,
  "data": {
    "uuid": "8f7a1b2c-3d4e-5f6a-7b8c-9d0e1f2a3b4c",
    "structure_type": "mining_platform",
    "structure_label": "Mining Platform",
    "name": "Mining Platform",
    "level": 1,
    "status": "constructing",
    "health": 300,
    "max_health": 300,
    "construction_progress": 0,
    "construction_started_at": "2026-02-16T10:30:00+00:00",
    "construction_completed_at": null,
    "attributes": {
      "extraction_rate": 50,
      "storage": 500
    },
    "operating_costs": {
      "credits_per_cycle": 0,
      "minerals_per_cycle": 0
    },
    "poi": {
      "uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
      "name": "Kepler-442 b"
    },
    "player": {
      "uuid": "6d5c4b3a-2f1e-0d9c-8b7a-6f5e4d3c2b1a",
      "call_sign": "StarHunter"
    },
    "created_at": "2026-02-16T10:30:00+00:00",
    "updated_at": "2026-02-16T10:30:00+00:00"
  },
  "message": "Construction of Mining Platform has begun",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | `BUILD_FAILED` | Construction failed (see message for details) |
| 401 | `UNAUTHORIZED` | User not authenticated |
| 403 | `FORBIDDEN` | Authenticated user does not own this player |
| 404 | `NOT_FOUND` | Player or POI not found |
| 422 | `VALIDATION_ERROR` | Missing required fields (poi_uuid or type) |

**Error Response Examples:**

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "poi_uuid": ["Required"],
      "type": ["Required"]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

```json
{
  "success": false,
  "error": {
    "code": "BUILD_FAILED",
    "message": "Insufficient credits",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Warnings & Caveats:**

- Player must be in the same star system as the target POI
- POI must be a valid planetary body (planet, moon, gas giant, etc.)
- Each structure type has a maximum per-body limit (see [Structure Types](#structure-types))
- Construction costs are deducted immediately
- Structures begin in "constructing" status (0% progress)
- Construction advances 10% per game cycle (configured in game_config.php)
- Base costs by type:
  - Mining Platform: 30,000 credits + 8,000 minerals
  - Orbital Defense: 50,000 credits + 10,000 minerals
  - Magnetic Mine: 5,000 credits + 2,000 minerals
  - Orbital Base: 100,000 credits + 20,000 minerals

---

### PUT /api/orbital-structures/{uuid}/upgrade

Upgrade an orbital structure to the next level (max level 5).

**Authentication:** Required (Laravel Sanctum)

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (UUID) | Yes | UUID of the orbital structure to upgrade |

**Request Body:** None

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "uuid": "8f7a1b2c-3d4e-5f6a-7b8c-9d0e1f2a3b4c",
    "structure_type": "mining_platform",
    "structure_label": "Mining Platform",
    "name": "Mining Platform",
    "level": 2,
    "status": "constructing",
    "health": 390,
    "max_health": 390,
    "construction_progress": 0,
    "construction_started_at": "2026-02-16T10:30:00+00:00",
    "construction_completed_at": null,
    "attributes": {
      "extraction_rate": 50,
      "storage": 500
    },
    "operating_costs": {
      "credits_per_cycle": 0,
      "minerals_per_cycle": 0
    },
    "poi": {
      "uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
      "name": "Kepler-442 b"
    },
    "player": {
      "uuid": "6d5c4b3a-2f1e-0d9c-8b7a-6f5e4d3c2b1a",
      "call_sign": "StarHunter"
    },
    "created_at": "2026-02-10T14:30:00+00:00",
    "updated_at": "2026-02-16T10:30:00+00:00"
  },
  "message": "Upgrading Mining Platform to level 2",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | `UPGRADE_FAILED` | Upgrade failed (see message for details) |
| 401 | `UNAUTHORIZED` | User not authenticated |
| 403 | `FORBIDDEN` | Authenticated user does not own this structure |
| 404 | `NOT_FOUND` | Orbital structure not found |

**Error Response Examples:**

```json
{
  "success": false,
  "error": {
    "code": "UPGRADE_FAILED",
    "message": "Structure is at maximum level",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

```json
{
  "success": false,
  "error": {
    "code": "UPGRADE_FAILED",
    "message": "Structure must be operational to upgrade",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Warnings & Caveats:**

- Structure must be in "operational" status (cannot upgrade while constructing)
- Maximum level is 5 for all structure types
- Upgrade costs scale with level: baseCost * (1 + (level - 1) * 0.5)
- Upon upgrade, structure returns to "constructing" status (0% progress)
- Health scales with level: baseHealth * (1 + (level - 1) * 0.3)
- Health is restored to maximum when upgrade begins
- Each level increases effectiveness (extraction rate, damage, etc.) by ~30%

---

### POST /api/orbital-structures/{uuid}/collect

Collect accumulated resources from a mining platform.

**Authentication:** Required (Laravel Sanctum)

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (UUID) | Yes | UUID of the mining platform |

**Request Body:** None

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "extracted": 250
  },
  "message": "Resources collected",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | `EXTRACTION_FAILED` | Mining extraction failed |
| 401 | `UNAUTHORIZED` | User not authenticated |
| 403 | `FORBIDDEN` | Authenticated user does not own this structure |
| 404 | `NOT_FOUND` | Orbital structure not found |

**Warnings & Caveats:**

- Only works for mining platforms (structure_type: "mining_platform")
- Structure must be in "operational" status
- Extraction amount calculated based on:
  - Base extraction rate (50 units/cycle)
  - Level multiplier
  - Time since last collection
  - Storage capacity limit
- Resources are added to the player's active ship cargo
- Extraction fails if player's cargo is full

---

### DELETE /api/orbital-structures/{uuid}

Demolish (scuttle) an orbital structure permanently.

**Authentication:** Required (Laravel Sanctum)

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (UUID) | Yes | UUID of the orbital structure to demolish |

**Request Body:** None

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": null,
  "message": "Mining Platform has been scuttled",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

**Error Responses:**

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | `DEMOLISH_FAILED` | Demolition failed |
| 401 | `UNAUTHORIZED` | User not authenticated |
| 403 | `FORBIDDEN` | Authenticated user does not own this structure |
| 404 | `NOT_FOUND` | Orbital structure not found |

**Warnings & Caveats:**

- This is a permanent, irreversible action
- No resources are refunded upon demolition
- Structure status is set to "destroyed" and health set to 0
- Destroyed structures are excluded from all listing endpoints
- Can demolish structures in any status (constructing, operational, or damaged)

---

## Data Structures

### POI Type Object

Full POI type object structure (used by `/api/poi-types` and `/api/poi-types/{idOrCode}`):

```json
{
  "id": 3,
  "code": "TERRESTRIAL",
  "label": "Terrestrial Planet",
  "description": "Rocky planet with solid surface suitable for colonization",
  "domain": "planetary",
  "category": "planet",
  "capabilities": {
    "is_habitable": true,
    "is_mineable": true,
    "is_orbital": true,
    "is_dockable": true,
    "can_have_trading_hub": true,
    "can_have_warp_gate": false
  },
  "base_danger_level": 0,
  "icon": "terrestrial",
  "color": "#8B4513",
  "produces_minerals": ["fe", "cu", "al", "ti"]
}
```

**Field Definitions:**

- **id**: Unique integer identifier
- **code**: Machine-readable constant (e.g., "TERRESTRIAL", "GAS_GIANT")
- **label**: Human-readable display name
- **description**: Detailed flavor text
- **domain**: Broad classification ("stellar", "planetary", "special")
- **category**: Specific sub-type ("star", "planet", "moon", "belt", "nebula", "station", "anomaly")
- **capabilities**: Boolean flags for game mechanics
  - **is_habitable**: Can support colonies
  - **is_mineable**: Can be mined for resources
  - **is_orbital**: Orbits another body
  - **is_dockable**: Ships can dock here
  - **can_have_trading_hub**: Trading hubs can spawn
  - **can_have_warp_gate**: Warp gates can connect here
- **base_danger_level**: Base danger rating (0-10, affects pirate encounters)
- **icon**: Icon identifier for UI rendering
- **color**: Hex color code for map visualization
- **produces_minerals**: Array of mineral symbols (e.g., ["fe", "au", "pt"])

### Orbital Structure Object

Full orbital structure object structure (used by all orbital structure endpoints):

```json
{
  "uuid": "8f7a1b2c-3d4e-5f6a-7b8c-9d0e1f2a3b4c",
  "structure_type": "mining_platform",
  "structure_label": "Mining Platform",
  "name": "Mining Platform Alpha",
  "level": 2,
  "status": "operational",
  "health": 390,
  "max_health": 390,
  "construction_progress": 100,
  "construction_started_at": "2026-02-10T14:30:00+00:00",
  "construction_completed_at": "2026-02-12T14:30:00+00:00",
  "attributes": {
    "extraction_rate": 50,
    "storage": 500
  },
  "operating_costs": {
    "credits_per_cycle": 50,
    "minerals_per_cycle": 0
  },
  "poi": {
    "uuid": "7e6d5c4b-3a2f-1e0d-9c8b-7a6f5e4d3c2b",
    "name": "Kepler-442 b"
  },
  "player": {
    "uuid": "6d5c4b3a-2f1e-0d9c-8b7a-6f5e4d3c2b1a",
    "call_sign": "StarHunter"
  },
  "created_at": "2026-02-10T14:30:00+00:00",
  "updated_at": "2026-02-16T10:30:00+00:00"
}
```

**Field Definitions:**

- **uuid**: Unique identifier for the structure
- **structure_type**: Type code ("mining_platform", "orbital_defense", "magnetic_mine", "orbital_base")
- **structure_label**: Human-readable type name
- **name**: Custom name for the structure
- **level**: Current level (1-5)
- **status**: Current status ("constructing", "operational", "destroyed")
- **health**: Current health points
- **max_health**: Maximum health points (scales with level)
- **construction_progress**: Construction completion percentage (0-100)
- **construction_started_at**: ISO 8601 timestamp when construction began
- **construction_completed_at**: ISO 8601 timestamp when construction completed (null if still constructing)
- **attributes**: Type-specific gameplay attributes (varies by structure type)
- **operating_costs**: Recurring costs per game cycle
  - **credits_per_cycle**: Credits cost per cycle
  - **minerals_per_cycle**: Minerals cost per cycle
- **poi**: Associated Point of Interest (location)
  - **uuid**: POI UUID
  - **name**: POI name
- **player**: Owner information
  - **uuid**: Player UUID
  - **call_sign**: Player call sign
- **created_at**: ISO 8601 timestamp when structure was created
- **updated_at**: ISO 8601 timestamp of last update

### Structure Types

Space Wars 3002 features four orbital structure types, each with unique capabilities and constraints:

#### Mining Platform

Automated mineral extraction platform for passive resource generation.

- **Code**: `mining_platform`
- **Max per body**: 2
- **Base cost**: 30,000 credits + 8,000 minerals
- **Base health**: 300
- **Operating costs**: 50 credits/cycle, 0 minerals/cycle
- **Attributes**:
  - `extraction_rate`: 50 units/cycle
  - `storage`: 500 units
- **Use case**: Passive income from resource-rich bodies
- **Notes**: Requires periodic collection via `/api/orbital-structures/{uuid}/collect`

#### Orbital Defense Platform

Defensive installation that engages hostile ships automatically.

- **Code**: `orbital_defense`
- **Max per body**: 4
- **Base cost**: 50,000 credits + 10,000 minerals
- **Base health**: 500
- **Operating costs**: 100 credits/cycle, 5 minerals/cycle
- **Attributes**:
  - `defense_rating`: 100 (reduces incoming damage)
  - `damage_per_round`: 25 damage
- **Use case**: Protect colonies and assets from pirate raids
- **Notes**: Engages hostile players and NPCs automatically in combat

#### Magnetic Mine

Single-use explosive device that detonates on contact with hostile ships.

- **Code**: `magnetic_mine`
- **Max per body**: 10
- **Base cost**: 5,000 credits + 2,000 minerals
- **Base health**: 50
- **Operating costs**: 0 credits/cycle, 0 minerals/cycle
- **Attributes**:
  - `mine_damage`: 150 hull damage
  - `decompression`: true (ignores shields)
- **Use case**: Area denial and ambush tactics
- **Notes**:
  - Consumed on detonation (status → "destroyed")
  - Detection chance: 30% base + 10% per sensor level
  - Owner and allies are exempt from triggering

#### Orbital Base

Multi-purpose station providing docking, storage, and repair services.

- **Code**: `orbital_base`
- **Max per body**: 1
- **Base cost**: 100,000 credits + 20,000 minerals
- **Base health**: 1000
- **Operating costs**: 200 credits/cycle, 10 minerals/cycle
- **Attributes**:
  - `docking_slots`: 4 ships
  - `cargo_capacity`: 2,000 units
  - `repair`: true (enables ship repair services)
- **Use case**: Forward operating base for deep space operations
- **Notes**: Most expensive but provides critical logistics support

#### Level Scaling

All structures can be upgraded to level 5 with the following scaling:

- **Upgrade cost**: `baseCost * (1 + (level - 1) * 0.5)`
- **Health**: `baseHealth * (1 + (level - 1) * 0.3)`
- **Effectiveness**: Attributes scale ~30% per level (extraction rate, damage, etc.)
- **Construction time**: 10 game cycles per level (configured in game_config.php)

**Example**: Level 3 Mining Platform
- Cost: 30,000 * (1 + 2 * 0.5) = 60,000 credits
- Health: 300 * (1 + 2 * 0.3) = 480 HP
- Extraction: 50 * 1.6 = 80 units/cycle

---

## Common Response Patterns

All endpoints follow the standardized API response format:

### Success Response Structure

```json
{
  "success": true,
  "data": { /* endpoint-specific data */ },
  "message": "Optional success message",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

### Error Response Structure

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": null  // Optional additional details
  },
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "9c5a2f1e-4b3c-4d5e-8f7a-1b2c3d4e5f6a"
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Request validation failed (includes `errors` object) |
| `UNAUTHORIZED` | 401 | Authentication required or token invalid |
| `FORBIDDEN` | 403 | Authenticated but lacks permission |
| `NOT_FOUND` | 404 | Resource does not exist |
| `BUILD_FAILED` | 400 | Orbital structure construction failed |
| `UPGRADE_FAILED` | 400 | Orbital structure upgrade failed |
| `DEMOLISH_FAILED` | 400 | Orbital structure demolition failed |
| `EXTRACTION_FAILED` | 400 | Mining extraction failed |

---

## Authentication

Orbital structure mutation endpoints (build, upgrade, collect, demolish) require authentication via Laravel Sanctum:

```bash
# Include bearer token in Authorization header
Authorization: Bearer {your-sanctum-token}
```

Players can only interact with their own structures, except for read-only operations (listing structures at a POI or viewing individual structures).

---

## Best Practices

1. **Use specific endpoints**: Use `/api/poi-types/habitable` instead of filtering `/api/poi-types` client-side
2. **Cache POI types**: POI types are static data that rarely changes - cache aggressively
3. **Poll construction progress**: Structure construction advances via game cycles - poll periodically or use WebSocket events
4. **Batch structure queries**: Use `/api/players/{uuid}/orbital-structures` instead of individual lookups
5. **Validate ownership client-side**: Check player ownership before attempting mutations to avoid unnecessary API calls
6. **Handle async construction**: Structures don't complete instantly - UI should reflect "constructing" status
7. **Monitor operating costs**: Operational structures incur recurring costs per game cycle
8. **Strategic placement**: Consider body mineral composition when placing mining platforms
9. **Defense in depth**: Layer orbital defenses (platforms + mines) for maximum protection
10. **Resource collection**: Mining platforms accumulate resources up to storage limit - collect regularly

---

## Related Documentation

- [Galaxy API Reference](/docs/api/galaxy.md) - Galaxy and POI queries
- [Player API Reference](/docs/api/player.md) - Player state and actions
- [Trading API Reference](/docs/api/trading.md) - Mineral trading and economy
- [Combat API Reference](/docs/api/combat.md) - Ship combat and encounters

---

**Last Updated**: 2026-02-16
**API Version**: 2026.02.10.001

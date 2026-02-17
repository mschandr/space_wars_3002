# Colony Management API Documentation

This document provides comprehensive API documentation for all Colony Management endpoints in Space Wars 3002. Colonies are player-controlled settlements on planets and moons that produce resources, support ship production, and expand territorial control.

## Table of Contents

- [Colony Management](#colony-management)
  - [List Player Colonies](#list-player-colonies)
  - [Establish Colony](#establish-colony)
  - [Get Colony Details](#get-colony-details)
  - [Update Colony](#update-colony)
  - [Abandon Colony](#abandon-colony)
  - [Get Production Summary](#get-production-summary)
  - [Upgrade Development](#upgrade-development)
  - [Get Ship Production Queue](#get-ship-production-queue)
- [Colony Buildings](#colony-buildings)
  - [List Buildings](#list-buildings)
  - [Construct Building](#construct-building)
  - [Upgrade or Repair Building](#upgrade-or-repair-building)
  - [Demolish Building](#demolish-building)
- [Colony Combat](#colony-combat)
  - [Get Colony Defenses](#get-colony-defenses)
  - [Attack Colony](#attack-colony)
  - [Fortify Colony](#fortify-colony)
- [Mining Operations](#mining-operations)
  - [Get Mining Opportunities](#get-mining-opportunities)
  - [Start Automated Mining](#start-automated-mining)
  - [Extract Resources with Ship](#extract-resources-with-ship)

---

## Colony Management

### List Player Colonies

Retrieve all colonies owned by a specific player.

**Endpoint:** `GET /api/players/{uuid}/colonies`

**Authentication:** Required (Laravel Sanctum)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Player UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "colonies": [
      {
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "name": "New Terra",
        "status": "established",
        "status_display": "✅ Established",
        "population": 5000,
        "max_population": 10000,
        "population_growth_rate": 0.02,
        "development_level": 3,
        "habitability_rating": 0.75,
        "production": {
          "food_production": 150,
          "food_storage": 500,
          "mineral_production": 250,
          "mineral_storage": 1000,
          "quantium_storage": 50,
          "credits_per_cycle": 500
        },
        "location": {
          "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
          "poi_name": "Kepler-442b",
          "poi_type": "planet",
          "planet_class": "terrestrial",
          "coordinates": {
            "x": 1250.5,
            "y": 3400.2
          }
        },
        "buildings_count": 4,
        "max_buildings": 6,
        "has_shipyard": true,
        "established_at": "2026-01-15T10:30:00Z",
        "last_growth_at": "2026-02-16T08:00:00Z",
        "age_in_days": 32
      }
    ],
    "total_count": 1
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | NOT_FOUND | Player not found |

**Field Descriptions:**

- `status`: Current colony state (`establishing`, `growing`, `established`, `threatened`)
- `status_display`: Human-readable status with emoji
- `population_growth_rate`: Decimal growth rate per cycle (0.02 = 2%)
- `habitability_rating`: Decimal 0.0-1.0 indicating planet suitability for life
- `development_level`: 1-10, affects max buildings and production capacity
- `max_buildings`: Always `development_level * 2`
- `age_in_days`: Days since colony was established

**Notes:**
- Colonies are ordered by establishment date (newest first)
- Includes related `poi` and `buildings` relationships
- Buildings count is only present when buildings are loaded

---

### Establish Colony

Create a new colony on a planet or moon. The player's ship must be at the target location.

**Endpoint:** `POST /api/players/{uuid}/colonies`

**Authentication:** Required (Laravel Sanctum) - Must own the player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Player UUID |
| poi_uuid | string (UUID) | Body | Yes | UUID of the planet or moon to colonize |
| name | string (max 100) | Body | Yes | Name for the new colony |

**Request Example:**

```json
{
  "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
  "name": "New Terra"
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "data": {
    "colony": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "New Terra",
      "status": "establishing",
      "status_display": "🏗️ Establishing",
      "population": 100,
      "max_population": 1000,
      "population_growth_rate": 0.02,
      "development_level": 1,
      "habitability_rating": 0.5,
      "production": {
        "food_production": 10,
        "food_storage": 100,
        "mineral_production": 5,
        "mineral_storage": 50,
        "quantium_storage": 0,
        "credits_per_cycle": 10
      },
      "location": {
        "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
        "poi_name": "Kepler-442b",
        "poi_type": "planet",
        "planet_class": "terrestrial",
        "coordinates": {
          "x": 1250.5,
          "y": 3400.2
        }
      },
      "buildings_count": 0,
      "max_buildings": 2,
      "has_shipyard": false,
      "established_at": "2026-02-16T12:00:00Z",
      "last_growth_at": null,
      "age_in_days": 0
    }
  },
  "message": "Colony established successfully",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "8d9e6679-7425-40de-944b-e07fc1f90ae8"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | INVALID_POI | Only planets and moons can be colonized |
| 400 | ALREADY_COLONIZED | This location already has a colony |
| 400 | NOT_AT_LOCATION | Your ship must be at the target location |
| 403 | FORBIDDEN | Not authorized to control this player |
| 404 | NOT_FOUND | Player or POI not found |
| 422 | VALIDATION_ERROR | Invalid request body |

**Starting Values:**
- Population: 100
- Population growth rate: 2% (0.02)
- Max population: 1,000
- Food production: 10
- Food storage: 100
- Mineral production: 5
- Mineral storage: 50
- Quantium storage: 0
- Credits per cycle: 10
- Development level: 1
- Status: `establishing`

**Caveats:**
- Only `PLANET` and `MOON` POI types can be colonized
- Player's ship must be physically present at the POI (`player.current_poi_id === poi.id`)
- Each POI can only support one colony (checked via database constraint)
- Habitability rating is derived from the POI's `habitability_score` or defaults to 0.5

---

### Get Colony Details

Retrieve detailed information about a specific colony.

**Endpoint:** `GET /api/colonies/{uuid}`

**Authentication:** Required (Laravel Sanctum)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "colony": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "New Terra",
      "status": "established",
      "status_display": "✅ Established",
      "population": 5000,
      "max_population": 10000,
      "population_growth_rate": 0.02,
      "development_level": 3,
      "habitability_rating": 0.75,
      "production": {
        "food_production": 150,
        "food_storage": 500,
        "mineral_production": 250,
        "mineral_storage": 1000,
        "quantium_storage": 50,
        "credits_per_cycle": 500
      },
      "location": {
        "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
        "poi_name": "Kepler-442b",
        "poi_type": "planet",
        "planet_class": "terrestrial",
        "coordinates": {
          "x": 1250.5,
          "y": 3400.2
        }
      },
      "buildings_count": 4,
      "max_buildings": 6,
      "has_shipyard": true,
      "established_at": "2026-01-15T10:30:00Z",
      "last_growth_at": "2026-02-16T08:00:00Z",
      "age_in_days": 32
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "9e9e6679-7425-40de-944b-e07fc1f90ae9"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | NOT_FOUND | Colony not found |

**Notes:**
- Includes loaded relationships: `poi`, `buildings`, and `player`
- No ownership verification on read operation (public data)

---

### Update Colony

Modify colony properties such as name or status.

**Endpoint:** `PUT /api/colonies/{uuid}`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |
| name | string (max 100) | Body | No | New colony name |
| status | string | Body | No | New status (`establishing`, `growing`, `established`, `threatened`) |

**Request Example:**

```json
{
  "name": "New Terra Prime",
  "status": "established"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "colony": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "New Terra Prime",
      "status": "established",
      "status_display": "✅ Established",
      "population": 5000,
      "max_population": 10000,
      "population_growth_rate": 0.02,
      "development_level": 3,
      "habitability_rating": 0.75,
      "production": {
        "food_production": 150,
        "food_storage": 500,
        "mineral_production": 250,
        "mineral_storage": 1000,
        "quantium_storage": 50,
        "credits_per_cycle": 500
      },
      "location": {
        "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
        "poi_name": "Kepler-442b",
        "poi_type": "planet",
        "planet_class": "terrestrial",
        "coordinates": {
          "x": 1250.5,
          "y": 3400.2
        }
      },
      "buildings_count": 4,
      "max_buildings": 6,
      "has_shipyard": true,
      "established_at": "2026-01-15T10:30:00Z",
      "last_growth_at": "2026-02-16T08:00:00Z",
      "age_in_days": 32
    }
  },
  "message": "Colony updated successfully",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "a09e6679-7425-40de-944b-e07fc1f90aea"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony not found |
| 422 | VALIDATION_ERROR | Invalid status value |

**Valid Status Values:**
- `establishing`
- `growing`
- `established`
- `threatened`

**Notes:**
- All fields are optional
- Only provided fields will be updated

---

### Abandon Colony

Permanently delete a colony. This action cannot be undone.

**Endpoint:** `DELETE /api/colonies/{uuid}`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "message": "Colony 'New Terra' has been abandoned"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "b19e6679-7425-40de-944b-e07fc1f90aeb"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony not found |

**Warning:**
- This is a destructive operation - colony is permanently deleted
- All buildings are destroyed
- Resources in storage (food, minerals, quantium) are lost
- No resource refund is provided
- Implement client-side confirmation before calling this endpoint

---

### Get Production Summary

Retrieve the current production rates and storage levels for a colony. This endpoint recalculates production based on operational buildings.

**Endpoint:** `GET /api/colonies/{uuid}/production`

**Authentication:** Required (Laravel Sanctum)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "food_production": 150,
    "food_storage": 500,
    "mineral_production": 250,
    "mineral_storage": 1000,
    "quantium_storage": 50,
    "credits_per_cycle": 500,
    "population": 5000,
    "max_population": 10000,
    "population_growth_rate": 0.02
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "c29e6679-7425-40de-944b-e07fc1f90aec"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | NOT_FOUND | Colony not found |

**Field Descriptions:**
- `food_production`: Food units produced per cycle (from hydroponics buildings)
- `food_storage`: Current food in storage
- `mineral_production`: Mineral units produced per cycle (from mining facilities)
- `mineral_storage`: Current minerals in storage
- `quantium_storage`: Current quantium in storage
- `credits_per_cycle`: Credits generated per economic cycle (from trade stations)
- `population`: Current population count
- `max_population`: Maximum population capacity
- `population_growth_rate`: Growth rate per cycle (0.02 = 2%)

**Production Calculation:**
- Food: Sum of `effects->food_production` from all operational `hydroponics` buildings
- Minerals: Sum of `effects->mineral_production` from all operational `mining_facility` buildings
- Credits: Sum of `effects->credits_per_cycle` from all operational `trade_station` buildings

**Notes:**
- This endpoint triggers `calculateProduction()` method which recalculates from scratch
- Only `operational` buildings contribute to production
- Population growth is affected by food production and habitability rating
- Useful for real-time monitoring after building changes

---

### Upgrade Development

Increase a colony's development level, raising the maximum population and building capacity.

**Endpoint:** `POST /api/colonies/{uuid}/upgrade`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |

**Request Body:**

None required. Costs are calculated automatically based on current development level.

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "colony": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "New Terra",
      "status": "established",
      "status_display": "✅ Established",
      "population": 5000,
      "max_population": 11000,
      "population_growth_rate": 0.02,
      "development_level": 4,
      "habitability_rating": 0.75,
      "production": {
        "food_production": 150,
        "food_storage": 500,
        "mineral_production": 250,
        "mineral_storage": 1000,
        "quantium_storage": 50,
        "credits_per_cycle": 500
      },
      "location": {
        "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
        "poi_name": "Kepler-442b",
        "poi_type": "planet",
        "planet_class": "terrestrial",
        "coordinates": {
          "x": 1250.5,
          "y": 3400.2
        }
      },
      "buildings_count": 4,
      "max_buildings": 8,
      "has_shipyard": true,
      "established_at": "2026-01-15T10:30:00Z",
      "last_growth_at": "2026-02-16T08:00:00Z",
      "age_in_days": 32
    },
    "cost_paid": {
      "credits": 30000,
      "minerals": 3000
    },
    "new_development_level": 4,
    "remaining_credits": 67000
  },
  "message": "Colony development upgraded successfully",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "d39e6679-7425-40de-944b-e07fc1f90aed"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | MAX_LEVEL | Colony is already at maximum development level (10) |
| 400 | INSUFFICIENT_CREDITS | Player doesn't have enough credits |
| 400 | INSUFFICIENT_MINERALS | Colony storage doesn't have enough minerals |
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony not found |

**Cost Formula:**
- Credits: `10,000 * current_development_level`
- Minerals: `1,000 * current_development_level` (deducted from colony's mineral storage)

**Example Costs:**

| Current Level | New Level | Credit Cost | Mineral Cost |
|--------------|-----------|-------------|--------------|
| 1 | 2 | 10,000 | 1,000 |
| 2 | 3 | 20,000 | 2,000 |
| 3 | 4 | 30,000 | 3,000 |
| 5 | 6 | 50,000 | 5,000 |
| 9 | 10 | 90,000 | 9,000 |

**Benefits per Upgrade:**
- `max_population` increases by 1,000
- `max_buildings` increases by 2 (formula: `development_level * 2`)

**Maximum Development Level:** 10

**Notes:**
- Credits are deducted from player's account
- Minerals are deducted from colony's storage (not player inventory)
- Upgrade is instant (no build time)
- Both resources must be available for upgrade to succeed

---

### Get Ship Production Queue

Retrieve the current ship production queue for a colony with a shipyard.

**Endpoint:** `GET /api/colonies/{uuid}/ship-production`

**Authentication:** Required (Laravel Sanctum)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |

**Response (200 OK) - With Shipyard:**

```json
{
  "success": true,
  "data": {
    "has_shipyard": true,
    "queue": [
      {
        "uuid": "750e8400-e29b-41d4-a716-446655440002",
        "ship_name": "Scout Frigate",
        "status": "building",
        "progress_percent": 45.5,
        "estimated_completion": "2026-02-18T14:30:00Z",
        "queue_position": 1
      },
      {
        "uuid": "850e8400-e29b-41d4-a716-446655440003",
        "ship_name": "Mining Hauler",
        "status": "queued",
        "progress_percent": 0,
        "estimated_completion": "2026-02-20T10:00:00Z",
        "queue_position": 2
      }
    ],
    "queue_count": 2
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "e49e6679-7425-40de-944b-e07fc1f90aee"
  }
}
```

**Response (200 OK) - Without Shipyard:**

```json
{
  "success": true,
  "data": {
    "has_shipyard": false,
    "queue": []
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "f59e6679-7425-40de-944b-e07fc1f90aef"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | NOT_FOUND | Colony not found |

**Field Descriptions:**
- `has_shipyard`: Boolean indicating if colony has operational shipyard building
- `queue`: Array of ship production orders
- `queue_count`: Number of ships in production

**Queue Entry Fields:**
- `uuid`: Production order UUID
- `ship_name`: Name of ship being built (from ship blueprint)
- `status`: Production status (`queued` or `building`)
- `progress_percent`: Build completion percentage (0-100)
- `estimated_completion`: ISO-8601 timestamp for estimated completion
- `queue_position`: Position in build queue (1-indexed)

**Notes:**
- Requires an operational `shipyard` building (checked via `hasShipyard()` method)
- Only returns ships with status `queued` or `building`
- Ships are processed in `queue_position` order
- Returns empty array if no shipyard exists

---

## Colony Buildings

### List Buildings

Retrieve all buildings in a colony.

**Endpoint:** `GET /api/colonies/{uuid}/buildings`

**Authentication:** Required (Laravel Sanctum)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "buildings": [
      {
        "uuid": "450e8400-e29b-41d4-a716-446655440010",
        "building_type": "hydroponics",
        "name": "Hydroponics Bay",
        "level": 2,
        "status": "operational",
        "effects": {
          "food_production": 50
        },
        "upkeep": {
          "quantium_per_cycle": 1,
          "food_per_cycle": 0,
          "minerals_per_cycle": 0,
          "credits_per_cycle": 100
        },
        "production": {
          "food_production": 50,
          "mineral_production": 0,
          "credits_generation": 0
        },
        "created_at": "2026-01-20T14:00:00Z",
        "updated_at": "2026-02-01T10:30:00Z"
      },
      {
        "uuid": "550e8400-e29b-41d4-a716-446655440011",
        "building_type": "mining_facility",
        "name": "Mining Facility",
        "level": 1,
        "status": "operational",
        "effects": {
          "mineral_production": 100
        },
        "upkeep": {
          "quantium_per_cycle": 2,
          "food_per_cycle": 0,
          "minerals_per_cycle": 0,
          "credits_per_cycle": 150
        },
        "production": {
          "food_production": 0,
          "mineral_production": 100,
          "credits_generation": 0
        },
        "created_at": "2026-01-22T09:00:00Z",
        "updated_at": "2026-01-22T09:00:00Z"
      }
    ],
    "total_count": 2,
    "max_buildings": 6,
    "can_build_more": true
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "069e6679-7425-40de-944b-e07fc1f90ae0"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | NOT_FOUND | Colony not found |

**Field Descriptions:**
- `total_count`: Number of buildings in colony
- `max_buildings`: Maximum allowed buildings (`development_level * 2`)
- `can_build_more`: Boolean - whether colony can construct additional buildings

**Building Fields:**
- `building_type`: Building type identifier (see Building Types below)
- `name`: Display name of building
- `level`: Current building level (1-10)
- `status`: Operational status (`operational`, `damaged`, `offline`)
- `effects`: Building effects stored as JSON (production bonuses, special abilities)
- `upkeep`: Per-cycle maintenance costs
- `production`: Resource generation rates (derived from effects)

**Building Types:**
- `hydroponics` - Increases food production
- `mining_facility` - Increases mineral production
- `trade_station` - Generates credits per cycle
- `shipyard` - Enables ship production
- `orbital_mining` - Advanced mineral production
- `warp_gate` - Enables warp capability
- `defense_station` - Increases defense rating
- `research_lab` - Generates research points

**Notes:**
- Buildings ordered by `building_type`, then `created_at` descending
- Building limit determined by colony development level
- Only `operational` buildings contribute to production

---

### Construct Building

Build a new structure in a colony.

**Endpoint:** `POST /api/colonies/{uuid}/buildings`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |
| building_type | string | Body | Yes | Type of building to construct |

**Valid Building Types:**
- `hydroponics`
- `mining_facility`
- `trade_station`
- `shipyard`
- `orbital_mining`
- `warp_gate`
- `defense_station`
- `research_lab`

**Request Example:**

```json
{
  "building_type": "mining_facility"
}
```

**Response (201 Created):**

```json
{
  "success": true,
  "data": {
    "building": {
      "uuid": "650e8400-e29b-41d4-a716-446655440012",
      "building_type": "mining_facility",
      "name": "Mining Facility",
      "level": 1,
      "status": "operational",
      "effects": {
        "mineral_production": 100
      },
      "upkeep": {
        "quantium_per_cycle": 2,
        "food_per_cycle": 0,
        "minerals_per_cycle": 0,
        "credits_per_cycle": 150
      },
      "production": {
        "food_production": 0,
        "mineral_production": 100,
        "credits_generation": 0
      },
      "created_at": "2026-02-16T12:00:00Z",
      "updated_at": "2026-02-16T12:00:00Z"
    },
    "cost_paid": {
      "credits": 8000,
      "minerals": 800
    },
    "remaining_credits": 92000
  },
  "message": "Building constructed successfully",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "179e6679-7425-40de-944b-e07fc1f90ae1"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | BUILDING_LIMIT | Colony cannot build more buildings at current development level |
| 400 | INSUFFICIENT_CREDITS | Player doesn't have enough credits |
| 400 | INSUFFICIENT_MINERALS | Colony storage doesn't have enough minerals |
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony not found |
| 422 | VALIDATION_ERROR | Invalid building type |

**Building Costs and Effects:**

| Building Type | Credits | Minerals | Primary Effect | Quantium/Cycle | Credits/Cycle |
|--------------|---------|----------|----------------|----------------|---------------|
| hydroponics | 5,000 | 500 | +50 food production | 1 | 100 |
| mining_facility | 8,000 | 800 | +100 mineral production | 2 | 150 |
| trade_station | 10,000 | 1,000 | +500 credits per cycle | 1 | 0 (food: 10) |
| shipyard | 50,000 | 5,000 | Ship production enabled | 5 | 1,000 |
| orbital_mining | 15,000 | 1,500 | +200 mineral production | 3 | 200 |
| warp_gate | 100,000 | 10,000 | Warp capability | 10 | 500 |
| defense_station | 25,000 | 2,500 | +100 defense rating | 4 | 300 |
| research_lab | 30,000 | 3,000 | +10 research points | 3 | 500 |

**Notes:**
- All buildings start at level 1 with status `operational`
- Credits are deducted from player's account
- Minerals are deducted from colony's mineral storage
- Colony production is automatically recalculated after construction
- Maximum buildings = `development_level * 2`
- Building limit check uses `canBuildBuilding()` method
- **Known Issue:** Per-type building limits are not enforced (see TODO comment in Colony model)

---

### Upgrade or Repair Building

Upgrade a building to increase its effectiveness or repair a damaged building.

**Endpoint:** `PUT /api/colonies/{uuid}/buildings/{buildingUuid}`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |
| buildingUuid | string (UUID) | Path | Yes | Building UUID |
| action | string | Body | Yes | Either `upgrade` or `repair` |

**Request Example (Upgrade):**

```json
{
  "action": "upgrade"
}
```

**Response (200 OK) - Upgrade:**

```json
{
  "success": true,
  "data": {
    "building": {
      "uuid": "650e8400-e29b-41d4-a716-446655440012",
      "building_type": "mining_facility",
      "name": "Mining Facility",
      "level": 2,
      "status": "operational",
      "effects": {
        "mineral_production": 100
      },
      "upkeep": {
        "quantium_per_cycle": 2,
        "food_per_cycle": 0,
        "minerals_per_cycle": 0,
        "credits_per_cycle": 150
      },
      "production": {
        "food_production": 0,
        "mineral_production": 100,
        "credits_generation": 0
      },
      "created_at": "2026-02-16T12:00:00Z",
      "updated_at": "2026-02-16T13:00:00Z"
    },
    "cost_paid": {
      "credits": 5000,
      "minerals": 500
    },
    "new_level": 2,
    "remaining_credits": 87000
  },
  "message": "Building upgraded successfully",
  "meta": {
    "timestamp": "2026-02-16T13:00:00Z",
    "request_id": "289e6679-7425-40de-944b-e07fc1f90ae2"
  }
}
```

**Request Example (Repair):**

```json
{
  "action": "repair"
}
```

**Response (200 OK) - Repair:**

```json
{
  "success": true,
  "data": {
    "building": {
      "uuid": "650e8400-e29b-41d4-a716-446655440012",
      "building_type": "mining_facility",
      "name": "Mining Facility",
      "level": 2,
      "status": "operational",
      "effects": {
        "mineral_production": 100
      },
      "upkeep": {
        "quantium_per_cycle": 2,
        "food_per_cycle": 0,
        "minerals_per_cycle": 0,
        "credits_per_cycle": 150
      },
      "production": {
        "food_production": 0,
        "mineral_production": 100,
        "credits_generation": 0
      },
      "created_at": "2026-02-16T12:00:00Z",
      "updated_at": "2026-02-16T13:30:00Z"
    },
    "cost_paid": {
      "credits": 2000,
      "minerals": 200
    },
    "remaining_credits": 85000
  },
  "message": "Building repaired successfully",
  "meta": {
    "timestamp": "2026-02-16T13:30:00Z",
    "request_id": "399e6679-7425-40de-944b-e07fc1f90ae3"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | MAX_LEVEL | Building is already at maximum level (10) - upgrade only |
| 400 | ALREADY_OPERATIONAL | Building is already operational - repair only |
| 400 | INSUFFICIENT_CREDITS | Player doesn't have enough credits |
| 400 | INSUFFICIENT_MINERALS | Colony storage doesn't have enough minerals |
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony or building not found |
| 422 | VALIDATION_ERROR | Invalid action value |

**Upgrade Cost Formula:**
- Credits: `5,000 * current_building_level`
- Minerals: `500 * current_building_level` (from colony storage)

**Example Upgrade Costs:**

| Current Level | New Level | Credit Cost | Mineral Cost |
|--------------|-----------|-------------|--------------|
| 1 | 2 | 5,000 | 500 |
| 2 | 3 | 10,000 | 1,000 |
| 3 | 4 | 15,000 | 1,500 |
| 9 | 10 | 45,000 | 4,500 |

**Repair Cost (Fixed):**
- Credits: 2,000
- Minerals: 200 (from colony storage)

**Notes:**
- Maximum building level is 10
- Colony production is recalculated after upgrade or repair via `calculateProduction()`
- Damaged buildings do not contribute to production
- Repair changes status from `damaged` or `offline` to `operational`
- Building effects typically scale with level (though effects JSON is not automatically updated)

---

### Demolish Building

Permanently destroy a building to free up space for new construction.

**Endpoint:** `DELETE /api/colonies/{uuid}/buildings/{buildingUuid}`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |
| buildingUuid | string (UUID) | Path | Yes | Building UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "message": "Building 'Mining Facility' has been demolished"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T14:00:00Z",
    "request_id": "4a9e6679-7425-40de-944b-e07fc1f90ae4"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony or building not found |

**Warning:**
- Demolishing a building is permanent and provides no resource refund
- Building effects are immediately removed
- Colony production is automatically recalculated via `calculateProduction()`
- Implement client-side confirmation before calling this endpoint

---

## Colony Combat

### Get Colony Defenses

Retrieve defense information for a colony, including defensive structures and garrison strength.

**Endpoint:** `GET /api/colonies/{uuid}/defenses`

**Authentication:** Required (Laravel Sanctum)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "colony": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "New Terra",
      "owner": {
        "uuid": "350e8400-e29b-41d4-a716-446655440020",
        "call_sign": "Commander Alpha"
      },
      "development_level": 3,
      "population": 5000,
      "defense_rating": 300,
      "garrison_strength": 600,
      "defensive_buildings": 2,
      "last_attacked_at": "2026-02-10T18:30:00Z"
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T14:00:00Z",
    "request_id": "5b9e6679-7425-40de-944b-e07fc1f90ae5"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | NOT_FOUND | Colony not found |

**Field Descriptions:**
- `defense_rating`: Overall defensive strength of fortifications (integer)
- `garrison_strength`: Military personnel defending the colony (integer)
- `defensive_buildings`: Count of operational defense-type buildings (`defense_turret`, `shield_generator`, `garrison`)
- `last_attacked_at`: ISO-8601 timestamp of last attack (null if never attacked)

**Notes:**
- Defensive buildings are those with types: `defense_turret`, `shield_generator`, `garrison`
- Only `operational` buildings are counted
- Defense rating and garrison strength affect combat outcomes in colony attacks

---

### Attack Colony

Initiate a siege on another player's colony. Can include allied players for team attacks.

**Endpoint:** `POST /api/players/{uuid}/attack-colony/{colonyUuid}`

**Authentication:** Required (Laravel Sanctum) - Must own the attacking player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Attacking player UUID |
| colonyUuid | string (UUID) | Path | Yes | Target colony UUID |
| ally_uuids | array of UUIDs | Body | No | UUIDs of allied players joining the attack |

**Request Example:**

```json
{
  "ally_uuids": [
    "450e8400-e29b-41d4-a716-446655440021",
    "550e8400-e29b-41d4-a716-446655440022"
  ]
}
```

**Response (200 OK) - Instant Capture:**

```json
{
  "success": true,
  "data": {
    "instant_capture": true,
    "colony": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "New Terra",
      "old_owner": {
        "uuid": "350e8400-e29b-41d4-a716-446655440020",
        "call_sign": "Commander Alpha"
      },
      "new_owner": {
        "uuid": "250e8400-e29b-41d4-a716-446655440023",
        "call_sign": "Admiral Beta"
      }
    }
  },
  "message": "Colony captured without resistance",
  "meta": {
    "timestamp": "2026-02-16T14:30:00Z",
    "request_id": "6c9e6679-7425-40de-944b-e07fc1f90ae6"
  }
}
```

**Response (200 OK) - Combat Result:**

```json
{
  "success": true,
  "data": {
    "combat_session": {
      "uuid": "750e8400-e29b-41d4-a716-446655440030"
    },
    "result": {
      "victor": "attacker",
      "rounds": 8,
      "attackers_survived": 3,
      "colony_captured": true,
      "buildings_damaged": 2,
      "combat_log": [
        "Round 1: Attackers deal 450 damage, defenders deal 320 damage",
        "Round 2: Attackers deal 420 damage, defenders deal 280 damage",
        "Round 3: Defenders fortifications weakened",
        "Round 4: Defense building destroyed",
        "Round 5: Attackers deal 380 damage, defenders deal 240 damage",
        "Round 6: Garrison strength reduced by 50%",
        "Round 7: Critical hit on defense station",
        "Round 8: Defenders defeated! Colony captured!"
      ]
    }
  },
  "message": "Colony siege completed",
  "meta": {
    "timestamp": "2026-02-16T14:45:00Z",
    "request_id": "7d9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | ATTACK_FAILED | Attack cannot proceed (various reasons from service) |
| 403 | FORBIDDEN | Not authorized to control this player |
| 404 | NOT_FOUND | Player or colony not found |
| 422 | VALIDATION_ERROR | Invalid ally UUID |

**Field Descriptions - Instant Capture:**
- `instant_capture`: Boolean indicating no combat occurred
- `old_owner`: Previous colony owner information
- `new_owner`: New owner (attacker) information

**Field Descriptions - Combat:**
- `combat_session.uuid`: Combat session UUID for tracking
- `victor`: Winner (`attacker` or `defender`)
- `rounds`: Number of combat rounds
- `attackers_survived`: Number/boolean indicating attackers survived
- `colony_captured`: Boolean - whether colony was successfully captured
- `buildings_damaged`: Number of buildings destroyed or damaged
- `combat_log`: Array of combat event descriptions

**Notes:**
- Instant capture occurs when defenses are negligible or colony is undefended
- Combat sessions are created via `ColonyCombatService` for significant battles
- Allies must exist and be valid players (validated)
- Captured colonies transfer ownership to the attacking player
- Buildings may be damaged during combat
- Failed attacks can result in ship damage or destruction

---

### Fortify Colony

Invest credits to increase a colony's defensive capabilities.

**Endpoint:** `POST /api/colonies/{uuid}/fortify`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |
| credits | integer | Body | Yes | Amount of credits to invest (1,000 - 100,000) |

**Request Example:**

```json
{
  "credits": 50000
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "colony": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "New Terra",
      "defense_rating": 800,
      "garrison_strength": 1600
    },
    "defense_increase": 500,
    "garrison_increase": 1000
  },
  "message": "Colony fortified successfully",
  "meta": {
    "timestamp": "2026-02-16T15:00:00Z",
    "request_id": "8e9e6679-7425-40de-944b-e07fc1f90ae8"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | INSUFFICIENT_CREDITS | Player doesn't have enough credits |
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony not found |
| 422 | VALIDATION_ERROR | Credits amount out of range (1,000 - 100,000) |

**Fortification Formula:**
- Defense Rating increase: `credits / 100`
- Garrison Strength increase: `credits / 50`

**Example Investments:**

| Credits Invested | Defense Increase | Garrison Increase |
|------------------|------------------|-------------------|
| 1,000 | 10 | 20 |
| 10,000 | 100 | 200 |
| 50,000 | 500 | 1,000 |
| 100,000 | 1,000 | 2,000 |

**Notes:**
- Minimum investment: 1,000 credits
- Maximum investment: 100,000 credits
- Credits are deducted from the player's account
- Effects are immediate and permanent
- More cost-effective than building multiple defense stations for quick defense boosts

---

## Mining Operations

### Get Mining Opportunities

Retrieve available mineral deposits at a specific Point of Interest (planet, moon, asteroid belt).

**Endpoint:** `GET /api/poi/{uuid}/mining-opportunities`

**Authentication:** Required (Laravel Sanctum)

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | POI UUID |

**Response (200 OK) - With Deposits:**

```json
{
  "success": true,
  "data": {
    "has_deposits": true,
    "minerals": [
      {
        "mineral_id": 1,
        "mineral_name": "Iron Ore",
        "deposit_size": 5000,
        "richness": 0.8,
        "rarity": "common"
      },
      {
        "mineral_id": 3,
        "mineral_name": "Titanium",
        "deposit_size": 2000,
        "richness": 0.6,
        "rarity": "uncommon"
      }
    ],
    "poi_name": "Kepler-442b",
    "poi_type": "planet",
    "planet_class": "terrestrial"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T15:30:00Z",
    "request_id": "9f9e6679-7425-40de-944b-e07fc1f90ae9"
  }
}
```

**Response (200 OK) - No Deposits:**

```json
{
  "success": true,
  "data": {
    "has_deposits": false,
    "minerals": [],
    "poi_type": "star",
    "planet_class": null
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T15:30:00Z",
    "request_id": "a09e6679-7425-40de-944b-e07fc1f90aea"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 404 | NOT_FOUND | POI not found |

**Field Descriptions:**
- `has_deposits`: Boolean indicating if POI has mineable resources
- `mineral_id`: Mineral database ID (for extraction requests)
- `mineral_name`: Display name of mineral
- `deposit_size`: Total amount of mineral available (units)
- `richness`: Quality/concentration of deposit (0.0-1.0, affects extraction efficiency)
- `rarity`: Mineral rarity tier (`common`, `uncommon`, `rare`, `very_rare`, `legendary`)

**Notes:**
- Uses `MiningService->getAvailableMinerals()` to retrieve deposit information
- Not all POIs have mineral deposits (e.g., stars typically don't)
- Deposit data is stored in POI's `mineral_deposits` JSON field

---

### Start Automated Mining

Begin automated mineral extraction from a colony with an orbital mining facility.

**Endpoint:** `POST /api/colonies/{uuid}/mining/start`

**Authentication:** Required (Laravel Sanctum) - Must own the colony's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Colony UUID |
| poi_uuid | string (UUID) | Body | Yes | POI UUID containing the mineral deposit |
| mineral_id | integer | Body | Yes | ID of the mineral to mine |

**Request Example:**

```json
{
  "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
  "mineral_id": 1
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "mineral_name": "Iron Ore",
    "production_per_cycle": 150,
    "sensor_efficiency": 0.85
  },
  "message": "Automated mining operation started",
  "meta": {
    "timestamp": "2026-02-16T16:00:00Z",
    "request_id": "b19e6679-7425-40de-944b-e07fc1f90aeb"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | NO_MINING_FACILITY | Colony does not have an operational orbital mining facility |
| 400 | MINING_FAILED | Mining operation could not start (various reasons from service) |
| 403 | FORBIDDEN | Not authorized to control this colony |
| 404 | NOT_FOUND | Colony, POI, or mineral not found |

**Field Descriptions:**
- `mineral_name`: Name of mineral being mined
- `production_per_cycle`: Units extracted per colony cycle
- `sensor_efficiency`: Extraction efficiency based on colony/player sensor level (0.0-1.0)

**Notes:**
- Requires an operational `orbital_mining` building in colony (checked via `MiningService->hasOrbitalMining()`)
- Mining efficiency is calculated via `MiningService->calculateSensorEfficiency()`
- Production continues automatically each colony cycle
- Minerals are deposited into colony's mineral storage
- Started via `MiningService->startAutomatedMining()`

---

### Extract Resources with Ship

Manually mine minerals using your ship's sensors and cargo hold.

**Endpoint:** `POST /api/ships/{uuid}/mining/extract`

**Authentication:** Required (Laravel Sanctum) - Must own the ship's player

**Parameters:**

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| uuid | string (UUID) | Path | Yes | Ship UUID |
| poi_uuid | string (UUID) | Body | Yes | POI UUID containing the mineral deposit |
| mineral_id | integer | Body | Yes | ID of the mineral to extract |

**Request Example:**

```json
{
  "poi_uuid": "650e8400-e29b-41d4-a716-446655440001",
  "mineral_id": 1
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "mineral_name": "Iron Ore",
    "amount_extracted": 850,
    "efficiency_percent": 85.0,
    "sensor_level": 3,
    "cargo_used": 1250,
    "cargo_remaining": 2750,
    "deposit_remaining": 4150
  },
  "message": "Resources extracted successfully",
  "meta": {
    "timestamp": "2026-02-16T16:30:00Z",
    "request_id": "c29e6679-7425-40de-944b-e07fc1f90aec"
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | NOT_AT_LOCATION | Your ship must be at the target location |
| 400 | MINERAL_NOT_AVAILABLE | Cannot mine this mineral from this location |
| 400 | NO_DEPOSIT | No deposits of this mineral found |
| 400 | CARGO_FULL | No cargo space available |
| 403 | FORBIDDEN | Not authorized to control this ship |
| 404 | NOT_FOUND | Ship, POI, or mineral not found |

**Field Descriptions:**
- `amount_extracted`: Units added to ship's cargo
- `efficiency_percent`: Extraction efficiency (0-100%)
- `sensor_level`: Ship's sensor level (affects efficiency)
- `cargo_used`: Current cargo hold usage after extraction
- `cargo_remaining`: Available cargo space after extraction
- `deposit_remaining`: Remaining mineral at POI after extraction

**Extraction Process:**
1. Ship must be at POI location (`player.current_poi_id === poi.id`)
2. Verify mineral exists at POI via `MiningService->canMineFromPOI()`
3. Calculate efficiency via `MiningService->calculateSensorEfficiency()`
4. Base extraction: `deposit_size * efficiency` (capped at 100% for manual)
5. Limited by available cargo space (`ship.cargo_hold - ship.current_cargo`)
6. Minerals added to ship cargo via `PlayerCargo` model
7. POI deposit size reduced by extracted amount

**Notes:**
- Ship must be physically at the POI
- Extracted minerals are added to ship's cargo via `PlayerCargo` table
- Deposit size in POI's `mineral_deposits` JSON is decreased
- Cargo space is automatically calculated and enforced
- Higher sensor levels = better extraction efficiency
- Efficiency is capped at 100% (1.0) for manual extraction

---

## Common Response Structure

All API endpoints follow this standardized response structure:

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message",
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
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
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00Z",
    "request_id": "uuid-v4"
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| NOT_FOUND | 404 | Resource not found |
| FORBIDDEN | 403 | Not authorized to access this resource |
| UNAUTHORIZED | 401 | Authentication required or invalid |
| VALIDATION_ERROR | 422 | Request validation failed |
| INSUFFICIENT_CREDITS | 400 | Player doesn't have enough credits |
| INSUFFICIENT_MINERALS | 400 | Colony doesn't have enough minerals |
| INVALID_POI | 400 | POI type invalid for operation |
| ALREADY_COLONIZED | 400 | POI already has a colony |
| NOT_AT_LOCATION | 400 | Ship/player not at required location |
| MAX_LEVEL | 400 | Building/colony at maximum level |
| BUILDING_LIMIT | 400 | Colony at building capacity |
| NO_MINING_FACILITY | 400 | Colony lacks orbital mining building |
| MINERAL_NOT_AVAILABLE | 400 | Mineral not present at location |
| CARGO_FULL | 400 | Ship cargo hold full |
| ATTACK_FAILED | 400 | Combat could not be initiated |
| ALREADY_OPERATIONAL | 400 | Building already operational |

---

## Authentication

All colony management endpoints require Laravel Sanctum authentication. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your-access-token}
```

### Authorization Rules

- **Player Ownership:** Actions that modify a player's colonies require the authenticated user to own that player (checked via `authorizePlayer()` method in `BaseApiController`)
- **Colony Ownership:** Colony modifications require ownership through the colony's player
- **Read Operations:** Most read operations are open to authenticated users, but write operations require ownership

---

## Rate Limiting

API endpoints are subject to rate limiting:
- Default: 60 requests per minute per user
- Authenticated users: 100 requests per minute

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Unix timestamp when limit resets

---

## Notes & Best Practices

### UUIDs
- All resource identifiers use UUIDs (v4), not auto-increment IDs
- UUIDs are case-sensitive strings in format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`

### Resource Relationships
- Colonies belong to Players and POIs
- Buildings belong to Colonies
- PlayerCargo links PlayerShips to Minerals
- Colony production is calculated from operational buildings

### Production Cycles
- Colony production occurs on scheduled game cycles (Laravel queue/scheduler)
- `calculateProduction()` recalculates from operational buildings when changes occur
- Only buildings with status `operational` contribute to production
- Production formulas:
  - Food: Sum of `hydroponics` buildings' `effects->food_production`
  - Minerals: Sum of `mining_facility` buildings' `effects->mineral_production`
  - Credits: Sum of `trade_station` buildings' `effects->credits_per_cycle`

### Development Progression
- Colony development level determines building capacity (`level * 2`)
- Building levels (1-10) scale effects
- Costs increase linearly with level
- Maximum development level: 10

### Combat Mechanics
- Colony capture transfers ownership
- Buildings can be damaged/destroyed
- Defense rating and garrison strength affect combat outcomes
- Allies can assist in attacks (passed as array of UUIDs)
- Instant capture occurs when defenses are minimal

### Mining Operations
Two methods available:
1. **Automated**: Colony orbital mining (passive, per-cycle, requires `orbital_mining` building)
2. **Manual**: Ship extraction (active, immediate, limited by cargo space)

### Resource Management
- **Credits**: Stored on Player model
- **Minerals**: Stored in colony `mineral_storage` or ship cargo
- **Quantium**: Premium resource for building upkeep
- **Food**: Required for population growth

### Known Issues
- `Colony->canBuildBuilding()` calculates per-type count but doesn't enforce per-type limits (see TODO comment in Colony model line 159)
- Building effects stored in JSON are not automatically scaled with level upgrades

---

## Changelog

### Version 2026.02.16.001

Complete API documentation with:
- Colony CRUD operations
- Building construction and management
- Colony combat and fortification
- Mining operations (automated and manual)
- Ship production queue viewing
- Comprehensive error codes
- Request/response examples for all endpoints

---

**Document Version:** 2.0
**Last Updated:** 2026-02-16
**API Version:** Laravel 11.x
**Game Version:** 2026.02.10.001

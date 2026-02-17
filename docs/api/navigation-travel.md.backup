# Navigation & Travel API Reference

Complete API documentation for navigation, travel, location queries, and star system information in Space Wars 3002.

**Authentication**: All endpoints require authentication via Laravel Sanctum (Bearer token).

**Base URL**: `/api`

---

## Table of Contents

1. [Location & Navigation](#location--navigation)
   - [Get Current Location](#get-current-location)
   - [Get Nearby Systems](#get-nearby-systems)
   - [Get Local Bodies](#get-local-bodies)
   - [Scan Local Area](#scan-local-area)
2. [Travel Execution](#travel-execution)
   - [Travel via Warp Gate](#travel-via-warp-gate)
   - [Jump to Coordinates](#jump-to-coordinates)
   - [Direct Jump to Hub](#direct-jump-to-hub)
3. [Travel Calculations](#travel-calculations)
   - [Calculate Fuel Cost](#calculate-fuel-cost)
   - [Preview XP](#preview-xp)
4. [Warp Gates](#warp-gates)
   - [List Warp Gates](#list-warp-gates)
   - [Get Warp Gate Pirates](#get-warp-gate-pirates)
5. [Location Info](#location-info)
   - [Get Location by Coordinates/UUID](#get-location-by-coordinatesuuid)
6. [Star Systems](#star-systems)
   - [Get Current System](#get-current-system)
   - [List Star Systems](#list-star-systems)
   - [Get Star System Details](#get-star-system-details)
   - [Get System Generation Status](#get-system-generation-status)

---

## Location & Navigation

### Get Current Location

Get detailed information about the player's current location including trading hub availability, warp gates, and inhabitance status.

**Endpoint**: `GET /api/players/{uuid}/location`

**Auth Required**: Yes (player must belong to authenticated user)

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| uuid      | string | Yes      | Player UUID (path param) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "location": {
      "id": 12345,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Centauri",
      "type": "star",
      "x": 150.0,
      "y": 200.0,
      "is_inhabited": true,
      "description": "A bright star system...",
      "attributes": {
        "stellar_class": "G2V",
        "habitable_zones": 2
      }
    },
    "galaxy": {
      "uuid": "660e8400-e29b-41d4-a716-446655440000",
      "name": "Milky Way"
    },
    "warp_gates_available": 5,
    "trading_hub": {
      "uuid": "770e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Centauri Trade Station",
      "type": "major",
      "has_salvage_yard": true,
      "services": ["trading", "repair", "refuel"]
    },
    "is_inhabited": true
  }
}
```

**Fields Explained**:
- `location`: Full PointOfInterest resource with coordinates, type, and attributes
- `galaxy`: The galaxy this location belongs to
- `warp_gates_available`: Count of active, non-hidden gates at this location
- `trading_hub`: Details if a trading hub exists here (null if none)
  - `services`: Array of available service types
  - `has_salvage_yard`: Whether salvage operations are available
- `is_inhabited`: Whether this is a civilized system (determines gate availability)

#### Error Responses

| Status | Code         | Description                      |
|--------|--------------|----------------------------------|
| 404    | NOT_FOUND    | Player not found or unauthorized |
| 400    | NO_LOCATION  | Player has no current location   |

---

### Get Nearby Systems

Scan for star systems within sensor range. Returns travel options for each detected system including warp gate and direct jump calculations.

**Endpoint**: `GET /api/players/{uuid}/nearby-systems`

**Auth Required**: Yes

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| uuid      | string | Yes      | Player UUID (path param) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "current_location": {
      "name": "Sol",
      "coordinates": {
        "x": 0.0,
        "y": 0.0
      }
    },
    "sensor_range": 300.0,
    "sensor_level": 3,
    "ship": {
      "current_fuel": 450,
      "max_fuel": 500,
      "warp_drive": 3,
      "max_jump_range": 150.0
    },
    "systems_detected": 12,
    "nearby_systems": [
      {
        "uuid": "880e8400-e29b-41d4-a716-446655440000",
        "name": "Proxima Centauri",
        "type": "star",
        "distance": 4.24,
        "coordinates": {
          "x": 4.0,
          "y": 1.5
        },
        "is_inhabited": true,
        "has_chart": true,
        "travel": {
          "warp_gate": {
            "gate_uuid": "990e8400-e29b-41d4-a716-446655440000",
            "fuel_cost": 5,
            "can_afford": true
          },
          "direct_jump": {
            "fuel_cost": 20,
            "in_range": true,
            "can_afford": true
          },
          "cheapest_option": "warp_gate",
          "cheapest_fuel_cost": 5,
          "can_reach": true
        }
      },
      {
        "uuid": "aa0e8400-e29b-41d4-a716-446655440000",
        "name": "Unknown System",
        "type": "star",
        "distance": 125.8,
        "coordinates": null,
        "is_inhabited": false,
        "has_chart": false,
        "travel": {
          "warp_gate": null,
          "direct_jump": {
            "fuel_cost": 503,
            "in_range": true,
            "can_afford": false
          },
          "cheapest_option": null,
          "cheapest_fuel_cost": null,
          "can_reach": false
        }
      }
    ]
  }
}
```

**Fields Explained**:
- `sensor_range`: Detection radius in light-years (sensor_level × 100)
- `sensor_level`: Ship's sensor component level (1-10)
- `ship.max_jump_range`: Maximum direct jump distance based on warp drive
- `nearby_systems`: Array of detected systems (limited to 50 closest)
  - `name`: Actual name if player has chart, otherwise "Unknown System"
  - `coordinates`: Null if player doesn't have chart for uninhabited systems
  - `has_chart`: Whether player owns a star chart for this system
  - `travel.warp_gate`: Null if no gate exists
  - `travel.direct_jump.fuel_cost`: 4x penalty compared to gate travel
  - `travel.cheapest_option`: "warp_gate", "direct_jump", or null
  - `travel.can_reach`: True if player has fuel for cheapest option

#### Error Responses

| Status | Code            | Description                       |
|--------|-----------------|-----------------------------------|
| 404    | NOT_FOUND       | Player not found or unauthorized  |
| 400    | NO_LOCATION     | Player has no current location    |
| 400    | NO_ACTIVE_SHIP  | Player has no active ship         |

**Warnings**:
- Systems are sorted by distance (closest first)
- Limited to 50 systems maximum
- Direct jump has 4x fuel penalty compared to warp gates
- Coordinates are hidden for uninhabited systems without charts

---

### Get Local Bodies

Get all orbital bodies (planets, moons, asteroid belts, stations) within the current star system. Includes defensive capabilities if sensor level is sufficient.

**Endpoint**: `GET /api/players/{uuid}/local-bodies`

**Auth Required**: Yes

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| uuid      | string | Yes      | Player UUID (path param) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "bb0e8400-e29b-41d4-a716-446655440000",
      "name": "Tau Ceti",
      "type": "star",
      "coordinates": {
        "x": 50,
        "y": 75
      },
      "is_inhabited": true
    },
    "sector": {
      "uuid": "cc0e8400-e29b-41d4-a716-446655440000",
      "name": "Outer Rim",
      "grid": {
        "x": 5,
        "y": 7
      }
    },
    "bodies": {
      "planets": [
        {
          "uuid": "dd0e8400-e29b-41d4-a716-446655440000",
          "name": "Tau Ceti Prime",
          "type": "terrestrial_planet",
          "type_label": "Terrestrial Planet",
          "orbital_index": 1,
          "is_inhabited": true,
          "has_colony": true,
          "attributes": {
            "habitable": true,
            "in_goldilocks_zone": true,
            "temperature": 288,
            "atmosphere": "nitrogen-oxygen",
            "mineral_richness": 0.75
          },
          "orbital_presence": {
            "structures": [
              {
                "type": "orbital_defense_platform",
                "name": "Shield Station Alpha",
                "level": 3,
                "status": "operational",
                "owner": {
                  "uuid": "ee0e8400-e29b-41d4-a716-446655440000",
                  "call_sign": "CommanderX"
                }
              }
            ],
            "system_defenses": [
              {
                "type": "planetary_shield",
                "quantity": 1,
                "level": 5
              }
            ]
          },
          "defensive_capability": {
            "orbital_defense_platforms": 450,
            "system_defenses": 200,
            "fighter_squadrons": 150,
            "colony_garrison": 300,
            "colony_defense_buildings": 100,
            "magnetic_mines": 5,
            "planetary_shield_hp": 50000,
            "total_damage_per_round": 1200,
            "threat_level": "fortress"
          },
          "moons": [
            {
              "uuid": "ff0e8400-e29b-41d4-a716-446655440000",
              "name": "Luna Minor",
              "is_inhabited": false,
              "has_colony": false,
              "orbital_presence": {
                "structures": [],
                "system_defenses": []
              },
              "defensive_capability": null
            }
          ]
        }
      ],
      "moons": [],
      "asteroid_belts": [
        {
          "uuid": "010e8400-e29b-41d4-a716-446655440000",
          "name": "Tau Ceti Belt",
          "type": "asteroid_belt",
          "type_label": "Asteroid Belt",
          "orbital_index": 3,
          "is_inhabited": false,
          "has_colony": false,
          "attributes": {
            "mineral_richness": 0.95,
            "has_rings": false
          },
          "orbital_presence": {
            "structures": [],
            "system_defenses": []
          },
          "defensive_capability": null
        }
      ],
      "stations": [],
      "defense_platforms": [],
      "other": []
    },
    "summary": {
      "total_bodies": 3,
      "planets": 1,
      "moons": 0,
      "asteroid_belts": 1,
      "stations": 0
    }
  }
}
```

**Fields Explained**:
- `bodies`: Categorized by type (planets, moons, asteroid_belts, stations, defense_platforms, other)
  - `orbital_index`: Position in the system (1 = innermost)
  - `attributes`: Visible properties like habitability, temperature, mineral richness
  - `orbital_presence`: Always visible player-built structures and system defenses
    - `structures`: Orbital platforms, mines, etc.
    - `system_defenses`: Pre-built planetary shields, fighter ports
  - `defensive_capability`: **Requires sensor level ≥5**, detailed combat stats
    - `total_damage_per_round`: Combined damage output in combat
    - `threat_level`: "none", "minimal", "moderate", "heavy", or "fortress"
    - `magnetic_mines`: Count only (damage is per-detonation)
    - `planetary_shield_hp`: Shield health points
  - `moons`: Nested array of moons orbiting this planet (if any)
- `summary`: Quick counts of body types

#### Error Responses

| Status | Code        | Description                      |
|--------|-------------|----------------------------------|
| 404    | NOT_FOUND   | Player not found or unauthorized |
| 400    | NO_LOCATION | Player has no current location   |
| 401    | -           | Unauthenticated                  |

**Warnings**:
- `defensive_capability` is null if sensor level < 5
- Magnetic mine damage is per-detonation, not reflected in damage_per_round
- Only includes children of current location (orbital bodies)
- Derelicts are mapped to "stations" category

---

### Scan Local Area

Comprehensive scan of all nearby POIs (not just stars) within sensor range. Includes planets, asteroids, nebulae, stations, and anomalies.

**Endpoint**: `GET /api/players/{uuid}/scan-local`

**Auth Required**: Yes

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| uuid      | string | Yes      | Player UUID (path param) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "current_location": {
      "name": "Vega",
      "type": "star",
      "coordinates": {
        "x": 100.0,
        "y": 150.0
      }
    },
    "sensor_range": 500.0,
    "sensor_level": 5,
    "total_pois_detected": 47,
    "pois_by_type": {
      "star": [
        {
          "uuid": "020e8400-e29b-41d4-a716-446655440000",
          "name": "Altair",
          "type": "Star",
          "distance": 124.5,
          "coordinates": {
            "x": 150.0,
            "y": 200.0
          },
          "is_inhabited": true,
          "has_chart": true,
          "parent_poi": null
        }
      ],
      "terrestrial_planet": [
        {
          "uuid": "030e8400-e29b-41d4-a716-446655440000",
          "name": "Unknown Terrestrial Planet",
          "type": "Terrestrial Planet",
          "distance": 45.2,
          "coordinates": null,
          "is_inhabited": false,
          "has_chart": false,
          "parent_poi": {
            "id": 567
          }
        }
      ],
      "asteroid_belt": [
        {
          "uuid": "040e8400-e29b-41d4-a716-446655440000",
          "name": "Vega Belt",
          "type": "Asteroid Belt",
          "distance": 12.3,
          "coordinates": {
            "x": 105.0,
            "y": 155.0
          },
          "is_inhabited": false,
          "has_chart": true,
          "parent_poi": {
            "id": 123
          }
        }
      ],
      "nebula": [
        {
          "uuid": "050e8400-e29b-41d4-a716-446655440000",
          "name": "Crimson Nebula",
          "type": "Nebula",
          "distance": 230.8,
          "coordinates": {
            "x": 200.0,
            "y": 300.0
          },
          "is_inhabited": false,
          "has_chart": true,
          "parent_poi": null
        }
      ]
    }
  }
}
```

**Fields Explained**:
- `pois_by_type`: Grouped by POI type (dynamic keys based on detected types)
  - Keys are the POI type enum values (star, terrestrial_planet, gas_giant, asteroid_belt, nebula, derelict, anomaly, etc.)
  - `name`: "Unknown {Type}" if player lacks chart and system is uninhabited
  - `coordinates`: Hidden (null) for uninhabited POIs without charts
  - `parent_poi`: Reference to parent POI ID (e.g., planet's parent star)
  - `has_chart`: Whether player owns a star chart for this POI
- `total_pois_detected`: Total count across all types (limited to 100)
- `sensor_range`: Detection radius in light-years

#### Error Responses

| Status | Code            | Description                       |
|--------|-----------------|-----------------------------------|
| 404    | NOT_FOUND       | Player not found or unauthorized  |
| 400    | NO_LOCATION     | Player has no current location    |
| 400    | NO_ACTIVE_SHIP  | Player has no active ship         |

**Warnings**:
- Returns up to 100 POIs maximum
- Includes all POI types, not just stars
- Coordinates are masked for uninhabited systems without charts
- Results sorted by distance (closest first)

---

## Travel Execution

### Travel via Warp Gate

Execute travel through a warp gate. Gates are bidirectional and provide efficient, lower-fuel travel between inhabited systems.

**Endpoint**: `POST /api/players/{uuid}/travel/warp-gate`

**Auth Required**: Yes

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| uuid      | string | Yes      | Player UUID (path param) |

#### Request Body

```json
{
  "gate_uuid": "060e8400-e29b-41d4-a716-446655440000"
}
```

| Field     | Type   | Required | Description          |
|-----------|--------|----------|----------------------|
| gate_uuid | string | Yes      | UUID of warp gate    |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Travel successful",
  "data": {
    "fuel_consumed": 15,
    "xp_earned": 75,
    "new_location": {
      "id": 456,
      "uuid": "070e8400-e29b-41d4-a716-446655440000",
      "name": "Betelgeuse",
      "type": "star",
      "x": 250.0,
      "y": 300.0,
      "is_inhabited": true,
      "description": "A red supergiant star...",
      "attributes": {
        "stellar_class": "M1Ia"
      }
    },
    "level_up": true,
    "new_level": 5,
    "pirate_encounter": {
      "encountered": true,
      "captain_name": "Dread Pirate Roberts",
      "fleet_size": 3,
      "threat_level": "medium"
    }
  }
}
```

**Fields Explained**:
- `fuel_consumed`: Actual fuel deducted from ship (formula: ceil(distance / efficiency))
- `xp_earned`: Experience points gained (formula: max(10, distance × 5))
- `new_location`: Full PointOfInterest resource of destination
- `level_up`: True if player leveled up from this travel
- `new_level`: Player's level after travel
- `pirate_encounter`: Present only if pirates attacked during travel
  - Encounter resolution handled separately via combat system

#### Error Responses

| Status | Code          | Description                                |
|--------|---------------|--------------------------------------------|
| 404    | NOT_FOUND     | Player or gate not found                   |
| 400    | GATE_NOT_FOUND| Gate not found at current location         |
| 400    | TRAVEL_FAILED | Insufficient fuel, cooldown, or other error|
| 422    | VALIDATION    | Invalid gate_uuid format                   |

**Warnings**:
- Gate must be at player's current location (source or destination)
- Gates are bidirectional (either direction is valid)
- Mirror universe gates have 24-hour cooldown and require sensor level 5
- Pirate encounters possible on certain lanes
- Warp drive level affects fuel efficiency (20% reduction per level)

---

### Jump to Coordinates

Execute a direct jump to specific coordinates without using a warp gate. Has 4x fuel penalty and range limitations.

**Endpoint**: `POST /api/players/{uuid}/travel/coordinate`

**Auth Required**: Yes

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| uuid      | string | Yes      | Player UUID (path param) |

#### Request Body

```json
{
  "target_x": 150,
  "target_y": 200
}
```

| Field    | Type    | Required | Description          |
|----------|---------|----------|----------------------|
| target_x | numeric | Yes      | X coordinate         |
| target_y | numeric | Yes      | Y coordinate         |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Jump successful",
  "data": {
    "fuel_consumed": 60,
    "xp_earned": 75,
    "new_location": {
      "id": 789,
      "uuid": "080e8400-e29b-41d4-a716-446655440000",
      "name": "Rigel",
      "type": "star",
      "x": 150.0,
      "y": 200.0,
      "is_inhabited": false,
      "description": null,
      "attributes": {}
    },
    "level_up": false,
    "new_level": 5
  }
}
```

**Fields Explained**:
- `fuel_consumed`: 4x penalty compared to warp gate (formula: ceil(distance / efficiency) × 4)
- `xp_earned`: Same as gate travel (max(10, distance × 5))
- `new_location`: Destination POI (creates new POI if empty space)
- No pirate encounters on coordinate jumps

#### Error Responses

| Status | Code         | Description                               |
|--------|--------------|-------------------------------------------|
| 404    | NOT_FOUND    | Player not found or unauthorized          |
| 400    | JUMP_FAILED  | Out of range, insufficient fuel, or error |
| 422    | VALIDATION   | Invalid coordinates                       |

**Warnings**:
- 4x fuel penalty compared to warp gate travel
- Maximum range limited by warp drive (formula: (warp_drive × 50) + 100)
- Can jump to empty space (system will be generated if none exists)
- No pirate encounters on direct jumps
- Useful for exploring uninhabited systems without gates

---

### Direct Jump to Hub

Direct jump to a specific POI by UUID. Internally converts to coordinate jump using the POI's coordinates.

**Endpoint**: `POST /api/players/{uuid}/travel/direct-jump`

**Auth Required**: Yes

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| uuid      | string | Yes      | Player UUID (path param) |

#### Request Body

```json
{
  "target_poi_uuid": "090e8400-e29b-41d4-a716-446655440000"
}
```

| Field           | Type   | Required | Description              |
|-----------------|--------|----------|--------------------------|
| target_poi_uuid | string | Yes      | UUID of destination POI  |

#### Success Response (200 OK)

Same as [Jump to Coordinates](#jump-to-coordinates) response.

#### Error Responses

| Status | Code         | Description                      |
|--------|--------------|----------------------------------|
| 404    | NOT_FOUND    | Player or target POI not found   |
| 400    | JUMP_FAILED  | Travel error (fuel, range, etc.) |
| 422    | VALIDATION   | Invalid UUID                     |

**Warnings**:
- Functionally identical to coordinate jump
- Target POI must exist in player's galaxy
- Subject to same fuel penalty and range limits as coordinate jumps

---

## Travel Calculations

### Calculate Fuel Cost

Calculate fuel costs for reaching a destination via warp gate or direct jump. Returns all travel options without executing travel.

**Endpoint**: `GET /api/travel/fuel-cost`

**Auth Required**: Yes

#### Query Parameters

| Parameter  | Type   | Required             | Description                      |
|------------|--------|----------------------|----------------------------------|
| ship_uuid  | string | Yes                  | Player ship UUID                 |
| poi_uuid   | string | Required without x,y | Destination POI UUID             |
| x          | numeric| Required without poi | Destination X coordinate         |
| y          | numeric| Required without poi | Destination Y coordinate         |

**Examples**:
- By POI: `?ship_uuid=abc123&poi_uuid=def456`
- By coordinates: `?ship_uuid=abc123&x=150&y=200`

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "from": {
      "uuid": "0a0e8400-e29b-41d4-a716-446655440000",
      "name": "Sol",
      "x": 0,
      "y": 0
    },
    "to": {
      "uuid": "0b0e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Centauri",
      "x": 4,
      "y": 1
    },
    "distance": 4.12,
    "ship": {
      "current_fuel": 300,
      "max_fuel": 500,
      "warp_drive": 4
    },
    "warp_gate": {
      "gate_uuid": "0c0e8400-e29b-41d4-a716-446655440000",
      "distance": 4.12,
      "fuel_cost": 4,
      "can_afford": true
    },
    "direct_jump": {
      "distance": 4.12,
      "fuel_cost": 16,
      "can_afford": true,
      "in_range": true,
      "max_range": 300.0
    },
    "cheapest_option": "warp_gate",
    "cheapest_fuel_cost": 4,
    "can_reach": true
  }
}
```

**Fields Explained**:
- `from` / `to`: Source and destination POI or coordinates
- `distance`: Euclidean distance in light-years
- `ship`: Current ship stats affecting travel
- `warp_gate`: Null if no direct gate exists
  - `gate_uuid`: Gate to use for this route
  - `fuel_cost`: Formula: max(1, ceil(distance / efficiency))
  - `efficiency` = 1 + (warp_drive - 1) × 0.2 (20% per level)
- `direct_jump`: Always calculated
  - `fuel_cost`: 4x penalty over gate travel
  - `in_range`: True if within max_range
  - `max_range`: (warp_drive × 50) + 100
- `cheapest_option`: "warp_gate", "direct_jump", or null if unreachable
- `can_reach`: True if player can afford cheapest option

#### Error Responses

| Status | Code        | Description                          |
|--------|-------------|--------------------------------------|
| 404    | NOT_FOUND   | Ship or destination not found        |
| 400    | NO_LOCATION | Ship player has no current location  |
| 422    | VALIDATION  | Missing or invalid parameters        |

**Warnings**:
- Ship must belong to authenticated user
- Destination POI must be in same galaxy as ship
- Warp gate option only present if direct gate exists
- Does not execute travel (preview only)

---

### Preview XP

Calculate XP earned for a given travel distance.

**Endpoint**: `GET /api/travel/xp-preview`

**Auth Required**: Yes

#### Query Parameters

| Parameter | Type    | Required | Description                      |
|-----------|---------|----------|----------------------------------|
| distance  | numeric | Yes      | Distance to calculate XP for     |

**Example**: `?distance=150`

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "distance": 150.0,
    "xp_earned": 750
  }
}
```

**Fields Explained**:
- `distance`: Input distance
- `xp_earned`: Formula: max(10, distance × 5)

#### Error Responses

| Status | Code       | Description                   |
|--------|------------|-------------------------------|
| 422    | VALIDATION | Missing or negative distance  |

**Warnings**:
- XP formula: max(10, distance × 5)
- Minimum 10 XP for any travel
- Independent of travel method (gate vs jump)

---

## Warp Gates

### List Warp Gates

Get all active, non-hidden warp gates at a specific location. Gates are bidirectional.

**Endpoint**: `GET /api/warp-gates/{locationUuid}`

**Auth Required**: No (public endpoint)

#### Request Parameters

| Parameter    | Type   | Required | Description                 |
|--------------|--------|----------|-----------------------------|
| locationUuid | string | Yes      | Location UUID (path param)  |

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "location": {
      "id": 123,
      "uuid": "0d0e8400-e29b-41d4-a716-446655440000",
      "name": "Sirius",
      "type": "star",
      "x": 100.0,
      "y": 150.0,
      "is_inhabited": true,
      "description": null,
      "attributes": {}
    },
    "gate_count": 4,
    "gates": [
      {
        "uuid": "0e0e8400-e29b-41d4-a716-446655440000",
        "destination": {
          "id": 456,
          "uuid": "0f0e8400-e29b-41d4-a716-446655440000",
          "name": "Procyon",
          "type": "star",
          "x": 125.0,
          "y": 175.0,
          "is_inhabited": true,
          "description": null,
          "attributes": {}
        },
        "fuel_cost": 8,
        "distance": 35.36
      },
      {
        "uuid": "100e8400-e29b-41d4-a716-446655440000",
        "destination": {
          "id": 789,
          "uuid": "110e8400-e29b-41d4-a716-446655440000",
          "name": "Vega",
          "type": "star",
          "x": 90.0,
          "y": 140.0,
          "is_inhabited": true,
          "description": null,
          "attributes": {}
        },
        "fuel_cost": 5,
        "distance": 16.16
      }
    ]
  }
}
```

**Fields Explained**:
- `location`: The POI where gates originate
- `gate_count`: Number of active gates
- `gates`: Array of gate objects
  - `destination`: The other end of the gate (bidirectional)
  - `fuel_cost`: Base fuel cost (subject to ship efficiency)
  - `distance`: Distance between gate endpoints

#### Error Responses

| Status | Code      | Description           |
|--------|-----------|-----------------------|
| 404    | NOT_FOUND | Location not found    |

**Warnings**:
- Only shows active, non-hidden gates
- Gates are bidirectional (can travel either direction)
- Hidden gates require discovery
- Mirror universe gates require sensor level 5 to detect

---

### Get Warp Gate Pirates

Get pirate presence information for a specific warp gate. **Note**: This endpoint is referenced but implementation not found in provided controllers. May be part of pirate encounter system.

**Endpoint**: `GET /api/warp-gates/{warpGateUuid}/pirates`

**Status**: Not implemented in provided code. Listed for completeness based on requirements.

---

## Location Info

### Get Location by Coordinates/UUID

Get comprehensive information about a location in the galaxy. Response detail varies based on player's knowledge level (scans, charts, inhabitance status).

**Endpoint**: `POST /api/location/current/{uuid?}`

**Query Parameters**: `?x={x}&y={y}` (if UUID not provided)

**Auth Required**: Yes

#### Request Parameters

| Parameter | Type   | Required             | Description                      |
|-----------|--------|----------------------|----------------------------------|
| uuid      | string | Required without x,y | System UUID (path param)         |
| x         | number | Required without uuid| X coordinate (query param)       |
| y         | number | Required without uuid| Y coordinate (query param)       |

**Examples**:
- By UUID: `POST /api/location/current/abc-123-def`
- By coordinates: `POST /api/location/current?x=150&y=200`

#### Success Response - Known System (200 OK)

```json
{
  "success": true,
  "data": {
    "location": "star_system",
    "system_name": "Barnard's Star",
    "system_uuid": "120e8400-e29b-41d4-a716-446655440000",
    "coordinates": {
      "x": 50,
      "y": 75
    },
    "sector": {
      "uuid": "130e8400-e29b-41d4-a716-446655440000",
      "name": "Frontier Sector",
      "grid": {
        "x": 5,
        "y": 7
      },
      "bounds": {
        "x_min": 40,
        "x_max": 60,
        "y_min": 70,
        "y_max": 90
      },
      "danger_level": "medium",
      "display_name": "Frontier Sector (5,7)"
    },
    "type": "star",
    "knowledge_level": "detailed",
    "is_current_location": false,
    "inhabited": {
      "is_inhabited": true,
      "bodies": [
        {
          "type": "Terrestrial Planet",
          "uuid": "140e8400-e29b-41d4-a716-446655440000",
          "name": "Barnard Prime"
        },
        {
          "type": "Gas Giant",
          "uuid": "150e8400-e29b-41d4-a716-446655440000",
          "name": "Barnard II"
        }
      ],
      "planet_count": 2,
      "moon_count": 3,
      "station_count": 1
    },
    "has": {
      "gates": {
        "160e8400-e29b-41d4-a716-446655440000": {
          "destination_uuid": "170e8400-e29b-41d4-a716-446655440000",
          "destination_name": "Wolf 359",
          "distance": 45.2
        },
        "180e8400-e29b-41d4-a716-446655440000": {
          "destination_uuid": null,
          "destination_name": "Unknown destination",
          "distance": null
        }
      },
      "gate_count": 2,
      "services": [
        "trading_hub",
        "cartography",
        "repair_yard"
      ],
      "orbital_defenses": [
        {
          "type": "orbital_cannons",
          "location": "Barnard Prime",
          "location_uuid": "140e8400-e29b-41d4-a716-446655440000"
        }
      ]
    },
    "danger": {
      "threat_level": "medium",
      "pirate_presence": true,
      "affected_lanes": 1,
      "anomalies": 0
    }
  }
}
```

#### Success Response - Unknown System (200 OK)

```json
{
  "success": true,
  "data": {
    "location": "star_system",
    "system_name": "Unknown System",
    "system_uuid": "190e8400-e29b-41d4-a716-446655440000",
    "coordinates": {
      "x": 250,
      "y": 300
    },
    "sector": {
      "uuid": "1a0e8400-e29b-41d4-a716-446655440000",
      "name": "Void",
      "grid": {
        "x": 25,
        "y": 30
      }
    },
    "knowledge_level": "unknown",
    "inhabited": "unknown",
    "planets": "unknown",
    "minerals": "unknown",
    "colonies": "unknown"
  }
}
```

#### Success Response - Empty Space (200 OK)

```json
{
  "success": true,
  "data": {
    "location": "empty_space",
    "coordinates": {
      "x": 999,
      "y": 888
    },
    "message": "User is in empty space",
    "sector": {
      "uuid": "1b0e8400-e29b-41d4-a716-446655440000",
      "name": "Outer Void",
      "grid": {
        "x": 99,
        "y": 88
      }
    }
  }
}
```

**Fields Explained**:
- `location`: Type of location ("star_system" or "empty_space")
- `knowledge_level`: Based on scan level and charts
  - "unknown": No knowledge (< scan level 2, no chart, uninhabited)
  - "minimal": scan level 0
  - "basic": scan level 1-2
  - "moderate": scan level 3-4
  - "detailed": scan level 5-6
  - "complete": scan level ≥7
- `inhabited.bodies`: List of bodies if scan level ≥1
- `has.gates`: Gate destinations (hidden if player doesn't know the lane)
  - `destination_uuid`: null if lane is unknown
  - `destination_name`: "Unknown destination" if lane is unknown
- `has.services`: Available services (trading_hub, cartography, ship_shop, repair_yard, plans_shop, salvage_yard)
- `has.orbital_defenses`: Present if scan level ≥4 and system is fortified
- `danger`: Present if scan level ≥3
  - `pirate_presence`: True if scan level ≥5 and pirates detected
  - `anomalies`: Visible if scan level ≥6

#### Error Responses

| Status | Code              | Description                          |
|--------|-------------------|--------------------------------------|
| 404    | NO_ACTIVE_PLAYER  | No active player found for user      |
| 400    | MISSING_PARAMETERS| Neither UUID nor coordinates provided|
| 404    | NOT_FOUND         | System not found in current galaxy   |
| 401    | UNAUTHENTICATED   | Authentication required              |

**Warnings**:
- System must be in player's current galaxy
- Knowledge level depends on: inhabitation status, star charts, scan level
- Core and inhabited systems have baseline knowledge visible to all
- Gate destinations hidden if player hasn't discovered/charted the lane
- Orbital defenses require scan level ≥4
- Pirate detection requires scan level ≥5
- Anomaly detection requires scan level ≥6

---

## Star Systems

### Get Current System

Get comprehensive information about the player's current star system, including position within the system.

**Endpoint**: `GET /api/players/{playerUuid}/current-system`

**Auth Required**: Yes

#### Request Parameters

| Parameter  | Type   | Required | Description              |
|------------|--------|----------|--------------------------|
| playerUuid | string | Yes      | Player UUID (path param) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Current system data retrieved",
  "data": {
    "system": {
      "uuid": "1c0e8400-e29b-41d4-a716-446655440000",
      "name": "Epsilon Eridani",
      "type": "star",
      "stellar_class": "K2V",
      "coordinates": {
        "x": 75,
        "y": 100
      },
      "is_inhabited": true,
      "sector": {
        "uuid": "1d0e8400-e29b-41d4-a716-446655440000",
        "name": "Core Worlds",
        "grid": {
          "x": 7,
          "y": 10
        }
      }
    },
    "visibility": {
      "level": 10,
      "label": "Complete"
    },
    "bodies": {
      "count": 5,
      "planets": 3,
      "moons": 8,
      "asteroid_belts": 1,
      "list": [
        {
          "uuid": "1e0e8400-e29b-41d4-a716-446655440000",
          "name": "Epsilon Prime",
          "type": "terrestrial_planet",
          "orbital_index": 1,
          "habitable": true,
          "has_colony": true,
          "owner": {
            "uuid": "1f0e8400-e29b-41d4-a716-446655440000",
            "call_sign": "Commander Shepard"
          }
        }
      ]
    },
    "facilities": {
      "trading_hub": {
        "uuid": "200e8400-e29b-41d4-a716-446655440000",
        "name": "Epsilon Trade Center",
        "type": "major"
      },
      "services": ["trading", "repair", "refuel", "cartography"],
      "warp_gates": 6
    },
    "current_position": {
      "uuid": "1e0e8400-e29b-41d4-a716-446655440000",
      "name": "Epsilon Prime",
      "type": "terrestrial_planet",
      "type_label": "Terrestrial Planet",
      "is_at_star": false
    }
  }
}
```

**Fields Explained**:
- `system`: Parent star system information
  - Automatically resolved if player is at a child body (planet/moon)
  - `stellar_class`: Star classification (G2V, K2V, M5V, etc.)
- `visibility.level`: Numeric visibility level (0-10+)
- `visibility.label`: Human-readable label ("Minimal", "Basic", "Moderate", "Detailed", "Complete")
- `bodies`: Comprehensive list of orbital bodies in system
- `facilities`: Available services and infrastructure
- `current_position`: Player's exact location within the system
  - `is_at_star`: True if player is at the star itself (not a planet)

#### Success Response - Generating (202 Accepted)

If system data is still being generated asynchronously:

```json
{
  "success": false,
  "message": "System data generation in progress",
  "data": {
    "status": "generating",
    "system_uuid": "1c0e8400-e29b-41d4-a716-446655440000",
    "system_name": "Epsilon Eridani",
    "progress": "Generating orbital bodies...",
    "percent": 45,
    "retry_after": 5,
    "current_position": {
      "uuid": "1c0e8400-e29b-41d4-a716-446655440000",
      "name": "Epsilon Eridani",
      "type": "star",
      "type_label": "Star",
      "is_at_star": true
    }
  }
}
```

**Generation Status**:
- HTTP 202 indicates data is still generating
- Client should poll with `retry_after` delay (typically 5 seconds)
- `percent`: Progress percentage (0-100)
- `progress`: Human-readable progress message

#### Error Responses

| Status | Code        | Description                          |
|--------|-------------|--------------------------------------|
| 404    | NOT_FOUND   | Player not found or unauthorized     |
| 400    | NO_LOCATION | Player has no current location       |

**Warnings**:
- System data may be generated asynchronously on first visit
- If generating, client should poll until status is "ready"
- Visibility level depends on inhabitance, scans, and charts
- Automatically resolves parent star if player is at a child body

---

### List Star Systems

Get a paginated list of star systems the player knows about. Supports filtering by inhabited, scanned, charted, or all known systems.

**Endpoint**: `GET /api/players/{playerUuid}/star-systems`

**Auth Required**: Yes

#### Query Parameters

| Parameter  | Type   | Required | Default | Description                              |
|------------|--------|----------|---------|------------------------------------------|
| playerUuid | string | Yes      | -       | Player UUID (path param)                 |
| filter     | string | No       | known   | Filter type (known/inhabited/scanned/charted) |
| limit      | number | No       | 50      | Results per page (max 200)               |
| offset     | number | No       | 0       | Pagination offset                        |

**Filter Options**:
- `known`: All systems player knows (inhabited + scanned + charted)
- `inhabited`: Only inhabited systems (publicly known)
- `scanned`: Only systems player has scanned
- `charted`: Only systems player has purchased charts for

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Star systems retrieved",
  "data": {
    "systems": [
      {
        "uuid": "210e8400-e29b-41d4-a716-446655440000",
        "name": "Tau Ceti",
        "coordinates": {
          "x": 120.0,
          "y": 180.0
        },
        "is_inhabited": true,
        "has_chart": true,
        "scan_level": 7,
        "scan_level_label": "Complete"
      },
      {
        "uuid": "220e8400-e29b-41d4-a716-446655440000",
        "name": "Unknown System",
        "coordinates": null,
        "is_inhabited": false,
        "has_chart": false,
        "scan_level": 2,
        "scan_level_label": "Basic"
      }
    ],
    "pagination": {
      "total": 147,
      "limit": 50,
      "offset": 0,
      "has_more": true
    },
    "filter": "known"
  }
}
```

**Fields Explained**:
- `systems`: Array of system summaries
  - `name`: "Unknown System" if no chart and uninhabited
  - `coordinates`: null if no chart and uninhabited
  - `scan_level`: Numeric sensor level equivalent (0-10+)
  - `scan_level_label`: Human-readable scan level
- `pagination.has_more`: True if more results available
- `filter`: Applied filter

#### Error Responses

| Status | Code      | Description                      |
|--------|-----------|----------------------------------|
| 404    | NOT_FOUND | Player not found or unauthorized |

**Warnings**:
- Results sorted alphabetically by name
- Maximum 200 systems per request
- Coordinates hidden for uninhabited systems without charts
- Scan level based on ship sensors and existing scans

---

### Get Star System Details

Get comprehensive, detailed information about a specific star system including bodies, facilities, defenses, and travel options.

**Endpoint**: `GET /api/players/{playerUuid}/star-systems/{systemUuid}`

**Auth Required**: Yes

#### Request Parameters

| Parameter  | Type   | Required | Description                   |
|------------|--------|----------|-------------------------------|
| playerUuid | string | Yes      | Player UUID (path param)      |
| systemUuid | string | Yes      | Star system UUID (path param) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Star system data retrieved",
  "data": {
    "system": {
      "uuid": "230e8400-e29b-41d4-a716-446655440000",
      "name": "82 Eridani",
      "type": "star",
      "stellar_class": "G5V",
      "coordinates": {
        "x": 200,
        "y": 250
      },
      "is_inhabited": true,
      "sector": {
        "uuid": "240e8400-e29b-41d4-a716-446655440000",
        "name": "Expansion Zone",
        "grid": {
          "x": 20,
          "y": 25
        },
        "danger_level": "low"
      },
      "attributes": {
        "luminosity": 0.75,
        "age": "4.6 billion years"
      }
    },
    "visibility": {
      "level": 8,
      "label": "Detailed"
    },
    "bodies": {
      "count": 7,
      "planets": 4,
      "moons": 12,
      "asteroid_belts": 2,
      "stations": 1,
      "list": [
        {
          "uuid": "250e8400-e29b-41d4-a716-446655440000",
          "name": "Eridani Alpha",
          "type": "terrestrial_planet",
          "type_label": "Terrestrial Planet",
          "orbital_index": 1,
          "attributes": {
            "habitable": true,
            "in_goldilocks_zone": true,
            "temperature": 285,
            "atmosphere": "nitrogen-oxygen",
            "mineral_richness": 0.65
          },
          "has_colony": true,
          "owner": {
            "uuid": "260e8400-e29b-41d4-a716-446655440000",
            "call_sign": "ColonialOne"
          },
          "moons": 2
        }
      ]
    },
    "facilities": {
      "trading_hub": {
        "uuid": "270e8400-e29b-41d4-a716-446655440000",
        "name": "Eridani Trade Station",
        "type": "major",
        "services": ["trading", "repair", "refuel", "ship_shop"]
      },
      "cartographer": {
        "uuid": "280e8400-e29b-41d4-a716-446655440000",
        "name": "Stellar Cartographer",
        "chart_coverage": 5,
        "base_price": 1000
      },
      "warp_gates": [
        {
          "uuid": "290e8400-e29b-41d4-a716-446655440000",
          "destination": {
            "uuid": "2a0e8400-e29b-41d4-a716-446655440000",
            "name": "Procyon",
            "distance": 85.3
          }
        }
      ]
    },
    "defenses": {
      "fortified": true,
      "threat_level": "moderate",
      "platforms": 3,
      "total_firepower": 850
    },
    "travel": {
      "distance_from_player": 124.5,
      "warp_gate_available": true,
      "estimated_fuel_cost": 18,
      "estimated_xp": 622
    }
  }
}
```

**Fields Explained**:
- `system`: Core system information
  - `stellar_class`: Star type (affects habitability zones)
  - `attributes`: Star-specific properties
- `visibility.level`: Determines which data is visible
  - Inhabited systems: Full visibility (level 10)
  - Uninhabited: Based on scan level and ship sensors
- `bodies.list`: Detailed body information
  - `orbital_index`: Position (1 = innermost)
  - `attributes`: Visible properties (habitability, temperature, minerals)
  - `moons`: Count of moons orbiting this body
- `facilities`: Infrastructure and services
  - `trading_hub`: null if none present
  - `cartographer`: Star chart vendor (if present)
  - `warp_gates`: Outgoing gates with destinations
- `defenses`: Present if visibility level ≥4
  - `fortified`: Whether system has defenses
  - `total_firepower`: Combat capability
- `travel`: Calculated travel options (if not current location)
  - `estimated_fuel_cost`: Based on player's current ship
  - `estimated_xp`: Expected XP for travel

#### Success Response - Generating (202 Accepted)

```json
{
  "success": false,
  "message": "System data generation in progress",
  "data": {
    "status": "generating",
    "system_uuid": "230e8400-e29b-41d4-a716-446655440000",
    "system_name": "82 Eridani",
    "progress": "Populating trading hub inventories...",
    "percent": 75,
    "retry_after": 5
  }
}
```

#### Error Responses

| Status | Code      | Description                          |
|--------|-----------|--------------------------------------|
| 404    | NOT_FOUND | Player or system not found           |
| 500    | GENERATION_FAILED | System generation error  |

**Warnings**:
- First visit to a system triggers async generation (HTTP 202)
- Client should poll status endpoint until ready
- Visibility limited for uninhabited systems without charts
- System must be in player's galaxy
- Detailed body information may be gated by scan level

---

### Get System Generation Status

Check the generation status of a star system. Used for polling during async system generation.

**Endpoint**: `GET /api/players/{playerUuid}/star-systems/{systemUuid}/status`

**Auth Required**: Yes

#### Request Parameters

| Parameter  | Type   | Required | Description                   |
|------------|--------|----------|-------------------------------|
| playerUuid | string | Yes      | Player UUID (path param)      |
| systemUuid | string | Yes      | Star system UUID (path param) |

#### Success Response - Ready (200 OK)

```json
{
  "success": true,
  "message": "System data is ready",
  "data": {
    "status": "ready",
    "system_uuid": "2b0e8400-e29b-41d4-a716-446655440000",
    "system_name": "Gliese 581",
    "ready": true,
    "progress": null,
    "percent": 100,
    "started_at": "2026-02-15T14:30:00Z",
    "completed_at": "2026-02-15T14:30:15Z",
    "polling": null
  }
}
```

#### Success Response - Generating (200 OK)

```json
{
  "success": true,
  "message": "System generation in progress",
  "data": {
    "status": "generating",
    "system_uuid": "2b0e8400-e29b-41d4-a716-446655440000",
    "system_name": "Gliese 581",
    "ready": false,
    "progress": "Generating minerals...",
    "percent": 60,
    "started_at": "2026-02-15T14:30:00Z",
    "completed_at": null,
    "polling": {
      "retry_after": 5
    }
  }
}
```

**Fields Explained**:
- `status`: "ready", "generating", "pending", or "error"
- `ready`: Boolean indicating if system data is available
- `progress`: Human-readable progress message (null when ready)
- `percent`: Generation progress (0-100)
- `started_at`: ISO 8601 timestamp when generation began
- `completed_at`: ISO 8601 timestamp when generation finished (null if in progress)
- `polling.retry_after`: Suggested delay in seconds before next poll

#### Error Response (500 Internal Server Error)

```json
{
  "success": false,
  "message": "Generation failed: Database constraint violation",
  "error": {
    "code": "GENERATION_FAILED",
    "data": {
      "system_uuid": "2b0e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

#### Error Responses

| Status | Code              | Description                      |
|--------|-------------------|----------------------------------|
| 404    | NOT_FOUND         | Player or system not found       |
| 500    | GENERATION_FAILED | System generation encountered error |

**Warnings**:
- Poll this endpoint while status is "generating"
- Use `retry_after` value for polling interval (typically 5 seconds)
- Status transitions: pending → generating → ready
- Error status indicates generation failure (may need retry)

---

## Common Response Patterns

### Standard Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Standard Error Response

```json
{
  "success": false,
  "message": "Human-readable error message",
  "error": {
    "code": "ERROR_CODE",
    "data": { ... }
  }
}
```

### Validation Error Response

```json
{
  "success": false,
  "message": "Validation failed",
  "error": {
    "code": "VALIDATION_ERROR",
    "data": {
      "errors": {
        "field_name": ["Error message 1", "Error message 2"]
      }
    }
  }
}
```

---

## Common Error Codes

| Code               | HTTP | Description                          |
|--------------------|------|--------------------------------------|
| NOT_FOUND          | 404  | Resource not found                   |
| VALIDATION_ERROR   | 422  | Request validation failed            |
| UNAUTHENTICATED    | 401  | Authentication required              |
| NO_LOCATION        | 400  | Player has no current location       |
| NO_ACTIVE_SHIP     | 400  | Player has no active ship            |
| NO_ACTIVE_PLAYER   | 404  | No active player found for user      |
| TRAVEL_FAILED      | 400  | Travel execution failed              |
| JUMP_FAILED        | 400  | Jump execution failed                |
| GATE_NOT_FOUND     | 400  | Warp gate not found at location      |
| GENERATION_FAILED  | 500  | System generation error              |
| MISSING_PARAMETERS | 400  | Required parameters missing          |

---

## Formulas & Constants

### Travel Calculations

**Fuel Cost (Warp Gate)**:
```
efficiency = 1 + (warp_drive - 1) × 0.2
fuel_cost = max(1, ceil(distance / efficiency))
```

**Fuel Cost (Direct Jump)**:
```
direct_fuel_cost = fuel_cost × 4  // 4x penalty
```

**Max Jump Range**:
```
max_range = (warp_drive × 50) + 100
```

**Travel XP**:
```
xp_earned = max(10, distance × 5)
```

**Sensor Range**:
```
sensor_range = sensor_level × 100  // in light-years
```

### Efficiency Scaling

| Warp Drive | Efficiency | Fuel Savings |
|------------|------------|--------------|
| 1          | 1.0        | 0%           |
| 2          | 1.2        | 17%          |
| 3          | 1.4        | 29%          |
| 4          | 1.6        | 38%          |
| 5          | 1.8        | 44%          |
| 10         | 2.8        | 64%          |

---

## Notes & Caveats

### General
- All endpoints require Bearer token authentication unless noted
- Player must belong to authenticated user (verified by `user_id`)
- UUIDs are used for player, POI, gate, and galaxy identifiers
- Distances are in arbitrary "light-years" (game units)

### Travel
- Warp gates provide 75% fuel savings compared to direct jumps (4x penalty)
- Gates are bidirectional (can travel either direction)
- Mirror universe gates require sensor level 5 and have 24-hour cooldown
- Pirate encounters possible on warp gate travel (not direct jumps)
- Fuel regenerates passively over time (10 units/hour base, scaled by warp drive)

### Visibility & Knowledge
- Inhabited systems: Full visibility to all players
- Uninhabited systems: Visibility gated by scan level and star charts
- Scan level = max(baseline, player_scans, current_ship_sensors)
- Star charts reveal name, coordinates, and 2-hop gate network
- Sensor level affects local scan detail and pirate detection accuracy

### System Generation
- Systems generated asynchronously on first visit
- Client should poll status endpoint with 5-second intervals
- Generation includes: bodies, minerals, trading hub inventory
- Generation typically completes in 5-15 seconds

### Performance
- Nearby systems limited to 50 results
- Local scan limited to 100 POIs
- Star system list limited to 200 per request (default 50)
- Queries use spatial bounding box pre-filtering for efficiency

### TODO Items in Code
- Weak validation: `gate_uuid` and `target_poi_uuid` should use 'uuid' rule instead of 'string'
- Pirates endpoint (`/api/warp-gates/{warpGateUuid}/pirates`) not implemented in provided code

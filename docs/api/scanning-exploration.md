# Scanning & Exploration API Documentation

API endpoints for system scanning, exploration data, and star chart management in Space Wars 3002.

## Table of Contents

- [System Scanning](#system-scanning)
  - [POST /api/players/{uuid}/scan-system](#post-scan-system)
  - [GET /api/players/{uuid}/scan-results/{poiUuid}](#get-scan-results)
  - [POST /api/players/{uuid}/bulk-scan-levels](#post-bulk-scan-levels)
  - [GET /api/players/{uuid}/system-data/{poiUuid}](#get-system-data)
  - [GET /api/players/{uuid}/exploration-log](#get-exploration-log)
- [Star Charts (Cartography)](#star-charts-cartography)
  - [GET /api/players/{uuid}/star-charts](#get-player-charts)
  - [POST /api/players/{uuid}/star-charts/purchase](#post-purchase-chart)
  - [GET /api/star-charts/preview](#get-chart-preview)
  - [GET /api/star-charts/pricing](#get-chart-pricing)
  - [GET /api/star-charts/system/{poiUuid}](#get-chart-system-info)
  - [GET /api/trading-hubs/{uuid}/cartographer](#get-cartographer)

---

## System Scanning

### Overview

System scanning is a progressive revelation system based on ship sensor levels (1-9). Higher sensor levels reveal increasingly detailed information about star systems:

| Scan Level | Sensor Required | Reveals |
|------------|----------------|---------|
| 0 | - | Unscanned - no data |
| 1 | 1 | Basic Geography - planet count, types, habitability |
| 2 | 2 | Gate Detection - warp gate presence and status |
| 3 | 3 | Basic Resources - common mineral deposits |
| 4 | 4 | Rare Resources - asteroid minerals, rare deposits |
| 5 | 5 | Hidden Features - habitable moons, orbital mining |
| 6 | 6 | Anomaly Detection - ruins, spatial anomalies, derelicts |
| 7 | 7 | Deep Scan - subsurface deposits, terraforming data |
| 8 | 8 | Advanced Intel - pirate hideouts, hidden bases |
| 9 | 9 | Precursor Secrets - hidden gates, ancient tech caches |

**Baseline Scan Levels**: Systems have automatic baseline knowledge based on their status:
- **Inhabited systems**: Level 3 baseline (well-documented)
- **Charted uninhabited systems**: Level 2 baseline (shared intel)
- **Uncharted systems**: Level 0 baseline (complete fog of war)

---

### POST /api/players/{uuid}/scan-system {#post-scan-system}

Scan a star system to reveal information based on ship sensor level. Can scan current location or nearby systems within sensor range.

**Authentication**: Required (Bearer token)

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Player UUID |

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `poi_uuid` | string | No | Target system POI UUID. If omitted, scans current location. |
| `force` | boolean | No | Force re-scan even if already scanned at this level (default: `false`) |

**Example Request**:
```json
{
  "poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
  "force": false
}
```

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
      "name": "Alpha Centauri",
      "coordinates": {
        "x": 150,
        "y": 200
      }
    },
    "scan_level": 3,
    "scan_data": {
      "geography": {
        "star_type": "G-class yellow dwarf",
        "planet_count": 5,
        "planet_types": {
          "rocky": 3,
          "gas": 2,
          "ice": 0,
          "other": 0
        },
        "dwarf_planets": 1,
        "asteroid_belts": 2,
        "habitability": {
          "goldilocks_planets": 1,
          "notes": [
            "1 planet(s) in habitable zone",
            "Centauri Prime: temperate"
          ]
        }
      },
      "gate_count": 3,
      "active_gates": 2,
      "dormant_gates": 1,
      "gates": [
        {
          "status": "active",
          "destination": "known"
        },
        {
          "status": "dormant",
          "destination": "unknown",
          "activation_hint": "Requires 500 units of exotic fuel"
        }
      ],
      "rocky_planets": ["iron", "copper", "titanium"],
      "gas_giants": ["metallic_hydrogen", "helium-3"]
    },
    "cached": false,
    "can_reveal_more": true,
    "next_level_reveals": [
      "minerals_rare",
      "asteroid_resources"
    ],
    "new_discoveries": [
      "1",
      "2",
      "3"
    ]
  },
  "message": "System scanned",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479"
  }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| `system.uuid` | string | System POI UUID |
| `system.name` | string | System name |
| `system.coordinates` | object | X/Y coordinates |
| `scan_level` | integer | Achieved scan level (0-9) |
| `scan_data` | object | Hierarchical scan data (see Scan Data Structure below) |
| `cached` | boolean | `true` if using cached scan data, `false` if newly scanned |
| `can_reveal_more` | boolean | Whether higher sensor levels would reveal more data |
| `next_level_reveals` | array | Categories revealed at next scan level (null if at max) |
| `new_discoveries` | array | Array of newly discovered level keys (e.g., `["3", "4"]`) |

**Scan Data Structure**:

The `scan_data` object is hierarchical and accumulates as scan levels increase:

- **Level 1 (Geography)**:
  - `geography.star_type`: Star classification
  - `geography.planet_count`: Number of planets
  - `geography.planet_types`: Breakdown by type (rocky/gas/ice/other)
  - `geography.dwarf_planets`: Count of dwarf planets
  - `geography.asteroid_belts`: Count of asteroid belts
  - `geography.habitability`: Goldilocks zone data and notes

- **Level 2 (Gates)**:
  - `gate_count`: Total warp gates
  - `active_gates`: Active gate count
  - `dormant_gates`: Dormant gate count
  - `gates[]`: Array of gate details (status, destination, activation hints)

- **Level 3 (Basic Resources)**:
  - `rocky_planets[]`: Common minerals on terrestrial planets
  - `gas_giants[]`: Gas giant resources (metallic hydrogen, helium-3)

- **Level 4 (Rare Resources)**:
  - `asteroid_minerals[]`: Asteroid belt resources
  - `rare_deposits[]`: Array of rare mineral locations
    - `location`: Planet/belt name
    - `minerals[]`: Array of rare minerals

- **Level 5 (Hidden Features)**:
  - `habitable_moons[]`: Habitable moon data (name, parent, climate)
  - `orbital_mining[]`: Mining opportunities (location, richness)
  - `ring_deposits[]`: Ring system deposits (planet, deposits)

- **Level 6 (Anomalies)**:
  - `ruins[]`: Ancient ruins (location, type, age estimate)
  - `spatial_anomalies[]`: Anomalies (name, type, danger level)
  - `derelicts[]`: Derelict ships (name, ship class, salvageable)

- **Level 7 (Deep Scan)**:
  - `subsurface_deposits[]`: Underground minerals (planet, minerals, depth)
  - `core_composition[]`: Planetary cores (planet, type, stability)
  - `terraforming[]`: Terraforming data (planet, viable, difficulty, time)

- **Level 8 (Advanced Intel)**:
  - `pirate_hideouts[]`: Pirate presence (gate_id, threat level)
  - `hidden_bases[]`: Hidden bases (type, faction)
  - `cloaked_structures[]`: Cloaked objects (name, type)

- **Level 9 (Precursor Secrets)**:
  - `hidden_gates[]`: Hidden warp gates (type, status, requirements)
  - `tech_caches[]`: Precursor tech caches (location, contents, danger)
  - `ancient_secrets[]`: Ancient mysteries (location, type, hint)

#### Error Responses

**400 Bad Request** - No active ship:
```json
{
  "success": false,
  "error": {
    "code": "NO_SHIP",
    "message": "No active ship",
    "details": null
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**400 Bad Request** - Out of scan range:
```json
{
  "success": false,
  "error": {
    "code": "OUT_OF_RANGE",
    "message": "System out of scan range",
    "details": {
      "distance": 156.42,
      "max_range": 100.0
    }
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**400 Bad Request** - No current location:
```json
{
  "success": false,
  "error": {
    "code": "NO_LOCATION",
    "message": "No current location",
    "details": null
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**401 Unauthorized** - Invalid/missing token

**403 Forbidden** - Player doesn't belong to authenticated user

**404 Not Found** - Player or system not found

#### Warnings & Caveats

- **Scan Range**: Remote scans (non-current location) require the target system to be within sensor range
  - Scan range formula: `sensorLevel × 100` light-years (e.g., sensor level 3 = 300 LY range)
- **Progressive Revelation**: Scans are cumulative. If you scan at level 2, then upgrade sensors to level 5, a re-scan will add levels 3-5 data
- **Caching**: Scans are cached. Use `force: true` to re-scan at the same level (useful after game events that might change system data)
- **Precursor Ships**: Ships with `is_precursor: true` have effective sensor level 100 and see everything immediately
- **Auto-scan on Arrival**: Systems are typically auto-scanned when a player arrives via warp gate travel

---

### GET /api/players/{uuid}/scan-results/{poiUuid} {#get-scan-results}

Retrieve existing scan results for a specific system. Returns cached scan data if available, or baseline data based on system region/inhabited status.

**Authentication**: Required (Bearer token)

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Player UUID |
| `poiUuid` | string | URL path | Yes | System POI UUID |

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
      "name": "Alpha Centauri",
      "type": "Star",
      "coordinates": {
        "x": 150,
        "y": 200
      },
      "is_inhabited": true
    },
    "scan": {
      "scan_level": 3,
      "scan_data": {
        "geography": { "..." },
        "gate_count": 3,
        "rocky_planets": ["iron", "copper"]
      },
      "scanned_at": "2026-02-16T08:45:22Z",
      "can_reveal_more": true,
      "next_level_reveals": [
        "minerals_rare",
        "asteroid_resources"
      ],
      "display": {
        "color": "#3366aa",
        "opacity": 0.6,
        "label": "Basic Resources"
      }
    }
  },
  "message": "Scan results retrieved",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| `system` | object | System metadata |
| `scan.scan_level` | integer | Achieved scan level (0-9) |
| `scan.scan_data` | object | Accumulated scan data (see scan-system endpoint) |
| `scan.scanned_at` | string\|null | ISO 8601 timestamp of last scan, null if baseline |
| `scan.baseline` | boolean | `true` if using baseline data (not explicitly scanned) |
| `scan.can_reveal_more` | boolean | Whether higher sensors would reveal more |
| `scan.next_level_reveals` | array\|null | Categories revealed at next level |
| `scan.display.color` | string | Hex color for UI display |
| `scan.display.opacity` | float | Opacity for UI (0.0 - 1.0) |
| `scan.display.label` | string | Human-readable scan level name |

**Baseline Data**: If the system hasn't been explicitly scanned, returns baseline data:
- Inhabited systems: Level 3 baseline with label "Baseline Intel"
- Charted uninhabited: Level 2 baseline
- Uncharted: Level 0 (no data)

#### Error Responses

**401 Unauthorized** - Invalid/missing token

**403 Forbidden** - Player doesn't belong to authenticated user

**404 Not Found** - Player or system not found

#### Warnings & Caveats

- This endpoint returns **existing** scan data. Use `/scan-system` to perform a new scan.
- Baseline data is provided for inhabited/charted systems even if never explicitly scanned
- The `display` object provides UI hints for map coloring based on scan level

---

### POST /api/players/{uuid}/bulk-scan-levels {#post-bulk-scan-levels}

Get scan levels for multiple systems at once. Optimized for map display. Returns scan level, color, opacity, and label for each system.

**Authentication**: Required (Bearer token)

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Player UUID |

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `poi_uuids` | array | Yes | Array of POI UUIDs to query (max 500) |

**Example Request**:
```json
{
  "poi_uuids": [
    "a7c3e9f2-1234-5678-90ab-cdef12345678",
    "b8d4f0a3-2345-6789-01bc-def123456789",
    "c9e5g1b4-3456-7890-12cd-ef1234567890"
  ]
}
```

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "scan_levels": {
      "a7c3e9f2-1234-5678-90ab-cdef12345678": {
        "scan_level": 3,
        "color": "#3366aa",
        "opacity": 0.6,
        "label": "Basic Resources"
      },
      "b8d4f0a3-2345-6789-01bc-def123456789": {
        "scan_level": 0,
        "color": "#1a1a2e",
        "opacity": 0.2,
        "label": "Unscanned"
      },
      "c9e5g1b4-3456-7890-12cd-ef1234567890": {
        "scan_level": 5,
        "color": "#33aa66",
        "opacity": 0.8,
        "label": "Hidden Features"
      }
    }
  },
  "message": "Bulk scan levels retrieved",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

The `scan_levels` object is keyed by POI UUID. Each entry contains:

| Field | Type | Description |
|-------|------|-------------|
| `scan_level` | integer | Achieved scan level (0-9, includes baseline levels) |
| `color` | string | Hex color for map display |
| `opacity` | float | Opacity for map display (0.0 - 1.0) |
| `label` | string | Scan level label |

**Color Scheme**:
- Unscanned (0): `#1a1a2e`, opacity 0.2
- Geography/Gates (1-2): `#4a4a6a`, opacity 0.4
- Basic/Rare Resources (3-4): `#3366aa`, opacity 0.6
- Hidden Features/Anomalies (5-6): `#33aa66`, opacity 0.8
- Deep Scan/Intel (7-8): `#aa9933`, opacity 0.9
- Precursor Secrets (9): `#ff6600`, opacity 1.0

#### Error Responses

**400 Bad Request** - Too many POIs:
```json
{
  "success": false,
  "error": {
    "code": "TOO_MANY",
    "message": "Too many POIs requested (max 500)",
    "details": null
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**401 Unauthorized** - Invalid/missing token

**403 Forbidden** - Player doesn't belong to authenticated user

**404 Not Found** - Player not found

**422 Unprocessable Entity** - Invalid request body:
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "poi_uuids": "Array of POI UUIDs required"
    }
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

#### Warnings & Caveats

- **Rate Limiting**: Maximum 500 POI UUIDs per request to prevent abuse
- **Performance**: Optimized with batch queries. Prefer this endpoint over multiple individual requests
- **Baseline Levels**: Includes baseline scan levels for inhabited/charted systems (see `/scan-results` for details)
- **Use Case**: Designed for map rendering, where you need to color many systems simultaneously

---

### GET /api/players/{uuid}/system-data/{poiUuid} {#get-system-data}

Get filtered system data based on player's achieved scan level. Returns only information that the player's sensors have revealed.

**Authentication**: Required (Bearer token)

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Player UUID |
| `poiUuid` | string | URL path | Yes | System POI UUID |

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "system_data": {
      "uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
      "name": "Alpha Centauri",
      "scan_level": 5,
      "coordinates": {
        "x": 150,
        "y": 200
      },
      "geography": {
        "star_type": "G-class yellow dwarf",
        "planet_count": 5,
        "planet_types": { "rocky": 3, "gas": 2, "ice": 0, "other": 0 },
        "dwarf_planets": 1,
        "asteroid_belts": 2,
        "habitability": {
          "goldilocks_planets": 1,
          "notes": ["1 planet(s) in habitable zone"]
        }
      },
      "gates": {
        "gate_count": 3,
        "active_gates": 2,
        "dormant_gates": 1,
        "gates": [
          { "status": "active", "destination": "known" }
        ]
      },
      "resources": {
        "rocky_planets": ["iron", "copper", "titanium"],
        "gas_giants": ["metallic_hydrogen", "helium-3"]
      },
      "rare_resources": {
        "asteroid_minerals": ["platinum", "iridium"],
        "rare_deposits": [
          {
            "location": "Centauri Beta",
            "minerals": ["uranium", "exotic_matter"]
          }
        ]
      },
      "hidden_features": {
        "habitable_moons": [
          {
            "name": "Luna Minor",
            "parent": "Centauri Prime",
            "climate": "temperate"
          }
        ],
        "orbital_mining": [
          {
            "location": "Belt Alpha",
            "richness": "rich"
          }
        ],
        "ring_deposits": []
      }
    },
    "scan_level": 5
  },
  "message": "System data retrieved",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

The `system_data` object contains only categories revealed at the player's scan level:

| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | System POI UUID |
| `name` | string | System name |
| `scan_level` | integer | Player's achieved scan level for this system |
| `coordinates` | object | Always visible - X/Y coordinates |
| `geography` | object | Level 1+ - Star type, planet counts, habitability |
| `gates` | object | Level 2+ - Warp gate data |
| `resources` | object | Level 3+ - Basic mineral deposits |
| `rare_resources` | object | Level 4+ - Rare minerals and asteroid resources |
| `hidden_features` | object | Level 5+ - Moons, orbital mining, rings |
| `anomalies` | object | Level 6+ - Ruins, anomalies, derelicts |
| `deep_scan` | object | Level 7+ - Subsurface, cores, terraforming |
| `intel` | object | Level 8+ - Pirate hideouts, hidden bases |
| `precursor` | object | Level 9+ - Hidden gates, tech caches, secrets |

#### Error Responses

**401 Unauthorized** - Invalid/missing token

**403 Forbidden** - Player doesn't belong to authenticated user

**404 Not Found** - Player or system not found

#### Warnings & Caveats

- **Progressive Filtering**: Only returns data categories revealed by the player's scan level
- **Baseline Included**: Includes baseline scan levels (inhabited/charted systems)
- **Coordinates Always Visible**: Even unscanned systems (level 0) show coordinates
- **Use Case**: Use this endpoint when you need to ensure the client only receives data the player should see (prevents client-side data mining)

---

### GET /api/players/{uuid}/exploration-log {#get-exploration-log}

Get complete exploration log showing all systems the player has scanned, with scan levels and statistics.

**Authentication**: Required (Bearer token)

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Player UUID |

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "uuid": "f1a2b3c4-1234-5678-90ab-def123456789",
        "system": {
          "uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
          "name": "Alpha Centauri",
          "type": "Star",
          "coordinates": {
            "x": 150,
            "y": 200
          },
          "is_inhabited": true,
          "region": "core"
        },
        "scan_level": 5,
        "scan_level_label": "Hidden Features",
        "scanned_at": "2026-02-16T08:45:22Z",
        "can_reveal_more": true,
        "display": {
          "color": "#33aa66",
          "opacity": 0.8
        }
      },
      {
        "uuid": "e2b3c4d5-2345-6789-01bc-ef1234567890",
        "system": {
          "uuid": "b8d4f0a3-2345-6789-01bc-def123456789",
          "name": "Beta Hydrae",
          "type": "Star",
          "coordinates": {
            "x": 275,
            "y": 320
          },
          "is_inhabited": false,
          "region": "outer"
        },
        "scan_level": 2,
        "scan_level_label": "Gate Detection",
        "scanned_at": "2026-02-15T14:22:10Z",
        "can_reveal_more": true,
        "display": {
          "color": "#4a4a6a",
          "opacity": 0.4
        }
      }
    ],
    "statistics": {
      "total_scanned": 2,
      "by_level": {
        "2": 1,
        "5": 1
      },
      "by_region": {
        "core": 1,
        "outer": 1
      }
    }
  },
  "message": "Exploration log retrieved",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

`entries[]` - Array of exploration log entries:

| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | SystemScan record UUID |
| `system.uuid` | string | System POI UUID |
| `system.name` | string | System name |
| `system.type` | string | POI type (usually "Star") |
| `system.coordinates` | object | X/Y coordinates |
| `system.is_inhabited` | boolean | Whether system is inhabited |
| `system.region` | string | Region: "core", "outer", or "unknown" |
| `scan_level` | integer | Achieved scan level (0-9) |
| `scan_level_label` | string | Human-readable scan level name |
| `scanned_at` | string\|null | ISO 8601 timestamp of scan |
| `can_reveal_more` | boolean | Whether higher sensors would reveal more |
| `display.color` | string | Hex color for UI |
| `display.opacity` | float | Opacity for UI (0.0 - 1.0) |

`statistics` - Aggregate data:

| Field | Type | Description |
|-------|------|-------------|
| `total_scanned` | integer | Total number of scanned systems |
| `by_level` | object | Count of systems at each scan level (keyed by level) |
| `by_region` | object | Count of systems by region (core/outer/unknown) |

#### Error Responses

**401 Unauthorized** - Invalid/missing token

**403 Forbidden** - Player doesn't belong to authenticated user

**404 Not Found** - Player not found

#### Warnings & Caveats

- **Ordered by Recency**: Entries are ordered by `scanned_at` descending (most recent first)
- **Only Explicit Scans**: Does not include systems with only baseline knowledge (never explicitly scanned)
- **Statistics**: The `by_level` and `by_region` objects may have gaps (e.g., no level 6 scans = no "6" key)
- **Use Case**: Ideal for exploration achievement tracking and progression displays

---

## Star Charts (Cartography)

### Overview

Star charts are purchasable navigation data sold at **Stellar Cartographer** shops (found at ~30% of inhabited trading hubs). Charts reveal multiple connected systems using a hybrid coverage system:

**Coverage Algorithm**:
1. **Spatial Radius** (inhabited hubs only): 5 LY radius from purchase location
2. **Warp Gate BFS**: 2-hop traversal through active warp gates (inhabited) or 1-hop (uninhabited)

**Pricing Formula**:
```
price = base_price × (multiplier ^ (unknown_count - 1)) × shop_markup
```

Where:
- `base_price`: 1000 credits (configurable)
- `multiplier`: 1.5 (exponential scaling - discourages repeated small purchases)
- `unknown_count`: Number of systems in coverage the player doesn't have charts for
- `shop_markup`: Shop-specific markup (typically 1.0 - 1.3)

**Starting Charts**: New players receive 3 free charts to the nearest inhabited systems.

**Pirate Detection**: Charts include probabilistic pirate warnings:
- Base accuracy: 70%
- Sensor bonus: +5% per sensor level
- Max accuracy: 95%
- Confidence levels: Low (sensors 1-2), Medium (3-4), High (5+)

---

### GET /api/players/{uuid}/star-charts {#get-player-charts}

Get all star charts the player has purchased or received (including free starting charts).

**Authentication**: Not explicitly required in code, but recommended

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Player UUID |

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "revealed_systems": [
      {
        "name": "Alpha Centauri",
        "coordinates": [150, 200],
        "type": "Star",
        "is_inhabited": true,
        "has_trading_hub": true,
        "pirate_warning": "Medium",
        "connections": [
          "Beta Hydrae",
          "Gamma Draconis",
          "Delta Pavonis"
        ],
        "poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
        "purchased_at": "2026-02-16T10:00:00Z",
        "price_paid": 0.0
      },
      {
        "name": "Beta Hydrae",
        "coordinates": [275, 320],
        "type": "Star",
        "is_inhabited": false,
        "has_trading_hub": false,
        "pirate_warning": "None",
        "connections": [
          "Alpha Centauri"
        ],
        "poi_uuid": "b8d4f0a3-2345-6789-01bc-def123456789",
        "purchased_at": "2026-02-15T12:30:00Z",
        "price_paid": 1500.0
      }
    ],
    "total_charts": 2
  },
  "message": "",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

`revealed_systems[]` - Array of charted systems:

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | System name |
| `coordinates` | array | `[x, y]` coordinates |
| `type` | string | POI type label (usually "Star") |
| `is_inhabited` | boolean | Whether system is inhabited |
| `has_trading_hub` | boolean | Whether system has an active trading hub |
| `pirate_warning` | string | Pirate confidence: "High", "Medium", "Low", or "None" |
| `connections[]` | array | Names of connected systems (via non-hidden warp gates) |
| `poi_uuid` | string | System POI UUID |
| `purchased_at` | string | ISO 8601 timestamp of purchase |
| `price_paid` | float | Credits paid (0.0 for free starting charts) |

`total_charts` - Total number of charts owned

#### Error Responses

**404 Not Found** - Player not found

#### Warnings & Caveats

- **Pirate Detection Probabilistic**: `pirate_warning` uses probabilistic detection. False negatives possible, but no false positives.
- **Sensor Level Affects Accuracy**: Pirate detection accuracy improves with higher sensors (70% base, +5% per sensor level, 95% max)
- **Connections**: Only shows non-hidden warp gate connections. Hidden gates (level 9 scans) are not listed.
- **Free Starting Charts**: Price paid will be 0.0 for the 3 free charts granted at player initialization

---

### POST /api/players/{uuid}/star-charts/purchase {#post-purchase-chart}

Purchase a star chart from a Stellar Cartographer shop. Charts reveal multiple systems based on coverage algorithm (see Overview).

**Authentication**: Required (Bearer token)

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Player UUID |

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `cartographer_poi_uuid` | string | Yes | POI UUID of the Stellar Cartographer's location |

**Example Request**:
```json
{
  "cartographer_poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678"
}
```

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "systems_revealed": 5,
    "total_systems": 8,
    "price_paid": 3375.0,
    "credits_remaining": 46625.0
  },
  "message": "Star chart purchased successfully",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| `systems_revealed` | integer | Number of **new** systems revealed (excludes already-owned charts) |
| `total_systems` | integer | Total systems in chart coverage (includes already-owned) |
| `price_paid` | float | Credits deducted |
| `credits_remaining` | float | Player's remaining credits after purchase |

#### Error Responses

**400 Bad Request** - Insufficient credits:
```json
{
  "success": false,
  "error": {
    "code": "ERROR",
    "message": "Insufficient credits",
    "details": null
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**400 Bad Request** - Already have all charts:
```json
{
  "success": false,
  "error": {
    "code": "ERROR",
    "message": "You already have charts for all systems in this region",
    "details": null
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**401 Unauthorized** - Invalid/missing token

**403 Forbidden** - Player doesn't belong to authenticated user

**404 Not Found** - Cartographer not found:
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "No stellar cartographer found at this location",
    "details": null
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**422 Unprocessable Entity** - Validation error (missing/invalid `cartographer_poi_uuid`)

#### Warnings & Caveats

- **Exponential Pricing**: Price increases exponentially with unknown systems. Buying 1 system at a time is extremely expensive.
- **Pre-purchase Preview**: Use `/star-charts/preview` endpoint to see coverage and price before purchase
- **Automatic Knowledge Grant**: Purchase also grants fog-of-war knowledge for charted systems (integrates with `PlayerKnowledgeService`)
- **Chart Coverage**: Charts are centered on the cartographer's location, not an arbitrary system
- **Inhabited vs Uninhabited**: Coverage differs by region:
  - Inhabited: 5 LY spatial radius + 2-hop BFS
  - Uninhabited: 0 LY radius + 1-hop BFS
- **Credits Deducted First**: Credits are deducted before charts are granted. Transaction is atomic.

---

### GET /api/star-charts/preview {#get-chart-preview}

Preview star chart coverage and pricing before purchase. Shows which systems will be revealed and whether they're already known.

**Authentication**: Not explicitly required in code, but recommended

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `player_uuid` | string | Query string | Yes | Player UUID |
| `center_poi_uuid` | string | Query string | Yes | Center POI UUID (cartographer location) |
| `cartographer_poi_uuid` | string | Query string | No | Cartographer POI UUID (for accurate pricing with markup) |

**Example Request**:
```
GET /api/star-charts/preview?player_uuid=123e4567-e89b-12d3-a456-426614174000&center_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678&cartographer_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678
```

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "center": {
      "poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
      "name": "Alpha Centauri"
    },
    "coverage": [
      {
        "poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
        "name": "Alpha Centauri",
        "coordinates": {
          "x": 150,
          "y": 200
        },
        "is_inhabited": true,
        "already_known": true
      },
      {
        "poi_uuid": "b8d4f0a3-2345-6789-01bc-def123456789",
        "name": "Beta Hydrae",
        "coordinates": {
          "x": 275,
          "y": 320
        },
        "is_inhabited": false,
        "already_known": false
      },
      {
        "poi_uuid": "c9e5g1b4-3456-7890-12cd-ef1234567890",
        "name": "Gamma Draconis",
        "coordinates": {
          "x": 180,
          "y": 205
        },
        "is_inhabited": true,
        "already_known": false
      }
    ],
    "total_systems": 3,
    "known_systems": 1,
    "unknown_systems": 2,
    "price": 2250.0,
    "can_afford": true
  },
  "message": "",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| `center.poi_uuid` | string | Chart center POI UUID |
| `center.name` | string | Chart center system name |
| `coverage[]` | array | All systems in chart coverage |
| `coverage[].poi_uuid` | string | System POI UUID |
| `coverage[].name` | string | System name |
| `coverage[].coordinates` | object | X/Y coordinates |
| `coverage[].is_inhabited` | boolean | Whether system is inhabited |
| `coverage[].already_known` | boolean | Whether player already has chart for this system |
| `total_systems` | integer | Total systems in coverage |
| `known_systems` | integer | Systems player already has charts for |
| `unknown_systems` | integer | New systems player would gain |
| `price` | float | Price in credits (includes shop markup if `cartographer_poi_uuid` provided) |
| `can_afford` | boolean | Whether player has enough credits |

#### Error Responses

**422 Unprocessable Entity** - Validation error (missing required query params)

#### Warnings & Caveats

- **Use Before Purchase**: Always call this endpoint before `/star-charts/purchase` to show the user what they're buying
- **Price Includes Markup**: If `cartographer_poi_uuid` is provided, price includes shop markup. If omitted, uses base price with 1.0 markup.
- **Coverage Algorithm**: See Star Charts Overview for details on spatial + BFS coverage
- **Zero Price**: If `unknown_systems` is 0, price will be 0 (and purchase will be rejected)

---

### GET /api/star-charts/pricing {#get-chart-pricing}

Get detailed pricing information for a star chart, including formula breakdown and configuration values.

**Authentication**: Not explicitly required in code, but recommended

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `player_uuid` | string | Query string | Yes | Player UUID |
| `center_poi_uuid` | string | Query string | Yes | Center POI UUID (cartographer location) |
| `cartographer_poi_uuid` | string | Query string | No | Cartographer POI UUID (for accurate markup) |

**Example Request**:
```
GET /api/star-charts/pricing?player_uuid=123e4567-e89b-12d3-a456-426614174000&center_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678&cartographer_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678
```

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "price": 2250.0,
    "unknown_systems_count": 2,
    "base_price": 1000,
    "multiplier": 1.5,
    "markup": 1.0,
    "formula": "base_price × (multiplier ^ (unknown_count - 1)) × markup",
    "can_afford": true,
    "player_credits": 50000.0
  },
  "message": "",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| `price` | float | Final price in credits |
| `unknown_systems_count` | integer | Number of new systems in coverage |
| `base_price` | integer | Base price per chart (config: `star_charts.base_price`) |
| `multiplier` | float | Exponential multiplier (config: `star_charts.unknown_multiplier`) |
| `markup` | float | Shop-specific markup (1.0 if no cartographer provided) |
| `formula` | string | Human-readable pricing formula |
| `can_afford` | boolean | Whether player can afford the chart |
| `player_credits` | float | Player's current credit balance |

#### Error Responses

**422 Unprocessable Entity** - Validation error (missing required query params)

#### Warnings & Caveats

- **Transparency**: This endpoint exposes the pricing formula to the player
- **Formula Example**: For 2 unknown systems, base 1000, multiplier 1.5, markup 1.2:
  - `1000 × (1.5 ^ (2 - 1)) × 1.2 = 1000 × 1.5 × 1.2 = 1800 credits`
- **Exponential Growth**: With multiplier 1.5, prices grow as:
  - 1 unknown: 1000 credits
  - 2 unknown: 1500 credits
  - 3 unknown: 2250 credits
  - 4 unknown: 3375 credits
  - 5 unknown: 5062.5 credits
- **Use Case**: Show pricing breakdown to educate players about exponential pricing

---

### GET /api/star-charts/system/{poiUuid} {#get-chart-system-info}

Get detailed information about a specific system if the player has a chart for it. Returns 403 if no chart owned.

**Authentication**: Not explicitly required in code, but recommended

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `poiUuid` | string | URL path | Yes | System POI UUID |
| `player_uuid` | string | Query string | Yes | Player UUID |

**Example Request**:
```
GET /api/star-charts/system/a7c3e9f2-1234-5678-90ab-cdef12345678?player_uuid=123e4567-e89b-12d3-a456-426614174000
```

#### Success Response (200 OK)

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "system": {
      "name": "Alpha Centauri",
      "coordinates": [150, 200],
      "type": "Star",
      "is_inhabited": true,
      "has_trading_hub": true,
      "pirate_warning": "Medium",
      "connections": [
        "Beta Hydrae",
        "Gamma Draconis",
        "Delta Pavonis"
      ]
    },
    "poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678"
  },
  "message": "",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| `system.name` | string | System name |
| `system.coordinates` | array | `[x, y]` coordinates |
| `system.type` | string | POI type label |
| `system.is_inhabited` | boolean | Whether system is inhabited |
| `system.has_trading_hub` | boolean | Whether system has active trading hub |
| `system.pirate_warning` | string | Pirate confidence: "High", "Medium", "Low", or "None" |
| `system.connections[]` | array | Names of connected systems (via non-hidden warp gates) |
| `poi_uuid` | string | System POI UUID |

#### Error Responses

**403 Forbidden** - No chart for this system:
```json
{
  "success": false,
  "error": {
    "code": "FORBIDDEN",
    "message": "You do not have a star chart for this system",
    "details": null
  },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**422 Unprocessable Entity** - Validation error (missing `player_uuid`)

#### Warnings & Caveats

- **Requires Chart Ownership**: Returns 403 if player doesn't own a chart for this system
- **Pirate Detection**: Pirate warning is probabilistic (see Star Charts Overview)
- **Connections**: Only shows non-hidden warp gate connections
- **Use Case**: Use this endpoint to display detailed system info in a navigation UI when clicking on a charted system

---

### GET /api/trading-hubs/{uuid}/cartographer {#get-cartographer}

Check if a trading hub has a Stellar Cartographer shop. Returns shop details if present.

**Authentication**: Not explicitly required in code, but recommended

#### Request Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string | URL path | Yes | Trading hub UUID or POI UUID |

#### Success Response (200 OK)

**With Cartographer**:
```json
{
  "success": true,
  "data": {
    "has_cartographer": true,
    "cartographer": {
      "shop_name": "Stellar Charts & Navigation Ltd.",
      "markup_multiplier": 1.15,
      "location": {
        "poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678",
        "name": "Alpha Centauri",
        "coordinates": {
          "x": 150,
          "y": 200
        }
      }
    }
  },
  "message": "",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Without Cartographer**:
```json
{
  "success": true,
  "data": {
    "has_cartographer": false
  },
  "message": "",
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

**Response Fields**:

| Field | Type | Description |
|-------|------|-------------|
| `has_cartographer` | boolean | Whether trading hub has a cartographer shop |
| `cartographer.shop_name` | string | Shop name (if present) |
| `cartographer.markup_multiplier` | float | Price markup multiplier (typically 1.0 - 1.3) |
| `cartographer.location.poi_uuid` | string | Location POI UUID |
| `cartographer.location.name` | string | System name |
| `cartographer.location.coordinates` | object | X/Y coordinates |

#### Error Responses

**404 Not Found** - Trading hub not found

#### Warnings & Caveats

- **Spawn Rate**: Cartographers spawn at ~30% of inhabited trading hubs (see galaxy generation)
- **Markup Variation**: `markup_multiplier` varies by shop (typically 1.0 - 1.3)
- **UUID Flexibility**: Endpoint accepts both TradingHub UUID and POI UUID
- **Use Case**: Call this endpoint when player docks at a trading hub to show available services

---

## General API Conventions

All endpoints follow these patterns:

### Standard Response Format

**Success**:
```json
{
  "success": true,
  "data": { /* endpoint-specific data */ },
  "message": "Success message",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479"
  }
}
```

**Error**:
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479"
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `UNAUTHORIZED` | 401 | Missing or invalid authentication token |
| `FORBIDDEN` | 403 | Player doesn't belong to authenticated user |
| `NOT_FOUND` | 404 | Resource (player, system, etc.) not found |
| `VALIDATION_ERROR` | 422 | Request body/query validation failed |
| `ERROR` | 400 | Generic error (check message for details) |
| `NO_SHIP` | 400 | Player has no active ship |
| `NO_LOCATION` | 400 | Player has no current location |
| `OUT_OF_RANGE` | 400 | Target system is out of sensor range |
| `SCAN_FAILED` | 400 | Scan operation failed |
| `TOO_MANY` | 400 | Request exceeds rate limits |

### Authentication

Most endpoints require Bearer token authentication:

```
Authorization: Bearer <token>
```

Token must belong to the user who owns the player with UUID `{uuid}`.

### UUIDs

All resource identifiers use UUIDs (RFC 4122 format):
- Player UUIDs
- POI UUIDs (systems)
- Trading Hub UUIDs
- Scan UUIDs

### Timestamps

All timestamps use ISO 8601 format with timezone:
```
2026-02-16T10:30:00Z
```

---

## Integration Examples

### Scanning Workflow

**1. Player arrives at a new system** (auto-scan triggered server-side):
```javascript
// Auto-scan happens during warp gate travel
// Client should refresh scan data after arrival
```

**2. Player manually scans current location**:
```bash
POST /api/players/123e4567-e89b-12d3-a456-426614174000/scan-system
{
  "force": false
}
```

**3. Player scans a nearby system**:
```bash
POST /api/players/123e4567-e89b-12d3-a456-426614174000/scan-system
{
  "poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678"
}
```

**4. Retrieve existing scan data**:
```bash
GET /api/players/123e4567-e89b-12d3-a456-426614174000/scan-results/a7c3e9f2-1234-5678-90ab-cdef12345678
```

### Map Display Workflow

**1. Load scan levels for all visible systems**:
```bash
POST /api/players/123e4567-e89b-12d3-a456-426614174000/bulk-scan-levels
{
  "poi_uuids": [
    "a7c3e9f2-1234-5678-90ab-cdef12345678",
    "b8d4f0a3-2345-6789-01bc-def123456789",
    "c9e5g1b4-3456-7890-12cd-ef1234567890"
  ]
}
```

**2. Render systems with color/opacity from response**:
```javascript
scanLevels.forEach(system => {
  renderSystem(system.poi_uuid, {
    color: system.color,
    opacity: system.opacity,
    label: system.label
  });
});
```

### Star Chart Purchase Workflow

**1. Player docks at trading hub, check for cartographer**:
```bash
GET /api/trading-hubs/a7c3e9f2-1234-5678-90ab-cdef12345678/cartographer
```

**2. Preview chart coverage and price**:
```bash
GET /api/star-charts/preview?player_uuid=123e4567-e89b-12d3-a456-426614174000&center_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678&cartographer_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678
```

**3. Show pricing breakdown**:
```bash
GET /api/star-charts/pricing?player_uuid=123e4567-e89b-12d3-a456-426614174000&center_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678&cartographer_poi_uuid=a7c3e9f2-1234-5678-90ab-cdef12345678
```

**4. Purchase chart**:
```bash
POST /api/players/123e4567-e89b-12d3-a456-426614174000/star-charts/purchase
{
  "cartographer_poi_uuid": "a7c3e9f2-1234-5678-90ab-cdef12345678"
}
```

**5. Load updated chart list**:
```bash
GET /api/players/123e4567-e89b-12d3-a456-426614174000/star-charts
```

---

## Configuration

Key configuration values from `config/game_config.php`:

### Scanning Configuration

```php
'scanning' => [
    'inhabited_baseline_level' => 3,    // Baseline scan level for inhabited systems
    'charted_baseline_level' => 2,      // Baseline for charted uninhabited systems
    'uncharted_baseline_level' => 0,    // Baseline for uncharted systems
],
```

### Star Charts Configuration

```php
'star_charts' => [
    'base_price' => 1000,                           // Base price per chart
    'unknown_multiplier' => 1.5,                    // Exponential pricing multiplier
    'starting_charts_count' => 3,                   // Free charts for new players
    'pirate_detection_base_accuracy' => 0.70,       // 70% base accuracy
    'pirate_detection_sensor_bonus' => 0.05,        // +5% per sensor level
    'pirate_detection_max_accuracy' => 0.95,        // 95% max accuracy
],

'knowledge' => [
    'chart_radius_inhabited_ly' => 5,               // Spatial radius for inhabited hubs
    'chart_hops_inhabited' => 2,                    // BFS hop count for inhabited
    'chart_hops_uninhabited' => 1,                  // BFS hop count for uninhabited
    'chart_sector_limited' => true,                 // Limit to same sector
],
```

### Sensor Range

Scan range formula (from `App\Support\SensorRangeCalculator`):
```
scan_range = sensor_level × 100 light-years
```

Examples:
- Sensor level 1: 100 LY range
- Sensor level 3: 300 LY range
- Sensor level 5: 500 LY range
- Sensor level 9: 900 LY range

---

## Notes & Best Practices

### Performance Optimization

1. **Use Bulk Endpoints**: Prefer `/bulk-scan-levels` over multiple `/scan-results` calls
2. **Cache Aggressively**: Scan data changes infrequently. Cache on client side.
3. **Rate Limiting**: `/bulk-scan-levels` has 500 POI limit per request

### Security Considerations

1. **Authorization**: Always verify player ownership via bearer token
2. **Data Filtering**: Use `/system-data` to ensure clients only see revealed data
3. **No False Positives**: Pirate detection can have false negatives but never false positives

### UI/UX Recommendations

1. **Color Coding**: Use provided `color` and `opacity` values for consistent map visualization
2. **Preview Before Purchase**: Always show coverage preview and pricing before chart purchase
3. **Sensor Upgrades**: Clearly communicate that higher sensors reveal more scan data
4. **Exploration Progress**: Use `/exploration-log` to show achievement-style progress tracking
5. **Exponential Pricing Warning**: Educate players about exponential chart pricing to encourage bulk purchases

### Common Pitfalls

1. **Forgetting to Force Scan**: If game events change system data (e.g., pirates move), old scans remain cached unless `force: true`
2. **Ignoring Baseline Levels**: Inhabited systems have level 3 baseline even if never scanned
3. **Sensor Range Confusion**: Remote scanning requires target to be within `sensor_level × 100` LY
4. **Chart Coverage Assumptions**: Coverage depends on inhabited status (5 LY + 2 hops vs 0 LY + 1 hop)

---

**Version**: 2026.02.16.001
**Last Updated**: 2026-02-16
**API Base**: `/api`
**Authentication**: Laravel Sanctum Bearer Tokens

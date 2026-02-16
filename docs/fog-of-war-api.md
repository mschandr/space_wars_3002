# Fog-of-War & Player Knowledge System — Frontend Integration Guide

## Overview

The galaxy map uses a **fog-of-war system** where players only see what they have discovered. All undiscovered systems are hidden. Knowledge is accumulated through travel, star charts, sensors, and rumors.

### Core Concepts

1. **Knowledge never fully vanishes.** Once discovered, a system stays at minimum `DETECTED` (level 1) forever. Only detail degrades over time.
2. **Core sector baseline.** Inhabited systems in the player's current sector are automatically known at `DETECTED` level (civilized space common knowledge).
3. **Sensor range is small.** Sensors reveal systems within 1–16 LY depending on level. 1 coordinate unit = 1 light year.
4. **Knowledge decays.** Chart/rumor-sourced knowledge degrades over 7 real-time days. Travel-sourced knowledge is permanent.

### Knowledge Levels

| Level | Value | Label | What's Revealed | UI Opacity |
|-------|-------|-------|-----------------|------------|
| UNKNOWN | 0 | Unknown | Nothing — system is hidden (fog) | 0.0 |
| DETECTED | 1 | Detected | Coordinates only (dot on map) | 0.3 |
| BASIC | 2 | Basic | Name, star type, inhabited status, planet count | 0.5 |
| SURVEYED | 3 | Surveyed | Full services (inhabited) or star+planets (uninhabited) | 0.8 |
| VISITED | 4 | Visited | Complete knowledge — permanent | 1.0 |

### Knowledge Sources

| Source | Decays? | How Acquired |
|--------|---------|-------------|
| `visit` | Never | Player traveled to the system |
| `spawn` | Never | Player's starting system |
| `warp_lane` | Never | Discovered via warp gate connection |
| `sensor` | N/A (real-time) | Within ship sensor range right now |
| `baseline` | N/A (computed) | Inhabited system in player's current sector |
| `chart` | Yes (7 days) | Purchased from stellar cartographer |
| `rumor` | Yes (7 days) | Acquired from NPC/precursor rumors |
| `scan` | Never | System was scanned |

### Sensor Range Formula

| Sensor Level | Range (LY) |
|-------------|-----------|
| 1 | 1 |
| 2 | 2 |
| 3 | 4 |
| 4 | 6 |
| 5 | 8 |
| 6 | 10 |
| 7 | 12 |
| 8 | 14 |
| 9 | 16 |

Formula: `level === 1 ? 1 : (level - 1) * 2`

### Freshness & Decay

The `freshness` field (0.0–1.0) indicates how current the knowledge is:

| Freshness | Hours Elapsed | Visual Treatment |
|-----------|---------------|------------------|
| 1.0 – 0.7 | 0–50h | Full opacity, solid borders |
| 0.7 – 0.3 | 50–117h | Slightly faded, dashed borders, detail drops 1 level |
| 0.3 – 0.1 | 117–168h | Very faded, dotted borders, drops to DETECTED floor |
| 0.1 | 168h+ (7d) | Minimum opacity, "?" overlay, coordinates only |

Permanent sources (`visit`, `spawn`, `warp_lane`) always return `freshness: 1.0`.

---

## API Endpoints

All endpoints require authentication via `Authorization: Bearer {token}` header.

---

### 1. Knowledge Map (Primary Map Endpoint)

The main endpoint for rendering the fog-of-war galaxy map. Returns **only what the player knows**.

```
GET /api/players/{playerUuid}/knowledge-map
```

**Query Parameters:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `sector_uuid` | string (UUID) | No | Filter results to a specific sector |

**Response: `200 OK`**

```json
{
  "success": true,
  "data": {
    "galaxy": {
      "uuid": "9f3e2a1b-...",
      "name": "Andromeda Reach",
      "width": 1000,
      "height": 1000
    },
    "player": {
      "uuid": "abc-123-...",
      "location": {
        "x": 150.0,
        "y": 200.0,
        "poi_uuid": "def-456-..."
      },
      "sensor_range_ly": 4.0,
      "sensor_level": 3
    },
    "known_systems": [
      {
        "poi_uuid": "def-456-...",
        "x": 150.0,
        "y": 200.0,
        "knowledge_level": 4,
        "knowledge_label": "Visited",
        "freshness": 1.0,
        "source": "visit",
        "name": "Kepler-442",
        "is_inhabited": true,
        "planet_count": 5,
        "star": {
          "type": "Star",
          "stellar_class": "G",
          "stellar_description": "G-Type (Yellow Star)",
          "temperature_range_k": { "min": 5200, "max": 6000 },
          "temperature_k": 5778,
          "luminosity": 1.0,
          "goldilocks_zone": { "inner_au": 0.95, "outer_au": 1.37 }
        },
        "services": {
          "trading_hub": true,
          "shipyard": false,
          "salvage_yard": true,
          "cartographer": true
        },
        "scan_level": 3,
        "has_scan_data": true
      },
      {
        "poi_uuid": "ghi-789-...",
        "x": 155.0,
        "y": 202.0,
        "knowledge_level": 1,
        "knowledge_label": "Detected",
        "freshness": 1.0,
        "source": "sensor",
        "star": {
          "type": "Star",
          "stellar_class": "K",
          "stellar_description": "K-Type (Orange Dwarf)",
          "temperature_range_k": { "min": 3700, "max": 5200 }
        }
      },
      {
        "poi_uuid": "jkl-012-...",
        "x": 148.0,
        "y": 195.0,
        "knowledge_level": 2,
        "knowledge_label": "Basic",
        "freshness": 0.65,
        "source": "chart",
        "name": "Proxima Hub",
        "is_inhabited": true,
        "planet_count": 3,
        "star": {
          "type": "Star",
          "stellar_class": "M",
          "stellar_description": "M-Type (Red Dwarf)",
          "temperature_range_k": { "min": 2400, "max": 3700 }
        },
        "pirate_warning": {
          "active": true,
          "danger_radius_ly": 5,
          "confidence": "Medium"
        }
      }
    ],
    "known_lanes": [
      {
        "gate_uuid": "gate-123-...",
        "from_poi_uuid": "def-456-...",
        "to_poi_uuid": "xyz-789-...",
        "from": { "x": 150.0, "y": 200.0 },
        "to": { "x": 180.0, "y": 220.0 },
        "has_pirate": true,
        "pirate_freshness": 0.8,
        "discovery_method": "travel"
      }
    ],
    "danger_zones": [
      {
        "center": { "x": 148.0, "y": 195.0 },
        "radius_ly": 5,
        "source": "pirate_warning",
        "confidence": "Medium"
      }
    ],
    "statistics": {
      "total_known": 42,
      "by_level": { "1": 10, "2": 15, "3": 12, "4": 5 },
      "known_lanes": 28,
      "pirate_warnings": 3
    }
  }
}
```

**Field Visibility by Knowledge Level:**

| Field | DETECTED (1) | BASIC (2) | SURVEYED (3) | VISITED (4) |
|-------|:---:|:---:|:---:|:---:|
| `poi_uuid` | Y | Y | Y | Y |
| `x`, `y` | Y | Y | Y | Y |
| `knowledge_level` | Y | Y | Y | Y |
| `knowledge_label` | Y | Y | Y | Y |
| `freshness` | Y | Y | Y | Y |
| `source` | Y | Y | Y | Y |
| `star.type` | Y | Y | Y | Y |
| `star.stellar_class` | Y | Y | Y | Y |
| `star.stellar_description` | Y | Y | Y | Y |
| `star.temperature_range_k` | Y | Y | Y | Y |
| `name` | - | Y | Y | Y |
| `is_inhabited` | - | Y | Y | Y |
| `planet_count` | - | Y | Y | Y |
| `star.temperature_k` | - | - | Y | Y |
| `star.luminosity` | - | - | Y | Y |
| `star.goldilocks_zone` | - | - | Y | Y |
| `services` | - | - | Y (inhabited only) | Y (inhabited only) |
| `pirate_warning` | Y (if known) | Y (if known) | Y (if known) | Y (if known) |
| `scan_level` | Y (if scanned) | Y (if scanned) | Y (if scanned) | Y (if scanned) |
| `has_scan_data` | Y (if scanned) | Y (if scanned) | Y (if scanned) | Y (if scanned) |

**`star` object structure (BASIC+):**

```typescript
interface StarInfo {
  type: string;                    // "Star", "Black Hole", etc.
  stellar_class?: string;          // "O"|"B"|"A"|"F"|"G"|"K"|"M" (Morgan-Keenan)
  stellar_description?: string;    // "G-Type (Yellow Star)"
  temperature_range_k?: {          // Temperature range for this class
    min: number;                   // e.g. 5200
    max: number;                   // e.g. 6000
  };
  // SURVEYED+ only:
  temperature_k?: number;          // Precise temperature in Kelvin (e.g. 5778)
  luminosity?: number;             // Relative to Sol (e.g. 1.0)
  goldilocks_zone?: {              // Habitable zone boundaries
    inner_au: number;              // Inner edge in AU
    outer_au: number;              // Outer edge in AU
  };
}
```

**Stellar classes (Morgan-Keenan system):**

| Class | Description | Temp Range (K) | Color | Rarity |
|-------|------------|----------------|-------|--------|
| O | Blue Supergiant | 30,000–60,000 | Blue | Ultra rare |
| B | Blue-White Giant | 10,000–30,000 | Blue-white | Very rare |
| A | White Star | 7,500–10,000 | White | Rare |
| F | Yellow-White Star | 6,000–7,500 | Yellow-white | Uncommon |
| G | Yellow Star (Sun-like) | 5,200–6,000 | Yellow | Common |
| K | Orange Dwarf | 3,700–5,200 | Orange | Very common |
| M | Red Dwarf | 2,400–3,700 | Red | Most common (76%) |

**Error Responses:**

| Status | Code | Condition |
|--------|------|-----------|
| 404 | - | Player not found |
| 403 | - | Accessing another user's player |
| 400 | `NO_LOCATION` | Player has no current location |

---

### 2. Current Location

Get detailed info about the player's current star system.

```
GET /api/players/{uuid}/location
```

**Response: `200 OK`**

```json
{
  "success": true,
  "data": {
    "location": {
      "uuid": "def-456-...",
      "name": "Kepler-442",
      "type": "star",
      "x": 150,
      "y": 200,
      "is_inhabited": true
    },
    "galaxy": {
      "uuid": "9f3e2a1b-...",
      "name": "Andromeda Reach"
    },
    "warp_gates_available": 3,
    "trading_hub": {
      "uuid": "hub-uuid-...",
      "name": "Central Trading Hub",
      "type": "standard",
      "has_salvage_yard": true,
      "services": ["trading", "repairs"]
    },
    "is_inhabited": true
  }
}
```

`trading_hub` is `null` if no trading hub exists at this location.

---

### 3. Nearby Systems (Sensor Scan)

Get star systems within the ship's sensor range.

```
GET /api/players/{uuid}/nearby-systems
```

**Response: `200 OK`**

```json
{
  "success": true,
  "data": {
    "current_location": {
      "name": "Kepler-442",
      "coordinates": { "x": 150.0, "y": 200.0 }
    },
    "sensor_range": 4.0,
    "sensor_level": 3,
    "systems_detected": 2,
    "nearby_systems": [
      {
        "uuid": "sys-uuid-...",
        "name": "Proxima Hub",
        "type": "Star",
        "distance": 2.24,
        "coordinates": { "x": 152.0, "y": 201.0 },
        "is_inhabited": true,
        "has_chart": true
      },
      {
        "uuid": "sys-uuid-2-...",
        "name": "Unknown System",
        "type": "Star",
        "distance": 3.61,
        "coordinates": null,
        "is_inhabited": false,
        "has_chart": false
      }
    ]
  }
}
```

**Notes:**
- `name` shows "Unknown System" if the player has no star chart for it
- `coordinates` is `null` if no star chart (the system is just a blip)
- Results capped at 50 systems
- Only returns star-type POIs (not planets/asteroids)

**Error Responses:**

| Status | Code | Condition |
|--------|------|-----------|
| 404 | - | Player not found |
| 400 | `NO_LOCATION` | Player has no current location |
| 400 | `NO_ACTIVE_SHIP` | Player has no active ship |

---

### 4. Local Area Scan

Scan all POI types (stars, planets, asteroids, nebulae) within sensor range.

```
GET /api/players/{uuid}/scan-local
```

**Response: `200 OK`**

```json
{
  "success": true,
  "data": {
    "current_location": {
      "name": "Kepler-442",
      "type": "star",
      "coordinates": { "x": 150.0, "y": 200.0 }
    },
    "sensor_range": 4.0,
    "sensor_level": 3,
    "total_pois_detected": 5,
    "pois_by_type": {
      "star": [
        {
          "uuid": "...",
          "name": "Nearby Star",
          "type": "Star",
          "distance": 2.5,
          "coordinates": { "x": 152.0, "y": 201.0 },
          "is_inhabited": true,
          "has_chart": true,
          "parent_poi": null
        }
      ],
      "terrestrial": [
        {
          "uuid": "...",
          "name": "Unknown Terrestrial",
          "type": "Terrestrial",
          "distance": 0.5,
          "coordinates": null,
          "is_inhabited": false,
          "has_chart": false,
          "parent_poi": { "id": 42 }
        }
      ],
      "asteroid_belt": [...]
    }
  }
}
```

**Notes:**
- Groups POIs by their type enum value
- Returns all POI types, not just stars
- Results capped at 100 POIs
- Same chart-based name/coordinate visibility as nearby-systems

---

### 5. Local Bodies (Orbital System)

Get planets, moons, asteroid belts, and stations orbiting the current star. Each body includes an always-visible `orbital_presence` showing large physical structures in orbit, and a sensor-gated `defensive_capability` breakdown available only when the player's ship sensors are level 5 or higher.

```
GET /api/players/{uuid}/local-bodies
```

**Response: `200 OK`**

```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "...",
      "name": "Kepler-442",
      "type": "star",
      "coordinates": { "x": 150, "y": 200 },
      "is_inhabited": true
    },
    "sector": {
      "uuid": "...",
      "name": "Sector Alpha-3",
      "grid": { "x": 2, "y": 3 }
    },
    "bodies": {
      "planets": [
        {
          "uuid": "...",
          "name": "Planet Alpha",
          "type": "terrestrial",
          "type_label": "Terrestrial",
          "orbital_index": 1,
          "is_inhabited": true,
          "has_colony": false,
          "attributes": {
            "habitable": true,
            "in_goldilocks_zone": true,
            "temperature": "temperate",
            "atmosphere": "breathable"
          },
          "orbital_presence": {
            "structures": [
              {
                "type": "orbital_defense",
                "name": "Defense Platform Alpha",
                "level": 2,
                "status": "operational",
                "owner": {
                  "uuid": "player-uuid",
                  "call_sign": "Captain Vex"
                }
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
              "uuid": "...",
              "name": "Moon Alpha-1",
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
          "uuid": "...",
          "name": "Inner Belt",
          "type": "asteroid_belt",
          "type_label": "Asteroid Belt",
          "orbital_index": 2,
          "is_inhabited": false,
          "has_colony": false,
          "attributes": { "mineral_richness": "high" },
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

**Notes:**
- `summary.moons` counts only direct children of the star (moons orbiting planets appear nested in their parent planet's `moons` array)
- Bodies are ordered by `orbital_index`
- `sector` is `null` if the star has no assigned sector
- `orbital_presence` is **always visible** on every body — large orbital structures (defense platforms, bases, stations, mining platforms) and system defenses (cannons, lasers, missiles, shields, fighter ports) are physically obvious
- `defensive_capability` is **sensor-gated** — requires ship sensor level >= 5 to populate; returns `null` otherwise
- `defensive_capability.threat_level` is one of: `none` (0 damage), `minimal` (1-50), `moderate` (51-150), `heavy` (151-400), `fortress` (401+)
- `defensive_capability.magnetic_mines` is a count, not a damage value (mines detonate per-hit, not per-round)
- `defensive_capability.planetary_shield_hp` is absorb capacity, not damage output
- `defensive_capability.total_damage_per_round` sums: orbital defense platforms + system defenses + fighter squadrons + colony garrison + colony defense buildings
- Moon sub-items include `has_colony`, `orbital_presence`, and `defensive_capability` (same rules as parent bodies)

**CHANGELOG**
---
**2026-02-15**
- Added `orbital_presence` (always visible) to every body and moon, showing player-built orbital structures and system defenses in orbit
- Added `defensive_capability` (sensor-gated, requires sensor level >= 5) to every body and moon, providing a full defensive breakdown and `total_damage_per_round` summary with `threat_level` label
- Added `has_colony` to moon sub-items
- Player's active ship is now eager-loaded for sensor level access
- Orbital structures, system defenses, and colony data (buildings, owner) are now eager-loaded to prevent N+1 queries

---

### 6. Scan System (Progressive Scan)

Perform a progressive scan of a system to reveal detailed information.

```
POST /api/players/{uuid}/scan-system
```

**Request Body:**

```json
{
  "poi_uuid": "target-system-uuid",
  "force": false
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `poi_uuid` | string (UUID) | No | Target system UUID. Omit to scan current location. |
| `force` | boolean | No | Force rescan even if already scanned. Default: `false`. |

**Response: `200 OK`**

```json
{
  "success": true,
  "message": "System scanned successfully",
  "data": {
    "system": {
      "uuid": "...",
      "name": "Kepler-442",
      "coordinates": { "x": 150, "y": 200 }
    },
    "scan_level": 3,
    "scan_data": {
      "star_class": "K-type",
      "planets": 5,
      "resources": ["iron", "titanium"],
      "anomalies": []
    },
    "cached": false,
    "can_reveal_more": true,
    "next_level_reveals": "Detailed mineral composition and hidden structures",
    "new_discoveries": ["Discovered iron deposits", "Detected orbital anomaly"]
  }
}
```

**Error Responses:**

| Status | Code | Condition |
|--------|------|-----------|
| 400 | `NO_SHIP` | No active ship |
| 400 | `OUT_OF_RANGE` | Target system beyond scan range |
| 400 | `SCAN_FAILED` | Scan failed for other reasons |
| 404 | - | Player or system not found |

`OUT_OF_RANGE` includes extra data:
```json
{
  "success": false,
  "error": {
    "code": "OUT_OF_RANGE",
    "message": "System out of scan range",
    "details": {
      "distance": 12.5,
      "max_range": 4.0
    }
  }
}
```

---

### 7. Get Scan Results

Retrieve existing scan data for a specific system.

```
GET /api/players/{uuid}/scan-results/{poiUuid}
```

**Response: `200 OK`**

```json
{
  "success": true,
  "message": "Scan results retrieved",
  "data": {
    "system": {
      "uuid": "...",
      "name": "Kepler-442",
      "type": "Star",
      "coordinates": { "x": 150, "y": 200 },
      "is_inhabited": true
    },
    "scan": {
      "scan_level": 3,
      "scanned_at": "2026-02-10T14:30:00+00:00",
      "data": { ... }
    }
  }
}
```

---

### 8. Exploration Log

Get all systems the player has ever scanned.

```
GET /api/players/{uuid}/exploration-log
```

**Response: `200 OK`**

```json
{
  "success": true,
  "message": "Exploration log retrieved",
  "data": {
    "entries": [
      {
        "uuid": "scan-uuid-...",
        "system": {
          "uuid": "...",
          "name": "Kepler-442",
          "type": "Star",
          "coordinates": { "x": 150, "y": 200 },
          "is_inhabited": true,
          "region": "core"
        },
        "scan_level": 3,
        "scan_level_label": "Detailed",
        "scanned_at": "2026-02-10T14:30:00+00:00",
        "can_reveal_more": true,
        "display": {
          "color": "#00ff88",
          "opacity": 0.9
        }
      }
    ],
    "statistics": {
      "total_scanned": 15,
      "by_level": { "1": 5, "2": 6, "3": 4 },
      "by_region": { "core": 10, "outer": 5 }
    }
  }
}
```

---

### 9. Bulk Scan Levels (Map Overlay)

Get scan levels for multiple systems at once. Useful for coloring systems on the map.

```
POST /api/players/{uuid}/bulk-scan-levels
```

**Request Body:**

```json
{
  "poi_uuids": [
    "uuid-1-...",
    "uuid-2-...",
    "uuid-3-..."
  ]
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `poi_uuids` | string[] | Yes | Array of POI UUIDs (max 500) |

**Response: `200 OK`**

```json
{
  "success": true,
  "message": "Bulk scan levels retrieved",
  "data": {
    "scan_levels": {
      "uuid-1-...": {
        "scan_level": 3,
        "color": "#00ff88",
        "opacity": 0.9,
        "label": "Detailed"
      },
      "uuid-2-...": {
        "scan_level": 0,
        "color": "#666666",
        "opacity": 0.2,
        "label": "Unscanned"
      }
    }
  }
}
```

---

### 10. Filtered System Data

Get system data filtered by the player's scan level of that system.

```
GET /api/players/{uuid}/system-data/{poiUuid}
```

**Response: `200 OK`**

```json
{
  "success": true,
  "message": "System data retrieved",
  "data": {
    "system_data": { ... },
    "scan_level": 2
  }
}
```

The contents of `system_data` vary based on the player's scan level. Higher scan levels reveal more fields.

---

## Frontend Rendering Guide

### Map Rendering Workflow

1. **Fetch knowledge map:** `GET /api/players/{uuid}/knowledge-map`
2. **Render galaxy canvas** using `galaxy.width` and `galaxy.height`
3. **Draw known systems** from `known_systems[]`:
   - Position at `(x, y)` coordinates
   - Style based on `knowledge_level` and `freshness`
4. **Draw warp lanes** from `known_lanes[]`:
   - Lines from `from` to `to` coordinates
   - Highlight pirate lanes (red/orange if `has_pirate: true`)
5. **Draw danger zones** from `danger_zones[]`:
   - Translucent red circles at `center` with `radius_ly`
6. **Draw sensor range** circle centered on `player.location` with radius `player.sensor_range_ly`
7. **Everything NOT in `known_systems` is fog** — do not render any system not in the response

### System Node Styling

```
DETECTED (1): Small dot, 30% opacity, no label
BASIC (2):    Medium dot, 50% opacity, show name
SURVEYED (3): Full dot, 80% opacity, show name + service icons
VISITED (4):  Full dot, 100% opacity, solid border, show all info
```

### Freshness Visual Modifiers

Apply freshness as a multiplier on top of the base knowledge-level opacity:

```javascript
function getSystemStyle(system) {
  const baseOpacity = { 1: 0.3, 2: 0.5, 3: 0.8, 4: 1.0 };
  const opacity = baseOpacity[system.knowledge_level] * system.freshness;

  const borderStyle =
    system.freshness >= 0.7 ? 'solid' :
    system.freshness >= 0.3 ? 'dashed' : 'dotted';

  const showStaleIndicator = system.freshness < 0.3;

  return { opacity, borderStyle, showStaleIndicator };
}
```

### Warp Lane Styling

```javascript
function getLaneStyle(lane) {
  const base = { color: '#4488ff', width: 1 };

  if (lane.has_pirate) {
    base.color = '#ff4444';  // Red for known pirate lanes
    if (lane.pirate_freshness < 0.5) {
      base.color = '#ff8844'; // Orange = stale pirate intel
      base.dashArray = '5,3';
    }
  }

  return base;
}
```

### System Detail Panel

When a user clicks a system node, show info based on `knowledge_level`:

| Level | Panel Content |
|-------|--------------|
| 1 (DETECTED) | Coordinates only. "Unknown system detected at (x, y)" |
| 2 (BASIC) | Name, star type, inhabited status, planet count |
| 3 (SURVEYED) | Above + services panel (trading hub, shipyard, etc.) |
| 4 (VISITED) | Full detail + "You have been here" badge |

If `has_scan_data: true`, show a "View Scan Data" button that calls `GET /scan-results/{poiUuid}`.

If `pirate_warning` is present, show a danger indicator with the `confidence` label.

### Recommended Polling Strategy

- **Knowledge map:** Fetch on navigation (after travel) and on star chart purchase. No need to poll.
- **Nearby systems:** Fetch when player opens the local star map view.
- **Local bodies:** Fetch when player views their current system detail.
- **After travel:** Refetch knowledge-map — travel triggers `markVisited()` on the backend which auto-discovers nearby systems.
- **After chart purchase:** Refetch knowledge-map — chart purchase grants BASIC/SURVEYED knowledge for systems in coverage area.

### Statistics Display

Use `statistics` from the knowledge-map response for a HUD element:

```
Known Systems: 42
  Detected: 10 | Basic: 15 | Surveyed: 12 | Visited: 5
Known Lanes: 28
Pirate Warnings: 3
```

---

## Error Response Format

All error responses follow this structure:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": { ... }
  }
}
```

Common error codes:

| Code | Meaning |
|------|---------|
| `NO_ACTIVE_SHIP` | Player has no active ship equipped |
| `NO_LOCATION` | Player has no current location set |
| `NO_SHIP` | No active ship (scan variant) |
| `OUT_OF_RANGE` | Target system is beyond scan/sensor range |
| `SCAN_FAILED` | System scan failed |
| `TOO_MANY` | Too many items requested (bulk endpoint limit) |

---

## Authentication

All endpoints require Laravel Sanctum bearer token authentication:

```
Authorization: Bearer {token}
```

Players can only access their own data. Attempting to access another user's player returns `403 Forbidden` or `404 Not Found`.

---

## Endpoint Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/players/{uuid}/knowledge-map` | Full fog-of-war map data |
| `GET` | `/api/players/{uuid}/location` | Current location details |
| `GET` | `/api/players/{uuid}/nearby-systems` | Systems within sensor range |
| `GET` | `/api/players/{uuid}/scan-local` | All POIs within sensor range |
| `GET` | `/api/players/{uuid}/local-bodies` | Orbital bodies at current star + orbital presence + defensive capability (sensor 5+) |
| `POST` | `/api/players/{uuid}/scan-system` | Perform progressive scan |
| `GET` | `/api/players/{uuid}/scan-results/{poiUuid}` | Get existing scan data |
| `GET` | `/api/players/{uuid}/exploration-log` | All scanned systems log |
| `POST` | `/api/players/{uuid}/bulk-scan-levels` | Batch scan level query |
| `GET` | `/api/players/{uuid}/system-data/{poiUuid}` | Filtered system data by scan level |

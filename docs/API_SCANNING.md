# System Scanning API

Progressive revelation system where a ship's **sensor level** determines the depth of information revealed when scanning a star system. Like Civilization's fog of war, but the "tech level" is your ship's sensors.

## Overview

- **Sensor level = scan depth**: Ship sensors (1-9) determine what you can see
- **Scan is automatic**: Systems are auto-scanned on arrival
- **Results are cached**: No expiration until higher-level re-scan or system changes
- **Baseline intel**: Core/inhabited systems have pre-existing intel

## Scan Levels

| Level | Name | What's Revealed |
|-------|------|-----------------|
| 0 | Unscanned | Complete fog - no data |
| 1 | Geography | Planet count, types (rocky/gas/ice), asteroid belts, habitability |
| 2 | Gates | Gate presence, dormant status, "unknown destination" |
| 3 | Basic Resources | Mineral deposits on rocky planets, metallic hydrogen on gas giants |
| 4 | Rare Resources | Asteroid field minerals, uncommon deposits |
| 5 | Hidden Features | Habitable moons, orbital mining, ring deposits |
| 6 | Anomalies | Ancient ruins, spatial anomalies, derelict ships |
| 7 | Deep Scan | Subsurface deposits, core composition, terraforming viability |
| 8 | Advanced Intel | Pirate hideouts, hidden bases, cloaked structures |
| 9 | Precursor Secrets | Hidden ancient gates, precursor tech caches |

## Baseline Scan Levels

Systems have baseline intel based on region:

| Region | Baseline Level | Reason |
|--------|----------------|--------|
| Core | 3 | Well-documented civilized space |
| Inhabited (any) | 2 | Shared intel from locals |
| Outer (uninhabited) | 0 | Complete fog |

## UI Color Scheme

| Scan Level | Color | Opacity | Visual Meaning |
|------------|-------|---------|----------------|
| 0 (Fog) | `#1a1a2e` | 0.2 | Dark, nearly invisible |
| 1-2 | `#4a4a6a` | 0.4 | Dim, basic info |
| 3-4 | `#3366aa` | 0.6 | Blue, resources visible |
| 5-6 | `#33aa66` | 0.8 | Green, hidden features |
| 7-8 | `#aa9933` | 0.9 | Gold, deep intel |
| 9 | `#ff6600` | 1.0 | Orange, full revelation |

---

## API Endpoints

All endpoints require authentication via `Authorization: Bearer {token}`.

### Scan System

Scan the current location or a nearby system.

```
POST /api/players/{playerUuid}/scan-system
```

**Request Body:**
```json
{
  "poi_uuid": "optional-system-uuid",
  "force": false
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `poi_uuid` | string | No | UUID of system to scan. Defaults to current location. |
| `force` | boolean | No | Force re-scan even if already scanned at this level. |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "abc-123",
      "name": "Kepler-442",
      "coordinates": { "x": 150, "y": 200 }
    },
    "scan_level": 5,
    "scan_data": {
      "star_type": "K-class orange dwarf",
      "planet_count": 8,
      "planet_types": { "rocky": 3, "gas": 2, "ice": 1, "other": 2 },
      "asteroid_belts": 1,
      "habitability": {
        "goldilocks_planets": 1,
        "notes": ["Planet 4 in habitable zone, temperate"]
      },
      "gate_count": 2,
      "active_gates": 1,
      "dormant_gates": 1,
      "rocky_planets": ["iron", "copper", "titanium"],
      "gas_giants": ["metallic_hydrogen", "helium-3"],
      "asteroid_minerals": ["platinum", "iridium"],
      "habitable_moons": [
        { "name": "Planet 6 Moon B", "parent": "Planet 6", "climate": "temperate" }
      ]
    },
    "cached": false,
    "can_reveal_more": true,
    "next_level_reveals": ["anomalies", "ruins", "derelicts"],
    "new_discoveries": ["4", "5"]
  },
  "message": "Scan upgraded"
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | `NO_SHIP` | Player has no active ship |
| 400 | `NO_LOCATION` | Player has no current location |
| 400 | `OUT_OF_RANGE` | Target system beyond scan range |
| 404 | `NOT_FOUND` | System not found |

**Scan Range:**
```
range = sensor_level × 100 units
```
Example: Sensor level 5 = 500 unit range

---

### Get Scan Results

Get cached scan results for a specific system.

```
GET /api/players/{playerUuid}/scan-results/{poiUuid}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "abc-123",
      "name": "Kepler-442",
      "type": "Star",
      "coordinates": { "x": 150, "y": 200 },
      "is_inhabited": true
    },
    "scan": {
      "scan_level": 5,
      "scan_data": { ... },
      "scanned_at": "2026-01-28T12:00:00Z",
      "can_reveal_more": true,
      "next_level_reveals": ["anomalies", "ruins", "derelicts"],
      "display": {
        "color": "#33aa66",
        "opacity": 0.8,
        "label": "Hidden Features"
      }
    }
  }
}
```

For unscanned systems with baseline intel:
```json
{
  "scan": {
    "scan_level": 2,
    "scan_data": { ... },
    "scanned_at": null,
    "baseline": true,
    "can_reveal_more": true,
    "display": {
      "color": "#4a4a6a",
      "opacity": 0.4,
      "label": "Baseline Intel"
    }
  }
}
```

---

### Exploration Log

Get all systems the player has scanned.

```
GET /api/players/{playerUuid}/exploration-log
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "uuid": "scan-uuid-1",
        "system": {
          "uuid": "poi-uuid-1",
          "name": "Kepler-442",
          "type": "Star",
          "coordinates": { "x": 150, "y": 200 },
          "is_inhabited": true,
          "region": "core"
        },
        "scan_level": 5,
        "scan_level_label": "Hidden Features",
        "scanned_at": "2026-01-28T12:00:00Z",
        "can_reveal_more": true,
        "display": {
          "color": "#33aa66",
          "opacity": 0.8
        }
      }
    ],
    "statistics": {
      "total_scanned": 42,
      "by_level": { "3": 15, "4": 12, "5": 10, "6": 5 },
      "by_region": { "core": 30, "outer": 12 }
    }
  }
}
```

---

### Bulk Scan Levels

Get scan levels for multiple systems (for map rendering).

```
POST /api/players/{playerUuid}/bulk-scan-levels
```

**Request Body:**
```json
{
  "poi_uuids": ["uuid-1", "uuid-2", "uuid-3"]
}
```

**Limits:** Maximum 500 POI UUIDs per request.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "scan_levels": {
      "uuid-1": {
        "scan_level": 5,
        "color": "#33aa66",
        "opacity": 0.8,
        "label": "Hidden Features"
      },
      "uuid-2": {
        "scan_level": 0,
        "color": "#1a1a2e",
        "opacity": 0.2,
        "label": "Unscanned"
      },
      "uuid-3": {
        "scan_level": 3,
        "color": "#3366aa",
        "opacity": 0.6,
        "label": "Basic Resources"
      }
    }
  }
}
```

---

### Get Filtered System Data

Get system data filtered by player's scan level.

```
GET /api/players/{playerUuid}/system-data/{poiUuid}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "system_data": {
      "uuid": "abc-123",
      "name": "Kepler-442",
      "scan_level": 5,
      "coordinates": { "x": 150, "y": 200 },
      "geography": { ... },
      "gates": { ... },
      "resources": { ... },
      "rare_resources": { ... },
      "hidden_features": { ... }
    },
    "scan_level": 5
  }
}
```

Note: Only includes data for levels ≤ player's scan level.

---

## Integration with Other Systems

### Galaxy Map

The `/api/galaxies/{uuid}/map` endpoint now includes scan data:

```json
{
  "systems": [
    {
      "uuid": "poi-uuid",
      "name": "Kepler-442",
      "type": "star",
      "x": 150,
      "y": 200,
      "is_inhabited": true,
      "is_current_location": false,
      "scan": {
        "level": 5,
        "label": "Hidden Features",
        "color": "#33aa66",
        "opacity": 0.8
      }
    }
  ]
}
```

### Travel (Auto-Scan)

When traveling to a system, it's automatically scanned:

```json
{
  "success": true,
  "message": "Travel successful",
  "destination": "Kepler-442",
  "distance": 45.5,
  "fuel_cost": 15,
  "xp_earned": 227,
  "scan": {
    "scan_level": 3,
    "cached": false,
    "new_discoveries": ["1", "2", "3"],
    "can_reveal_more": true
  }
}
```

### Star Charts vs Scanning

These are **separate systems**:

| System | Purpose |
|--------|---------|
| **Star Charts** | Know WHERE a system is (location on map) |
| **Scanning** | Know WHAT'S IN the system (planets, resources) |

A player might have a star chart (sees system on map) but never scanned it (no details).

Flow:
1. Acquire star chart → System appears on map with "?"
2. Travel to system → Auto-scan at sensor level
3. Upgrade sensors → Re-scan for more detail

---

## Frontend Implementation Guide

### Rendering the Galaxy Map

1. Fetch map data: `GET /api/galaxies/{uuid}/map`
2. For each system, use `scan.color` and `scan.opacity` for fog effect
3. Unscanned systems (level 0) should be nearly invisible
4. Show "?" icon for charted but unscanned systems

```typescript
function getSystemStyle(system: MapSystemData): CSSProperties {
  return {
    backgroundColor: system.scan.color,
    opacity: system.scan.opacity,
    // Add glow effect for higher levels
    boxShadow: system.scan.level >= 7
      ? `0 0 10px ${system.scan.color}`
      : 'none',
  };
}
```

### System Detail View

1. When user clicks a system, fetch: `GET /api/players/{uuid}/scan-results/{poiUuid}`
2. Show data based on `scan_level`:
   - Level 0: "Unknown system - scan required"
   - Level 1-2: Basic geography, gates
   - Level 3-4: Resources
   - Level 5+: Detailed information
3. Show "Upgrade sensors to reveal more" if `can_reveal_more` is true

### Manual Scan Button

```typescript
async function scanSystem(playerUuid: string, poiUuid?: string) {
  const response = await fetch(`/api/players/${playerUuid}/scan-system`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ poi_uuid: poiUuid }),
  });

  const result = await response.json();

  if (result.success) {
    if (result.data.cached) {
      showToast('Using cached scan data');
    } else {
      showToast(`New discoveries at level ${result.data.scan_level}!`);
      // Highlight new_discoveries in UI
    }
  }
}
```

### Exploration Log Screen

Display player's scanning history with statistics:

```typescript
interface ExplorationStats {
  totalScanned: number;
  byLevel: Record<number, number>;
  byRegion: Record<string, number>;
}

function ExplorationLog({ entries, statistics }: ExplorationLogResponse) {
  return (
    <div>
      <h2>Exploration Log ({statistics.total_scanned} systems)</h2>

      {/* Stats summary */}
      <div className="stats">
        <span>Core: {statistics.by_region.core || 0}</span>
        <span>Outer: {statistics.by_region.outer || 0}</span>
      </div>

      {/* Entry list */}
      {entries.map(entry => (
        <ExplorationEntry
          key={entry.uuid}
          entry={entry}
          style={{ borderColor: entry.display.color }}
        />
      ))}
    </div>
  );
}
```

---

## Configuration

Configuration values in `config/game_config.php`:

```php
'scanning' => [
    'core_baseline_level' => 3,
    'inhabited_baseline_level' => 2,
    'outer_baseline_level' => 0,
    'scan_range_multiplier' => 100,
    'precursor_sensor_level' => 100,
    'auto_scan_on_arrival' => true,
],
```

---

## TypeScript Types

See `docs/types/scanning.ts` for complete TypeScript type definitions.

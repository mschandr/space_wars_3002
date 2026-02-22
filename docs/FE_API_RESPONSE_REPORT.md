# FE API Response Report

> **Purpose**: Document every endpoint where the BE response does not match what the FE expects.
> Each entry includes: the request, the expected response, the documented/actual response, and what is missing or wrong.
>
> **Date**: 2026-02-17
> **Branch**: Feat/SectorMap-tng

---

## Table of Contents

1. [Knowledge Map](#1-knowledge-map)
2. [Sector Map](#2-sector-map)
3. [Player Status](#3-player-status)
4. [Location Current](#4-location-current)
5. [Scan System](#5-scan-system)
6. [Scan Results](#6-scan-results)
7. [Bulk Scan Levels](#7-bulk-scan-levels)
8. [Star Charts](#8-star-charts)
9. [Star Chart Purchase](#9-star-chart-purchase)
10. [Star Chart Preview](#10-star-chart-preview)
11. [Star Chart System Info](#11-star-chart-system-info)
12. [Cartographer](#12-cartographer)
13. [Player List / Detail](#13-player-list--detail)
14. [Star Systems List / Detail](#14-star-systems-list--detail)
15. [Current System](#15-current-system)
16. [Colonies](#16-colonies)
17. [Combat Death / Respawn](#17-combat-death--respawn)
18. [Mirror Gate](#18-mirror-gate)
19. [NPCs](#19-npcs)
20. [Trading Hubs](#20-trading-hubs)
21. [Minerals](#21-minerals)
22. [POI Types](#22-poi-types)
23. [Scan Local](#23-scan-local)

---

## 1. Knowledge Map

**Endpoint**: `GET /api/players/{playerUuid}/knowledge-map`

**FE Request**: `GET /api/players/0f8036c0-.../knowledge-map`

### Expected Response (what the FE needs)

```json
{
  "success": true,
  "data": {
    "player": {
      "uuid": "0f8036c0-...",
      "x": 1364,
      "y": 1466,
      "system_uuid": "88cc2cbc-...",
      "sector_uuid": "504630c3-...",
      "sensor_range_ly": 1,
      "sensor_level": 1
    },
    "known_systems": [
      {
        "uuid": "b134a9d9-...",
        "x": 1324,
        "y": 1270,
        "knowledge_level": 3,
        "knowledge_label": "Surveyed",
        "freshness": 1,
        "source": "spawn",
        "star": { "type": "Star", "stellar_class": "F", "..." : "..." },
        "name": "Freya",
        "is_inhabited": true,
        "planet_count": 6,
        "services": { "trading_hub": true, "shipyard": false }
      }
    ],
    "known_lanes": [
      {
        "gate_uuid": "df8bdbd2-...",
        "from_uuid": "45918180-...",
        "to_uuid": "f6ee34b7-...",
        "from": { "x": 1336, "y": 1326 },
        "to": { "x": 1321, "y": 1368 },
        "distance": 44.6,
        "has_pirate": false,
        "pirate_freshness": null,
        "discovery_method": "spawn"
      }
    ],
    "danger_zones": [],
    "statistics": { "..." : "..." }
  }
}
```

### Actual Response (from live BE, 2026-02-17)

```json
{
  "player": {
    "uuid": "0f8036c0-...",
    "x": 1364,
    "y": 1466,
    "poi_uuid": "88cc2cbc-...",
    "sector_uuid": "504630c3-...",
    "sensor_range_ly": 1,
    "sensor_level": 1
  },
  "known_systems": [
    {
      "uuid": "b134a9d9-...",
      "...": "(systems already use uuid -- CORRECT)"
    }
  ],
  "known_lanes": [
    {
      "gate_uuid": "df8bdbd2-...",
      "from_poi_uuid": "45918180-...",
      "to_poi_uuid": "f6ee34b7-...",
      "from": { "x": 1336, "y": 1326 },
      "to": { "x": 1321, "y": 1368 },
      "distance": 44.6,
      "has_pirate": false,
      "pirate_freshness": null,
      "discovery_method": "spawn"
    }
  ]
}
```

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `player.poi_uuid` | `poi_uuid` | `system_uuid` | Player position not matched to current system; "YOU" indicator broken, fuel cost sidebar broken |
| `known_lanes[].from_poi_uuid` | `from_poi_uuid` | `from_uuid` | All warp lanes invisible on sector map and star map (field resolves to `undefined`) |
| `known_lanes[].to_poi_uuid` | `to_poi_uuid` | `to_uuid` | Same — lanes cannot be matched to systems |

**Note**: `known_systems[].uuid` is already correct (BE sends `uuid`, not `poi_uuid`). The FE has temporary normalizers to work around the lane/player issues, but these should be removed once the BE is updated.

---

## 2. Sector Map

**Endpoint**: `GET /api/galaxies/{galaxyUuid}/sector-map`

### Expected Response

```json
{
  "success": true,
  "data": {
    "sectors": [ "..." ],
    "grid_size": { "cols": 15, "rows": 15 },
    "player_sector_uuid": "504630c3-...",
    "player_location": { "x": 1364, "y": 1466, "system_uuid": "88cc2cbc-..." }
  }
}
```

### What's Wrong

The docs show `player_sector_uuid` and `player_location` fields. **Need to confirm the live BE actually sends these.** The FE reads `smResult.player_sector_uuid` to determine which sector the player is in for fog-of-war highlighting. If absent, it falls back to `playerState.currentSector?.uuid` which may not be set yet on initial load.

---

## 3. Player Status

**Endpoint**: `GET /api/players/{uuid}/status`

### Expected Response

```json
{
  "data": {
    "location": {
      "name": "Sol",
      "type": "star",
      "x": 150.5,
      "y": 200.3
    }
  }
}
```

### Actual (Documented) Response

```json
{
  "data": {
    "location": {
      "name": "Sol",
      "type": "star",
      "coordinates": {
        "x": 150.5,
        "y": 200.3
      }
    }
  }
}
```

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `location.coordinates.x` | Nested under `coordinates` | Flat `location.x` | FE must unwrap nested object; inconsistent with knowledge-map which sends flat `x, y` |

---

## 4. Location Current

**Endpoint**: `POST /api/location/current/{systemUuid}`

### Expected Response

Coordinates should be flat at the top level, not nested.

### Actual (Documented + Live) Response

```json
{
  "data": {
    "coordinates": {
      "x": 1364,
      "y": 1466
    }
  }
}
```

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data.coordinates` | Nested `{ x, y }` object | Flat `data.x`, `data.y` | Inconsistent with knowledge-map flat coords |

---

## 5. Scan System

**Endpoint**: `POST /api/players/{uuid}/scan-system`

### Expected Request

```json
{
  "uuid": "a7c3e9f2-...",
  "force": false
}
```

### Actual (Documented) Request

```json
{
  "poi_uuid": "a7c3e9f2-...",
  "force": false
}
```

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| Request body `poi_uuid` | `poi_uuid` | `uuid` | FE must send `poi_uuid` instead of standardized `uuid` |

---

## 6. Scan Results

**Endpoint**: `GET /api/players/{uuid}/scan-results/{poiUuid}`

### Expected URL

```
GET /api/players/{uuid}/scan-results/{uuid}
```

### Actual URL

```
GET /api/players/{uuid}/scan-results/{poiUuid}
```

### What's Wrong

| Issue | Current | Expected | Impact |
|-------|---------|----------|--------|
| URL path parameter name | `{poiUuid}` | `{uuid}` | Naming inconsistency (functionally works since the value is the same, but naming should be standardized) |

---

## 7. Bulk Scan Levels

**Endpoint**: `POST /api/players/{uuid}/bulk-scan-levels`

### Expected Request

```json
{
  "uuids": ["a7c3e9f2-...", "b8d4f0a3-..."]
}
```

### Actual (Documented) Request

```json
{
  "poi_uuids": ["a7c3e9f2-...", "b8d4f0a3-..."]
}
```

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| Request body `poi_uuids` | `poi_uuids` | `uuids` | FE must send non-standard field name |

---

## 8. Star Charts

**Endpoint**: `GET /api/players/{uuid}/star-charts`

### Expected Response

```json
{
  "data": {
    "revealed_systems": [
      {
        "uuid": "a7c3e9f2-...",
        "name": "Alpha Centauri",
        "x": 150,
        "y": 200,
        "type": "Star",
        "is_inhabited": true,
        "has_trading_hub": true,
        "connections": ["Beta Hydrae"]
      }
    ]
  }
}
```

### Actual (Documented) Response

```json
{
  "data": {
    "revealed_systems": [
      {
        "poi_uuid": "a7c3e9f2-...",
        "name": "Alpha Centauri",
        "coordinates": [150, 200],
        "type": "Star",
        "is_inhabited": true,
        "has_trading_hub": true,
        "connections": ["Beta Hydrae"]
      }
    ]
  }
}
```

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `revealed_systems[].poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier field |
| `revealed_systems[].coordinates` | Array `[150, 200]` | Flat `{ x: 150, y: 200 }` | Coordinates in array format instead of object; inconsistent with every other endpoint |

---

## 9. Star Chart Purchase

**Endpoint**: `POST /api/players/{uuid}/star-charts/purchase`

### Expected Request

```json
{
  "cartographer_uuid": "cart-uuid-...",
  "chart_uuid": "chart-uuid-..."
}
```

### Actual (Documented) Request

```json
{
  "cartographer_poi_uuid": "cart-uuid-...",
  "chart_uuid": "chart-uuid-..."
}
```

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| Request body `cartographer_poi_uuid` | `cartographer_poi_uuid` | `cartographer_uuid` | Non-standard field name |

---

## 10. Star Chart Preview

**Endpoint**: `GET /api/star-charts/preview`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data.center.poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |
| `data.coverage[].poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |

---

## 11. Star Chart System Info

**Endpoint**: `GET /api/star-charts/system/{poiUuid}`

### What's Wrong

| Issue | Current | Expected | Impact |
|-------|---------|----------|--------|
| URL path parameter | `{poiUuid}` | `{uuid}` | Non-standard naming |
| Response `data.poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |
| Response coordinates | `[x, y]` array | `{ x, y }` object | Inconsistent format |

---

## 12. Cartographer

**Endpoint**: `GET /api/trading-hubs/{uuid}/cartographer`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data.cartographer.location.poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |

---

## 13. Player List / Detail

**Endpoints**:
- `GET /api/players`
- `POST /api/players`
- `GET /api/players/{uuid}`

### What's Wrong (all three endpoints)

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `current_location.id` | Numeric `id` present | Remove `id` field | Numeric IDs should not be on POI resources |

---

## 14. Star Systems List / Detail

**Endpoints**:
- `GET /api/players/{playerUuid}/star-systems`
- `GET /api/players/{playerUuid}/star-systems/{systemUuid}`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `systems[].coordinates` | Nested `{ x, y }` under `coordinates` | Flat `x`, `y` at system level | Inconsistent with knowledge-map |

---

## 15. Current System

**Endpoint**: `GET /api/players/{playerUuid}/current-system`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data.system.coordinates` | Nested `{ x, y }` under `coordinates` | Flat `x`, `y` at system level | Inconsistent with knowledge-map |

---

## 16. Colonies

**Endpoints**:
- `GET /api/players/{uuid}/colonies`
- `POST /api/players/{uuid}/colonies`
- `POST /api/colonies/{uuid}/mining/start`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `colonies[].location.poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |
| `colonies[].location.poi_name` | `poi_name` | `name` | Non-standard naming (should be just `name`) |
| POST colony request body | `poi_uuid` | `uuid` | Non-standard request param |
| POST mining request body | `poi_uuid` | `uuid` | Non-standard request param |

---

## 17. Combat Death / Respawn

**Endpoints**:
- `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept`
- `POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept-team`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `result.death_result.respawn_location.poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |

---

## 18. Mirror Gate

**Endpoints**:
- `GET /api/galaxies/{uuid}/mirror-gate`
- `GET /api/players/{uuid}/mirror-access`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `mirror_gate.location.poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |
| `mirror_gate.destination.poi_uuid` | `poi_uuid` | `uuid` | Non-standard identifier |
| `mirror_gate.location.coordinates` | Nested `{ x, y }` | Flat `x`, `y` | Inconsistent coordinate format |
| `mirror_gate.destination.coordinates` | Nested `{ x, y }` | Flat `x`, `y` | Inconsistent coordinate format |

---

## 19. NPCs

**Endpoints**:
- `GET /api/galaxies/{uuid}/npcs`
- `GET /api/npcs/{uuid}`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data.npcs[].location.id` / `data.location.id` | Numeric `id` | Remove — use `uuid` only | Numeric IDs should not be on POI resources |

---

## 20. Trading Hubs

**Endpoints**:
- `GET /api/trading-hubs`
- `GET /api/trading-hubs/{uuid}`
- `GET /api/trading-hubs/{uuid}/inventory`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `hubs[].id` / `data.id` / `data.hub.id` | Numeric `id` present | Remove — use `uuid` only | Numeric IDs should not exist on these resources |
| `hubs[].location.id` / `data.hub.location.id` | Numeric `id` in location | Remove — use `uuid` only | Same |

---

## 21. Minerals

**Endpoint**: `GET /api/minerals`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data[].id` | Numeric `id` (e.g., `1`, `2`) | Remove or add `uuid` | Minerals use numeric IDs with no UUID alternative |

---

## 22. POI Types

**Endpoints**:
- `GET /api/poi-types`
- `GET /api/poi-types/by-category`
- `GET /api/poi-types/habitable`
- `GET /api/poi-types/mineable`
- `GET /api/poi-types/{idOrCode}`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data.types[].id` | Numeric `id` (e.g., `1`, `2`, `15`) | Remove — use `code` as primary identifier | Numeric IDs on reference data |

---

## 23. Scan Local

**Endpoint**: `GET /api/players/{uuid}/scan-local`

### What's Wrong

| Field | Current | Expected | Impact |
|-------|---------|----------|--------|
| `data.pois_by_type[].parent_poi.id` | Numeric `id` (e.g., `567`) | Use `uuid` | Numeric ID reference to parent POI |

---

## Summary by Priority

### Critical (breaks FE map rendering now)

| # | Endpoint | Fix Needed |
|---|----------|-----------|
| 1 | `GET .../knowledge-map` | `player.poi_uuid` → `player.system_uuid` |
| 2 | `GET .../knowledge-map` | `known_lanes[].from_poi_uuid` → `from_uuid` |
| 3 | `GET .../knowledge-map` | `known_lanes[].to_poi_uuid` → `to_uuid` |

### High (used by FE, causes bugs or requires workarounds)

| # | Endpoint | Fix Needed |
|---|----------|-----------|
| 4 | `POST .../scan-system` | Request body `poi_uuid` → `uuid` |
| 5 | `POST .../bulk-scan-levels` | Request body `poi_uuids` → `uuids` |
| 6 | `GET .../star-charts` | `poi_uuid` → `uuid`, coordinates `[x,y]` → `{x,y}` |
| 7 | `GET .../status` | Flatten `location.coordinates` to `location.x`, `location.y` |

### Medium (consistency, not yet breaking)

All remaining `poi_uuid` → `uuid` renames, coordinate flattening, and numeric `id` removal listed in sections 9-23 above.

---

## Notes for BE Developer

1. **The FE has temporary normalizers** in `src/lib/types/scanning.ts` (`normalizeKnownSystem`, `normalizeKnownLane`) that map old field names → new ones. Once the BE fixes items 1-3, we will remove these.

2. **`known_systems[].uuid` is already correct** — the BE already sends `uuid` (not `poi_uuid`) for systems in the knowledge-map response. Only lanes and the player object still use the old names.

3. **The knowledge-map now correctly auto-includes current-location warp gates** (with `discovery_method: "current_location"`). This is working as intended.

4. **Coordinate flattening** should be consistent: the knowledge-map already sends flat `x, y` on systems and lane endpoints. Other endpoints should follow the same pattern.

# Space Wars 3002 - API Documentation

**Base URL:** `/api`
**Authentication:** Bearer Token (Laravel Sanctum) — all endpoints except those marked "public" require `Authorization: Bearer {token}`
**Total Endpoints:** ~150+

## Documentation Index

| File | Domain | Endpoints | Description |
|------|--------|-----------|-------------|
| [authentication.md](authentication.md) | Authentication | 6 | Register, login, logout, token refresh, user info, email verification |
| [galaxies.md](galaxies.md) | Galaxy Management | 19 | List, create, join, view, map, settings, statistics, NPCs, map summaries, sector map |
| [players.md](players.md) | Player Management | 9 | CRUD, status, stats, settings, set active |
| [navigation-travel.md](navigation-travel.md) | Navigation & Travel | 18 | Location, nearby systems, warp gates, coordinate jumps, fuel cost, star systems, facilities, knowledge map, bar |
| [ships.md](ships.md) | Ships | 8 | Active ship, rename, fuel regeneration, status, fuel, damage, upgrades |
| [ship-services.md](ship-services.md) | Ship Services | 27 | Repairs, upgrades, shipyard (unique ships), ship shop, salvage yard, plans shop, orbital structures |
| [trading.md](trading.md) | Trading | 8 | Trading hubs, buy/sell minerals, cargo, affordability |
| [combat.md](combat.md) | Combat | 13 | PvE pirate encounters, PvP challenges, team combat, combat sessions |
| [colonies.md](colonies.md) | Colonies | 15 | Establish, manage, buildings, production, defenses, mining |
| [scanning-exploration.md](scanning-exploration.md) | Scanning & Exploration | 5 | System scans, scan results, exploration log, bulk scan levels, system data |
| [world-data.md](world-data.md) | World Data | 5 | POI types (all, by category, habitable, mineable), individual type lookup |
| [leaderboards-victory.md](leaderboards-victory.md) | Leaderboards & Victory | 9 | Overall/combat/economic/colonial rankings, player stats, victory conditions, progress |
| [notifications.md](notifications.md) | Notifications | 6 | List, read, mark all read, clear read, delete, unread count |
| [pirate-factions.md](pirate-factions.md) | Pirate Factions | 4 | Faction list, details, captains, player reputation |
| [special-content.md](special-content.md) | Special Content | 7 | Mirror universe access/entry, precursor rumors/gossip/bribe, market events |

## Standard Response Format

All API responses use the standardized format from `BaseApiController`:

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation description",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error description",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### Validation Error Response
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "field_name": ["Error message"]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

## Authentication

All endpoints (except those marked "public") require a valid Bearer token:

```
Authorization: Bearer {your-token-here}
```

Tokens are obtained via `POST /api/auth/login` or `POST /api/auth/register`.

### Public Endpoints (no auth required)

- `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/verify-email`
- `GET /api/galaxies/{uuid}`, `GET /api/galaxies/{uuid}/statistics`, `GET /api/galaxies/{uuid}/map`
- `GET /api/sectors/{uuid}`
- `GET /api/galaxies/{galaxyUuid}/leaderboards/*`
- `GET /api/galaxies/{galaxyUuid}/victory-conditions`, `GET /api/galaxies/{galaxyUuid}/victory-leaders`
- `GET /api/galaxies/{galaxyUuid}/market-events`, `GET /api/market-events/{eventUuid}`
- `GET /api/galaxies/{galaxyUuid}/pirate-factions`, `GET /api/pirate-factions/{factionUuid}`, `GET /api/pirate-factions/{factionUuid}/captains`
- `GET /api/poi-types`, `GET /api/poi-types/*`

## Common HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 202 | Accepted (async operation in progress) |
| 400 | Bad request / business logic error |
| 401 | Unauthorized (missing/invalid token) |
| 403 | Forbidden (not your resource) |
| 404 | Not found |
| 409 | Conflict (duplicate action) |
| 422 | Unprocessable entity (validation failed) |
| 500 | Server error |

## UUID Parameters

All entity identifiers use UUIDs (v4). When a route parameter says `{uuid}`, `{playerUuid}`, `{galaxyUuid}`, etc., provide the full UUID string (e.g., `550e8400-e29b-41d4-a716-446655440000`).

## Key Concepts

- **Fog of War**: Players only see systems they've charted, scanned, or visited. Use the knowledge-map endpoint for fog-aware rendering.
- **Sensor-Gated Information**: Many responses filter data based on ship sensor level (1-9). Higher sensors reveal more detail.
- **Lazy Generation**: Shipyard and salvage yard inventories are generated on first player visit and persist thereafter.
- **Fuel Regeneration**: Ship fuel regenerates passively over time. Many endpoints auto-trigger regeneration before returning fuel data.
- **Rarity Tiers**: Ships and components use 6 tiers: common, uncommon, rare, epic, unique, exotic.

# Pirate Factions API

API endpoints for interacting with pirate factions, their captains, and managing player reputation with outlaw organizations across the galaxy.

---

## GET /api/galaxies/{galaxyUuid}/pirate-factions

List all pirate factions operating within a specific galaxy.

### Authentication
Not required (public endpoint)

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxyUuid | string (UUID) | Yes | UUID of the galaxy to query |

### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Pirate factions retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Quadrant"
    },
    "total_factions": 3,
    "factions": [
      {
        "uuid": "650e8400-e29b-41d4-a716-446655440000",
        "name": "The Crimson Raiders",
        "ideology": "Profit-driven mercenaries",
        "strength": 75,
        "territory_control": 12,
        "statistics": {
          "total_captains": 8,
          "total_fleets": 23
        },
        "description": "A notorious pirate faction operating in the galaxy."
      }
    ]
  }
}
```

**Field Descriptions:**
- `galaxy.uuid` - UUID of the galaxy
- `galaxy.name` - Name of the galaxy
- `total_factions` - Total number of pirate factions in this galaxy
- `factions` - Array of pirate faction objects
- `factions[].uuid` - Unique identifier for the faction
- `factions[].name` - Display name of the faction
- `factions[].ideology` - Faction's philosophy/motivation (defaults to "Unknown" if not set)
- `factions[].strength` - Military/combat strength rating (0-100 scale, defaults to 0)
- `factions[].territory_control` - Number of sectors/systems controlled by faction (defaults to 0)
- `factions[].statistics.total_captains` - Number of pirate captains in this faction
- `factions[].statistics.total_fleets` - Total number of pirate fleets under this faction
- `factions[].description` - Lore description of the faction (defaults to generic text)

### Error Responses

**404 Not Found** - Galaxy with specified UUID does not exist
```json
{
  "success": false,
  "message": "Not found",
  "error_code": "NOT_FOUND"
}
```

### Warnings & Caveats
- This endpoint uses `withCount()` which performs aggregation queries - may be slow for large datasets
- Factions without any captains or fleets will show counts of 0
- Ideology, strength, territory_control, and description all have fallback defaults if null

---

## GET /api/pirate-factions/{factionUuid}

Get detailed information about a specific pirate faction, including notable captains.

### Authentication
Not required (public endpoint)

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| factionUuid | string (UUID) | Yes | UUID of the pirate faction to retrieve |

### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Pirate faction details retrieved successfully",
  "data": {
    "uuid": "650e8400-e29b-41d4-a716-446655440000",
    "name": "The Crimson Raiders",
    "ideology": "Profit-driven mercenaries",
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Quadrant"
    },
    "statistics": {
      "strength": 75,
      "territory_control": 12,
      "total_captains": 8,
      "total_fleets": 23
    },
    "description": "A notorious pirate faction operating in the galaxy.",
    "notable_captains": [
      {
        "uuid": "750e8400-e29b-41d4-a716-446655440000",
        "name": "Captain Blackjaw",
        "reputation": 85
      }
    ]
  }
}
```

**Field Descriptions:**
- `uuid` - Unique identifier for the faction
- `name` - Display name of the faction
- `ideology` - Faction's philosophy/motivation
- `galaxy.uuid` - UUID of the galaxy this faction operates in
- `galaxy.name` - Name of the galaxy
- `statistics.strength` - Military/combat strength rating (0-100)
- `statistics.territory_control` - Number of sectors/systems controlled
- `statistics.total_captains` - Total number of captains in faction
- `statistics.total_fleets` - Total number of fleets controlled by faction
- `description` - Lore description of the faction
- `notable_captains` - Array of up to 5 most notable captains (sorted by reputation)
- `notable_captains[].uuid` - Captain's unique identifier
- `notable_captains[].name` - Captain's name
- `notable_captains[].reputation` - Captain's reputation score (defaults to 0)

### Error Responses

**404 Not Found** - Faction with specified UUID does not exist
```json
{
  "success": false,
  "message": "Not found",
  "error_code": "NOT_FOUND"
}
```

### Warnings & Caveats
- Only returns first 5 captains via `take(5)` - use `/captains` endpoint for full list
- Captains are not explicitly ordered, so "notable" selection is arbitrary beyond the 5-captain limit
- Uses eager loading for `galaxy` and `captains` relationships

---

## GET /api/pirate-factions/{factionUuid}/captains

List all pirate captains belonging to a specific faction.

### Authentication
Not required (public endpoint)

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| factionUuid | string (UUID) | Yes | UUID of the pirate faction |

### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Faction captains retrieved successfully",
  "data": {
    "faction": {
      "uuid": "650e8400-e29b-41d4-a716-446655440000",
      "name": "The Crimson Raiders"
    },
    "total_captains": 8,
    "captains": [
      {
        "uuid": "750e8400-e29b-41d4-a716-446655440000",
        "name": "Captain Blackjaw",
        "reputation": 85,
        "bounty": 150000,
        "rank": "Fleet Admiral",
        "fleet_count": 4,
        "status": "Active"
      },
      {
        "uuid": "850e8400-e29b-41d4-a716-446655440000",
        "name": "Dead Eye Dan",
        "reputation": 42,
        "bounty": 0,
        "rank": "Captain",
        "fleet_count": 0,
        "status": "Deceased"
      }
    ]
  }
}
```

**Field Descriptions:**
- `faction.uuid` - UUID of the faction
- `faction.name` - Name of the faction
- `total_captains` - Total number of captains in this faction
- `captains` - Array of all pirate captain objects
- `captains[].uuid` - Captain's unique identifier
- `captains[].name` - Captain's display name
- `captains[].reputation` - Reputation score (defaults to 0 if not set)
- `captains[].bounty` - Credits offered as bounty for this captain (defaults to 0)
- `captains[].rank` - Military rank (defaults to "Captain" if not set)
- `captains[].fleet_count` - Number of fleets under this captain's command
- `captains[].status` - "Active" if `is_alive` is true, "Deceased" otherwise

### Error Responses

**404 Not Found** - Faction with specified UUID does not exist
```json
{
  "success": false,
  "message": "Not found",
  "error_code": "NOT_FOUND"
}
```

### Warnings & Caveats
- Includes deceased captains (check `status` field)
- `fleet_count` uses `withCount('fleets')` aggregation
- All captains are returned (no pagination) - could be performance issue for large factions

---

## GET /api/players/{uuid}/pirate-reputation

Get player's reputation standing with all pirate factions in their galaxy, including calculated effects.

### Authentication
Required

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Pirate faction reputations retrieved successfully",
  "data": {
    "player": {
      "uuid": "950e8400-e29b-41d4-a716-446655440000",
      "call_sign": "Starblazer"
    },
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Quadrant"
    },
    "faction_reputations": [
      {
        "faction": {
          "uuid": "650e8400-e29b-41d4-a716-446655440000",
          "name": "The Crimson Raiders"
        },
        "reputation": -50,
        "standing": "Unfriendly",
        "effects": {
          "description": "Pirates are more aggressive",
          "drawbacks": [
            "Increased encounter rate",
            "Higher combat difficulty"
          ]
        }
      }
    ]
  }
}
```

**Field Descriptions:**
- `player.uuid` - Player's unique identifier
- `player.call_sign` - Player's call sign
- `galaxy.uuid` - UUID of player's current galaxy
- `galaxy.name` - Name of player's galaxy
- `faction_reputations` - Array of reputation objects for each faction
- `faction_reputations[].faction.uuid` - Faction's UUID
- `faction_reputations[].faction.name` - Faction's name
- `faction_reputations[].reputation` - Numeric reputation score (starts at 0, decreases by 10 per pirate kill)
- `faction_reputations[].standing` - Text standing based on reputation threshold
- `faction_reputations[].effects.description` - Summary of standing effects
- `faction_reputations[].effects.benefits` - Array of positive effects (only for positive standings)
- `faction_reputations[].effects.drawbacks` - Array of negative effects (only for negative standings)

**Reputation Thresholds:**
- **Allied** (≥100): "Pirates will not attack you" - Benefits: Safe passage, Trade opportunities, Intelligence sharing
- **Friendly** (50-99): "Pirates are less likely to attack" - Benefits: Reduced encounter rate, Better trading prices
- **Neutral** (0-49): "Standard pirate behavior" - No benefits or drawbacks
- **Unfriendly** (-1 to -49): "Pirates are more aggressive" - Drawbacks: Increased encounter rate, Higher combat difficulty
- **Hostile** (-50 to -99): "Pirates will actively hunt you" - Drawbacks: Frequent ambushes, Reinforcements called, No quarter given
- **Hated** (≤-100): "You are a priority target" - Drawbacks: Elite fleets dispatched, Bounty on your head, No escape possible

**Reputation Calculation:**
- Base reputation: 0 (neutral)
- Each completed pirate combat session where player wins: -10 reputation
- Queries `combat_sessions` table: `combat_type='pirate'`, `status='completed'`, `result->victor='player'`
- Counts victories via `participants` relationship matching player ID

### Error Responses

**404 Not Found** - Player with specified UUID does not exist
```json
{
  "success": false,
  "message": "Not found",
  "error_code": "NOT_FOUND"
}
```

**401 Unauthorized** - Request does not match authenticated player

### Warnings & Caveats
- **Reputation is NOT persisted in database** - calculated on-the-fly from combat history each request
- Currently ALL factions share the same reputation based on total pirate kills (no per-faction tracking)
- Queries `CombatSession` model with JSON contains check on `result->victor` field
- Uses `whereJsonContains()` which may not work on all database engines (requires JSON column support)
- Currently no way to improve reputation (only degrades from killing pirates)
- No faction-specific reputation modifiers or diplomacy options implemented yet

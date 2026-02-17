# Leaderboards & Victory API Documentation

This document covers all endpoints related to player leaderboards, rankings, statistics, and victory conditions in Space Wars 3002.

---

## Table of Contents

### Leaderboards
- [GET /api/galaxies/{galaxyUuid}/leaderboards/overall](#get-overall-leaderboard)
- [GET /api/galaxies/{galaxyUuid}/leaderboards/economic](#get-economic-leaderboard)
- [GET /api/galaxies/{galaxyUuid}/leaderboards/combat](#get-combat-leaderboard)
- [GET /api/galaxies/{galaxyUuid}/leaderboards/colonial](#get-colonial-leaderboard)

### Player Rankings & Statistics
- [GET /api/players/{uuid}/ranking](#get-player-ranking)
- [GET /api/players/{uuid}/statistics](#get-player-statistics)

### Victory Conditions
- [GET /api/galaxies/{galaxyUuid}/victory-conditions](#get-victory-conditions)
- [GET /api/galaxies/{galaxyUuid}/victory-leaders](#get-victory-leaders)
- [GET /api/players/{uuid}/victory-progress](#get-player-victory-progress)

---

## Leaderboards

### GET Overall Leaderboard

**Endpoint:** `GET /api/galaxies/{galaxyUuid}/leaderboards/overall`

**Description:** Returns the overall leaderboard ranked by player level, experience, and credits. Shows top players in the galaxy based on general progression.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxyUuid | string (UUID) | Yes | The UUID of the galaxy (path parameter) |
| limit | integer | No | Number of results to return (default: 100, max: 500) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Overall leaderboard retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "leaderboard_type": "overall",
    "total_players": 234,
    "leaders": [
      {
        "rank": 1,
        "player": {
          "uuid": "660e8400-e29b-41d4-a716-446655440000",
          "call_sign": "StarLord",
          "user_name": "John Doe"
        },
        "stats": {
          "level": 45,
          "experience": 1250000,
          "credits": 5000000
        },
        "ship": {
          "name": "USS Enterprise",
          "hull": 850
        }
      },
      {
        "rank": 2,
        "player": {
          "uuid": "770e8400-e29b-41d4-a716-446655440000",
          "call_sign": "Nova",
          "user_name": "Jane Smith"
        },
        "stats": {
          "level": 44,
          "experience": 1180000,
          "credits": 4200000
        },
        "ship": {
          "name": "Falcon",
          "hull": 720
        }
      }
    ]
  }
}
```

**Response Fields Explained:**

- `galaxy.uuid`: The UUID of the galaxy
- `galaxy.name`: Human-readable name of the galaxy
- `leaderboard_type`: Type of leaderboard (always "overall" for this endpoint)
- `total_players`: Total count of active players in the galaxy
- `leaders`: Array of leaderboard entries
  - `rank`: Player's position on the leaderboard (1-indexed)
  - `player.uuid`: Player's unique identifier
  - `player.call_sign`: Player's in-game name/callsign
  - `player.user_name`: Associated user account name (may be "Unknown" if user deleted)
  - `stats.level`: Player's current level
  - `stats.experience`: Total experience points accumulated
  - `stats.credits`: Current liquid credits (cash on hand)
  - `ship.name`: Name of player's active ship (null if no active ship)
  - `ship.hull`: Current hull integrity of active ship (null if no active ship)

**Sorting Logic:**
1. Primary: Level (descending)
2. Secondary: Experience (descending)
3. Tertiary: Credits (descending)

**Error Responses:**

- **404 Not Found:** Galaxy with provided UUID does not exist
  ```json
  {
    "success": false,
    "message": "No query results for model [App\\Models\\Galaxy]."
  }
  ```

- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- Only includes players with `status = 'active'` (excludes deleted/inactive accounts)
- Ship data may be null if player has no active ship assigned
- User name shows "Unknown" if the associated user account no longer exists
- The limit parameter is capped at 500 to prevent excessive database load

---

### GET Economic Leaderboard

**Endpoint:** `GET /api/galaxies/{galaxyUuid}/leaderboards/economic`

**Description:** Returns the economic leaderboard ranked by total net worth (credits + ship value + cargo value + colony value). Shows the wealthiest players in the galaxy.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxyUuid | string (UUID) | Yes | The UUID of the galaxy (path parameter) |
| limit | integer | No | Number of results to return (default: 100, max: 500) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Economic leaderboard retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "leaderboard_type": "economic",
    "leaders": [
      {
        "rank": 1,
        "player": {
          "uuid": "660e8400-e29b-41d4-a716-446655440000",
          "call_sign": "Midas",
          "user_name": "John Doe"
        },
        "economic_stats": {
          "net_worth": 25750000.50,
          "liquid_credits": 15000000,
          "ship_value": 5000000.00,
          "cargo_value": 3250000.50,
          "colony_count": 12,
          "colony_value": 2500000.00
        }
      }
    ]
  }
}
```

**Response Fields Explained:**

- `leaderboard_type`: Type of leaderboard (always "economic" for this endpoint)
- `leaders[].economic_stats.net_worth`: Total value of all assets (sum of all values below)
- `leaders[].economic_stats.liquid_credits`: Cash on hand
- `leaders[].economic_stats.ship_value`: Total value of all owned ships (based on ship blueprint `base_price`)
- `leaders[].economic_stats.cargo_value`: Current cargo value (quantity × mineral `base_value`)
- `leaders[].economic_stats.colony_count`: Number of colonies owned
- `leaders[].economic_stats.colony_value`: Total colony value (development_level × 10,000 per colony)

**Sorting Logic:** Net worth (descending)

**Error Responses:**

- **404 Not Found:** Galaxy with provided UUID does not exist
- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- **Performance optimized:** Uses pre-aggregated queries to avoid N+1 problems
- Cargo values are calculated using mineral `base_value` (not current market prices)
- Ship value uses the blueprint's `base_price` (does not account for upgrades or damage)
- Colony value is a simplified calculation: `development_level × 10,000` per colony
- Players with zero assets across all categories will have `net_worth: 0.00`
- Only includes players with `status = 'active'`

---

### GET Combat Leaderboard

**Endpoint:** `GET /api/galaxies/{galaxyUuid}/leaderboards/combat`

**Description:** Returns the combat leaderboard ranked by combat score (weighted combination of PvP wins, pirate kills, and K/D ratio). Shows the most formidable combatants in the galaxy.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxyUuid | string (UUID) | Yes | The UUID of the galaxy (path parameter) |
| limit | integer | No | Number of results to return (default: 100, max: 500) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Combat leaderboard retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "leaderboard_type": "combat",
    "leaders": [
      {
        "rank": 1,
        "player": {
          "uuid": "660e8400-e29b-41d4-a716-446655440000",
          "call_sign": "WarHawk",
          "user_name": "John Doe"
        },
        "combat_stats": {
          "pvp_wins": 45,
          "pvp_losses": 12,
          "pirate_kills": 230,
          "total_kills": 275,
          "kd_ratio": 22.92
        },
        "combat_score": 1695.84
      }
    ]
  }
}
```

**Response Fields Explained:**

- `leaders[].combat_stats.pvp_wins`: Number of PvP battles won (player was on winning side)
- `leaders[].combat_stats.pvp_losses`: Number of PvP battles lost (player was on losing side, as attacker or defender)
- `leaders[].combat_stats.pirate_kills`: Number of pirate encounters won
- `leaders[].combat_stats.total_kills`: Sum of PvP wins and pirate kills
- `leaders[].combat_stats.kd_ratio`: Kill/Death ratio (total_kills / pvp_losses, or total_kills if no losses)
- `leaders[].combat_score`: Weighted combat score used for ranking

**Combat Score Calculation:**

```
combat_score = (pvp_wins × 10) + (pirate_kills × 5) + (kd_ratio × 2)
```

**PvP Win/Loss Logic:**

- PvP win counted when: `combat_type = 'pvp'`, player side is `attacker` or `ally_attacker`, and `victor_type = 'attacker'`
- PvP loss counted when: `combat_type = 'pvp'`, player side is `defender` or `ally_defender`, and `victor_type = 'attacker'`
- Pirate kill counted when: `combat_type = 'pirate'` and `victor_type` is `player` or `attacker`

**Sorting Logic:** Combat score (descending)

**Error Responses:**

- **404 Not Found:** Galaxy with provided UUID does not exist
- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- **Performance optimized:** Uses single aggregation query to avoid N+1 problems
- Only counts completed combat sessions (`status = 'completed'`)
- K/D ratio will equal `total_kills` (as a float) if the player has zero PvP losses
- Players with no combat history will have all stats at 0 and combat_score of 0.00
- Only includes players with `status = 'active'`
- PvP wins do NOT include defensive victories (where player defended successfully)

---

### GET Colonial Leaderboard

**Endpoint:** `GET /api/galaxies/{galaxyUuid}/leaderboards/colonial`

**Description:** Returns the colonial leaderboard ranked by colonial score (weighted combination of colony count, population, and development level). Shows the most successful colonizers in the galaxy.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxyUuid | string (UUID) | Yes | The UUID of the galaxy (path parameter) |
| limit | integer | No | Number of results to return (default: 100, max: 500) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Colonial leaderboard retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "leaderboard_type": "colonial",
    "leaders": [
      {
        "rank": 1,
        "player": {
          "uuid": "660e8400-e29b-41d4-a716-446655440000",
          "call_sign": "ColonyMaster",
          "user_name": "John Doe"
        },
        "colonial_stats": {
          "colony_count": 18,
          "total_population": 5600000,
          "avg_development": 7.5,
          "population_share": 23.45
        },
        "colonial_score": 562175.00
      }
    ]
  }
}
```

**Response Fields Explained:**

- `leaders[].colonial_stats.colony_count`: Number of colonies owned by the player
- `leaders[].colonial_stats.total_population`: Sum of population across all player's colonies
- `leaders[].colonial_stats.avg_development`: Average development level across all colonies
- `leaders[].colonial_stats.population_share`: Percentage of total galactic population controlled (0-100)
- `leaders[].colonial_score`: Weighted colonial score used for ranking

**Colonial Score Calculation:**

```
colonial_score = (colony_count × 100) + (total_population / 10) + (avg_development × 50)
```

**Population Share Calculation:**

```
population_share = (player_total_population / galaxy_total_population) × 100
```

Where `galaxy_total_population` is the sum of all colony populations across all players in the galaxy.

**Sorting Logic:** Colonial score (descending)

**Error Responses:**

- **404 Not Found:** Galaxy with provided UUID does not exist
- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- **Performance optimized:** Calculates galaxy population once, uses aggregation query for colony stats
- Players with no colonies will have all stats at 0 and colonial_score of 0.00
- `population_share` will be 0.00 if there are no colonies in the entire galaxy
- Average development is rounded to 2 decimal places
- Only includes players with `status = 'active'`
- Colonial score heavily weights colony count, making expansionist strategies most competitive

---

## Player Rankings & Statistics

### GET Player Ranking

**Endpoint:** `GET /api/players/{uuid}/ranking`

**Description:** Returns a specific player's ranking across all leaderboard categories (overall, economic, colonial). Does not include combat rankings.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | The UUID of the player (path parameter) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Player rankings retrieved successfully",
  "data": {
    "player": {
      "uuid": "660e8400-e29b-41d4-a716-446655440000",
      "call_sign": "StarLord"
    },
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "rankings": {
      "overall": 5,
      "economic": 12,
      "colonial": 8
    },
    "total_players": 234
  }
}
```

**Response Fields Explained:**

- `player.uuid`: The player's unique identifier
- `player.call_sign`: The player's in-game name/callsign
- `galaxy.uuid`: The UUID of the galaxy the player belongs to
- `galaxy.name`: Human-readable name of the galaxy
- `rankings.overall`: Player's rank on the overall leaderboard (1 = top rank)
- `rankings.economic`: Player's rank on the economic leaderboard (simplified by credits only)
- `rankings.colonial`: Player's rank on the colonial leaderboard (by colony count)
- `total_players`: Total number of active players in the galaxy

**Ranking Calculation Logic:**

- **Overall Rank:** Calculated by level (primary), experience (secondary), credits (tertiary)
- **Economic Rank:** Simplified calculation based on credits only (NOT full net worth like the economic leaderboard)
- **Colonial Rank:** Based on number of colonies owned (NOT colonial score like the colonial leaderboard)

**Error Responses:**

- **404 Not Found:** Player with provided UUID does not exist
  ```json
  {
    "success": false,
    "message": "No query results for model [App\\Models\\Player]."
  }
  ```

- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- **Performance optimized:** Uses COUNT queries with conditions instead of loading all players
- **Simplified rankings:** Economic and colonial rankings use simplified metrics (credits and colony count) rather than the full scoring algorithms used in leaderboards
- Rankings only count players with `status = 'active'`
- A rank of 1 means the player is in first place
- If multiple players have identical stats, ties are broken arbitrarily (database order)
- Combat rankings are NOT included in this endpoint (would require complex aggregation)

---

### GET Player Statistics

**Endpoint:** `GET /api/players/{uuid}/statistics`

**Description:** Returns comprehensive statistics for a specific player including combat, economic, and exploration metrics.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | The UUID of the player (path parameter) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Player statistics retrieved successfully",
  "data": {
    "player": {
      "uuid": "660e8400-e29b-41d4-a716-446655440000",
      "call_sign": "StarLord",
      "level": 35,
      "experience": 875000
    },
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "statistics": {
      "combat": {
        "total_battles": 87,
        "victories": 65,
        "defeats": 22,
        "total_damage_dealt": 456000,
        "total_damage_taken": 123000
      },
      "economic": {
        "current_credits": 3500000,
        "cargo_value": 450000.50,
        "total_colonies": 8,
        "total_ships": 3
      },
      "exploration": {
        "systems_visited": 142,
        "current_location": {
          "name": "Sol",
          "type": "star"
        }
      }
    }
  }
}
```

**Response Fields Explained:**

- `player.uuid`: Player's unique identifier
- `player.call_sign`: Player's in-game name/callsign
- `player.level`: Player's current level
- `player.experience`: Total experience points
- `galaxy.uuid`: UUID of the galaxy the player belongs to
- `galaxy.name`: Name of the galaxy

**Combat Statistics:**

- `total_battles`: Total number of completed combat sessions the player participated in
- `victories`: Number of battles where player's result was 'victory'
- `defeats`: Number of battles where player's result was 'defeat'
- `total_damage_dealt`: Sum of all damage dealt across all battles
- `total_damage_taken`: Sum of all damage taken across all battles

**Economic Statistics:**

- `current_credits`: Player's liquid credits (cash on hand)
- `cargo_value`: Total value of all cargo across all player's ships (quantity × mineral `base_value`)
- `total_colonies`: Count of colonies owned by the player
- `total_ships`: Count of ships owned by the player (including inactive ships)

**Exploration Statistics:**

- `systems_visited`: Count of unique star charts the player owns (systems they've visited/mapped)
- `current_location.name`: Name of the POI where the player is currently located (null if no location)
- `current_location.type`: Type of POI (e.g., "star", "planet", "asteroid_belt", "space_station")

**Error Responses:**

- **404 Not Found:** Player with provided UUID does not exist
- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- **Performance optimized:** Uses aggregation queries to avoid loading all records
- Combat statistics only include completed battles (`status = 'completed'`)
- Cargo value uses mineral `base_value` (not current market prices)
- `systems_visited` reflects star charts owned, not necessarily physically visited
- `current_location` will be null if the player has no assigned `current_poi_id`
- Damage values are cumulative totals (not averages)
- Victory/defeat counts include both PvP and pirate encounters

---

## Victory Conditions

### GET Victory Conditions

**Endpoint:** `GET /api/galaxies/{galaxyUuid}/victory-conditions`

**Description:** Returns the four victory paths available in the galaxy with their requirements. Victory conditions are configured in `config/game_config.php` under the `victory` key.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxyUuid | string (UUID) | Yes | The UUID of the galaxy (path parameter) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Victory conditions retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "victory_conditions": {
      "merchant_empire": {
        "name": "Merchant Empire",
        "description": "Accumulate vast wealth through trading and commerce",
        "requirement": {
          "credits": 1000000000
        },
        "formatted_requirement": "1,000,000,000 credits"
      },
      "colonization": {
        "name": "Colonization Victory",
        "description": "Control the majority of the galaxy's population",
        "requirement": {
          "population_share": 50
        },
        "formatted_requirement": "50% of galactic population"
      },
      "conquest": {
        "name": "Conquest Victory",
        "description": "Dominate the galaxy through military might",
        "requirement": {
          "systems_controlled_share": 60
        },
        "formatted_requirement": "60% of star systems"
      },
      "pirate_king": {
        "name": "Pirate King",
        "description": "Seize control of the outlaw network",
        "requirement": {
          "pirate_power_share": 70
        },
        "formatted_requirement": "70% of pirate network"
      }
    }
  }
}
```

**Response Fields Explained:**

- `victory_conditions`: Object containing all four victory paths

**Merchant Empire:**
- `requirement.credits`: Number of credits required (default: 1,000,000,000)
- `formatted_requirement`: Human-readable requirement with number formatting

**Colonization Victory:**
- `requirement.population_share`: Percentage of galactic population required (default: 50)
- `formatted_requirement`: Human-readable requirement

**Conquest Victory:**
- `requirement.systems_controlled_share`: Percentage of inhabited star systems required (default: 60)
- `formatted_requirement`: Human-readable requirement

**Pirate King Victory:**
- `requirement.pirate_power_share`: Percentage of pirate network control required (default: 70)
- `formatted_requirement`: Human-readable requirement

**Default Configuration Values:**

```php
// From config/game_config.php
'victory' => [
    'merchant_credits' => 1_000_000_000,    // 1 billion credits
    'colonization_share' => 0.5,            // 50% of population
    'conquest_share' => 0.6,                // 60% of systems
    'pirate_power' => 0.7,                  // 70% of pirate network
]
```

**Error Responses:**

- **404 Not Found:** Galaxy with provided UUID does not exist
- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- Victory conditions are the same for all players in a galaxy
- Values come from `config/game_config.php` and are NOT snapshotted per galaxy (unlike other galaxy config)
- Changing config values will affect all galaxies immediately
- Pirate King victory path is currently not fully implemented (see victory progress endpoint)
- Percentages in requirements are stored as whole numbers (50 = 50%, not 0.5)

---

### GET Victory Leaders

**Endpoint:** `GET /api/galaxies/{galaxyUuid}/victory-leaders`

**Description:** Returns the top 5 players closest to victory in each category (Merchant Empire, Colonization, Conquest). Does not include Pirate King leaders.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| galaxyUuid | string (UUID) | Yes | The UUID of the galaxy (path parameter) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Victory leaders retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "victory_leaders": {
      "merchant_empire": [
        {
          "uuid": "660e8400-e29b-41d4-a716-446655440000",
          "call_sign": "Midas",
          "user_name": "John Doe",
          "credits": 750000000,
          "progress_percent": 75
        },
        {
          "uuid": "770e8400-e29b-41d4-a716-446655440000",
          "call_sign": "TradeLord",
          "user_name": "Jane Smith",
          "credits": 500000000,
          "progress_percent": 50
        }
      ],
      "colonization": [
        {
          "uuid": "880e8400-e29b-41d4-a716-446655440000",
          "call_sign": "ColonyMaster",
          "user_name": "Bob Johnson",
          "population": 12500000,
          "population_share_percent": 45.5,
          "progress_percent": 91
        }
      ],
      "conquest": [
        {
          "uuid": "990e8400-e29b-41d4-a716-446655440000",
          "call_sign": "Conqueror",
          "user_name": "Alice Williams",
          "systems_controlled": 180,
          "systems_share_percent": 55.2,
          "progress_percent": 92
        }
      ]
    }
  }
}
```

**Response Fields Explained:**

- `victory_leaders`: Object containing three arrays (one per implemented victory path)

**Merchant Empire Leaders:**
- `credits`: Player's current liquid credits
- `progress_percent`: Progress toward merchant victory (capped at 100)

**Colonization Leaders:**
- `population`: Total population across all player's colonies
- `population_share_percent`: Percentage of galactic population controlled
- `progress_percent`: Progress toward colonization victory (capped at 100)

**Conquest Leaders:**
- `systems_controlled`: Number of inhabited systems with player colonies
- `systems_share_percent`: Percentage of total inhabited systems controlled
- `progress_percent`: Progress toward conquest victory (capped at 100)

**Progress Calculation:**

```
Merchant: (current_credits / required_credits) × 100 (capped at 100)
Colonization: (population_share / required_share) × 100 (capped at 100)
Conquest: (systems_share / required_share) × 100 (capped at 100)
```

**Sorting Logic:**

- Merchant Empire: Credits (descending)
- Colonization: Population share percentage (descending)
- Conquest: Systems share percentage (descending)

**Error Responses:**

- **404 Not Found:** Galaxy with provided UUID does not exist
- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- Each category shows top 5 players only
- Pirate King leaders are NOT included (feature not fully implemented)
- Progress percent is capped at 100 (will not exceed even if requirement is surpassed)
- Only includes players with `status = 'active'`
- Conquest only counts inhabited systems (not all POIs)
- Population/systems share percentages are rounded to 2 decimal places
- If fewer than 5 players have progress in a category, the array will be shorter
- Players with 0 progress are excluded from results

---

### GET Player Victory Progress

**Endpoint:** `GET /api/players/{uuid}/victory-progress`

**Description:** Returns a specific player's progress toward all four victory conditions, including which path they're closest to winning.

**Authentication:** Required

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | The UUID of the player (path parameter) |

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Victory progress retrieved successfully",
  "data": {
    "player": {
      "uuid": "660e8400-e29b-41d4-a716-446655440000",
      "call_sign": "StarLord"
    },
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Andromeda Prime"
    },
    "victory_paths": {
      "merchant_empire": {
        "progress_percent": 35.5,
        "achieved": false,
        "current": 355000000,
        "required": 1000000000,
        "remaining": 645000000
      },
      "colonization": {
        "progress_percent": 82.4,
        "achieved": false,
        "current_population": 11000000,
        "galaxy_population": 26700000,
        "population_share_percent": 41.2,
        "required_share_percent": 50
      },
      "conquest": {
        "progress_percent": 75.0,
        "achieved": false,
        "current_systems": 135,
        "total_systems": 300,
        "systems_share_percent": 45.0,
        "required_share_percent": 60
      },
      "pirate_king": {
        "progress_percent": 0,
        "achieved": false,
        "note": "Pirate faction takeover not yet implemented"
      }
    },
    "closest_to_victory": {
      "path": "colonization",
      "name": "Colonization",
      "progress_percent": 82.4
    }
  }
}
```

**Response Fields Explained:**

- `victory_paths`: Object containing progress for all four victory paths

**Merchant Empire Progress:**
- `progress_percent`: Percentage progress toward requirement (0-100, capped)
- `achieved`: Boolean indicating if victory condition is met
- `current`: Player's current credits
- `required`: Credits required for victory (from config)
- `remaining`: Credits still needed (0 if achieved)

**Colonization Progress:**
- `progress_percent`: Percentage progress toward requirement (0-100, capped)
- `achieved`: Boolean indicating if victory condition is met
- `current_population`: Total population across player's colonies
- `galaxy_population`: Total population across all colonies in galaxy
- `population_share_percent`: Player's percentage of galactic population (0-100)
- `required_share_percent`: Percentage required for victory (from config)

**Conquest Progress:**
- `progress_percent`: Percentage progress toward requirement (0-100, capped)
- `achieved`: Boolean indicating if victory condition is met
- `current_systems`: Number of inhabited systems with player colonies
- `total_systems`: Total number of inhabited systems in galaxy
- `systems_share_percent`: Player's percentage of inhabited systems (0-100)
- `required_share_percent`: Percentage required for victory (from config)

**Pirate King Progress:**
- `progress_percent`: Always 0 (not implemented)
- `achieved`: Always false
- `note`: Explains feature is not yet implemented

**Closest to Victory:**
- `path`: Key of the victory path with highest progress ("merchant", "colonization", "conquest", "pirate")
- `name`: Human-readable name of the victory path
- `progress_percent`: The highest progress percentage

**Progress Calculation:**

```
Merchant: min((current_credits / required_credits) × 100, 100)
Colonization: min((population_share / required_share) × 100, 100)
Conquest: min((systems_share / required_share) × 100, 100)
```

**Error Responses:**

- **404 Not Found:** Player with provided UUID does not exist
- **401 Unauthorized:** Missing or invalid authentication token

**Warnings & Caveats:**

- **Pirate King victory is not implemented:** Always shows 0 progress with explanatory note
- Progress percentages are capped at 100 (will not show > 100% even if requirement exceeded)
- `remaining` credits will be 0 (not negative) if merchant victory is achieved
- Conquest only counts inhabited systems, not all POIs
- Colonization uses total galaxy population (sum of all colonies), not total POI count
- `achieved` flags do NOT automatically trigger a win state (game logic elsewhere must check)
- Population and system share percentages are rounded to 2 decimal places
- If galaxy has zero population, `population_share_percent` will be 0.00
- If galaxy has zero inhabited systems, `systems_share_percent` will be 0.00

---

## Common Response Structure

All endpoints in this API follow the standardized response format from `BaseApiController`:

### Success Response Format

```json
{
  "success": true,
  "message": "Operation description",
  "data": {
    // Endpoint-specific data
  }
}
```

### Error Response Format

```json
{
  "success": false,
  "message": "Error description"
}
```

---

## Authentication

All endpoints require authentication via Laravel Sanctum. Include the authentication token in the request headers:

```
Authorization: Bearer {token}
```

**Common Authentication Errors:**

- **401 Unauthorized:** Missing or invalid token
- **403 Forbidden:** Valid token but insufficient permissions (rare in these endpoints)

---

## Performance Considerations

### Optimization Strategies

The leaderboard and victory controllers implement several performance optimizations:

1. **Pre-aggregation queries:** Combat, economic, and colonial leaderboards use single aggregation queries instead of N+1 patterns
2. **Selective loading:** Only loads necessary columns via `select()`
3. **Lazy calculations:** Rankings calculate once for the galaxy instead of per-player
4. **Indexed queries:** Uses indexed columns (galaxy_id, status, player_id)

### Rate Limiting

These endpoints may be subject to rate limiting:

- Leaderboards: Relatively expensive queries, recommend client-side caching for 1-5 minutes
- Player stats: Moderate cost, suitable for real-time updates
- Victory conditions: Very cheap (static data), cache aggressively

### Best Practices

- Use the `limit` parameter to request only what you need (max 500)
- Cache leaderboard results on the client side
- Avoid polling these endpoints at high frequency
- Victory conditions rarely change; cache for the duration of the session
- Consider websockets or long-polling for real-time leaderboard updates in competitive scenarios

---

## Game Design Notes

### Victory Paths Overview

**Merchant Empire (Economic)**
- Pure economic victory
- Requires 1 billion credits liquid (not net worth)
- Fastest path for skilled traders
- Does not require combat or colonies

**Colonization (Population)**
- Population-based victory
- Requires controlling 50% of galactic population
- Emphasizes colony management and growth
- Population accumulates over time with proper development

**Conquest (Military)**
- Territory-based victory
- Requires controlling 60% of inhabited systems
- Must establish colonies on inhabited worlds
- Most aggressive path, likely to trigger PvP

**Pirate King (Not Implemented)**
- Would require controlling 70% of pirate faction influence
- Placeholder in current implementation
- Future feature for pirate-aligned gameplay

### Leaderboard Scoring Algorithms

**Combat Score:**
```
(pvp_wins × 10) + (pirate_kills × 5) + (kd_ratio × 2)
```
- Heavily weights PvP wins (worth 2× pirate kills)
- K/D ratio is minor component (prevents farming weak targets)

**Colonial Score:**
```
(colony_count × 100) + (total_population / 10) + (avg_development × 50)
```
- Prioritizes expansion (colony count most valuable)
- Population is secondary metric
- Development level tertiary (quality over quantity)

### Victory Condition Design Philosophy

- **Multiple paths:** Encourages diverse playstyles
- **Percentage-based:** Scales with galaxy size
- **Increasing difficulty:** Conquest (60%) harder than colonization (50%)
- **Balanced requirements:** All paths achievable with focused effort
- **Competition built-in:** Population/systems are zero-sum (taking share from others)

---

## Changelog

**2026-02-16:** Initial API documentation for leaderboards and victory endpoints

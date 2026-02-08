# Galaxy API

## Overview

The Galaxy API provides endpoints for listing, viewing, and creating galaxies. Galaxy creation uses a high-performance generator pipeline with generation time for the largest tier at approximately **38 seconds**.

## Endpoints

---

## List Galaxies

### List Galaxies for User

```
GET /api/galaxies
```

**Authentication:** Required (Bearer token)

Returns galaxies organized into two sections:
1. **my_games**: Galaxies the user is part of, ordered by last access (most recent first)
2. **open_games**: Active galaxies open for registration, ordered by player count (fewest players first)

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxies retrieved successfully",
  "data": {
    "my_games": [
      {
        "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "name": "Andromeda Nexus",
        "size": "massive",
        "players": 42,
        "max_players": 100,
        "slots_available": 58,
        "mode": "multiplayer",
        "status": "active"
      }
    ],
    "open_games": [
      {
        "uuid": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
        "name": "Orion Frontier",
        "size": "medium",
        "players": 5,
        "max_players": 50,
        "slots_available": 45,
        "mode": "multiplayer",
        "status": "active"
      },
      {
        "uuid": "c3d4e5f6-a7b8-9012-cdef-123456789012",
        "name": "New Galaxy",
        "size": "small",
        "players": 12,
        "max_players": 100,
        "slots_available": 88,
        "mode": "mixed",
        "status": "active"
      }
    ]
  },
  "meta": {
    "timestamp": "2026-01-27T18:30:00+00:00",
    "request_id": "uuid-here"
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | Galaxy UUID for API references |
| `name` | string | Galaxy display name |
| `size` | string | Size tier: `small`, `medium`, `large`, `massive` |
| `players` | integer | Current count of active players |
| `max_players` | integer | Maximum player capacity (default: 100) |
| `slots_available` | integer | Remaining slots (max_players - players) |
| `mode` | string | Game mode: `multiplayer`, `single_player`, `mixed` |
| `status` | string | Galaxy status: `active`, `draft`, `processing`, `archived` |

**Open Games Criteria:**
- Status is `active`
- Game mode is `multiplayer` or `mixed`
- Player count is below `max_players` limit
- User is not already a member

---

### List Galaxies (Cached)

```
GET /api/galaxies/list
```

**Authentication:** Required (Bearer token)

Same as `GET /api/galaxies` but with caching:
- **my_games**: Fetched fresh (personalized data)
- **open_games**: Cached for 60 seconds (shared data)

Use this endpoint for game selection screens where fresh data isn't critical.

**Response (200 OK):**

Same structure as `GET /api/galaxies`.

---

### Get Galaxy Details (Hydrated)

```
GET /api/galaxies/{uuid}
```

**Authentication:** Not required

Returns full galaxy details including statistics, dimensions, and related data.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxy details retrieved successfully",
  "data": {
    "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "name": "Andromeda Nexus",
    "status": "active",
    "game_mode": "multiplayer",
    "size_tier": "massive",

    "dimensions": {
      "width": 5000,
      "height": 5000
    },

    "core_bounds": {
      "x_min": 1250,
      "x_max": 3750,
      "y_min": 1250,
      "y_max": 3750
    },

    "statistics": {
      "total_players": 50,
      "active_players": 42,
      "total_systems": 2500,
      "sectors": 400,
      "warp_gates": 5094,
      "trading_hubs": 1000
    },

    "players": [
      {
        "uuid": "player-uuid-1",
        "call_sign": "StarCaptain",
        "level": 15,
        "status": "active"
      },
      {
        "uuid": "player-uuid-2",
        "call_sign": "SpaceTrader",
        "level": 8,
        "status": "active"
      }
    ],

    "owner": null,

    "created_at": "2026-01-15T10:30:00+00:00",
    "updated_at": "2026-01-27T18:00:00+00:00",
    "generation_completed_at": "2026-01-15T10:30:38+00:00"
  }
}
```

**Hydrated Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | Galaxy UUID |
| `name` | string | Galaxy display name |
| `status` | string | `active`, `draft`, `processing`, `archived` |
| `game_mode` | string | `multiplayer`, `single_player`, `mixed` |
| `size_tier` | string | `small`, `medium`, `large`, `massive` |
| `dimensions` | object | Galaxy width and height |
| `core_bounds` | object | Core region boundaries (x_min, x_max, y_min, y_max) |
| `statistics.total_players` | integer | All players (including inactive) |
| `statistics.active_players` | integer | Currently active players |
| `statistics.total_systems` | integer | Total star systems |
| `statistics.sectors` | integer | Navigation sectors |
| `statistics.warp_gates` | integer | Total warp gate connections |
| `statistics.trading_hubs` | integer | Commerce stations |
| `players` | array | First 20 active players (call_sign, level, status) |
| `owner` | object\|null | Galaxy owner (single_player mode only) |
| `created_at` | string | ISO 8601 creation timestamp |
| `updated_at` | string | ISO 8601 last update timestamp |
| `generation_completed_at` | string | ISO 8601 generation completion timestamp |

**Error Response (404 Not Found):**

```json
{
  "success": false,
  "message": "Galaxy not found",
  "error_code": "NOT_FOUND"
}
```

---

## Frontend Integration

### TypeScript Interfaces

```typescript
// Dehydrated galaxy for lists
interface GalaxyListItem {
  uuid: string;
  name: string;
  size: 'small' | 'medium' | 'large' | 'massive';
  players: number;
  max_players: number;
  slots_available: number;
  mode: 'multiplayer' | 'single_player' | 'mixed';
  status: 'active' | 'draft' | 'processing' | 'archived';
}

// Galaxy list response (authenticated)
interface GalaxyListResponse {
  my_games: GalaxyListItem[];
  open_games: GalaxyListItem[];
}

// Full galaxy details
interface Galaxy {
  uuid: string;
  name: string;
  status: 'active' | 'draft' | 'processing' | 'archived';
  game_mode: 'multiplayer' | 'single_player' | 'mixed';
  size_tier: 'small' | 'medium' | 'large' | 'massive' | null;

  dimensions: {
    width: number;
    height: number;
  };

  core_bounds?: {
    x_min: number;
    x_max: number;
    y_min: number;
    y_max: number;
  };

  statistics: {
    total_players: number | null;
    active_players: number | null;
    total_systems: number | null;
    sectors?: number;
    warp_gates?: number;
    trading_hubs?: number;
  };

  players?: Array<{
    uuid: string;
    call_sign: string;
    level: number;
    status: string;
  }>;

  owner?: {
    id: number;
    name: string;
  } | null;

  created_at: string;
  updated_at: string;
  generation_completed_at: string | null;
}

// API response wrapper
interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
  meta?: {
    timestamp: string;
    request_id: string;
  };
}
```

### Usage Examples

```typescript
// Fetch galaxy list for selection UI (requires authentication)
async function fetchGalaxies(token: string): Promise<GalaxyListResponse> {
  const response = await fetch('/api/galaxies/list', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (response.status === 401) {
    throw new Error('Authentication required');
  }

  const result: ApiResponse<GalaxyListResponse> = await response.json();
  return result.data;
}

// Fetch full galaxy details (public)
async function fetchGalaxyDetails(uuid: string): Promise<Galaxy> {
  const response = await fetch(`/api/galaxies/${uuid}`);
  if (!response.ok) {
    throw new Error('Galaxy not found');
  }
  const result: ApiResponse<Galaxy> = await response.json();
  return result.data;
}

// Example: Galaxy selection component
const { my_games, open_games } = await fetchGalaxies(userToken);

console.log('=== Your Games ===');
my_games.forEach(galaxy => {
  console.log(`${galaxy.name} (${galaxy.size}) - ${galaxy.players}/${galaxy.max_players} players`);
});

console.log('=== Open Games ===');
open_games.forEach(galaxy => {
  console.log(`${galaxy.name} - ${galaxy.slots_available} slots available`);
});
```

---

## Create Galaxy

```
POST /api/galaxies/create
```

**Authentication:** Required (Bearer token)

**Request Body:**

```json
{
  "size_tier": "massive",
  "game_mode": "multiplayer",
  "name": "My Galaxy Name",
  "skip_mirror": false,
  "skip_precursors": false,
  "npc_count": 0,
  "npc_difficulty": "medium"
}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `size_tier` | string | **Yes** | Galaxy size: `small`, `medium`, `large`, or `massive` |
| `game_mode` | string | **Yes** | One of: `multiplayer`, `single_player`, `mixed` |
| `name` | string | No | Custom galaxy name (auto-generated if omitted) |
| `skip_mirror` | boolean | No | Skip mirror universe gate (default: false) |
| `skip_precursors` | boolean | No | Skip precursor content (default: false) |
| `npc_count` | integer | No | Number of NPCs for single_player/mixed (default: 0-5) |
| `npc_difficulty` | string | No | NPC difficulty: `easy`, `medium`, `hard`, `expert` |

### Size Tiers

| Tier | Dimensions | Core Stars | Outer Stars | Total Stars | Secret |
|------|------------|------------|-------------|-------------|--------|
| `small` | 500×500 | 100 | 150 | 250 | No |
| `medium` | 1500×1500 | 300 | 450 | 750 | No |
| `large` | 2500×2500 | 500 | 750 | 1,250 | No |
| `massive` | 5000×5000 | 1,000 | 1,500 | 2,500 | **Yes** |

**Note:** The `massive` tier is not returned by `GET /api/galaxies/size-tiers` but can be used directly in requests.

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Galaxy created successfully using optimized pipeline",
  "data": {
    "galaxy": {
      "id": 42,
      "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "name": "Andromeda Nexus",
      "status": "active"
    },
    "statistics": {
      "total_pois": 10451,
      "total_stars": 2500,
      "core_stars": 1000,
      "outer_stars": 1500,
      "inhabited_systems": 1000,
      "fortified_systems": 1000,
      "warp_gates": 5094,
      "active_gates": 3166,
      "dormant_gates": 1927,
      "trading_hubs": 1000
    },
    "metrics": {
      "total_elapsed_ms": 38663.04,
      "total_elapsed_seconds": 38.663,
      "generators": {
        "star_field": {
          "success": true,
          "metrics": {
            "elapsed_ms": 3096.86,
            "counts": {
              "core_points_generated": 1000,
              "outer_points_generated": 1500,
              "stars_inserted": 2500
            }
          }
        },
        "planetary_systems": {
          "success": true,
          "metrics": {
            "elapsed_ms": 21296.81,
            "counts": {
              "stars_processed": 1500,
              "planets_inserted": 7950,
              "moon_specs_queued": 3600
            }
          }
        },
        "warp_gate_network": {
          "success": true,
          "metrics": {
            "elapsed_ms": 8686.03,
            "counts": {
              "core_stars": 1000,
              "core_pairs_found": 3166,
              "outer_stars": 1500,
              "outer_pairs_found": 1927,
              "total_gates_created": 5093
            }
          }
        },
        "mineral_deposits": {
          "success": true,
          "metrics": {
            "elapsed_ms": 1789.78,
            "counts": {
              "mineable_bodies": 7950,
              "bodies_with_deposits": 7550
            }
          }
        },
        "defense_network": {
          "success": true,
          "metrics": {
            "elapsed_ms": 2859.54,
            "counts": {
              "core_stars": 1000,
              "defenses_created": 5000,
              "systems_fortified": 1000
            }
          }
        },
        "trading_infrastructure": {
          "success": true,
          "metrics": {
            "elapsed_ms": 843.17,
            "counts": {
              "core_stars": 1000,
              "hubs_created": 1000
            }
          }
        },
        "precursor_content": {
          "success": true,
          "metrics": {
            "elapsed_ms": 37.59,
            "counts": {
              "precursor_gate_placed": 1,
              "precursor_ship_placed": 1
            }
          }
        }
      }
    },
    "config": {
      "tier": "massive",
      "game_mode": "multiplayer",
      "dimensions": {
        "width": 5000,
        "height": 5000
      },
      "star_counts": {
        "core": 1000,
        "outer": 1500,
        "total": 2500
      }
    }
  }
}
```

### Error Response (422/500)

```json
{
  "success": false,
  "message": "size_tier is required for optimized galaxy creation",
  "error_code": "MISSING_SIZE_TIER",
  "data": {
    "valid_tiers": [
      {"value": "small", "label": "Small Galaxy (500×500)", ...},
      {"value": "medium", "label": "Medium Galaxy (1500×1500)", ...},
      {"value": "large", "label": "Large Galaxy (2500×2500)", ...}
    ]
  }
}
```

## Frontend Integration Guide

### 1. Galaxy Creation Form

Update the galaxy creation form to use the new endpoint:

```typescript
// Old endpoint (legacy)
// POST /api/galaxies/create-tiered

// New endpoint (optimized)
POST /api/galaxies/create
```

### 2. Size Tier Selection

```typescript
interface SizeTier {
  value: 'small' | 'medium' | 'large' | 'massive';
  label: string;
  outer_bounds: number;
  core_bounds: number;
  core_stars: number;
  outer_stars: number;
  total_stars: number;
  secret?: boolean;
}

// Fetch public tiers
const publicTiers = await fetch('/api/galaxies/size-tiers');

// To allow massive tier (admin/special users), add it manually:
const allTiers = [
  ...publicTiers,
  {
    value: 'massive',
    label: 'Massive Galaxy (5000×5000)',
    outer_bounds: 5000,
    core_bounds: 2500,
    core_stars: 1000,
    outer_stars: 1500,
    total_stars: 2500,
    secret: true
  }
];
```

### 3. Creation Progress Display

The response includes detailed metrics for each generation step. Use this for progress display:

```typescript
interface GeneratorMetrics {
  success: boolean;
  metrics: {
    elapsed_ms: number;
    elapsed_seconds: number;
    counts: Record<string, number>;
  };
  data?: Record<string, any>;
  error?: string;
}

// Display generation breakdown
const generators = response.data.metrics.generators;
Object.entries(generators).forEach(([name, data]) => {
  console.log(`${name}: ${data.metrics.elapsed_ms}ms`);
});
```

### 4. Expected Generation Times

| Tier | Expected Time | POIs Created |
|------|---------------|--------------|
| small | ~6-8s | ~4,000 |
| medium | ~15-20s | ~12,000 |
| large | ~25-35s | ~20,000 |
| massive | ~35-45s | ~10,000* |

*Massive tier has fewer POIs due to deferred moon generation.

### 5. Handling Long Requests

For larger galaxies, consider:

```typescript
// Set appropriate timeout (60+ seconds)
const response = await fetch('/api/galaxies/create-optimized', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify(payload),
  signal: AbortSignal.timeout(120000) // 2 minute timeout
});
```

### 6. Post-Creation Navigation

After successful creation, redirect to the galaxy:

```typescript
const result = await createGalaxy(payload);

if (result.success) {
  const galaxyUuid = result.data.galaxy.uuid;

  // Navigate to galaxy view
  router.push(`/galaxies/${galaxyUuid}`);

  // Or show statistics first
  showCreationSummary(result.data.statistics);
}
```

## Key Differences from Legacy Endpoint

| Feature | Legacy (`create-tiered`) | Optimized (`create-optimized`) |
|---------|--------------------------|--------------------------------|
| Speed | 80+ seconds (massive) | ~38 seconds (massive) |
| Response | Basic galaxy info | Full metrics + statistics |
| Moon generation | Synchronous | Deferred (async ready) |
| Precursor content | Separate step | Included in pipeline |
| Error detail | Basic message | Per-generator status |

## Galaxy Structure

### Core Region (Civilized Space)
- 100% inhabited systems
- Active warp gate network
- Trading hubs with all services
- Orbital defense platforms
- Full mineral deposits

### Outer Region (Frontier)
- 0% inhabited (colony candidates)
- Dormant warp gates (require activation)
- Rich mineral deposits (2x richness)
- Hidden precursor content
- No defenses (player must build)

### Precursor Content
- **Mirror Gate**: Hidden in outer region, requires sensor level 5 to detect
- **Precursor Ship**: Derelict vessel with valuable technology rewards

## Response Field Reference

### galaxy object
| Field | Type | Description |
|-------|------|-------------|
| id | integer | Database ID |
| uuid | string | UUID for API references |
| name | string | Galaxy display name |
| status | string | `active`, `draft`, `archived` |

### statistics object
| Field | Type | Description |
|-------|------|-------------|
| total_pois | integer | All points of interest |
| total_stars | integer | Star systems |
| core_stars | integer | Inhabited core systems |
| outer_stars | integer | Frontier systems |
| inhabited_systems | integer | Systems with civilization |
| fortified_systems | integer | Systems with defenses |
| warp_gates | integer | Total warp connections |
| active_gates | integer | Usable gates |
| dormant_gates | integer | Gates requiring activation |
| trading_hubs | integer | Commerce stations |

### metrics object
| Field | Type | Description |
|-------|------|-------------|
| total_elapsed_ms | float | Total generation time (ms) |
| total_elapsed_seconds | float | Total generation time (s) |
| generators | object | Per-generator breakdown |

---

## Additional Galaxy Endpoints

### Get Galaxy Map

```
GET /api/galaxies/{uuid}/map
```

**Authentication:** Optional (authenticated users see their player location)

Returns map data optimized for rendering the galaxy view.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxy map retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "name": "Andromeda Nexus",
      "width": 5000,
      "height": 5000
    },
    "systems": [
      {
        "uuid": "system-uuid-1",
        "name": "Alpha Centauri",
        "type": "star",
        "x": 2500,
        "y": 2500,
        "is_inhabited": true,
        "is_current_location": false
      }
    ],
    "warp_gates": [
      {
        "uuid": "gate-uuid-1",
        "from": { "x": 2500, "y": 2500 },
        "to": { "x": 2600, "y": 2400 },
        "is_mirror": false
      }
    ],
    "sectors": [
      {
        "uuid": "sector-uuid-1",
        "name": "Alpha-1",
        "x_min": 0,
        "x_max": 250,
        "y_min": 0,
        "y_max": 250,
        "danger_level": 0
      }
    ],
    "player_location": {
      "x": 2500,
      "y": 2500
    }
  }
}
```

**Notes:**
- `player_location` is `null` for unauthenticated requests
- `is_current_location` is set on systems for authenticated players
- Only visible systems are returned (based on star charts if player authenticated)

---

### Get Galaxy Statistics

```
GET /api/galaxies/{uuid}/statistics
```

**Authentication:** Not required

Returns comprehensive galaxy-wide statistics.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Galaxy statistics retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "name": "Andromeda Nexus",
      "dimensions": {
        "width": 5000,
        "height": 5000
      },
      "total_systems": 2500,
      "inhabited_systems": 1000
    },
    "players": {
      "total": 50,
      "active": 42,
      "destroyed": 3
    },
    "economy": {
      "total_credits_in_circulation": 50000000,
      "average_player_credits": 1190476,
      "trading_hubs": 1000
    },
    "colonies": {
      "total": 85,
      "total_population": 12500000,
      "average_development": 3.5
    },
    "combat": {
      "total_pvp_challenges": 156,
      "completed_battles": 89
    },
    "infrastructure": {
      "warp_gates": 5094,
      "sectors": 400,
      "pirate_fleets": 250
    }
  }
}
```

---

### Get Sector Information

```
GET /api/sectors/{uuid}
```

**Authentication:** Not required

Returns detailed information about a specific sector.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Sector information retrieved successfully",
  "data": {
    "uuid": "sector-uuid-1",
    "name": "Alpha-1",
    "galaxy": {
      "uuid": "galaxy-uuid",
      "name": "Andromeda Nexus"
    },
    "bounds": {
      "x_min": 0,
      "x_max": 250,
      "y_min": 0,
      "y_max": 250
    },
    "danger_level": 2,
    "statistics": {
      "total_systems": 12,
      "inhabited_systems": 5,
      "active_players": 3,
      "pirate_fleets": 2
    },
    "systems": [
      {
        "uuid": "system-uuid",
        "name": "Proxima",
        "type": "star",
        "x": 125,
        "y": 100,
        "is_inhabited": true
      }
    ]
  }
}
```

---

## Galaxy Membership

These endpoints manage player membership within galaxies.

### Check Player in Galaxy

```
GET /api/galaxies/{uuid}/my-player
```

**Authentication:** Required (Bearer token)

Check if the authenticated user has a player in a specific galaxy.

**Response (200 OK):** User has a player in this galaxy

```json
{
  "success": true,
  "message": "Player found",
  "data": {
    "uuid": "player-uuid-here",
    "call_sign": "StarCaptain",
    "credits": 15000,
    "experience": 2500,
    "level": 5,
    "status": "active",
    "galaxy": {
      "uuid": "galaxy-uuid",
      "name": "Andromeda Nexus"
    },
    "location": {
      "uuid": "poi-uuid",
      "name": "Alpha Centauri",
      "x": 2500,
      "y": 2500
    },
    "ship": {
      "uuid": "ship-uuid",
      "name": "StarCaptain's Scout",
      "class": "scout"
    }
  }
}
```

**Error Response (404 Not Found):** User has no player in this galaxy

```json
{
  "success": false,
  "message": "You do not have a player in this galaxy",
  "error": {
    "code": "NO_PLAYER_IN_GALAXY",
    "details": {
      "galaxy_uuid": "galaxy-uuid-here"
    }
  }
}
```

**Use Cases:**
- Check membership before showing galaxy-specific UI
- Determine whether to show "Join" or "Play" button
- Validate player context before API calls

---

### Join Galaxy

```
POST /api/galaxies/{uuid}/join
```

**Authentication:** Required (Bearer token)

**Idempotent endpoint** - Returns existing player or creates a new one. This is the recommended way to handle "create player if not exists" logic.

**Request Body:**

```json
{
  "call_sign": "StarCaptain"
}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `call_sign` | string | Conditional | Required only when creating a new player. Max 50 characters. |

**Response (200 OK):** Player already exists (returns existing player)

```json
{
  "success": true,
  "message": "Player already exists in this galaxy",
  "data": {
    "player": {
      "uuid": "player-uuid-here",
      "call_sign": "StarCaptain",
      "credits": 15000,
      "experience": 2500,
      "level": 5,
      "status": "active",
      "galaxy": {
        "uuid": "galaxy-uuid",
        "name": "Andromeda Nexus"
      },
      "location": {
        "uuid": "poi-uuid",
        "name": "Alpha Centauri",
        "x": 2500,
        "y": 2500
      },
      "ship": {
        "uuid": "ship-uuid",
        "name": "StarCaptain's Scout",
        "class": "scout"
      }
    },
    "created": false
  }
}
```

**Response (201 Created):** New player created

```json
{
  "success": true,
  "message": "Successfully joined galaxy",
  "data": {
    "player": {
      "uuid": "new-player-uuid",
      "call_sign": "NewPilot",
      "credits": 10000,
      "experience": 0,
      "level": 1,
      "status": "active",
      "galaxy": {
        "uuid": "galaxy-uuid",
        "name": "Andromeda Nexus"
      },
      "location": {
        "uuid": "poi-uuid",
        "name": "Starting System",
        "x": 1500,
        "y": 1500
      },
      "ship": {
        "uuid": "ship-uuid",
        "name": "NewPilot's Scout",
        "class": "scout"
      }
    },
    "created": true
  }
}
```

**Error Responses:**

| Status | Code | Description |
|--------|------|-------------|
| 400 | `GALAXY_NOT_ACTIVE` | Galaxy is not accepting new players (draft/archived) |
| 400 | `GALAXY_FULL` | Galaxy has reached maximum player capacity |
| 403 | `SINGLE_PLAYER_GALAXY` | Non-owner trying to join a single-player galaxy |
| 422 | `DUPLICATE_CALL_SIGN` | Call sign already taken in this galaxy |
| 422 | Validation | Missing or invalid `call_sign` when creating |
| 500 | `NO_STARTING_LOCATION` | No inhabited systems to spawn player |

**Example Error (Galaxy Full):**

```json
{
  "success": false,
  "message": "Galaxy has reached maximum player capacity",
  "error": {
    "code": "GALAXY_FULL",
    "details": {
      "max_players": 100,
      "current_players": 100
    }
  }
}
```

**Example Error (Duplicate Call Sign):**

```json
{
  "success": false,
  "message": "Call sign already exists in this galaxy",
  "error": {
    "code": "DUPLICATE_CALL_SIGN"
  }
}
```

### Frontend Integration

```typescript
interface JoinGalaxyResponse {
  player: Player;
  created: boolean;
}

/**
 * Join a galaxy - idempotent operation
 * Returns existing player or creates new one
 */
async function joinGalaxy(
  galaxyUuid: string,
  callSign: string,
  token: string
): Promise<JoinGalaxyResponse> {
  const response = await fetch(`/api/galaxies/${galaxyUuid}/join`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ call_sign: callSign }),
  });

  const result = await response.json();

  if (!result.success) {
    throw new Error(result.error?.code || 'JOIN_FAILED');
  }

  return result.data;
}

// Usage example
async function handlePlayButton(galaxyUuid: string, callSign: string) {
  try {
    const { player, created } = await joinGalaxy(galaxyUuid, callSign, token);

    if (created) {
      showToast('Welcome to the galaxy, pilot!');
    } else {
      showToast('Welcome back, ' + player.call_sign + '!');
    }

    // Navigate to game view
    router.push(`/game/${galaxyUuid}`);

  } catch (error) {
    switch (error.message) {
      case 'GALAXY_FULL':
        showError('This galaxy is full. Try another one.');
        break;
      case 'DUPLICATE_CALL_SIGN':
        showError('That call sign is taken. Choose another.');
        break;
      case 'SINGLE_PLAYER_GALAXY':
        showError('This is a private single-player galaxy.');
        break;
      default:
        showError('Failed to join galaxy.');
    }
  }
}
```

### Idempotency

The `/join` endpoint is idempotent:
- Calling it multiple times with different `call_sign` values returns the same player
- The `call_sign` is only used on first creation
- Safe to call on every "Play" button click without duplicate player creation

```typescript
// First call - creates player with "Pilot1"
const result1 = await joinGalaxy(uuid, "Pilot1", token);
// result1.created === true, result1.player.call_sign === "Pilot1"

// Second call - returns existing player, ignores new call_sign
const result2 = await joinGalaxy(uuid, "Pilot2", token);
// result2.created === false, result2.player.call_sign === "Pilot1"
```

---

## Quick Reference

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/galaxies` | GET | **Yes** | User's games + open games (dehydrated) |
| `/api/galaxies/list` | GET | **Yes** | Same as above (cached open_games) |
| `/api/galaxies/{uuid}` | GET | No | Galaxy details (hydrated) |
| `/api/galaxies/{uuid}/map` | GET | Optional | Galaxy map data |
| `/api/galaxies/{uuid}/statistics` | GET | No | Galaxy statistics |
| `/api/galaxies/{uuid}/my-player` | GET | **Yes** | Check if user has player in galaxy |
| `/api/galaxies/{uuid}/join` | POST | **Yes** | Join galaxy (get or create player) |
| `/api/galaxies/create` | POST | **Yes** | Create new galaxy |
| `/api/galaxies/size-tiers` | GET | **Yes** | Get available size tiers |
| `/api/sectors/{uuid}` | GET | No | Sector information |

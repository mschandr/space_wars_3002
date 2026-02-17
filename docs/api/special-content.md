# Special Content API

API endpoints for accessing high-risk/high-reward special content: Mirror Universe gates, Precursor ship rumors, facilities (bars, cartographers, salvage yards), and upgrade plan shops.

---

## Mirror Universe

The Mirror Universe is an ultra-rare parallel dimension accessible through special warp gates. Only one mirror gate exists per galaxy, requiring sensor level 5 to detect. Offers 2x resource rewards but 2x pirate difficulty, with a 24-hour cooldown between travels.

### GET /api/galaxies/{uuid}/mirror-gate

Get the location of the mirror gate in a specific galaxy.

#### Authentication
Not required (public endpoint)

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the galaxy |

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Mirror gate location retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Quadrant"
    },
    "mirror_gate": {
      "uuid": "660e8400-e29b-41d4-a716-446655440000",
      "location": {
        "poi_uuid": "770e8400-e29b-41d4-a716-446655440000",
        "name": "Abandoned Starbase Theta",
        "coordinates": {
          "x": 245.7,
          "y": 189.3
        }
      },
      "destination": {
        "poi_uuid": "880e8400-e29b-41d4-a716-446655440000",
        "name": "Mirror Starbase Theta",
        "coordinates": {
          "x": 245.7,
          "y": 189.3
        }
      }
    },
    "requirements": {
      "sensor_level": 5,
      "cooldown_hours": 24
    },
    "warnings": {
      "increased_pirate_difficulty": true,
      "resource_rewards": "Doubled",
      "cannot_return_immediately": true
    }
  }
}
```

**Field Descriptions:**
- `galaxy.uuid` - UUID of the galaxy
- `galaxy.name` - Name of the galaxy
- `mirror_gate.uuid` - UUID of the warp gate object
- `mirror_gate.location.poi_uuid` - UUID of the POI where gate entrance is located
- `mirror_gate.location.name` - Name of the entry point location
- `mirror_gate.location.coordinates.x` - X coordinate of entry gate
- `mirror_gate.location.coordinates.y` - Y coordinate of entry gate
- `mirror_gate.destination` - Mirror universe destination (may be null if gate is one-way)
- `mirror_gate.destination.poi_uuid` - UUID of mirror universe POI
- `mirror_gate.destination.name` - Name of destination in mirror universe
- `mirror_gate.destination.coordinates` - Coordinates in mirror space
- `requirements.sensor_level` - Minimum sensor level required to detect/use gate (from config)
- `requirements.cooldown_hours` - Hours between allowed mirror travels (from config)
- `warnings` - Array of cautionary notes about mirror universe risks

#### Error Responses

**404 Not Found** - Galaxy exists but has no mirror gate
```json
{
  "success": false,
  "message": "No mirror gate exists in this galaxy",
  "error_code": "MIRROR_GATE_NOT_FOUND"
}
```

**404 Not Found** - Galaxy with specified UUID does not exist
```json
{
  "success": false,
  "message": "Not found",
  "error_code": "NOT_FOUND"
}
```

#### Warnings & Caveats
- Only one mirror gate exists per galaxy
- Gate type must be `GateType::MIRROR_ENTRY` enum value
- Requirements pulled from `config('game_config.mirror_universe')` - may vary per deployment
- Destination can be null for one-way gates
- Coordinates are float values, not integers

---

### GET /api/players/{uuid}/mirror-access

Check if a specific player can access the mirror universe based on their ship's sensors and cooldown status.

#### Authentication
Required (player must own this UUID or be admin)

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Mirror universe access information retrieved",
  "data": {
    "player": {
      "uuid": "950e8400-e29b-41d4-a716-446655440000",
      "call_sign": "Starblazer"
    },
    "access": {
      "has_sufficient_sensors": true,
      "required_sensor_level": 5,
      "current_sensor_level": 6,
      "can_travel": false,
      "cooldown_remaining_hours": 12,
      "next_available_at": "2026-02-17T14:30:00+00:00"
    },
    "mirror_gate": {
      "uuid": "660e8400-e29b-41d4-a716-446655440000",
      "location": {
        "poi_uuid": "770e8400-e29b-41d4-a716-446655440000",
        "name": "Abandoned Starbase Theta",
        "x": 245.7,
        "y": 189.3
      },
      "is_at_gate": false
    },
    "mirror_modifiers": {
      "required_sensor_level": 5,
      "cooldown_hours": 24,
      "resource_multiplier": 2.0,
      "pirate_difficulty_multiplier": 2.0
    }
  }
}
```

**Field Descriptions:**
- `player.uuid` - Player's unique identifier
- `player.call_sign` - Player's call sign
- `access.has_sufficient_sensors` - Boolean: player's ship meets sensor requirement
- `access.required_sensor_level` - Minimum sensor level needed (from config)
- `access.current_sensor_level` - Player's active ship's sensor level
- `access.can_travel` - Boolean: true only if sensors sufficient AND cooldown expired
- `access.cooldown_remaining_hours` - Hours remaining until next travel allowed (0 if ready)
- `access.next_available_at` - ISO 8601 timestamp when travel will be available (null if ready now)
- `mirror_gate` - Gate location details (null if no gate exists in galaxy)
- `mirror_gate.uuid` - UUID of the warp gate
- `mirror_gate.location.poi_uuid` - UUID of gate's POI
- `mirror_gate.location.name` - Name of gate location
- `mirror_gate.location.x` - X coordinate
- `mirror_gate.location.y` - Y coordinate
- `mirror_gate.is_at_gate` - Boolean: true if player is currently at the gate POI
- `mirror_modifiers` - Full mirror universe configuration from game config

#### Error Responses

**400 Bad Request** - Player has no active ship
```json
{
  "success": false,
  "message": "No active ship",
  "error_code": "NO_ACTIVE_SHIP"
}
```

**404 Not Found** - Player UUID does not exist

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- Requires player to have `activeShip` relationship loaded
- Uses `abs()` on `diffInHours()` to handle edge case of negative time differences
- Cooldown calculation: `max(0, cooldownHours - hoursSinceTravel)`
- `next_available_at` only present if cooldown is active (null otherwise)
- `mirror_gate` will be null if galaxy has no mirror gate
- Player's `last_mirror_travel_at` timestamp may be null if never traveled

---

### POST /api/players/{uuid}/mirror/enter

Attempt to enter the mirror universe through the mirror gate.

#### Authentication
Required (player must own this UUID or be admin)

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Request Body
No body required (POST with empty body)

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Entered mirror universe successfully",
  "data": {
    "travel_result": {
      "success": true,
      "from": {
        "uuid": "770e8400-e29b-41d4-a716-446655440000",
        "name": "Abandoned Starbase Theta"
      },
      "to": {
        "uuid": "880e8400-e29b-41d4-a716-446655440000",
        "name": "Mirror Starbase Theta"
      },
      "fuel_consumed": 15,
      "xp_gained": 500
    },
    "message": "Successfully entered the mirror universe",
    "warnings": {
      "doubled_pirate_difficulty": true,
      "doubled_resources": true,
      "return_cooldown_active": true,
      "next_available_return": "2026-02-17T14:30:00+00:00"
    }
  }
}
```

**Field Descriptions:**
- `travel_result` - Result object from `TravelService::executeTravel()`
- `travel_result.success` - Boolean indicating successful travel
- `travel_result.from.uuid` - UUID of origin POI
- `travel_result.from.name` - Name of origin location
- `travel_result.to.uuid` - UUID of destination POI in mirror universe
- `travel_result.to.name` - Name of destination in mirror universe
- `travel_result.fuel_consumed` - Fuel units consumed by travel
- `travel_result.xp_gained` - Experience points awarded for travel
- `message` - Success confirmation text
- `warnings.doubled_pirate_difficulty` - Always true (2x pirate strength in mirror)
- `warnings.doubled_resources` - Always true (2x resource spawns in mirror)
- `warnings.return_cooldown_active` - Always true (cannot immediately return)
- `warnings.next_available_return` - ISO 8601 timestamp when return travel available

#### Error Responses

**400 Bad Request** - Player has no active ship
```json
{
  "success": false,
  "message": "No active ship",
  "error_code": "NO_ACTIVE_SHIP"
}
```

**400 Bad Request** - Insufficient sensor level
```json
{
  "success": false,
  "message": "Insufficient sensor level. Required: 5, Current: 3",
  "error_code": "INSUFFICIENT_SENSORS"
}
```

**400 Bad Request** - Cooldown still active
```json
{
  "success": false,
  "message": "Mirror universe cooldown active. Can travel again in 12 hours",
  "error_code": "COOLDOWN_ACTIVE"
}
```

**400 Bad Request** - Not at mirror gate
```json
{
  "success": false,
  "message": "You must be at the mirror gate to enter the mirror universe",
  "error_code": "NOT_AT_GATE"
}
```

**404 Not Found** - No mirror gate exists in galaxy (from `firstOrFail()`)

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- **IMPORTANT**: Player's `last_mirror_travel_at` is updated AFTER successful travel
- Cooldown prevents BOTH entry and exit for 24 hours (bi-directional restriction)
- Travel consumes fuel based on `TravelService` calculations
- Player must be at exact POI of mirror gate (`current_poi_id === source_poi_id`)
- Sensor requirement and cooldown pulled from `config('game_config.mirror_universe')`
- Uses `abs(now()->diffInHours())` to avoid negative cooldown edge cases
- Successful travel may trigger pirate encounters (handled by TravelService)

---

## Precursor Ship Rumors

Ship yards across the galaxy have rumors about the legendary Precursor ship's location. Every ship yard "knows" where it is. Every ship yard is wrong. Players can bribe ship yard owners for their incorrect location information.

### GET /api/players/{uuid}/precursor/check

Check if the current location has a ship yard with Precursor rumor available.

#### Authentication
Required

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Success Response

**Status Code:** `200 OK`

**Response Structure (at trading hub with rumor):**
```json
{
  "success": true,
  "data": {
    "has_trading_hub": true,
    "trading_hub_name": "Starport Alpha",
    "has_shipyard": true,
    "has_rumor": true,
    "already_obtained": false,
    "bribe_cost": 5000,
    "owner_name": "Rusty McShipface",
    "can_afford": true
  }
}
```

**Response Structure (no trading hub):**
```json
{
  "success": true,
  "data": {
    "has_trading_hub": false,
    "has_shipyard": false,
    "has_rumor": false
  }
}
```

**Field Descriptions:**
- `has_trading_hub` - Boolean: true if player's current POI has a trading hub
- `trading_hub_name` - Name of trading hub (only if has_trading_hub is true)
- `has_shipyard` - Boolean: true if trading hub has ship yard facility
- `has_rumor` - Boolean: true if ship yard owner has Precursor rumor available
- `already_obtained` - Boolean: true if player already obtained this specific rumor
- `bribe_cost` - Credits required to obtain rumor (null if no rumor)
- `owner_name` - Ship yard owner's name (null if no shipyard)
- `can_afford` - Boolean: true if player has enough credits (null if no rumor)

#### Error Responses

**404 Not Found** - Player UUID does not exist

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- Returns success with `has_trading_hub: false` when not at trading hub (not an error)
- `bribe_cost` varies per trading hub (generated/stored in trading_hub table)
- `already_obtained` checks `player_precursor_rumors` pivot table
- Uses `TradingHub::hasPrecursorRumor()` and `playerHasRumor()` methods

---

### GET /api/players/{uuid}/precursor/rumors

Get all Precursor rumors the player has collected across all ship yards.

#### Authentication
Required

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "rumors": [
      {
        "trading_hub_uuid": "aa0e8400-e29b-41d4-a716-446655440000",
        "trading_hub_name": "Starport Alpha",
        "owner_name": "Rusty McShipface",
        "rumor_text": "I heard from a reliable source that the Precursor ship is hidden near the Vega system...",
        "coordinates": {
          "x": 123.5,
          "y": 456.7
        },
        "bribe_paid": 5000,
        "obtained_at": "2026-02-15T10:30:00+00:00"
      }
    ],
    "total_rumors": 1,
    "total_invested": 5000,
    "hint": "Each ship yard believes they know where the Precursor ship is hidden. None of them are right... but comparing their stories might help narrow it down."
  }
}
```

**Field Descriptions:**
- `rumors` - Array of rumor objects player has obtained
- `rumors[].trading_hub_uuid` - UUID of trading hub where rumor was obtained
- `rumors[].trading_hub_name` - Name of trading hub
- `rumors[].owner_name` - Ship yard owner's name
- `rumors[].rumor_text` - Flavor text about the rumored location
- `rumors[].coordinates` - Incorrect coordinates where owner claims ship is located
- `rumors[].bribe_paid` - Credits player paid for this rumor
- `rumors[].obtained_at` - ISO 8601 timestamp when rumor was purchased
- `total_rumors` - Count of rumors collected
- `total_invested` - Sum of all bribes paid across all rumors
- `hint` - Context-aware hint (different message if no rumors collected yet)

#### Error Responses

**404 Not Found** - Player UUID does not exist

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- Returns empty array if no rumors collected (not an error)
- Rumors are retrieved via `PrecursorRumorService::getPlayerRumors()`
- All rumors are intentionally incorrect - this is a treasure hunt mechanic
- `total_invested` uses `sum('bribe_paid')` on collection

---

### GET /api/players/{uuid}/precursor/gossip

Get free gossip about the Precursor ship at the current location. Provides flavor text without revealing coordinates.

#### Authentication
Required

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "gossip": "The bartender leans in and whispers: 'You want to know about the Precursor ship? Old Rusty over at the shipyard claims he knows something... but it'll cost you.'",
    "has_rumor": true,
    "bribe_cost": 5000,
    "owner_name": "Rusty McShipface",
    "already_obtained": false
  }
}
```

**Field Descriptions:**
- `gossip` - Free flavor text hinting at Precursor legend (generated by `PrecursorRumorService::getShipyardGossip()`)
- `has_rumor` - Boolean: true if this location has bribeable rumor
- `bribe_cost` - Credits required to obtain full rumor (null if no rumor)
- `owner_name` - Ship yard owner's name
- `already_obtained` - Boolean: true if player already bought this rumor

#### Error Responses

**400 Bad Request** - Not at a trading hub
```json
{
  "success": false,
  "message": "You must be at a trading hub to hear gossip about the Precursor ship."
}
```

**404 Not Found** - Player UUID does not exist

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- Gossip is free (no cost or transaction)
- Gossip text is procedurally generated based on location context
- Must be at trading hub to receive gossip (error if not)
- Does not reveal coordinates - only hints at rumor availability

---

### POST /api/players/{uuid}/precursor/bribe

Bribe the ship yard owner at current location to obtain their Precursor rumor.

#### Authentication
Required

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Request Body
No body required (POST with empty body)

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Rumor obtained successfully",
  "data": {
    "rumor": {
      "trading_hub_uuid": "aa0e8400-e29b-41d4-a716-446655440000",
      "trading_hub_name": "Starport Alpha",
      "owner_name": "Rusty McShipface",
      "rumor_text": "The Precursor ship is definitely hidden in the Vega sector. I saw it with my own eyes!",
      "coordinates": {
        "x": 123.5,
        "y": 456.7
      },
      "obtained_at": "2026-02-16T14:22:00+00:00"
    },
    "bribe_paid": 5000,
    "remaining_credits": 45000,
    "message": "The ship yard owner pockets your credits and leans in conspiratorially..."
  }
}
```

**Field Descriptions:**
- `rumor` - Full rumor object obtained
- `rumor.trading_hub_uuid` - UUID of trading hub
- `rumor.trading_hub_name` - Name of trading hub
- `rumor.owner_name` - Ship yard owner's name
- `rumor.rumor_text` - Flavor text about the location
- `rumor.coordinates.x` - X coordinate of rumored location (INCORRECT)
- `rumor.coordinates.y` - Y coordinate of rumored location (INCORRECT)
- `rumor.obtained_at` - ISO 8601 timestamp of purchase
- `bribe_paid` - Credits deducted from player
- `remaining_credits` - Player's credits after transaction
- `message` - Flavor text for successful bribe

#### Error Responses

**400 Bad Request** - Not at a trading hub
```json
{
  "success": false,
  "message": "You must be at a trading hub to bribe the ship yard owner."
}
```

**400 Bad Request** - Insufficient credits
```json
{
  "success": false,
  "message": "Insufficient credits to bribe owner",
  "error_code": "BRIBE_FAILED"
}
```

**409 Conflict** - Already obtained this rumor
```json
{
  "success": false,
  "message": "You already obtained this rumor",
  "error_code": "BRIBE_FAILED"
}
```

**404 Not Found** - Player UUID does not exist

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- **Transaction is atomic**: credits deducted and rumor granted together
- Uses `PrecursorRumorService::bribeForRumor()` for business logic
- Rumor coordinates are ALWAYS INCORRECT (by design)
- Each ship yard can only be bribed once per player
- No partial refunds - if bribe fails after payment, transaction is rolled back
- Service returns `['success' => false, 'error' => 'message']` on failure

---

## Facilities & Services

Unified API for discovering and interacting with facilities (trading hubs, ship yards, bars, cartographers, salvage yards) in star systems.

### GET /api/players/{uuid}/facilities

List all facilities available in the player's current star system, categorized by type.

#### Authentication
Required

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Facilities retrieved",
  "data": {
    "system": {
      "uuid": "bb0e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Centauri",
      "is_inhabited": true
    },
    "facilities": {
      "trading_hubs": [
        {
          "uuid": "cc0e8400-e29b-41d4-a716-446655440000",
          "name": "Starport Alpha",
          "type": "trading_post",
          "location": "Main System Hub",
          "services": ["trading", "refueling", "repairs"],
          "has_cartographer": true,
          "has_salvage_yard": false,
          "actions": {
            "trade": "/api/players/950e8400-e29b-41d4-a716-446655440000/trading",
            "inventory": "/api/players/950e8400-e29b-41d4-a716-446655440000/trading/inventory"
          }
        }
      ],
      "shipyards": [
        {
          "uuid": "dd0e8400-e29b-41d4-a716-446655440000",
          "name": "Alpha Shipworks",
          "type": "shipyard",
          "type_label": "Shipyard",
          "orbital_index": 3,
          "is_inhabited": true,
          "actions": {
            "browse_ships": "/api/players/950e8400-e29b-41d4-a716-446655440000/ship-shop",
            "buy_ship": "/api/players/950e8400-e29b-41d4-a716-446655440000/ship-shop/purchase",
            "repairs": "/api/players/950e8400-e29b-41d4-a716-446655440000/ship-services/repair",
            "upgrades": "/api/players/950e8400-e29b-41d4-a716-446655440000/upgrades"
          }
        }
      ],
      "salvage_yards": [],
      "cartographers": [
        {
          "uuid": "cc0e8400-e29b-41d4-a716-446655440000",
          "name": "Stellar Cartographer",
          "location": "Starport Alpha",
          "actions": {
            "browse": "/api/players/950e8400-e29b-41d4-a716-446655440000/cartography/available",
            "purchase": "/api/players/950e8400-e29b-41d4-a716-446655440000/cartography/purchase"
          }
        }
      ],
      "bars": [
        {
          "id": 1,
          "name": "The Nebula's Edge",
          "location": "Main Trading Hub",
          "atmosphere": "Smoky and dimly lit",
          "actions": {
            "visit": "/api/players/950e8400-e29b-41d4-a716-446655440000/facilities/bar"
          }
        }
      ],
      "trading_stations": [],
      "defense_platforms": [],
      "summary": {
        "total_trading_hubs": 1,
        "total_trading_stations": 0,
        "total_shipyards": 1,
        "total_salvage_yards": 0,
        "total_cartographers": 1,
        "total_bars": 1,
        "total_defense_platforms": 0,
        "has_trading": true,
        "has_ship_services": true,
        "has_salvage": false,
        "has_cartography": true,
        "has_bar": true
      },
      "available_actions": [
        {
          "id": "trading",
          "label": "Trading Hub",
          "description": "Buy and sell commodities",
          "endpoint": "/api/players/950e8400-e29b-41d4-a716-446655440000/trading",
          "icon": "trading"
        },
        {
          "id": "bar",
          "label": "Bar",
          "description": "Hear rumors and local gossip",
          "endpoint": "/api/players/950e8400-e29b-41d4-a716-446655440000/facilities/bar",
          "icon": "bar"
        }
      ]
    }
  }
}
```

**Field Descriptions:**
- `system.uuid` - UUID of the star system player is in
- `system.name` - Name of the star system
- `system.is_inhabited` - Boolean: whether system is inhabited (affects available facilities)
- `facilities.trading_hubs` - Array of main trading hubs (attached to star)
- `facilities.shipyards` - Array of orbital shipyard facilities
- `facilities.salvage_yards` - Array of salvage yard facilities
- `facilities.cartographers` - Array of star chart vendors
- `facilities.bars` - Array of bars (every inhabited system has at least one)
- `facilities.trading_stations` - Array of orbital trading stations
- `facilities.defense_platforms` - Array of automated defense systems
- `summary` - Aggregate counts and boolean flags
- `available_actions` - UI-friendly action list with icons and descriptions

**Facility Object Fields:**
- `uuid` - Unique identifier (for POI-based facilities)
- `name` - Display name
- `type` - Internal type identifier
- `type_label` - Human-readable type label
- `location` - Description of where facility is located
- `orbital_index` - Position in orbital ring (for orbital facilities)
- `services` - Array of service type strings
- `has_cartographer` - Boolean (for trading hubs)
- `has_salvage_yard` - Boolean (for trading hubs)
- `actions` - Object mapping action names to API endpoints

#### Error Responses

**400 Bad Request** - Player has no current location
```json
{
  "success": false,
  "message": "Player has no current location",
  "error_code": "NO_LOCATION"
}
```

**400 Bad Request** - Could not determine star system
```json
{
  "success": false,
  "message": "Could not determine star system",
  "error_code": "NO_SYSTEM"
}
```

**404 Not Found** - Player UUID does not exist

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- Uses `ParentStarResolver::resolve()` to find parent star system (handles orbital mechanics)
- Only inhabited systems have bars
- Trading hubs can have embedded services (cartographer, salvage yard)
- Shipyards, salvage yards, and trading stations are separate orbital POIs
- Defense platforms are not directly interactable (passive security)
- Bar names generated by `BarNameGenerator::generate()` using system seed
- Empty arrays returned for facility types not present in system
- Action endpoints include player UUID for direct API calls

---

### GET /api/players/{uuid}/facilities/bar

Get detailed bar information and current rumors for player's current system.

#### Authentication
Required

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the player |

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "message": "Welcome to the bar",
  "data": {
    "system": {
      "uuid": "bb0e8400-e29b-41d4-a716-446655440000",
      "name": "Alpha Centauri"
    },
    "bar": {
      "name": "The Nebula's Edge",
      "atmosphere": "Smoky and dimly lit",
      "patrons": 18
    },
    "rumors": [
      {
        "id": 1,
        "type": "pirate_activity",
        "text": "I heard the Crimson Raiders are planning something big near the outer rim...",
        "reliability": "low",
        "source": "Drunk miner"
      },
      {
        "id": 2,
        "type": "trade_opportunity",
        "text": "Word is the mining colony on Proxima IV is paying top dollar for medical supplies.",
        "reliability": "high",
        "source": "Merchant captain"
      }
    ],
    "tip": "The reliability of rumors varies. Confirmed intel from trusted sources is more valuable than bar gossip."
  }
}
```

**Field Descriptions:**
- `system.uuid` - UUID of star system
- `system.name` - Name of star system
- `bar.name` - Procedurally generated bar name (based on system)
- `bar.atmosphere` - Flavor text describing bar ambiance
- `bar.patrons` - Random number of NPCs present (5-30)
- `rumors` - Array of rumor objects from `BarRumorService`
- `rumors[].id` - Rumor identifier
- `rumors[].type` - Category of rumor (pirate_activity, trade_opportunity, etc.)
- `rumors[].text` - Rumor content
- `rumors[].reliability` - Quality indicator (low, medium, high)
- `rumors[].source` - NPC source description
- `tip` - Meta-game hint about rumor mechanics

#### Error Responses

**400 Bad Request** - Player has no current location
```json
{
  "success": false,
  "message": "Player has no current location",
  "error_code": "NO_LOCATION"
}
```

**400 Bad Request** - System is not inhabited (no bar)
```json
{
  "success": false,
  "message": "No bar available in this system",
  "error_code": "NO_BAR"
}
```

**404 Not Found** - Player UUID does not exist

**401 Unauthorized** - User does not own this player

#### Warnings & Caveats
- Only inhabited systems have bars
- Bar name is deterministic based on system (same name on repeat visits)
- Patron count is random each request (not persistent)
- Atmosphere randomly selected via `BarNameGenerator::randomAtmosphere()`
- Rumors generated by `BarRumorService::getRumors()` - may be context-aware
- Rumor reliability affects game mechanics elsewhere (not enforced in this endpoint)

---

## Upgrade Plans Shop

Rare upgrade plans allow players to exceed normal component upgrade caps. Plans are sold at select trading hubs.

### GET /api/trading-hubs/{uuid}/plans-shop

Check if a trading hub sells upgrade plans and list available plans.

#### Authentication
Optional (enriched data if authenticated)

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| uuid | string (UUID) | Yes | UUID of the trading hub |

#### Success Response (has plans)

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "has_plans_shop": true,
    "trading_hub_name": "Starport Alpha",
    "available_plans": [
      {
        "plan": {
          "id": 1,
          "name": "Advanced Hull Reinforcement",
          "full_name": "Hull Advanced Hull Reinforcement",
          "component": "hull",
          "component_display_name": "Hull",
          "description": "Allows hull upgrades beyond standard limits",
          "additional_levels": 2,
          "price": 50000,
          "rarity": "rare",
          "requirements": {
            "min_level": 10
          }
        },
        "owned_count": 1,
        "current_bonus": 2,
        "projected_bonus": 4
      }
    ]
  }
}
```

**Response Structure (no plans):**
```json
{
  "success": true,
  "data": {
    "has_plans_shop": false,
    "available_plans": []
  }
}
```

**Field Descriptions:**
- `has_plans_shop` - Boolean: true if trading hub sells plans
- `trading_hub_name` - Name of trading hub (only if has plans)
- `available_plans` - Array of plan objects with ownership info
- `plan.id` - Database ID of the plan
- `plan.name` - Short plan name
- `plan.full_name` - Full name including component prefix
- `plan.component` - Component type (hull, shields, weapons, cargo, warp_drive, sensors)
- `plan.component_display_name` - Human-readable component name
- `plan.description` - Flavor text describing plan
- `plan.additional_levels` - Number of extra upgrade levels granted
- `plan.price` - Cost in credits
- `plan.rarity` - Rarity tier (common, uncommon, rare, epic, legendary)
- `plan.requirements` - Object of requirements (e.g., min_level)
- `owned_count` - Number of times player owns this plan (0 if not authenticated)
- `current_bonus` - Current total bonus levels from owned plans
- `projected_bonus` - Total bonus if player purchases this plan

#### Error Responses

**404 Not Found** - Trading hub UUID does not exist
```json
{
  "success": false,
  "message": "Trading hub not found"
}
```

#### Warnings & Caveats
- Trading hub must have `has_plans` flag set to true
- Plans are linked via many-to-many relationship (`trading_hub_plans` pivot)
- Player ownership calculated via `player_plans` pivot table
- Unauthenticated requests show `owned_count: 0` for all plans
- Multiple purchases of same plan stack (`owned_count > 1` possible)
- Projected bonus calculation: `(owned_count + 1) * additional_levels`

---

### GET /api/plans/catalog

Browse full catalog of all available upgrade plans with optional filters.

#### Authentication
Not required (public catalog)

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| component | string | No | Filter by component type (hull, shields, weapons, cargo, warp_drive, sensors) |
| rarity | string | No | Filter by rarity (common, uncommon, rare, epic, legendary) |
| min_price | integer | No | Minimum price in credits |
| max_price | integer | No | Maximum price in credits |

#### Success Response

**Status Code:** `200 OK`

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "plans": [
      {
        "id": 1,
        "name": "Advanced Hull Reinforcement",
        "full_name": "Hull Advanced Hull Reinforcement",
        "component": "hull",
        "component_display_name": "Hull",
        "description": "Allows hull upgrades beyond standard limits",
        "additional_levels": 2,
        "price": 50000,
        "rarity": "rare",
        "requirements": {
          "min_level": 10
        }
      },
      {
        "id": 2,
        "name": "Quantum Shield Harmonics",
        "full_name": "Shields Quantum Shield Harmonics",
        "component": "shields",
        "component_display_name": "Shields",
        "description": "Experimental shield technology from the outer rim",
        "additional_levels": 3,
        "price": 120000,
        "rarity": "epic",
        "requirements": {
          "min_level": 15,
          "faction_reputation": "friendly"
        }
      }
    ],
    "total_count": 2
  }
}
```

**Field Descriptions:**
- `plans` - Array of all plan objects matching filters
- `total_count` - Number of plans in result set
- (See plan field descriptions in previous endpoint)

#### Error Responses
No specific errors - invalid filters result in empty array

#### Warnings & Caveats
- Returns all plans if no filters specified
- Results ordered by `component` then `price` (ascending)
- Filter parameters use exact match (case-sensitive for component/rarity)
- Price filters are inclusive (>= min_price, <= max_price)
- Does NOT show which trading hubs sell each plan
- Does NOT show player ownership (use `/trading-hubs/{uuid}/plans-shop` for that)
- Requirements object structure varies by plan (not standardized schema)

---

## Summary Notes

### Authentication Pattern
- **Public endpoints**: Galaxy/faction data, mirror gate locations, plans catalog
- **Player-scoped endpoints**: All player-specific actions require authentication
- **Authorization**: Controller uses `authorizePlayer()` to verify ownership

### Common Error Codes
- `NO_ACTIVE_SHIP` - Player must have active ship equipped
- `NO_LOCATION` - Player has no current POI assigned
- `NO_SYSTEM` - Cannot resolve parent star system
- `NOT_AT_GATE` - Player not at required warp gate POI
- `INSUFFICIENT_SENSORS` - Ship sensors below requirement
- `COOLDOWN_ACTIVE` - Time-based restriction active
- `BRIBE_FAILED` - Payment or eligibility failure

### Configuration Dependencies
All special content mechanics pull from `config/game_config.php`:
- `mirror_universe.required_sensor_level` (default: 5)
- `mirror_universe.cooldown_hours` (default: 24)
- `mirror_universe.resource_multiplier` (default: 2.0)
- `mirror_universe.pirate_difficulty_multiplier` (default: 2.0)

### Performance Notes
- Mirror gate queries use `where('gate_type', GateType::MIRROR_ENTRY)` on indexed column
- Pirate reputation endpoint queries entire `combat_sessions` table (no pagination)
- Facilities endpoint loads multiple relationships (may be slow in large systems)
- Bar rumor generation is real-time (not cached)

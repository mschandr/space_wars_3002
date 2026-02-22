# Trading API Reference

This document covers all trading, mineral, cargo, and market event endpoints for Space Wars 3002.

## Table of Contents

- [Minerals](#minerals)
  - [GET /api/minerals](#get-apiminerals)
- [Trading Hubs](#trading-hubs)
  - [GET /api/trading-hubs](#get-apitrading-hubs)
  - [GET /api/trading-hubs/{uuid}](#get-apitrading-hubsuuid)
  - [GET /api/trading-hubs/{uuid}/inventory](#get-apitrading-hubsuuidinventory)
- [Trading Transactions](#trading-transactions)
  - [POST /api/trading-hubs/{uuid}/buy](#post-apitrading-hubsuuidbuy)
  - [POST /api/trading-hubs/{uuid}/sell](#post-apitrading-hubsuuidsell)
  - [GET /api/players/{uuid}/cargo](#get-apiplayersuuidcargo)
  - [GET /api/trading/affordability](#get-apitradingaffordability)
- [Market Events](#market-events)
  - [GET /api/trading-hubs/{uuid}/active-events](#get-apitrading-hubsuuidactive-events)
  - [GET /api/galaxies/{galaxyUuid}/market-events](#get-apigalaxiesgalaxyuuidmarket-events)
  - [GET /api/market-events/{eventUuid}](#get-apimarket-eventseventuuid)

---

## Minerals

### GET /api/minerals

List all available minerals in the game.

**Authentication:** Required (Sanctum)

**Description:** Returns a complete list of all mineral types available for trading. This data is static game configuration and is cached for 1 hour to improve performance.

#### Request Parameters

None.

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Iron Ore",
      "symbol": "Fe",
      "description": "Common metallic ore used in ship construction",
      "base_value": 125.50,
      "rarity": "common",
      "market_value": 125.50
    },
    {
      "id": 2,
      "uuid": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Platinum",
      "symbol": "Pt",
      "description": "Rare precious metal with high conductivity",
      "base_value": 3450.00,
      "rarity": "rare",
      "market_value": 3450.00
    }
  ]
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Database ID of the mineral |
| `uuid` | string | UUID identifier for the mineral |
| `name` | string | Display name of the mineral |
| `symbol` | string | Chemical/trading symbol (e.g., "Fe", "Au") |
| `description` | string | Lore description of the mineral |
| `base_value` | float | Base price without market fluctuations |
| `rarity` | string | Rarity tier: "common", "uncommon", "rare", "exotic" |
| `market_value` | float | Current market value (may differ from base_value due to events) |

#### Error Responses

**401 Unauthorized:** Missing or invalid authentication token.

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

#### Warnings & Caveats

- Data is cached for 1 hour (3600 seconds)
- `market_value` equals `base_value` in this endpoint; real-time prices are hub-specific (see inventory endpoint)
- This endpoint does not reflect current trading hub prices or market event multipliers

---

## Trading Hubs

### GET /api/trading-hubs

List trading hubs near the player's current location.

**Authentication:** Required (Sanctum)

**Description:** Returns all active trading hubs within sensor range of the player's current position. Uses optimized bounding box pre-filtering before calculating precise circular distances.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `player_uuid` | string | Yes | UUID of the player |
| `radius` | numeric | No | Search radius in map units (default: `sensors × 100`) |

**Example:** `/api/trading-hubs?player_uuid=550e8400-e29b-41d4-a716-446655440000&radius=500`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "data": {
    "hubs": [
      {
        "id": 42,
        "uuid": "650e8400-e29b-41d4-a716-446655440010",
        "name": "Meridian Trade Station",
        "type": "commercial",
        "tier": "major",
        "location": {
          "id": 123,
          "uuid": "750e8400-e29b-41d4-a716-446655440020",
          "name": "Meridian System",
          "type": "star",
          "x": 1250.5,
          "y": 3200.75,
          "is_inhabited": true,
          "description": "A bustling commercial hub in the core sectors",
          "attributes": {
            "star_class": "G2V",
            "population": 15000000
          }
        },
        "gate_count": 8,
        "tax_rate": 0.05,
        "services": ["trading", "repairs", "shipyard"],
        "has_salvage_yard": true,
        "has_plans": false,
        "has_shipyard": true,
        "is_active": true
      }
    ],
    "search_radius": 500
  }
}
```

**Response Fields:**

**Top Level:**
| Field | Type | Description |
|-------|------|-------------|
| `hubs` | array | Array of TradingHub objects within range |
| `search_radius` | numeric | Actual search radius used (in map units) |

**TradingHub Object:**
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Database ID |
| `uuid` | string | UUID identifier |
| `name` | string | Display name of the trading hub |
| `type` | string | Hub type (e.g., "commercial", "industrial", "black_market") |
| `tier` | string | Hub tier: "minor", "major", "capital" (affects inventory variety) |
| `location` | object | PointOfInterest where the hub is located (see below) |
| `gate_count` | integer | Number of warp gates at this location |
| `tax_rate` | float | Transaction tax rate (0.0-1.0, e.g., 0.05 = 5%) |
| `services` | array | Available services (e.g., ["trading", "repairs"]) |
| `has_salvage_yard` | boolean | Whether salvage yard is available |
| `has_plans` | boolean | Whether plans shop is available |
| `has_shipyard` | boolean | Whether shipyard is available |
| `is_active` | boolean | Whether hub is currently operational |

**PointOfInterest (location) Object:**
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Database ID |
| `uuid` | string | UUID identifier |
| `name` | string | Name of the system/station |
| `type` | string | POI type (e.g., "star", "planet", "station") |
| `x` | float | X coordinate in the galaxy |
| `y` | float | Y coordinate in the galaxy |
| `is_inhabited` | boolean | Whether the system has a civilization |
| `description` | string | Optional description (only if present) |
| `attributes` | object | Optional additional attributes (only if present) |

#### Error Responses

**400 Bad Request:** Validation failed.
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "player_uuid": ["The player uuid field is required."]
  }
}
```

**404 Not Found:** Player not found or doesn't belong to authenticated user.
```json
{
  "success": false,
  "message": "Player not found"
}
```

#### Warnings & Caveats

- Default radius is `sensors × 100` (e.g., sensor level 3 = 300 units radius)
- Only returns active hubs (`is_active = true`)
- Uses bounding box pre-filtering for performance on large galaxies
- Player must own the specified `player_uuid` (checked against `user_id`)
- Result set is not paginated; adjust `radius` if too many results

---

### GET /api/trading-hubs/{uuid}

Get detailed information about a specific trading hub.

**Authentication:** Required (Sanctum)

**Description:** Returns detailed information about a single trading hub identified by its UUID or PointOfInterest UUID.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (path) | Yes | UUID of the PointOfInterest where the hub is located |

**Example:** `/api/trading-hubs/750e8400-e29b-41d4-a716-446655440020`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "uuid": "650e8400-e29b-41d4-a716-446655440010",
    "name": "Meridian Trade Station",
    "type": "commercial",
    "tier": "major",
    "location": {
      "id": 123,
      "uuid": "750e8400-e29b-41d4-a716-446655440020",
      "name": "Meridian System",
      "type": "star",
      "x": 1250.5,
      "y": 3200.75,
      "is_inhabited": true
    },
    "gate_count": 8,
    "tax_rate": 0.05,
    "services": ["trading", "repairs", "shipyard"],
    "has_salvage_yard": true,
    "has_plans": false,
    "has_shipyard": true,
    "is_active": true
  }
}
```

**Response Fields:** Same structure as individual hub object in [GET /api/trading-hubs](#get-apitrading-hubs).

#### Error Responses

**404 Not Found:** Trading hub not found at that location.
```json
{
  "success": false,
  "message": "Trading hub not found"
}
```

#### Warnings & Caveats

- The `{uuid}` parameter is the **PointOfInterest UUID**, not the TradingHub UUID
- This endpoint does not require player ownership validation
- Inactive hubs (`is_active = false`) can still be retrieved

---

### GET /api/trading-hubs/{uuid}/inventory

Get current inventory and prices at a trading hub.

**Authentication:** Required (Sanctum)

**Description:** Returns the complete inventory of minerals available at a trading hub, including current buy/sell prices and stock quantities.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (path) | Yes | UUID of the PointOfInterest where the hub is located |

**Example:** `/api/trading-hubs/750e8400-e29b-41d4-a716-446655440020/inventory`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "data": {
    "hub": {
      "id": 42,
      "uuid": "650e8400-e29b-41d4-a716-446655440010",
      "name": "Meridian Trade Station",
      "type": "commercial",
      "tier": "major",
      "location": {
        "id": 123,
        "uuid": "750e8400-e29b-41d4-a716-446655440020",
        "name": "Meridian System",
        "type": "star",
        "x": 1250.5,
        "y": 3200.75,
        "is_inhabited": true
      },
      "gate_count": 8,
      "tax_rate": 0.05,
      "services": ["trading", "repairs"],
      "has_salvage_yard": false,
      "has_plans": false,
      "has_shipyard": false,
      "is_active": true
    },
    "inventory": [
      {
        "mineral": {
          "id": 1,
          "uuid": "550e8400-e29b-41d4-a716-446655440000",
          "name": "Iron Ore",
          "symbol": "Fe",
          "description": "Common metallic ore",
          "base_value": 125.50,
          "rarity": "common",
          "market_value": 125.50
        },
        "quantity": 5000,
        "buy_price": 100.40,
        "sell_price": 150.60
      },
      {
        "mineral": {
          "id": 2,
          "uuid": "550e8400-e29b-41d4-a716-446655440001",
          "name": "Platinum",
          "symbol": "Pt",
          "description": "Rare precious metal",
          "base_value": 3450.00,
          "rarity": "rare",
          "market_value": 3450.00
        },
        "quantity": 250,
        "buy_price": 3105.00,
        "sell_price": 3795.00
      }
    ]
  }
}
```

**Response Fields:**

**Top Level:**
| Field | Type | Description |
|-------|------|-------------|
| `hub` | object | TradingHub details (same as hub details endpoint) |
| `inventory` | array | Array of inventory items available at this hub |

**Inventory Item:**
| Field | Type | Description |
|-------|------|-------------|
| `mineral` | object | Mineral details (same structure as minerals endpoint) |
| `quantity` | integer | Current stock quantity available at the hub |
| `buy_price` | float | Price the hub pays to **buy from players** (what you get when selling) |
| `sell_price` | float | Price the hub charges to **sell to players** (what you pay when buying) |

#### Error Responses

**404 Not Found:** Trading hub not found.
```json
{
  "success": false,
  "message": "Trading hub not found"
}
```

#### Warnings & Caveats

- **Price nomenclature:** `buy_price` = what hub pays YOU, `sell_price` = what YOU pay hub
- Prices reflect current market events and dynamic pricing
- `quantity` is hub stock; may limit purchase amount
- Empty inventory array means hub has no minerals currently
- Prices do NOT include the hub's `tax_rate` (tax is applied during transactions)

---

## Trading Transactions

### POST /api/trading-hubs/{uuid}/buy

Purchase minerals from a trading hub.

> ---
> **OLD — DO NOT USE** (prior to 2026-02-21)
>
> The previous version of this endpoint accepted `mineral_id` (integer) to identify the mineral, `ship_uuid` was not required (the server implicitly used the player's active ship), and there was no ship-at-hub location validation. This allowed remote trading exploits (cargo teleportation / remote arbitrage).
>
> **Request Body (OLD):**
>
> | Parameter | Type | Required | Description |
> |-----------|------|----------|-------------|
> | `player_uuid` | string | Yes | UUID of the player making the purchase |
> | `mineral_id` | integer | Yes | Database ID of the mineral to buy |
> | `quantity` | integer | Yes | Amount to purchase (min: 1) |
>
> ---

#### Updated: 2026-02-21

**Authentication:** Required (Sanctum)

**Description:** Buy a specified quantity of a mineral from a trading hub. The ship specified by `ship_uuid` must be physically located at the trading hub. Deducts credits, adds minerals to ship cargo, consumes cargo space. Awards trading XP on success. The total cost is calculated server-side (not provided by the client).

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (path) | Yes | UUID of the PointOfInterest where the hub is located (doubles as location context for price history) |

#### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `player_uuid` | string | Yes | UUID of the player making the purchase |
| `ship_uuid` | string | Yes | UUID of the player's ship (must be at the hub's POI) |
| `mineral_uuid` | string | Yes* | UUID of the mineral to buy (*required if `mineral_name` not provided) |
| `mineral_name` | string | Yes* | Name of the mineral to buy (*required if `mineral_uuid` not provided) |
| `quantity` | integer | Yes | Amount to purchase (min: 1) |

*Mineral identification: Provide `mineral_uuid` (preferred) or `mineral_name` as fallback. At least one is required.*

**Example Request:**
```json
{
  "player_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "ship_uuid": "850e8400-e29b-41d4-a716-446655440030",
  "mineral_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "quantity": 100
}
```

**Example Request (using mineral_name fallback):**
```json
{
  "player_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "ship_uuid": "850e8400-e29b-41d4-a716-446655440030",
  "mineral_name": "Iron Ore",
  "quantity": 100
}
```

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "message": "Purchase successful. Bought 100 Iron Ore for 15,060 credits.",
  "data": {
    "transaction_type": "buy",
    "mineral": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Iron Ore",
      "symbol": "Fe",
      "description": "Common metallic ore",
      "base_value": 125.50,
      "rarity": "common",
      "market_value": 125.50
    },
    "quantity": 100,
    "price_per_unit": 150.60,
    "total_cost": 15060.00,
    "credits_remaining": 84940.00,
    "cargo_remaining": 1100,
    "xp_earned": 150
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `transaction_type` | string | Always "buy" for this endpoint |
| `mineral` | object | Mineral object that was purchased |
| `quantity` | integer | Amount purchased |
| `price_per_unit` | float | Price per unit paid (hub's `sell_price`) |
| `total_cost` | float | Total credits deducted (quantity x price_per_unit, calculated server-side) |
| `credits_remaining` | float | Player's credit balance after transaction |
| `cargo_remaining` | integer | Ship's current cargo capacity used after transaction |
| `xp_earned` | integer | Trading XP awarded for this transaction |

#### Error Responses

**422 Unprocessable Entity:** Validation failed.
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "ship_uuid": ["The ship uuid field is required."],
      "quantity": ["The quantity must be at least 1."]
    }
  }
}
```

**404 Not Found:** Player, hub, ship, or mineral not found.
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found"
  }
}
```

**400 Bad Request:** Ship is not at the trading hub.
```json
{
  "success": false,
  "error": {
    "code": "SHIP_NOT_AT_HUB",
    "message": "Ship is not at this trading hub"
  }
}
```

**400 Bad Request:** Transaction failed due to business logic.
```json
{
  "success": false,
  "error": {
    "code": "BUY_FAILED",
    "message": "Insufficient credits. You need 15,060 but only have 10,000."
  }
}
```

**Possible failure codes:**
- `SHIP_NOT_AT_HUB` — Ship's `current_poi_id` does not match the hub's POI
- `BUY_FAILED` — Insufficient credits, insufficient cargo space, or insufficient hub stock

#### Warnings & Caveats

- Player must own the `player_uuid` (validated against authenticated user)
- Ship must belong to the player (`player_id` check on `PlayerShip`)
- **Ship must be physically at the hub** — `ship.current_poi_id` must equal the hub's POI ID
- `null` `current_poi_id` on the ship is treated as "not at hub" (surfaces missing location data as errors)
- Transaction checks (in order):
  1. Ship is at the hub (`SHIP_NOT_AT_HUB`)
  2. Hub has sufficient stock (`quantity` available)
  3. Player has sufficient credits (`total_cost <= player.credits`)
  4. Ship has sufficient cargo space (`quantity <= available_space`)
- `total_cost` is calculated server-side using the hub's `sell_price` (what you pay to buy from them)
- Cargo space is 1:1 (1 unit mineral = 1 unit cargo)
- XP formula: `(quantity / 10) * (mineral.base_value / 100)` (approximate, varies by TradingService)
- Transaction is atomic (all-or-nothing)
- The path `{uuid}` serves as the location context for price history tracking

---

### POST /api/trading-hubs/{uuid}/sell

Sell minerals to a trading hub.

> ---
> **OLD — DO NOT USE** (prior to 2026-02-21)
>
> The previous version of this endpoint accepted `mineral_id` (integer) to identify the mineral, `ship_uuid` was not required (the server implicitly used the player's active ship), and there was no ship-at-hub location validation. This allowed selling cargo from a ship that wasn't physically at the hub.
>
> **Request Body (OLD):**
>
> | Parameter | Type | Required | Description |
> |-----------|------|----------|-------------|
> | `player_uuid` | string | Yes | UUID of the player making the sale |
> | `mineral_id` | integer | Yes | Database ID of the mineral to sell |
> | `quantity` | integer | Yes | Amount to sell (min: 1) |
>
> ---

#### Updated: 2026-02-21

**Authentication:** Required (Sanctum)

**Description:** Sell a specified quantity of a mineral from a specific ship's cargo to a trading hub. The ship must be physically located at the hub. Adds credits, removes minerals from cargo, frees cargo space. Awards trading XP on success.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (path) | Yes | UUID of the PointOfInterest where the hub is located |

#### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `player_uuid` | string | Yes | UUID of the player making the sale |
| `ship_uuid` | string | Yes | UUID of the player's ship (must be at the hub's POI) |
| `mineral_uuid` | string | Yes* | UUID of the mineral to sell (*required if `mineral_name` not provided) |
| `mineral_name` | string | Yes* | Name of the mineral to sell (*required if `mineral_uuid` not provided) |
| `quantity` | integer | Yes | Amount to sell (min: 1) |

*Mineral identification: Provide `mineral_uuid` (preferred) or `mineral_name` as fallback. At least one is required.*

**Example Request:**
```json
{
  "player_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "ship_uuid": "850e8400-e29b-41d4-a716-446655440030",
  "mineral_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "quantity": 50
}
```

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "message": "Sale successful. Sold 50 Iron Ore for 5,020 credits.",
  "data": {
    "transaction_type": "sell",
    "mineral": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Iron Ore",
      "symbol": "Fe",
      "description": "Common metallic ore",
      "base_value": 125.50,
      "rarity": "common",
      "market_value": 125.50
    },
    "quantity": 50,
    "price_per_unit": 100.40,
    "total_revenue": 5020.00,
    "credits_remaining": 89960.00,
    "cargo_remaining": 1050,
    "xp_earned": 75
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `transaction_type` | string | Always "sell" for this endpoint |
| `mineral` | object | Mineral object that was sold |
| `quantity` | integer | Amount sold |
| `price_per_unit` | float | Price per unit received (hub's `buy_price`) |
| `total_revenue` | float | Total credits earned (quantity x price_per_unit, calculated server-side) |
| `credits_remaining` | float | Player's credit balance after transaction |
| `cargo_remaining` | integer | Ship's current cargo capacity used after transaction |
| `xp_earned` | integer | Trading XP awarded for this transaction |

#### Error Responses

**422 Unprocessable Entity:** Validation failed.
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "ship_uuid": ["The ship uuid field is required."]
    }
  }
}
```

**404 Not Found:** Player, hub, ship, or mineral not found.
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found"
  }
}
```

**400 Bad Request:** Ship is not at the trading hub.
```json
{
  "success": false,
  "error": {
    "code": "SHIP_NOT_AT_HUB",
    "message": "Ship is not at this trading hub"
  }
}
```

**400 Bad Request:** Player doesn't have the mineral in cargo on that ship.
```json
{
  "success": false,
  "error": {
    "code": "NO_CARGO",
    "message": "You do not have this mineral in cargo"
  }
}
```

**404 Not Found:** Hub doesn't trade this mineral.
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "This hub does not trade this mineral"
  }
}
```

**400 Bad Request:** Transaction failed (insufficient quantity).
```json
{
  "success": false,
  "error": {
    "code": "SELL_FAILED",
    "message": "You only have 30 units but tried to sell 50."
  }
}
```

#### Warnings & Caveats

- Player must own the `player_uuid` (validated against authenticated user)
- Ship must belong to the player (`player_id` check)
- **Ship must be physically at the hub** — `ship.current_poi_id` must equal the hub's POI ID
- Cargo lookup uses the specified ship's cargo (not the player's active ship)
- Transaction checks:
  1. Ship is at the hub (`SHIP_NOT_AT_HUB`)
  2. Player has mineral in cargo on that ship (`PlayerCargo` exists for `ship.id`)
  3. Player has sufficient quantity in cargo
  4. Hub accepts this mineral (`TradingHubInventory` exists for hub + mineral)
- `total_revenue` uses the hub's `buy_price` (what they pay to buy from you)
- Selling frees cargo space
- XP formula: similar to buying (based on quantity and mineral value)
- Transaction is atomic (all-or-nothing)
- If you sell all units of a mineral, the `PlayerCargo` record may be deleted (implementation detail)

---

### GET /api/players/{uuid}/cargo

Get the player's current cargo manifest.

**Authentication:** Required (Sanctum)

**Description:** Returns detailed information about all minerals currently stored in the player's active ship cargo hold.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (path) | Yes | UUID of the player |

**Example:** `/api/players/550e8400-e29b-41d4-a716-446655440000/cargo`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "data": {
    "ship_uuid": "850e8400-e29b-41d4-a716-446655440030",
    "ship_name": "Voyager III",
    "current_cargo": 1100,
    "cargo_capacity": 5000,
    "available_space": 3900,
    "cargo": [
      {
        "mineral": {
          "id": 1,
          "uuid": "550e8400-e29b-41d4-a716-446655440000",
          "name": "Iron Ore",
          "symbol": "Fe",
          "description": "Common metallic ore",
          "base_value": 125.50,
          "rarity": "common",
          "market_value": 125.50
        },
        "quantity": 1000
      },
      {
        "mineral": {
          "id": 2,
          "uuid": "550e8400-e29b-41d4-a716-446655440001",
          "name": "Platinum",
          "symbol": "Pt",
          "description": "Rare precious metal",
          "base_value": 3450.00,
          "rarity": "rare",
          "market_value": 3450.00
        },
        "quantity": 100
      }
    ]
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `ship_uuid` | string | UUID of the player's active ship |
| `ship_name` | string | Name of the active ship |
| `current_cargo` | integer | Total cargo space currently occupied |
| `cargo_capacity` | integer | Maximum cargo capacity of the ship (from `cargo_hold` upgrade) |
| `available_space` | integer | Free cargo space (`cargo_capacity - current_cargo`) |
| `cargo` | array | Array of cargo items (minerals and quantities) |

**Cargo Item:**
| Field | Type | Description |
|-------|------|-------------|
| `mineral` | object | Mineral details (same structure as minerals endpoint) |
| `quantity` | integer | Amount of this mineral in cargo |

#### Error Responses

**404 Not Found:** Player not found or doesn't belong to authenticated user.
```json
{
  "success": false,
  "message": "Player not found"
}
```

**400 Bad Request:** Player has no active ship.
```json
{
  "success": false,
  "message": "No active ship",
  "error_code": "NO_ACTIVE_SHIP"
}
```

#### Warnings & Caveats

- Player must own the `player_uuid` (validated against authenticated user)
- Empty `cargo` array means no minerals in cargo
- `current_cargo` should equal sum of all cargo item quantities (1:1 ratio)
- Cargo space is per-ship; switching ships changes capacity and cargo
- `cargo_capacity` is determined by ship's `cargo_hold` component level

---

### GET /api/trading/affordability

Calculate maximum affordable quantity of a mineral at a hub.

> ---
> **OLD — DO NOT USE** (prior to 2026-02-21)
>
> The previous version accepted `mineral_id` (integer) and did not require `ship_uuid`. Cargo space was calculated from the player's active ship implicitly, with no ship-at-hub validation.
>
> **Request Parameters (OLD):**
>
> | Parameter | Type | Required | Description |
> |-----------|------|----------|-------------|
> | `player_uuid` | string | Yes | UUID of the player |
> | `hub_uuid` | string | Yes | UUID of the PointOfInterest where the hub is located |
> | `mineral_id` | integer | Yes | Database ID of the mineral |
>
> ---

#### Updated: 2026-02-21

**Authentication:** Required (Sanctum)

**Description:** Calculates the maximum quantity of a mineral a player can afford to purchase, considering both credit limits and cargo space constraints on the specified ship. The ship must be at the hub.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `player_uuid` | string | Yes | UUID of the player |
| `hub_uuid` | string | Yes | UUID of the PointOfInterest where the hub is located |
| `ship_uuid` | string | Yes | UUID of the player's ship (must be at the hub's POI) |
| `mineral_uuid` | string | Yes* | UUID of the mineral (*required if `mineral_name` not provided) |
| `mineral_name` | string | Yes* | Name of the mineral (*required if `mineral_uuid` not provided) |

*Mineral identification: Provide `mineral_uuid` (preferred) or `mineral_name` as fallback. At least one is required.*

**Example:** `/api/trading/affordability?player_uuid=550e8400...&hub_uuid=750e8400...&ship_uuid=850e8400...&mineral_uuid=550e8400...`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "data": {
    "max_affordable": 667,
    "max_by_cargo_space": 3900,
    "max_purchasable": 667,
    "price_per_unit": 150.60,
    "total_cost": 100450.20
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `max_affordable` | integer | Maximum quantity affordable based on player's credits |
| `max_by_cargo_space` | integer | Maximum quantity that fits in the specified ship's available cargo space |
| `max_purchasable` | integer | Actual maximum purchasable (minimum of the two limits) |
| `price_per_unit` | float | Current sell price at the hub |
| `total_cost` | float | Total cost if purchasing `max_purchasable` units |

#### Error Responses

**422 Unprocessable Entity:** Validation failed.
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "ship_uuid": ["The ship uuid field is required."]
    }
  }
}
```

**404 Not Found:** Player, hub, ship, or mineral not found.
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Ship not found"
  }
}
```

**400 Bad Request:** Ship is not at the trading hub.
```json
{
  "success": false,
  "error": {
    "code": "SHIP_NOT_AT_HUB",
    "message": "Ship is not at this trading hub"
  }
}
```

**404 Not Found:** Mineral not available at this hub.
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Mineral not available"
  }
}
```

#### Warnings & Caveats

- Ship must be at the hub (`SHIP_NOT_AT_HUB` error if not)
- Cargo space is calculated from the specified ship (not the player's active ship)
- Does NOT check hub inventory stock; only player/ship constraints
- Frontend should compare `max_purchasable` with hub inventory `quantity`
- Calculation: `max_affordable = floor(player.credits / price_per_unit)`
- Calculation: `max_by_cargo_space = ship.cargo_hold - ship.current_cargo`
- Result: `max_purchasable = min(max_affordable, max_by_cargo_space)`
- All values are integers (fractional units not supported)
- Use this endpoint to populate "Buy Max" buttons in UIs

---

## Market Events

### GET /api/trading-hubs/{uuid}/active-events

Get all active market events affecting a specific trading hub.

**Authentication:** Required (Sanctum)

**Description:** Returns all currently active market events at a specific trading hub. Market events modify mineral prices temporarily (e.g., shortages, surpluses, booms).

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uuid` | string (path) | Yes | UUID of the TradingHub |

**Example:** `/api/trading-hubs/650e8400-e29b-41d4-a716-446655440010/active-events`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "message": "Trading hub events retrieved successfully",
  "data": {
    "trading_hub": {
      "uuid": "650e8400-e29b-41d4-a716-446655440010",
      "name": "Meridian Trade Station",
      "location": {
        "x": 1250.5,
        "y": 3200.75
      }
    },
    "active_events_count": 2,
    "events": [
      {
        "uuid": "950e8400-e29b-41d4-a716-446655440050",
        "event_type": "shortage",
        "mineral": {
          "name": "Iron Ore",
          "symbol": "Fe",
          "base_price": 125.50
        },
        "price_multiplier": 1.75,
        "modified_price": 219.63,
        "price_change_percent": 75.0,
        "description": "Supply chain disruption causes iron shortage",
        "expires_at": "2026-02-17T14:30:00Z",
        "time_remaining_seconds": 82800
      },
      {
        "uuid": "950e8400-e29b-41d4-a716-446655440051",
        "event_type": "surplus",
        "mineral": {
          "name": "Platinum",
          "symbol": "Pt",
          "base_price": 3450.00
        },
        "price_multiplier": 0.65,
        "modified_price": 2242.50,
        "price_change_percent": -35.0,
        "description": "New mining operations flood market with platinum",
        "expires_at": "2026-02-18T08:15:00Z",
        "time_remaining_seconds": 147300
      }
    ]
  }
}
```

**Response Fields:**

**Top Level:**
| Field | Type | Description |
|-------|------|-------------|
| `trading_hub` | object | Basic hub information |
| `active_events_count` | integer | Number of active events at this hub |
| `events` | array | Array of active market events (sorted by expiration) |

**TradingHub Object:**
| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | UUID of the trading hub |
| `name` | string | Name of the trading hub |
| `location` | object | Coordinates (`x`, `y`) |

**Market Event Object:**
| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | UUID of the market event |
| `event_type` | string | Event type (e.g., "shortage", "surplus", "boom", "bust") |
| `mineral` | object | Affected mineral (name, symbol, base_price) |
| `price_multiplier` | float | Price modifier (e.g., 1.75 = +75%, 0.65 = -35%) |
| `modified_price` | float | Calculated price after multiplier (`base_price × multiplier`) |
| `price_change_percent` | float | Percentage change (e.g., 75.0 = +75%, -35.0 = -35%) |
| `description` | string | Lore description of the event |
| `expires_at` | string | ISO 8601 timestamp when event expires |
| `time_remaining_seconds` | integer | Seconds until expiration (null if expired) |

#### Error Responses

**404 Not Found:** Trading hub not found.
```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\TradingHub]."
}
```

#### Warnings & Caveats

- Only returns active events (`is_active = true`)
- Events are sorted by `expires_at` (soonest first)
- `time_remaining_seconds` can be 0 if event just expired (will be cleaned up on next cron)
- `modified_price` is calculated at response time, may differ from actual hub prices if multiple events affect same mineral
- Multiple events can affect same hub but typically target different minerals
- The `{uuid}` parameter is the **TradingHub UUID**, not the PointOfInterest UUID (different from other endpoints)

---

### GET /api/galaxies/{galaxyUuid}/market-events

List all active market events in a galaxy.

**Authentication:** Required (Sanctum)

**Description:** Returns all currently active market events across an entire galaxy, with optional filtering by event type or mineral.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `galaxyUuid` | string (path) | Yes | UUID of the galaxy |
| `event_type` | string (query) | No | Filter by event type (e.g., "shortage", "surplus") |
| `mineral` | string (query) | No | Filter by mineral name or symbol (e.g., "Iron Ore" or "Fe") |

**Example:** `/api/galaxies/450e8400-e29b-41d4-a716-446655440040/market-events?event_type=shortage`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "message": "Market events retrieved successfully",
  "data": {
    "galaxy": {
      "uuid": "450e8400-e29b-41d4-a716-446655440040",
      "name": "Andromeda Sector"
    },
    "total_active_events": 5,
    "events": [
      {
        "uuid": "950e8400-e29b-41d4-a716-446655440050",
        "event_type": "shortage",
        "mineral": {
          "name": "Iron Ore",
          "symbol": "Fe"
        },
        "price_multiplier": 1.75,
        "trading_hub": {
          "uuid": "650e8400-e29b-41d4-a716-446655440010",
          "name": "Meridian Trade Station",
          "location": {
            "x": 1250.5,
            "y": 3200.75
          }
        },
        "description": "Supply chain disruption causes iron shortage",
        "created_at": "2026-02-16T10:00:00Z",
        "expires_at": "2026-02-17T14:30:00Z",
        "time_remaining_seconds": 82800
      }
    ]
  }
}
```

**Response Fields:**

**Top Level:**
| Field | Type | Description |
|-------|------|-------------|
| `galaxy` | object | Galaxy information (uuid, name) |
| `total_active_events` | integer | Total number of active events in galaxy (after filters) |
| `events` | array | Array of market events (sorted by expiration) |

**Market Event Object:**
| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | UUID of the market event |
| `event_type` | string | Event type (e.g., "shortage", "surplus", "boom") |
| `mineral` | object | Affected mineral (name, symbol) |
| `price_multiplier` | float | Price modifier |
| `trading_hub` | object | Hub where event is active (uuid, name, location) |
| `description` | string | Event description |
| `created_at` | string | ISO 8601 timestamp when event was created |
| `expires_at` | string | ISO 8601 timestamp when event expires |
| `time_remaining_seconds` | integer\|null | Seconds until expiration (null if expired) |

#### Error Responses

**404 Not Found:** Galaxy not found.
```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\Galaxy]."
}
```

#### Warnings & Caveats

- Only returns active events (`is_active = true`)
- Events are sorted by `expires_at` (soonest expiring first)
- `mineral` filter accepts either full name ("Iron Ore") or symbol ("Fe")
- Empty `events` array means no active events (or none match filters)
- Useful for players scouting profitable trade routes galaxy-wide
- Performance: May be slow on massive galaxies with many events (consider pagination if needed)

---

### GET /api/market-events/{eventUuid}

Get detailed information about a specific market event.

**Authentication:** Required (Sanctum)

**Description:** Returns comprehensive details about a single market event, including calculated pricing and time-sensitive information.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `eventUuid` | string (path) | Yes | UUID of the market event |

**Example:** `/api/market-events/950e8400-e29b-41d4-a716-446655440050`

#### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "message": "Market event details retrieved successfully",
  "data": {
    "uuid": "950e8400-e29b-41d4-a716-446655440050",
    "event_type": "shortage",
    "galaxy": {
      "uuid": "450e8400-e29b-41d4-a716-446655440040",
      "name": "Andromeda Sector"
    },
    "mineral": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Iron Ore",
      "symbol": "Fe",
      "base_price": 125.50
    },
    "price_multiplier": 1.75,
    "modified_price": 219.63,
    "trading_hub": {
      "uuid": "650e8400-e29b-41d4-a716-446655440010",
      "name": "Meridian Trade Station",
      "location": {
        "x": 1250.5,
        "y": 3200.75
      }
    },
    "description": "Supply chain disruption causes iron shortage across the sector",
    "is_active": true,
    "created_at": "2026-02-16T10:00:00Z",
    "expires_at": "2026-02-17T14:30:00Z",
    "time_remaining_seconds": 82800
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `uuid` | string | UUID of the market event |
| `event_type` | string | Event type (e.g., "shortage", "surplus", "boom", "bust") |
| `galaxy` | object | Galaxy where event is happening (uuid, name) |
| `mineral` | object | Affected mineral with full details (uuid, name, symbol, base_price) |
| `price_multiplier` | float | Price modifier (e.g., 1.75 = +75%) |
| `modified_price` | float | Calculated result: `mineral.base_price × price_multiplier` |
| `trading_hub` | object | Hub where event is active (uuid, name, location) |
| `description` | string | Narrative description of the event |
| `is_active` | boolean | Whether event is currently active |
| `created_at` | string | ISO 8601 timestamp when event was created |
| `expires_at` | string | ISO 8601 timestamp when event expires |
| `time_remaining_seconds` | integer | Seconds until expiration (0 if inactive) |

#### Error Responses

**404 Not Found:** Market event not found.
```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\MarketEvent]."
}
```

#### Warnings & Caveats

- Returns both active and inactive events (check `is_active` field)
- `time_remaining_seconds` is 0 for inactive events
- `modified_price` is calculated based on current `mineral.base_price` at query time
- Actual hub prices may differ if multiple events stack or if dynamic pricing applies
- Use this endpoint for event detail views or tracking specific event progression

---

## Common Response Patterns

All endpoints follow the `BaseApiController` pattern:

**Success Response:**
```json
{
  "success": true,
  "message": "Optional success message",
  "data": { /* endpoint-specific data */ }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "error_code": "OPTIONAL_ERROR_CODE"
}
```

**Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Validation message"]
  }
}
```

## Authentication

All endpoints require Laravel Sanctum authentication. Include the bearer token in the request header:

```
Authorization: Bearer {token}
```

Unauthenticated requests will return:
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

## Data Types

- **UUID:** String in format `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
- **ISO 8601 Timestamp:** String in format `YYYY-MM-DDTHH:MM:SSZ` (UTC)
- **Float:** Numbers with decimal precision (prices, coordinates)
- **Integer:** Whole numbers (quantities, IDs, seconds)

## Caching

- **Minerals:** Cached for 1 hour (3600 seconds)
- Other endpoints: Real-time data, no caching

## Rate Limiting

Rate limiting is handled by Laravel Sanctum middleware. Check your application's rate limiting configuration in `app/Http/Kernel.php`.

---

**Last Updated:** 2026-02-21
**Version:** 2026.02.10.001

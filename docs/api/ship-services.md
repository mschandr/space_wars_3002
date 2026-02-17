# Ship Services API Documentation

This document provides comprehensive documentation for all ship service endpoints in Space Wars 3002, including ship repair, maintenance, purchasing, upgrades, salvage operations, and orbital structures.

---

## Table of Contents

1. [Ship Repair & Maintenance](#ship-repair--maintenance)
2. [Ship Shop (Trading Hub)](#ship-shop-trading-hub)
3. [Shipyard (Pre-rolled Ships)](#shipyard-pre-rolled-ships)
4. [Salvage Yard](#salvage-yard)
5. [Ship Upgrades](#ship-upgrades)
6. [Plans Shop](#plans-shop)
7. [Orbital Structures](#orbital-structures)

---

## Ship Repair & Maintenance

### Get Repair Estimate

**GET** `/api/ships/{uuid}/repair-estimate`

Get detailed repair cost estimates for a ship including hull damage and downgraded components.

**Authentication:** Not required (but ship must exist)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "hull_damage": 45,
    "hull_repair_cost": 450,
    "needs_hull_repair": true,
    "downgraded_components": [
      {
        "component": "weapons",
        "name": "Weapons",
        "current": 5,
        "should_be": 10,
        "deficit": 5,
        "repair_cost": 250
      }
    ],
    "component_repair_cost": 250,
    "needs_component_repair": true,
    "total_repair_cost": 700,
    "hull_percentage": 67.5
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `hull_damage` (integer): Total hull points lost
- `hull_repair_cost` (integer): Cost to repair hull (10 credits per point)
- `needs_hull_repair` (boolean): Whether hull repair is needed
- `downgraded_components` (array): List of components below base values
  - `component` (string): Component key (weapons, sensors, etc.)
  - `name` (string): Human-readable component name
  - `current` (integer): Current value
  - `should_be` (integer): Base ship value
  - `deficit` (integer): Points below base
  - `repair_cost` (integer): Cost to restore (50 credits per level)
- `component_repair_cost` (integer): Total cost for all component repairs
- `needs_component_repair` (boolean): Whether component repair is needed
- `total_repair_cost` (integer): Combined hull + component repair cost
- `hull_percentage` (float): Current hull as percentage of max (0-100)

**Error Responses:**

- `404 Not Found`: Ship not found

---

### Repair Hull

**POST** `/api/ships/{uuid}/repair/hull`

Repair ship hull to maximum capacity.

**Authentication:** Required (must own ship)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "hull_repaired": 45,
    "cost": 450,
    "current_hull": 100,
    "max_hull": 100,
    "remaining_credits": 5550
  },
  "message": "Hull repaired: 45 points restored",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `hull_repaired` (integer): Hull points restored
- `cost` (integer): Credits spent
- `current_hull` (integer): Current hull after repair
- `max_hull` (integer): Maximum hull capacity
- `remaining_credits` (integer): Player credits after repair

**Error Responses:**

- `400 Bad Request`: Insufficient credits
- `403 Forbidden`: Not authorized to repair this ship
- `404 Not Found`: Ship not found

---

### Repair Components

**POST** `/api/ships/{uuid}/repair/components`

Repair all downgraded ship components to their base values.

**Authentication:** Required (must own ship)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "components_repaired": ["Weapons", "Sensors"],
    "cost": 500,
    "remaining_credits": 5000
  },
  "message": "Components repaired: Weapons, Sensors",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `components_repaired` (array): List of component names repaired
- `cost` (integer): Total credits spent
- `remaining_credits` (integer): Player credits after repair

**Error Responses:**

- `400 Bad Request`: Insufficient credits OR no components need repair
- `403 Forbidden`: Not authorized to repair this ship
- `404 Not Found`: Ship not found

---

### Repair All

**POST** `/api/ships/{uuid}/repair/all`

Repair both hull and components in a single transaction.

**Authentication:** Required (must own ship)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "cost": 950,
    "current_hull": 100,
    "max_hull": 100,
    "remaining_credits": 4050
  },
  "message": "Hull repaired: 45 points restored\nComponents repaired: Weapons, Sensors",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `cost` (integer): Total credits spent
- `current_hull` (integer): Hull after repair
- `max_hull` (integer): Maximum hull capacity
- `remaining_credits` (integer): Player credits after repair

**Error Responses:**

- `400 Bad Request`: Insufficient credits OR ship already in perfect condition
- `403 Forbidden`: Not authorized to repair this ship
- `404 Not Found`: Ship not found

---

### Get Maintenance Status

**GET** `/api/ships/{uuid}/maintenance`

Get overall maintenance assessment for a ship.

**Authentication:** Not required (but ship must exist)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "status": "good",
    "hull_percentage": 82.5,
    "current_hull": 82,
    "max_hull": 100,
    "damage": 18,
    "needs_repair": true,
    "estimated_repair_cost": 180,
    "is_operational": true
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `status` (string): Maintenance status
  - `excellent`: 90-100% hull
  - `good`: 70-89% hull
  - `fair`: 50-69% hull
  - `poor`: 30-49% hull
  - `critical`: 10-29% hull
  - `emergency`: 0-9% hull
- `hull_percentage` (float): Current hull as percentage of max
- `current_hull` (integer): Current hull points
- `max_hull` (integer): Maximum hull points
- `damage` (integer): Hull points lost
- `needs_repair` (boolean): Whether repair is recommended
- `estimated_repair_cost` (integer): Total cost to fully repair
- `is_operational` (boolean): Whether ship is operational

**Error Responses:**

- `404 Not Found`: Ship not found

---

## Ship Shop (Trading Hub)

Legacy ship purchasing system based at trading hubs. Ships are purchased from inventory with optional trade-in.

### Get Ship Shop at Trading Hub

**GET** `/api/trading-hubs/{uuid}/ship-shop`

Check if a trading hub has a ship shop and list available ships.

> **Changed 2026-02-17:** Route renamed from `/api/trading-hubs/{uuid}/shipyard` to `/api/trading-hubs/{uuid}/ship-shop` to disambiguate from the unique-ship shipyard (`/api/systems/{uuid}/shipyard`).

**Authentication:** Not required

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Trading hub UUID or POI UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "has_shipyard": true,
    "trading_hub_name": "Nexus Prime Trading Hub",
    "available_ships": [
      {
        "ship": {
          "id": 1,
          "name": "Scout-class Vessel",
          "class": "scout",
          "base_price": 5000,
          "hull_strength": 50,
          "cargo_capacity": 20,
          "rarity": "common"
        },
        "current_price": 5000,
        "quantity": 3
      }
    ]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `has_shipyard` (boolean): Whether this hub has a shipyard
- `trading_hub_name` (string): Name of trading hub
- `available_ships` (array): List of ships in stock
  - `ship` (object): Ship blueprint details
  - `current_price` (integer): Current price at this hub
  - `quantity` (integer): Number in stock

**Error Responses:**

- `404 Not Found`: Trading hub not found

---

### Get Ship Catalog

**GET** `/api/ships/catalog`

Browse all available ship blueprints.

**Authentication:** Required

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| rarity | string | No | Filter by rarity (common, uncommon, rare, epic, unique, exotic) |
| class | string | No | Filter by ship class (scout, freighter, battleship, etc.) |
| min_price | integer | No | Minimum base price |
| max_price | integer | No | Maximum base price |

**Response:**

```json
{
  "success": true,
  "data": {
    "ships": [
      {
        "id": 1,
        "uuid": "abc-123",
        "name": "Scout-class Vessel",
        "class": "scout",
        "base_price": 5000,
        "hull_strength": 50,
        "cargo_capacity": 20,
        "rarity": "common",
        "is_available": true
      }
    ],
    "total_count": 12
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `ships` (array): List of ship blueprints
- `total_count` (integer): Number of ships returned

---

### Purchase Ship

**POST** `/api/players/{uuid}/ships/purchase`

Purchase a new ship from a trading hub with optional trade-in of current ship.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| ship_id | integer | Yes | Body | Ship blueprint ID |
| trading_hub_uuid | string | Yes | Body | Trading hub UUID |
| trade_in_current_ship | boolean | No | Body | Trade in active ship for credit (default: false) |

**Request Body:**

```json
{
  "ship_id": 2,
  "trading_hub_uuid": "def-456-ghi-789",
  "trade_in_current_ship": true
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "ship": {
      "uuid": "new-ship-uuid",
      "name": "Freighter MK-II",
      "hull": 100,
      "max_hull": 100,
      "cargo_hold": 100,
      "weapons": 15,
      "sensors": 1,
      "warp_drive": 1
    },
    "cost_paid": 12000,
    "trade_in_value": 2500,
    "net_cost": 9500,
    "remaining_credits": 40500
  },
  "message": "Ship purchased successfully",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `ship` (object): Newly purchased ship details
- `cost_paid` (integer): Original ship price
- `trade_in_value` (integer): Credit from trade-in (0 if no trade-in)
- `net_cost` (integer): Final cost after trade-in
- `remaining_credits` (integer): Player credits after purchase

**Trade-in Calculation:**
- Base trade-in: 50% of ship's original base price
- Condition penalty: Multiplied by (current_hull / max_hull)
- Minimum value: 50% of base trade-in even if destroyed

**Warnings:**
- Trading in a ship deletes it permanently
- All ships are deactivated, new ship becomes active
- Inventory must be available at the specified trading hub

**Error Responses:**

- `400 Bad Request`: Insufficient credits, ship not available, or no shipyard
- `403 Forbidden`: Not authorized to make purchases for this player
- `404 Not Found`: Player or trading hub not found

---

### Switch Active Ship

**POST** `/api/players/{uuid}/ships/switch`

Change which ship in the player's fleet is active.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| ship_uuid | string | Yes | Body | Ship UUID to activate |

**Request Body:**

```json
{
  "ship_uuid": "ship-uuid-here"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "active_ship": {
      "uuid": "ship-uuid-here",
      "name": "Scout Alpha",
      "is_active": true
    }
  },
  "message": "Active ship switched successfully",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Error Responses:**

- `403 Forbidden`: Ship not owned by player
- `404 Not Found`: Player or ship not found

---

### Get Player Fleet

**GET** `/api/players/{uuid}/ships/fleet`

List all ships owned by a player.

**Authentication:** Not required

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "fleet": [
      {
        "uuid": "ship-1-uuid",
        "name": "Battlecruiser Alpha",
        "is_active": true,
        "hull": 200,
        "max_hull": 200
      },
      {
        "uuid": "ship-2-uuid",
        "name": "Scout Beta",
        "is_active": false,
        "hull": 50,
        "max_hull": 50
      }
    ],
    "total_ships": 2,
    "active_ship_uuid": "ship-1-uuid"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `fleet` (array): List of player's ships (sorted by active status, then creation date)
- `total_ships` (integer): Total number of ships owned
- `active_ship_uuid` (string|null): UUID of currently active ship

---

## Shipyard (Pre-rolled Ships)

Unique pre-rolled ships with randomized stats and rarity. Each ship is a one-of-a-kind instance.

### List Shipyard Inventory

**GET** `/api/systems/{uuid}/shipyard`

List all available ships at a system's shipyard. Triggers lazy inventory generation on first visit.

**Authentication:** Not required

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | System (POI) UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "system-uuid",
      "name": "Alpha Centauri"
    },
    "ships": [
      {
        "uuid": "inv-uuid-1",
        "name": "Majestic Star F4A1",
        "rarity": "epic",
        "rarity_label": "Epic",
        "rarity_color": "#9b30ff",
        "price": 45000,
        "blueprint": {
          "name": "Battlecruiser",
          "class": "combat"
        },
        "stats": {
          "hull_strength": 250,
          "shield_strength": 100,
          "cargo_capacity": 50,
          "speed": 80,
          "weapon_slots": 6,
          "utility_slots": 4,
          "max_fuel": 150,
          "sensors": 3,
          "warp_drive": 2,
          "weapons": 35
        },
        "is_sold": false
      }
    ]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `system` (object): System information
- `ships` (array): Available ships (unsold only)
  - `uuid` (string): Shipyard inventory UUID (use for purchase)
  - `name` (string): Procedurally generated ship name
  - `rarity` (string): Rarity tier (common, uncommon, rare, epic, unique, exotic)
  - `rarity_label` (string): Display label for rarity
  - `rarity_color` (string): Hex color code for rarity
  - `price` (float): Purchase price (affected by rarity)
  - `blueprint` (object): Base ship template
  - `stats` (object): Pre-rolled stats (affected by rarity)
  - `is_sold` (boolean): Whether already purchased

**Rarity Tiers:**
- Common: 0.9-1.0x stats, 0.8-0.9x price
- Uncommon: 1.0-1.1x stats, 0.9-1.1x price
- Rare: 1.1-1.25x stats, 1.2-1.5x price
- Epic: 1.25-1.5x stats, 1.5-2.0x price
- Unique: 1.5-1.75x stats, 2.0-3.0x price
- Exotic: 1.75-2.0x stats, 3.0-5.0x price

**Error Responses:**

- `404 Not Found`: System not found

---

### Get Shipyard Item Details

**GET** `/api/shipyard-inventory/{uuid}`

Get detailed view of a specific shipyard inventory item.

**Authentication:** Not required

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Shipyard inventory UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "uuid": "inv-uuid-1",
    "name": "Legendary Phoenix A7B2",
    "rarity": "unique",
    "rarity_label": "Unique",
    "rarity_color": "#ff6600",
    "price": 125000,
    "blueprint": {
      "name": "Dreadnought",
      "class": "combat"
    },
    "stats": {
      "hull_strength": 450,
      "shield_strength": 200,
      "cargo_capacity": 75,
      "speed": 60,
      "weapon_slots": 8,
      "utility_slots": 6,
      "max_fuel": 200,
      "sensors": 4,
      "warp_drive": 3,
      "weapons": 60
    },
    "is_sold": false,
    "variation_traits": {
      "reinforced_hull": true,
      "advanced_sensors": true
    },
    "attributes": {
      "special_ability": "Shield Overcharge"
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- Same as list response plus:
  - `variation_traits` (object): Special traits from generation
  - `attributes` (object): Additional ship attributes

**Error Responses:**

- `404 Not Found`: Shipyard inventory item not found

---

### Purchase Shipyard Ship

**POST** `/api/players/{uuid}/shipyard/purchase`

Purchase a unique pre-rolled ship from a shipyard.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| inventory_uuid | string | Yes | Body | Shipyard inventory UUID |
| custom_name | string | No | Body | Custom name for ship (max 100 chars) |

**Request Body:**

```json
{
  "inventory_uuid": "inv-uuid-1",
  "custom_name": "My Epic Warship"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "ship": {
      "uuid": "player-ship-uuid",
      "name": "My Epic Warship",
      "hull": 450,
      "max_hull": 450,
      "cargo_hold": 75,
      "weapons": 60,
      "sensors": 4,
      "warp_drive": 3
    },
    "credits_remaining": 375000
  },
  "message": "Ship purchased successfully.",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Warnings:**
- Ship is removed from inventory after purchase (one-of-a-kind)
- Ship becomes active immediately
- Previous active ship is deactivated (not deleted)

**Error Responses:**

- `400 Bad Request`: Ship already sold, insufficient credits, or requirement not met
- `403 Forbidden`: Not authorized
- `404 Not Found`: Player or inventory item not found

---

## Salvage Yard

Purchase and sell ship components. Components are installed in weapon_slots or utility_slots.

### Browse Salvage Yard (Current Location)

**GET** `/api/players/{uuid}/salvage-yard`

List all components available at the salvage yard at player's current location.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "hub": {
      "id": 42,
      "name": "Frontier Outpost",
      "tier": "standard"
    },
    "inventory": {
      "weapons": [
        {
          "id": 1,
          "component": {
            "id": 10,
            "uuid": "comp-uuid",
            "name": "Pulse Laser MK-II",
            "type": "weapon",
            "slot_type": "weapon_slot",
            "description": "Medium-range energy weapon",
            "slots_required": 1,
            "rarity": "uncommon",
            "rarity_color": "#00ff00",
            "effects": {
              "damage": 25,
              "range": 500,
              "cooldown": 2
            },
            "requirements": {
              "level": 5
            }
          },
          "quantity": 3,
          "price": 1500,
          "condition": 85,
          "condition_description": "Good",
          "source": "salvage",
          "source_description": "Salvaged",
          "is_new": false
        }
      ],
      "utilities": [
        {
          "id": 2,
          "component": {
            "id": 20,
            "uuid": "comp-uuid-2",
            "name": "Shield Regenerator Alpha",
            "type": "utility",
            "slot_type": "utility_slot",
            "description": "Restores shield over time",
            "slots_required": 1,
            "rarity": "rare",
            "rarity_color": "#0066ff",
            "effects": {
              "regen_rate": 5,
              "efficiency": 1.2
            },
            "requirements": null
          },
          "quantity": 1,
          "price": 3500,
          "condition": 100,
          "condition_description": "Pristine",
          "source": "manufactured",
          "source_description": "New",
          "is_new": true
        }
      ]
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `hub` (object): Trading hub information
  - `tier` (string): Hub tier (standard, premium, etc.)
- `inventory` (object): Components grouped by type
  - `weapons` (array): Weapon components for weapon_slots
  - `utilities` (array): Utility components for utility_slots

**Component Fields:**
- `condition` (integer): 0-100, affects performance and price
- `source` (string): salvage, manufactured, or stolen
- `is_new` (boolean): Whether condition is 100

**Condition Descriptions:**
- 100: Pristine
- 80-99: Good
- 60-79: Fair
- 40-59: Worn
- 20-39: Damaged
- 0-19: Broken

**Source Descriptions:**
- manufactured: New (premium price 1.2x)
- salvage: Salvaged (discount 0.7x)
- stolen: Stolen (discount 0.6x, risky)

**Error Responses:**

- `400 Bad Request`: Not at a trading hub with salvage yard
- `403 Forbidden`: Not authorized

---

### Browse Salvage Yard (By System)

**GET** `/api/systems/{uuid}/salvage-yard`

Browse salvage yard components at a specific system POI. Triggers lazy inventory generation.

**Authentication:** Not required

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | System (POI) UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "system": {
      "uuid": "system-uuid",
      "name": "Beta Station"
    },
    "inventory": [
      {
        "id": 5,
        "component": {
          "name": "Torpedo Launcher",
          "type": "weapon",
          "slot_type": "weapon_slot"
        },
        "quantity": 2,
        "price": 8500,
        "condition": 75,
        "source": "salvage"
      }
    ]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Error Responses:**

- `404 Not Found`: System not found

---

### Get Ship Components

**GET** `/api/players/{uuid}/ship-components`

Get all components currently installed on the player's active ship.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "ship": {
      "id": 123,
      "name": "Battlecruiser Alpha",
      "class": "Dreadnought"
    },
    "components": {
      "weapon_slots": {
        "1": {
          "id": 50,
          "component": {
            "id": 10,
            "uuid": "comp-uuid",
            "name": "Pulse Laser MK-II",
            "type": "weapon",
            "rarity": "uncommon",
            "rarity_color": "#00ff00",
            "effects": {
              "damage": 25,
              "range": 500
            }
          },
          "slot_index": 1,
          "condition": 85,
          "is_damaged": false,
          "is_broken": false,
          "ammo": 100,
          "max_ammo": 100,
          "needs_ammo": false,
          "is_active": true
        }
      },
      "utility_slots": {
        "1": {
          "id": 51,
          "component": {
            "name": "Shield Regenerator"
          },
          "slot_index": 1,
          "condition": 100,
          "is_active": true
        }
      },
      "total_weapon_slots": 6,
      "total_utility_slots": 4
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `ship` (object): Active ship details
- `components` (object): Installed components
  - `weapon_slots` (object): Keyed by slot index
  - `utility_slots` (object): Keyed by slot index
  - `total_weapon_slots` (integer): Max weapon slots
  - `total_utility_slots` (integer): Max utility slots

**Component Status:**
- `is_damaged` (boolean): Condition < 50
- `is_broken` (boolean): Condition < 20
- `needs_ammo` (boolean): Ammo < max_ammo

**Error Responses:**

- `403 Forbidden`: Not authorized
- `404 Not Found`: Player or ship not found

---

### Purchase Component

**POST** `/api/players/{uuid}/salvage-yard/purchase`

Purchase and install a component from the salvage yard.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| inventory_id | integer | Yes | Body | Salvage yard inventory ID |
| slot_index | integer | Yes | Body | Slot index to install (1-based) |
| ship_id | integer | No | Body | Ship ID (defaults to active ship) |

**Request Body:**

```json
{
  "inventory_id": 42,
  "slot_index": 3,
  "ship_id": 123
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "component_id": 55,
    "credits_remaining": 8500
  },
  "message": "Pulse Laser MK-II installed in weapon slot 3.",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Warnings:**
- Slot must be empty (uninstall first if occupied)
- Player must be at the trading hub
- Component requirements must be met
- Inventory quantity decremented after purchase

**Error Responses:**

- `400 Bad Request`: Not at hub, out of stock, insufficient credits, slot occupied, invalid slot index, or requirements not met
- `403 Forbidden`: Not authorized

---

### Uninstall Component

**POST** `/api/players/{uuid}/ship-components/{componentId}/uninstall`

Uninstall a component from the player's ship. Optionally sell it to salvage yard.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| componentId | integer | Yes | Path | Installed component ID |
| sell | boolean | No | Query | Sell to salvage yard (default: false) |

**Query Example:**

```
POST /api/players/player-uuid/ship-components/55/uninstall?sell=true
```

**Response (Uninstall Only):**

```json
{
  "success": true,
  "data": {
    "credits_received": 0,
    "credits_total": 8500
  },
  "message": "Pulse Laser MK-II uninstalled.",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response (Sell to Yard):**

```json
{
  "success": true,
  "data": {
    "credits_received": 637,
    "credits_total": 9137
  },
  "message": "Pulse Laser MK-II sold for 637 credits.",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Sell Price Calculation:**
- Base: 50% of component's base_price
- Condition multiplier: (condition / 100)
- Added to salvage yard inventory at 70% of base price

**Warnings:**
- Selling requires being at a trading hub
- Component is deleted after uninstall
- If sold, added to salvage yard inventory

**Error Responses:**

- `400 Bad Request`: Not at trading hub (if selling)
- `403 Forbidden`: Component not owned by player
- `404 Not Found`: Component not found

---

### Sell Ship to Salvage Yard

**POST** `/api/players/{uuid}/salvage-yard/sell-ship`

Sell an entire ship to the salvage yard for credits. Components are extracted to salvage yard inventory.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| ship_uuid | string | Yes | Body | Ship UUID to sell |

**Request Body:**

```json
{
  "ship_uuid": "ship-uuid-to-sell"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "credits_received": 8750,
    "components_salvaged": 5,
    "credits_total": 58750
  },
  "message": "Ship sold for 8,750 credits. 5 components salvaged.",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Sale Price Calculation:**
- Base: 35% of ship's base_price (configurable)
- Condition multiplier: (current_hull / max_hull)
- Formula: base_price × 0.35 × condition_multiplier

**Response Fields:**

- `credits_received` (integer): Credits from ship sale
- `components_salvaged` (integer): Number of components extracted
- `credits_total` (integer): Player's total credits after sale

**Warnings:**
- Cannot sell your only ship
- Cannot sell active ship (switch first)
- Ship and all cargo deleted
- Components added to salvage yard inventory

**Error Responses:**

- `400 Bad Request`: Only ship, active ship, or other restriction
- `404 Not Found`: Ship not found

---

## Ship Upgrades

Legacy upgrade system. Deprecated in favor of component-based upgrades.

### List Upgrade Options

**GET** `/api/ships/{uuid}/upgrade-options`

List all upgradeable components for a ship with current status.

**Authentication:** Required (must own ship)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "ship_uuid": "ship-uuid",
    "ship_name": "Battlecruiser Alpha",
    "player_credits": 50000,
    "components": {
      "weapons": {
        "current_value": 25,
        "current_level": 3,
        "base_max_level": 100,
        "max_level": 105,
        "additional_levels": 5,
        "can_upgrade": true,
        "upgrade_cost": 375,
        "next_value": 30
      },
      "sensors": {
        "current_value": 3,
        "current_level": 2,
        "base_max_level": 10,
        "max_level": 12,
        "additional_levels": 2,
        "can_upgrade": true,
        "upgrade_cost": 600,
        "next_value": 4
      }
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Upgradeable Components:**
- `max_fuel`: Fuel capacity (+10 per level, max 50)
- `max_hull`: Hull strength (+10 per level, max 50)
- `weapons`: Weapon power (+5 per level, max 100)
- `cargo_hold`: Cargo capacity (+10 per level, max 100)
- `sensors`: Sensor range (+1 per level, max 10)
- `warp_drive`: Warp drive efficiency (+1 per level, max 10)

**Response Fields:**
- `current_value` (integer): Current component value
- `current_level` (integer): Upgrade level above base
- `base_max_level` (integer): Default maximum level
- `max_level` (integer): Including plan bonuses
- `additional_levels` (integer): Bonus levels from upgrade plans
- `can_upgrade` (boolean): Whether upgrade is possible
- `upgrade_cost` (integer|null): Cost for next upgrade
- `next_value` (integer|null): Value after upgrade

**Cost Formula:**
`base_cost × (1 + (current_level × 0.5))`

**Error Responses:**

- `403 Forbidden`: Not authorized
- `404 Not Found`: Ship not found

---

### Get Component Upgrade Details

**GET** `/api/ships/{uuid}/upgrade/{component}`

Get detailed upgrade information for a specific component.

**Authentication:** Required (must own ship)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |
| component | string | Yes | Path | Component name (weapons, sensors, etc.) |

**Response:**

```json
{
  "success": true,
  "data": {
    "component": "sensors",
    "current_value": 3,
    "current_level": 2,
    "max_level": 12,
    "can_upgrade": true,
    "upgrade_cost": 600,
    "next_value": 4,
    "increment": 1,
    "player_credits": 50000,
    "can_afford": true
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `increment` (integer): Value increase per upgrade
- `can_afford` (boolean): Whether player has enough credits

**Error Responses:**

- `400 Bad Request`: Invalid component name
- `403 Forbidden`: Not authorized
- `404 Not Found`: Ship not found

---

### Execute Component Upgrade

**POST** `/api/ships/{uuid}/upgrade/{component}`

Perform an upgrade on a ship component.

**Authentication:** Required (must own ship)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Ship UUID |
| component | string | Yes | Path | Component name |

**Response:**

```json
{
  "success": true,
  "data": {
    "component": "sensors",
    "old_value": 3,
    "new_value": 4,
    "new_level": 3,
    "cost": 600,
    "credits_remaining": 49400
  },
  "message": "Successfully upgraded sensors to level 3",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Special Behavior:**
- Upgrading `max_hull` also increases `hull` by the same increment

**Error Responses:**

- `400 Bad Request`: Already at max level or insufficient credits
- `403 Forbidden`: Not authorized
- `404 Not Found`: Ship not found

---

### Get Owned Plans

**GET** `/api/players/{uuid}/plans`

Get all upgrade plans owned by a player, grouped by component.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "player_uuid": "player-uuid",
    "plans_by_component": [
      {
        "component": "sensors",
        "total_additional_levels": 5,
        "plans": [
          {
            "id": 10,
            "uuid": "plan-uuid-1",
            "name": "Advanced Sensor Array Plans",
            "additional_levels": 3,
            "rarity": "rare"
          },
          {
            "id": 11,
            "uuid": "plan-uuid-2",
            "name": "Quantum Sensor Blueprints",
            "additional_levels": 2,
            "rarity": "epic"
          }
        ]
      }
    ],
    "total_plans": 2
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `plans_by_component` (array): Plans grouped by component
  - `component` (string): Component affected
  - `total_additional_levels` (integer): Sum of all plan bonuses for this component
  - `plans` (array): Individual plans
- `total_plans` (integer): Total number of plans owned

**Error Responses:**

- `403 Forbidden`: Not authorized
- `404 Not Found`: Player not found

---

### Get Upgrade Cost Formulas

**GET** `/api/upgrade-costs`

Get the upgrade cost calculation formulas and base values.

**Authentication:** Required

**Response:**

```json
{
  "success": true,
  "data": {
    "formula": "base_cost * (1 + (current_level * 0.5))",
    "base_costs": {
      "max_fuel": 100,
      "max_hull": 200,
      "weapons": 150,
      "cargo_hold": 100,
      "sensors": 300,
      "warp_drive": 500
    },
    "increments": {
      "max_fuel": 10,
      "max_hull": 10,
      "weapons": 5,
      "cargo_hold": 10,
      "sensors": 1,
      "warp_drive": 1
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

---

### Get Upgrade Limits

**GET** `/api/upgrade-limits`

Get maximum upgrade levels for each component.

**Authentication:** Required

**Response:**

```json
{
  "success": true,
  "data": {
    "base_max_levels": {
      "max_fuel": 50,
      "max_hull": 50,
      "weapons": 100,
      "cargo_hold": 100,
      "sensors": 10,
      "warp_drive": 10
    },
    "note": "Additional levels can be gained from upgrade plans"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

---

## Plans Shop

Upgrade plans increase the maximum upgrade level for ship components.

### Get Plans Shop

**GET** `/api/trading-hubs/{uuid}/plans-shop`

Check if a trading hub sells upgrade plans and list available plans.

**Authentication:** Optional (enriched if authenticated)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Trading hub UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "has_plans_shop": true,
    "trading_hub_name": "Central Hub",
    "available_plans": [
      {
        "plan": {
          "id": 5,
          "uuid": "plan-uuid",
          "name": "Advanced Sensor Plans Mk-II",
          "component": "sensors",
          "additional_levels": 2,
          "rarity": "rare",
          "price": 15000,
          "description": "Unlocks 2 additional sensor upgrade levels",
          "requirements": {
            "min_level": 10
          }
        },
        "owned_count": 1,
        "current_bonus": 2,
        "projected_bonus": 4
      }
    ]
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `has_plans_shop` (boolean): Whether hub sells plans
- `trading_hub_name` (string): Hub name
- `available_plans` (array): Plans available at this hub
  - `plan` (object): Plan details
    - `component` (string): Component affected (sensors, weapons, etc.)
    - `additional_levels` (integer): Levels granted by this plan
    - `rarity` (string): Plan rarity
    - `requirements` (object|null): Purchase requirements
  - `owned_count` (integer): How many copies player owns (if authenticated)
  - `current_bonus` (integer): Current total bonus from owned copies
  - `projected_bonus` (integer): Bonus after purchasing this plan

**Note:** Plans can be purchased multiple times and stack.

**Error Responses:**

- `404 Not Found`: Trading hub not found

---

### Get Plans Catalog

**GET** `/api/plans/catalog`

Browse all available upgrade plans.

**Authentication:** Required

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| component | string | No | Filter by component (sensors, weapons, etc.) |
| rarity | string | No | Filter by rarity |
| min_price | integer | No | Minimum price |
| max_price | integer | No | Maximum price |

**Response:**

```json
{
  "success": true,
  "data": {
    "plans": [
      {
        "id": 1,
        "uuid": "plan-uuid",
        "name": "Basic Weapon Plans",
        "component": "weapons",
        "additional_levels": 1,
        "rarity": "common",
        "price": 5000
      }
    ],
    "total_count": 24
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

---

### Purchase Upgrade Plan

**POST** `/api/players/{uuid}/plans/purchase`

Purchase an upgrade plan from a trading hub.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| plan_id | integer | Yes | Body | Plan ID |
| trading_hub_uuid | string | Yes | Body | Trading hub UUID |

**Request Body:**

```json
{
  "plan_id": 5,
  "trading_hub_uuid": "hub-uuid"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "plan": {
      "id": 5,
      "name": "Advanced Sensor Plans Mk-II",
      "component": "sensors",
      "additional_levels": 2
    },
    "cost_paid": 15000,
    "remaining_credits": 35000,
    "owned_count": 2,
    "total_bonus": 4
  },
  "message": "Upgrade plan purchased successfully",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Response Fields:**

- `owned_count` (integer): Total copies of this plan now owned
- `total_bonus` (integer): Total additional levels from all copies

**Warnings:**
- Plans can be purchased multiple times
- Effects stack (2 copies of +2 levels = +4 total)
- Requirements checked before purchase

**Error Responses:**

- `400 Bad Request`: Hub doesn't sell plans, plan not available, insufficient credits, or requirements not met
- `403 Forbidden`: Not authorized
- `404 Not Found`: Player or trading hub not found

---

## Orbital Structures

Build and manage structures in orbit around planets and moons.

### List Structures at Body

**GET** `/api/poi/{uuid}/orbital-structures`

List all orbital structures at a specific planet or moon.

**Authentication:** Not required

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | POI (planet/moon) UUID |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "uuid": "struct-uuid-1",
      "poi_id": 42,
      "player_id": 10,
      "structure_type": "mining_platform",
      "level": 2,
      "status": "operational",
      "name": "Mining Platform",
      "construction_progress": 100,
      "health": 156,
      "max_health": 156,
      "attributes": {
        "extraction_rate": 50,
        "efficiency": 1.2
      },
      "player": {
        "uuid": "player-uuid",
        "call_sign": "CommanderAlpha"
      }
    }
  ],
  "message": "Orbital structures retrieved",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Structure Types:**
- `mining_platform`: Automated resource extraction
- `orbital_defense`: Defensive weapons platform
- `magnetic_mine`: Proximity mine (detonates on hostile approach)
- `sensor_array`: Extended sensor coverage
- `research_station`: Science and research

**Status Values:**
- `constructing`: Under construction (0-100% progress)
- `operational`: Fully operational
- `damaged`: Damaged but functional
- `destroyed`: Destroyed/demolished

**Error Responses:**

- `404 Not Found`: POI not found

---

### List Player's Structures

**GET** `/api/players/{uuid}/orbital-structures`

List all orbital structures owned by a player across all locations.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "uuid": "struct-uuid-1",
      "structure_type": "mining_platform",
      "level": 2,
      "status": "operational",
      "poi": {
        "uuid": "poi-uuid",
        "name": "Kepler-186f",
        "type": "terrestrial"
      }
    }
  ],
  "message": "Player orbital structures retrieved",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Error Responses:**

- `401 Unauthorized`: Not authenticated
- `403 Forbidden`: Not authorized

---

### Build Orbital Structure

**POST** `/api/players/{uuid}/orbital-structures/build`

Build a new orbital structure at a planet or moon.

**Authentication:** Required (must own player)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Player UUID |
| poi_uuid | string | Yes | Body | Planet/moon POI UUID |
| type | string | Yes | Body | Structure type |

**Request Body:**

```json
{
  "poi_uuid": "planet-uuid",
  "type": "mining_platform"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "uuid": "new-struct-uuid",
    "structure_type": "mining_platform",
    "level": 1,
    "status": "constructing",
    "construction_progress": 0,
    "poi": {
      "uuid": "planet-uuid",
      "name": "Kepler-186f"
    },
    "player": {
      "uuid": "player-uuid",
      "call_sign": "CommanderAlpha"
    }
  },
  "message": "Construction of Mining Platform has begun",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Structure Costs (Level 1):**
- `mining_platform`: 10,000 credits
- `orbital_defense`: 15,000 credits
- `magnetic_mine`: 5,000 credits
- `sensor_array`: 12,000 credits
- `research_station`: 20,000 credits

**Cost Scaling:**
- Level 2+: base_cost × (1 + ((level - 1) × 0.5))

**Restrictions:**
- Must be in the same star system
- Can only build on planets/moons
- Per-body limits apply (varies by type)

**Error Responses:**

- `400 Bad Request`: Invalid type, not a valid body, maximum per body reached, insufficient credits, or not in same system
- `401 Unauthorized`: Not authenticated
- `403 Forbidden`: Not authorized
- `404 Not Found`: Player or POI not found

---

### Get Structure Details

**GET** `/api/orbital-structures/{uuid}`

Get detailed information about a specific orbital structure.

**Authentication:** Not required

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Structure UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "uuid": "struct-uuid",
    "structure_type": "mining_platform",
    "level": 3,
    "status": "operational",
    "name": "Mining Platform",
    "construction_progress": 100,
    "construction_started_at": "2026-02-15T08:00:00Z",
    "construction_completed_at": "2026-02-15T18:00:00Z",
    "health": 195,
    "max_health": 195,
    "attributes": {
      "extraction_rate": 75,
      "efficiency": 1.6
    },
    "credits_per_cycle": 500,
    "minerals_per_cycle": 25,
    "poi": {
      "uuid": "planet-uuid",
      "name": "Kepler-186f"
    },
    "player": {
      "uuid": "player-uuid",
      "call_sign": "CommanderAlpha"
    }
  },
  "message": "Orbital structure retrieved",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Error Responses:**

- `404 Not Found`: Structure not found

---

### Upgrade Structure

**PUT** `/api/orbital-structures/{uuid}/upgrade`

Upgrade an orbital structure to the next level (max level 5).

**Authentication:** Required (must own structure)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Structure UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "uuid": "struct-uuid",
    "structure_type": "mining_platform",
    "level": 4,
    "status": "constructing",
    "construction_progress": 0
  },
  "message": "Upgrading Mining Platform to level 4",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Upgrade Effects:**
- Health scales: base_health × (1 + ((level - 1) × 0.3))
- Mining damage: mine_damage × (1 + ((level - 1) × 0.3))
- Construction cycle required: Status returns to `constructing` at 0%

**Warnings:**
- Structure must be operational to upgrade
- Maximum level is 5
- Credits deducted before construction starts

**Error Responses:**

- `400 Bad Request`: Not operational, already max level, or insufficient credits
- `403 Forbidden`: Not owned by player
- `404 Not Found`: Structure not found

---

### Demolish Structure

**DELETE** `/api/orbital-structures/{uuid}`

Demolish (scuttle) an orbital structure.

**Authentication:** Required (must own structure)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Structure UUID |

**Response:**

```json
{
  "success": true,
  "data": null,
  "message": "Mining Platform has been scuttled",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Warnings:**
- Structure is not deleted, status set to `destroyed`
- No refund provided
- Permanent action

**Error Responses:**

- `403 Forbidden`: Not owned by player
- `404 Not Found`: Structure not found

---

### Collect Resources

**POST** `/api/orbital-structures/{uuid}/collect`

Collect resources from a mining platform.

**Authentication:** Required (must own structure)

**Parameters:**

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| uuid | string | Yes | Path | Structure UUID |

**Response:**

```json
{
  "success": true,
  "data": {
    "extracted": {
      "minerals": 150,
      "credits": 0
    }
  },
  "message": "Resources collected",
  "meta": {
    "timestamp": "2026-02-16T10:30:00Z",
    "request_id": "abc-123-def-456"
  }
}
```

**Warnings:**
- Only works for `mining_platform` structures
- Structure must be `operational`
- Extraction based on level and attributes

**Error Responses:**

- `400 Bad Request`: Not a mining platform or not operational
- `403 Forbidden`: Not owned by player
- `404 Not Found`: Structure not found

---

## Error Response Format

All endpoints follow a consistent error response format:

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
    "request_id": "abc-123-def-456"
  }
}
```

**Common Error Codes:**
- `UNAUTHORIZED` (401): Not authenticated
- `FORBIDDEN` (403): Not authorized to access resource
- `NOT_FOUND` (404): Resource not found
- `VALIDATION_ERROR` (422): Invalid input data
- `ERROR` (400): Generic error (check message)

---

## Authentication

All protected endpoints require a valid authentication token passed in the `Authorization` header:

```
Authorization: Bearer {token}
```

Tokens are obtained via the `/api/auth/login` endpoint and managed by Laravel Sanctum.

---

## Rate Limiting

API endpoints are rate-limited per user. Default limits apply unless otherwise specified. Refer to the main API documentation for rate limit details.

---

## Changelog

- **2026-02-16**: Comprehensive documentation created covering all ship service endpoints
- Includes repair, maintenance, shops, upgrades, salvage, and orbital structures
- Deprecated legacy upgrade system documented

---

## Support

For questions or issues with these endpoints, refer to:
- Project repository: `/home/mdhas/workspace/space-wars-3002`
- CLAUDE.md for development guidance
- Laravel logs for debugging

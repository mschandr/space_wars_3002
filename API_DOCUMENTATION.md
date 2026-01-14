# Space Wars 3002 - Complete API Documentation

## Overview

Space Wars 3002 provides a comprehensive RESTful API for managing players, ships, trading, combat, colonies, and more in a vast procedurally-generated galaxy.

**Base URL:** `/api`
**Authentication:** Laravel Sanctum (Bearer tokens)
**Response Format:** JSON
**API Version:** 1.0

---

## Table of Contents

1. [Authentication](#authentication)
2. [Galaxy Information](#galaxy-information)
3. [Player Management](#player-management)
4. [Ships](#ships)
5. [Navigation & Travel](#navigation--travel)
6. [Trading & Economy](#trading--economy)
7. [Combat Systems](#combat-systems)
8. [Colonies](#colonies)
9. [Leaderboards & Statistics](#leaderboards--statistics)
10. [Victory Conditions](#victory-conditions)
11. [Market Events](#market-events)
12. [Pirate Factions](#pirate-factions)
13. [Notifications](#notifications)
14. [Mirror Universe](#mirror-universe)
15. [Mining](#mining)
16. [Error Handling](#error-handling)

---

## Authentication

All authentication endpoints return a Bearer token for use with protected routes.

### Register
```
POST /api/auth/register
```

**Request Body:**
```json
{
  "name": "string",
  "email": "string",
  "password": "string",
  "password_confirmation": "string"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "string", "email": "string" },
    "token": "bearer_token_here"
  },
  "message": "User registered successfully"
}
```

### Login
```
POST /api/auth/login
```

**Request Body:**
```json
{
  "email": "string",
  "password": "string"
}
```

### Logout
```
POST /api/auth/logout
Headers: Authorization: Bearer {token}
```

### Get Current User
```
GET /api/auth/me
Headers: Authorization: Bearer {token}
```

---

## Galaxy Information

### List All Galaxies
```
GET /api/galaxies
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "uuid": "string",
      "name": "string",
      "dimensions": { "width": 300, "height": 300 },
      "statistics": {
        "total_systems": 3000,
        "active_players": 42
      }
    }
  ]
}
```

### Get Galaxy Details
```
GET /api/galaxies/{uuid}
```

### Get Galaxy Statistics
```
GET /api/galaxies/{uuid}/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "galaxy": { "uuid": "string", "name": "string" },
    "players": { "total": 50, "active": 42, "destroyed": 8 },
    "economy": {
      "total_credits_in_circulation": 5000000,
      "average_player_credits": 100000,
      "trading_hubs": 120
    },
    "colonies": { "total": 85, "total_population": 250000 },
    "combat": { "total_pvp_challenges": 156, "completed_battles": 320 },
    "infrastructure": { "warp_gates": 450, "sectors": 100 }
  }
}
```

### Get Galaxy Map
```
GET /api/galaxies/{uuid}/map?player_uuid=optional
```

Returns optimized map data for rendering, including POIs, warp gates, and sectors.

### Get Sector Information
```
GET /api/sectors/{uuid}
```

---

## Player Management

### List Players (for current user)
```
GET /api/players
Headers: Authorization: Bearer {token}
```

### Create Player
```
POST /api/players
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "galaxy_uuid": "string",
  "call_sign": "string"
}
```

### Get Player Details
```
GET /api/players/{uuid}
Headers: Authorization: Bearer {token}
```

### Update Player
```
PATCH /api/players/{uuid}
Headers: Authorization: Bearer {token}
```

### Delete Player
```
DELETE /api/players/{uuid}
Headers: Authorization: Bearer {token}
```

### Get Player Status
```
GET /api/players/{uuid}/status
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "uuid": "string",
    "call_sign": "string",
    "level": 5,
    "experience": 2500,
    "credits": 150000,
    "current_location": { "name": "Alpha Centauri", "coordinates": { "x": 100, "y": 150 } },
    "active_ship": { "name": "Millennium Falcon", "hull": 150, "fuel": 80 },
    "status": "active"
  }
}
```

### Get Player Statistics
```
GET /api/players/{uuid}/stats
Headers: Authorization: Bearer {token}
```

---

## Ships

### Get Active Ship
```
GET /api/players/{playerUuid}/ship
Headers: Authorization: Bearer {token}
```

### Get Ship Status
```
GET /api/ships/{uuid}/status
Headers: Authorization: Bearer {token}
```

### Get Fuel Status
```
GET /api/ships/{uuid}/fuel
Headers: Authorization: Bearer {token}
```

### Manually Regenerate Fuel
```
POST /api/ships/{uuid}/regenerate-fuel
Headers: Authorization: Bearer {token}
```

### Get Ship Upgrades
```
GET /api/ships/{uuid}/upgrades
Headers: Authorization: Bearer {token}
```

### Get Damage Status
```
GET /api/ships/{uuid}/damage
Headers: Authorization: Bearer {token}
```

### Rename Ship
```
PATCH /api/ships/{uuid}/name
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "name": "string"
}
```

### View Ship Catalog
```
GET /api/ships/catalog
Headers: Authorization: Bearer {token}
```

### Purchase Ship
```
POST /api/players/{uuid}/ships/purchase
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "ship_blueprint_id": 1,
  "name": "string"
}
```

### Switch Active Ship
```
POST /api/players/{uuid}/ships/switch
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "ship_uuid": "string"
}
```

### View Fleet
```
GET /api/players/{uuid}/ships/fleet
Headers: Authorization: Bearer {token}
```

---

## Navigation & Travel

### Get Current Location
```
GET /api/players/{uuid}/location
Headers: Authorization: Bearer {token}
```

### Scan Nearby Systems
```
GET /api/players/{uuid}/nearby-systems
Headers: Authorization: Bearer {token}
```

Returns systems within sensor range.

### Scan Local Area
```
GET /api/players/{uuid}/scan-local
Headers: Authorization: Bearer {token}
```

Returns ships, colonies, and other features at current location.

### List Warp Gates
```
GET /api/warp-gates/{locationUuid}
Headers: Authorization: Bearer {token}
```

### Travel Via Warp Gate
```
POST /api/players/{uuid}/travel/warp-gate
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "warp_gate_uuid": "string"
}
```

### Jump to Coordinates
```
POST /api/players/{uuid}/travel/coordinate
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "x": 100,
  "y": 150
}
```

### Direct Jump to Trading Hub
```
POST /api/players/{uuid}/travel/direct-jump
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "trading_hub_uuid": "string"
}
```

### Preview XP for Travel
```
GET /api/travel/xp-preview?from_x=100&from_y=150&to_x=200&to_y=250
Headers: Authorization: Bearer {token}
```

### Calculate Fuel Cost
```
GET /api/travel/fuel-cost?distance=100&warp_drive_level=3
Headers: Authorization: Bearer {token}
```

---

## Trading & Economy

### List Nearby Trading Hubs
```
GET /api/trading-hubs?player_uuid={uuid}
Headers: Authorization: Bearer {token}
```

### Get Trading Hub Details
```
GET /api/trading-hubs/{uuid}
Headers: Authorization: Bearer {token}
```

### Get Hub Inventory
```
GET /api/trading-hubs/{uuid}/inventory
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "trading_hub": { "uuid": "string", "name": "string" },
    "inventory": [
      {
        "mineral": { "name": "Gold", "symbol": "Au" },
        "quantity": 1000,
        "price_per_unit": 150.50,
        "total_value": 150500
      }
    ]
  }
}
```

### List All Minerals
```
GET /api/minerals
Headers: Authorization: Bearer {token}
```

### Buy Minerals
```
POST /api/trading-hubs/{uuid}/buy
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "mineral_uuid": "string",
  "quantity": 100
}
```

### Sell Minerals
```
POST /api/trading-hubs/{uuid}/sell
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "mineral_uuid": "string",
  "quantity": 50
}
```

### View Player Cargo
```
GET /api/players/{uuid}/cargo
Headers: Authorization: Bearer {token}
```

### Calculate Affordability
```
GET /api/trading/affordability?player_uuid={uuid}&mineral_uuid={uuid}&quantity=100
Headers: Authorization: Bearer {token}
```

---

## Combat Systems

### Pirate Combat

#### Check for Pirates
```
GET /api/warp-gates/{warpGateUuid}/pirates
Headers: Authorization: Bearer {token}
```

#### Get Encounter Details
```
GET /api/pirate-encounters/{encounterUuid}
Headers: Authorization: Bearer {token}
```

#### Preview Combat Odds
```
GET /api/players/{uuid}/combat/preview
Headers: Authorization: Bearer {token}
```

#### Engage in Combat
```
POST /api/players/{uuid}/combat/engage
Headers: Authorization: Bearer {token}
```

#### Attempt Escape
```
POST /api/players/{uuid}/combat/escape
Headers: Authorization: Bearer {token}
```

#### Surrender
```
POST /api/players/{uuid}/combat/surrender
Headers: Authorization: Bearer {token}
```

#### Collect Salvage
```
POST /api/players/{uuid}/combat/salvage
Headers: Authorization: Bearer {token}
```

### PvP Combat

#### Issue Challenge
```
POST /api/players/{uuid}/pvp/challenge
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "challenged_player_uuid": "string",
  "wager_credits": 10000
}
```

#### List Challenges
```
GET /api/players/{uuid}/pvp/challenges?status=pending
Headers: Authorization: Bearer {token}
```

#### Accept Challenge
```
POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept
Headers: Authorization: Bearer {token}
```

#### Decline Challenge
```
POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/decline
Headers: Authorization: Bearer {token}
```

#### Cancel Challenge
```
DELETE /api/players/{uuid}/pvp/challenge/{challengeUuid}
Headers: Authorization: Bearer {token}
```

#### Get Combat Session
```
GET /api/combat-sessions/{uuid}
Headers: Authorization: Bearer {token}
```

### Team Combat

#### Invite Ally
```
POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/invite
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "invited_player_uuid": "string",
  "side": "attacker"
}
```

#### List Team Invitations
```
GET /api/players/{uuid}/team-invitations
Headers: Authorization: Bearer {token}
```

#### Accept Team Invitation
```
POST /api/players/{uuid}/team-invitations/{invitationId}/accept
Headers: Authorization: Bearer {token}
```

#### Decline Team Invitation
```
POST /api/players/{uuid}/team-invitations/{invitationId}/decline
Headers: Authorization: Bearer {token}
```

#### View Team Composition
```
GET /api/pvp/challenge/{challengeUuid}/teams
Headers: Authorization: Bearer {token}
```

#### Accept Team Challenge
```
POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept-team
Headers: Authorization: Bearer {token}
```

### Colony Combat

#### View Colony Defenses
```
GET /api/colonies/{uuid}/defenses
Headers: Authorization: Bearer {token}
```

#### Attack Colony
```
POST /api/players/{uuid}/attack-colony/{colonyUuid}
Headers: Authorization: Bearer {token}
```

#### Fortify Colony
```
POST /api/colonies/{uuid}/fortify
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "credits": 10000
}
```

---

## Colonies

### List Player Colonies
```
GET /api/players/{uuid}/colonies
Headers: Authorization: Bearer {token}
```

### Establish Colony
```
POST /api/players/{uuid}/colonies
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "poi_uuid": "string",
  "name": "New Terra"
}
```

### Get Colony Details
```
GET /api/colonies/{uuid}
Headers: Authorization: Bearer {token}
```

### Update Colony
```
PUT /api/colonies/{uuid}
Headers: Authorization: Bearer {token}
```

### Abandon Colony
```
DELETE /api/colonies/{uuid}
Headers: Authorization: Bearer {token}
```

### Get Production Stats
```
GET /api/colonies/{uuid}/production
Headers: Authorization: Bearer {token}
```

### Upgrade Development
```
POST /api/colonies/{uuid}/upgrade
Headers: Authorization: Bearer {token}
```

### Get Ship Production
```
GET /api/colonies/{uuid}/ship-production
Headers: Authorization: Bearer {token}
```

### Colony Buildings

#### List Buildings
```
GET /api/colonies/{uuid}/buildings
Headers: Authorization: Bearer {token}
```

#### Construct Building
```
POST /api/colonies/{uuid}/buildings
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "building_type": "mining_facility"
}
```

#### Upgrade Building
```
PUT /api/colonies/{uuid}/buildings/{buildingUuid}
Headers: Authorization: Bearer {token}
```

#### Demolish Building
```
DELETE /api/colonies/{uuid}/buildings/{buildingUuid}
Headers: Authorization: Bearer {token}
```

---

## Leaderboards & Statistics

### Overall Leaderboard
```
GET /api/galaxies/{galaxyUuid}/leaderboards/overall?limit=100
```

Returns top players by level, XP, and credits.

### Combat Leaderboard
```
GET /api/galaxies/{galaxyUuid}/leaderboards/combat?limit=100
```

Returns top players by PvP wins, pirate kills, and K/D ratio.

### Economic Leaderboard
```
GET /api/galaxies/{galaxyUuid}/leaderboards/economic?limit=100
```

Returns top players by net worth, colonies owned, and trade volume.

### Colonial Leaderboard
```
GET /api/galaxies/{galaxyUuid}/leaderboards/colonial?limit=100
```

Returns top players by colony count, population, and development.

### Player Rankings
```
GET /api/players/{uuid}/ranking
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "player": { "uuid": "string", "call_sign": "string" },
    "rankings": {
      "overall": 15,
      "economic": 22,
      "colonial": 8
    },
    "total_players": 42
  }
}
```

### Detailed Player Statistics
```
GET /api/players/{uuid}/statistics
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "player": { "uuid": "string", "call_sign": "string", "level": 10 },
    "statistics": {
      "combat": {
        "total_battles": 45,
        "victories": 32,
        "defeats": 13,
        "total_damage_dealt": 125000,
        "ships_destroyed": 18
      },
      "economic": {
        "current_credits": 500000,
        "cargo_value": 75000,
        "total_colonies": 5
      },
      "exploration": {
        "systems_visited": 120,
        "current_location": { "name": "Sol", "type": "star" }
      }
    }
  }
}
```

---

## Victory Conditions

### Get Victory Conditions
```
GET /api/galaxies/{galaxyUuid}/victory-conditions
```

**Response:**
```json
{
  "success": true,
  "data": {
    "galaxy": { "uuid": "string", "name": "string" },
    "victory_conditions": {
      "merchant_empire": {
        "name": "Merchant Empire",
        "description": "Accumulate vast wealth",
        "requirement": { "credits": 1000000000 }
      },
      "colonization": {
        "name": "Colonization Victory",
        "requirement": { "population_share": 50 }
      }
    }
  }
}
```

### Get Player Victory Progress
```
GET /api/players/{uuid}/victory-progress
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "player": { "uuid": "string", "call_sign": "string" },
    "victory_paths": {
      "merchant_empire": {
        "progress_percent": 25.5,
        "achieved": false,
        "current": 255000000,
        "required": 1000000000
      },
      "colonization": {
        "progress_percent": 12.3,
        "achieved": false,
        "current_population": 30750,
        "galaxy_population": 250000
      }
    },
    "closest_to_victory": {
      "path": "merchant",
      "name": "Merchant Empire",
      "progress_percent": 25.5
    }
  }
}
```

### Get Victory Leaders
```
GET /api/galaxies/{galaxyUuid}/victory-leaders
```

Returns top 5 players closest to victory in each path.

---

## Market Events

### List Galaxy Market Events
```
GET /api/galaxies/{galaxyUuid}/market-events?event_type=shortage&mineral=Gold
```

**Response:**
```json
{
  "success": true,
  "data": {
    "galaxy": { "uuid": "string", "name": "string" },
    "total_active_events": 5,
    "events": [
      {
        "uuid": "string",
        "event_type": "shortage",
        "mineral": { "name": "Gold", "symbol": "Au" },
        "price_modifier": 1.5,
        "trading_hub": { "uuid": "string", "name": "Trade Station Alpha" },
        "expires_at": "2026-01-14T12:00:00Z",
        "time_remaining_seconds": 3600
      }
    ]
  }
}
```

### Get Market Event Details
```
GET /api/market-events/{eventUuid}
```

### Get Trading Hub Events
```
GET /api/trading-hubs/{uuid}/active-events
Headers: Authorization: Bearer {token}
```

---

## Pirate Factions

### List Pirate Factions
```
GET /api/galaxies/{galaxyUuid}/pirate-factions
```

### Get Faction Details
```
GET /api/pirate-factions/{factionUuid}
```

### Get Player Reputation
```
GET /api/players/{uuid}/pirate-reputation
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "player": { "uuid": "string", "call_sign": "string" },
    "faction_reputations": [
      {
        "faction": { "uuid": "string", "name": "Crimson Raiders" },
        "reputation": -50,
        "standing": "Hostile",
        "effects": {
          "description": "Pirates will actively hunt you",
          "drawbacks": ["Frequent ambushes", "No quarter given"]
        }
      }
    ]
  }
}
```

### List Faction Captains
```
GET /api/pirate-factions/{factionUuid}/captains
```

---

## Notifications

### List Notifications
```
GET /api/players/{uuid}/notifications?read=false&type=combat&limit=50
Headers: Authorization: Bearer {token}
```

### Get Unread Count
```
GET /api/players/{uuid}/notifications/unread
Headers: Authorization: Bearer {token}
```

### Mark as Read
```
POST /api/players/{uuid}/notifications/{notificationId}/read
Headers: Authorization: Bearer {token}
```

### Delete Notification
```
DELETE /api/players/{uuid}/notifications/{notificationId}
Headers: Authorization: Bearer {token}
```

### Mark All as Read
```
POST /api/players/{uuid}/notifications/mark-all-read
Headers: Authorization: Bearer {token}
```

### Clear Read Notifications
```
POST /api/players/{uuid}/notifications/clear-read
Headers: Authorization: Bearer {token}
```

---

## Mirror Universe

### Check Mirror Universe Access
```
GET /api/players/{uuid}/mirror-access
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "player": { "uuid": "string", "call_sign": "string" },
    "access": {
      "has_sufficient_sensors": true,
      "required_sensor_level": 5,
      "current_sensor_level": 5,
      "can_travel": true,
      "cooldown_remaining_hours": 0
    },
    "mirror_gate": {
      "uuid": "string",
      "location": { "name": "Mirror Portal", "x": 150, "y": 150 },
      "is_at_gate": true
    }
  }
}
```

### Get Mirror Gate Location
```
GET /api/galaxies/{uuid}/mirror-gate
Headers: Authorization: Bearer {token}
```

### Enter Mirror Universe
```
POST /api/players/{uuid}/mirror/enter
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "travel_result": { "destination": {...}, "fuel_consumed": 50 },
    "message": "Successfully entered the mirror universe",
    "warnings": {
      "doubled_pirate_difficulty": true,
      "doubled_resources": true,
      "return_cooldown_active": true,
      "next_available_return": "2026-01-14T12:00:00Z"
    }
  }
}
```

---

## Mining

### Get Mining Opportunities
```
GET /api/poi/{uuid}/mining-opportunities
Headers: Authorization: Bearer {token}
```

### Start Automated Mining (Colony)
```
POST /api/colonies/{uuid}/mining/start
Headers: Authorization: Bearer {token}
```

### Extract Resources (Ship)
```
POST /api/ships/{uuid}/mining/extract
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "poi_uuid": "string"
}
```

---

## Error Handling

All API errors follow this format:

**Error Response:**
```json
{
  "success": false,
  "error": {
    "message": "Error description here",
    "code": "ERROR_CODE"
  }
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request (validation error, insufficient resources, etc.)
- `401` - Unauthorized (invalid/missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Unprocessable Entity (validation failed)
- `500` - Internal Server Error

**Common Error Codes:**
- `INSUFFICIENT_FUNDS` - Not enough credits
- `INSUFFICIENT_FUEL` - Not enough fuel
- `INVALID_LOCATION` - Player not at required location
- `COOLDOWN_ACTIVE` - Action is on cooldown
- `VALIDATION_ERROR` - Input validation failed
- `RESOURCE_NOT_FOUND` - Requested resource doesn't exist

---

## Rate Limiting

The API implements rate limiting to prevent abuse:

- **Authenticated requests:** 60 per minute
- **Unauthenticated requests:** 30 per minute

Rate limit headers are included in all responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1610000000
```

---

## Pagination

List endpoints support pagination via query parameters:

```
GET /api/endpoint?page=2&per_page=50
```

**Pagination Response:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 2,
    "per_page": 50,
    "total": 250,
    "last_page": 5
  },
  "links": {
    "first": "/api/endpoint?page=1",
    "last": "/api/endpoint?page=5",
    "prev": "/api/endpoint?page=1",
    "next": "/api/endpoint?page=3"
  }
}
```

---

## WebSocket Events (Future)

Real-time events will be broadcast via WebSockets:

- `player.location.updated` - Player moved
- `combat.started` - Combat initiated
- `pvp.challenge.received` - New PvP challenge
- `colony.attacked` - Colony under siege
- `market.event.created` - New market event

---

## Changelog

### Version 1.0 (2026-01-13)
- Complete API implementation
- 174+ endpoints across 24 categories
- Full combat system (PvP, Team, Colony, Pirate)
- Leaderboards and victory conditions
- Mirror universe support
- Comprehensive documentation

---

## Support & Feedback

- **Documentation:** This file
- **API Issues:** https://github.com/anthropics/space-wars-3002/issues
- **Developer Guide:** See `/docs` folder

---

**Total Endpoints:** 174+
**Last Updated:** 2026-01-13
**API Stability:** Production Ready

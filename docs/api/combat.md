# Combat API Documentation

This document provides comprehensive documentation for all Combat-related endpoints in Space Wars 3002.

## Table of Contents

- [PvE Combat (Pirates)](#pve-combat-pirates)
  - [Check Pirate Presence](#check-pirate-presence)
  - [Get Encounter Details](#get-encounter-details)
  - [Preview Combat](#preview-combat)
  - [Attempt Escape](#attempt-escape)
  - [Surrender](#surrender)
  - [Engage Combat](#engage-combat)
  - [Collect Salvage](#collect-salvage)
- [PvP Combat](#pvp-combat)
  - [Issue Challenge](#issue-challenge)
  - [List Challenges](#list-challenges)
  - [Accept Challenge](#accept-challenge)
  - [Decline Challenge](#decline-challenge)
  - [Cancel Challenge](#cancel-challenge)
  - [Get Combat Session](#get-combat-session)
- [Team Combat](#team-combat)
  - [Invite Ally](#invite-ally)
  - [List Team Invitations](#list-team-invitations)
  - [Accept Team Invitation](#accept-team-invitation)
  - [Decline Team Invitation](#decline-team-invitation)
  - [Get Team Composition](#get-team-composition)
  - [Accept Team Challenge](#accept-team-challenge)

---

## PvE Combat (Pirates)

### Check Pirate Presence

Check if a warp gate has pirate presence before traveling through it.

**HTTP Method:** `GET`

**URL:** `/api/warp-gates/{warpGateUuid}/pirates`

**Description:** Checks if pirates are currently stationed at a specific warp gate. If pirates are present, returns basic encounter information.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `warpGateUuid` | string (UUID) | Yes | Path | UUID of the warp gate to check |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "has_pirates": true,
    "encounter": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "captain": {
        "name": "Captain Blackbeard",
        "reputation": 85,
        "faction": {
          "name": "Red Star Corsairs",
          "uuid": "650e8400-e29b-41d4-a716-446655440000"
        }
      },
      "fleet": {
        "total_ships": 3,
        "total_combat_power": 450,
        "ships": [
          {
            "ship_name": "Crimson Blade",
            "hull": 1200,
            "weapons": 8,
            "shields": 6,
            "speed": 7
          }
        ]
      },
      "difficulty": "hard",
      "is_active": true
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "750e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `has_pirates` | boolean | Whether pirates are present at this warp gate |
| `encounter` | object/null | Encounter details if pirates are present, null otherwise |
| `encounter.uuid` | string | UUID of the pirate encounter |
| `encounter.captain` | object | Information about the pirate captain |
| `encounter.fleet` | object | Details about the pirate fleet composition |
| `encounter.difficulty` | string | Difficulty rating (easy, medium, hard, extreme) |
| `encounter.is_active` | boolean | Whether the encounter is currently active |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Warp gate with specified UUID not found |

---

### Get Encounter Details

Get comprehensive details about a specific pirate encounter.

**HTTP Method:** `GET`

**URL:** `/api/pirate-encounters/{encounterUuid}`

**Description:** Retrieves detailed information about a pirate encounter including fleet composition, captain stats, and tactical information.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `encounterUuid` | string (UUID) | Yes | Path | UUID of the pirate encounter |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "encounter": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "captain": {
        "name": "Captain Blackbeard",
        "reputation": 85,
        "faction": {
          "name": "Red Star Corsairs",
          "uuid": "650e8400-e29b-41d4-a716-446655440000"
        }
      },
      "fleet": {
        "total_ships": 3,
        "total_combat_power": 450,
        "ships": [
          {
            "ship_name": "Crimson Blade",
            "hull": 1200,
            "weapons": 8,
            "shields": 6,
            "speed": 7,
            "warp_drive": 5
          }
        ]
      },
      "warp_gate": {
        "uuid": "750e8400-e29b-41d4-a716-446655440000",
        "from_poi": {
          "name": "Alpha Centauri"
        },
        "to_poi": {
          "name": "Beta Prime"
        }
      },
      "is_active": true
    },
    "details": {
      "total_hull": 3600,
      "average_weapons": 7,
      "average_shields": 5,
      "fastest_ship_speed": 9,
      "recommended_player_power": 400,
      "estimated_difficulty": "hard"
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "850e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `encounter` | object | Full encounter information |
| `details` | object | Tactical analysis of the encounter |
| `details.total_hull` | integer | Combined hull points of all pirate ships |
| `details.average_weapons` | integer | Average weapon level across the fleet |
| `details.average_shields` | integer | Average shield level across the fleet |
| `details.fastest_ship_speed` | integer | Speed of the fastest ship (important for escape attempts) |
| `details.recommended_player_power` | integer | Suggested combat power for player to engage |
| `details.estimated_difficulty` | string | Difficulty assessment (easy, medium, hard, extreme) |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Pirate encounter not found or no longer active |

---

### Preview Combat

Get a combat preview showing estimated win chance and tactical analysis before engaging.

**HTTP Method:** `GET`

**URL:** `/api/players/{uuid}/combat/preview`

**Description:** Analyzes the player's ship capabilities against a pirate fleet and provides combat predictions including win probability and escape chances.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player |
| `encounter_uuid` | string (UUID) | Yes | Query/Body | UUID of the pirate encounter to preview |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "combat_preview": {
      "win_probability": 0.65,
      "estimated_rounds": 8,
      "player_hull_loss_estimate": 450,
      "difficulty_rating": "medium",
      "recommended": true,
      "warnings": [
        "Your shields are below recommended level for this encounter"
      ]
    },
    "escape_analysis": {
      "escape_probability": 0.45,
      "fastest_enemy_speed": 9,
      "player_speed": 7,
      "speed_advantage": -2,
      "warp_drive_advantage": 0,
      "recommended_action": "Engage - escape unlikely due to speed disadvantage"
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "950e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `combat_preview.win_probability` | float | Estimated chance of victory (0.0 to 1.0) |
| `combat_preview.estimated_rounds` | integer | Predicted number of combat rounds |
| `combat_preview.player_hull_loss_estimate` | integer | Estimated hull damage player will take |
| `combat_preview.difficulty_rating` | string | Overall difficulty (trivial, easy, medium, hard, extreme) |
| `combat_preview.recommended` | boolean | Whether engaging is recommended |
| `combat_preview.warnings` | array | List of tactical warnings |
| `escape_analysis.escape_probability` | float | Chance of successfully escaping (0.0 to 1.0) |
| `escape_analysis.speed_advantage` | integer | Player speed minus fastest enemy speed |
| `escape_analysis.warp_drive_advantage` | integer | Player warp drive advantage |
| `escape_analysis.recommended_action` | string | Tactical recommendation |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ERROR | Player has no active ship |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player or encounter not found |
| 422 | VALIDATION_ERROR | Invalid encounter_uuid parameter |

---

### Attempt Escape

Attempt to escape from a pirate encounter without engaging in combat.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/combat/escape`

**Description:** Player attempts to flee from pirates. Success depends on ship speed, warp drive level, and pirate fleet composition. Failed escape attempts may result in damage.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player |
| `encounter_uuid` | string (UUID) | Yes | Body | UUID of the pirate encounter |

#### Request Body Example

```json
{
  "encounter_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

#### Success Response (Escaped)

```json
{
  "success": true,
  "data": {
    "escaped": true,
    "message": "Your superior warp drive allowed you to jump away before they could close the gap!"
  },
  "message": "Successfully escaped from pirates",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "a50e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Failure Response (Intercepted)

```json
{
  "success": true,
  "data": {
    "escaped": false,
    "message": "The Crimson Blade matched your speed and cut off your escape vector!",
    "interceptor": {
      "name": "Crimson Blade",
      "speed": 9,
      "warp_drive": 7
    }
  },
  "message": "Escape failed - pirates intercepted",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "b50e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `escaped` | boolean | Whether the escape attempt succeeded |
| `message` | string | Narrative description of the escape attempt |
| `interceptor` | object/null | Details about the pirate ship that intercepted (only if escaped=false) |
| `interceptor.name` | string | Name of the intercepting ship |
| `interceptor.speed` | integer | Speed level of the interceptor |
| `interceptor.warp_drive` | integer | Warp drive level of the interceptor |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ERROR | Player has no active ship |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player or encounter not found |
| 422 | VALIDATION_ERROR | Invalid encounter_uuid parameter |

#### Warnings

- Escape attempts are recorded and count as an encounter
- Failed escape attempts may trigger combat automatically
- Speed and warp drive upgrades significantly improve escape chances

---

### Surrender

Surrender to pirates, giving up cargo and potentially other resources to avoid combat.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/combat/surrender`

**Description:** Player surrenders to pirates without fighting. Pirates will take cargo, may steal upgrade plans, downgrade ship components, or steal installed upgrades depending on their faction and your cargo value.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player |
| `encounter_uuid` | string (UUID) | Yes | Body | UUID of the pirate encounter |

#### Request Body Example

```json
{
  "encounter_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

#### Response Structure

```json
{
  "success": true,
  "data": {
    "cargo_lost": [
      {
        "mineral": "Gold Ore",
        "quantity": 50
      },
      {
        "mineral": "Platinum",
        "quantity": 25
      }
    ],
    "plans_stolen": [
      {
        "plan": "Advanced Weapons System",
        "rarity": "rare"
      }
    ],
    "components_downgraded": [
      {
        "component": "weapons",
        "old_level": 8,
        "new_level": 7
      }
    ],
    "upgrades_stolen": [
      {
        "component": "Shield Regenerator Mk3",
        "type": "shields"
      }
    ],
    "message": "The pirates took your cargo and ransacked your ship. They stripped some components before letting you go."
  },
  "message": "Surrender processed",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "c50e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `cargo_lost` | array | List of minerals taken by pirates |
| `cargo_lost[].mineral` | string | Name of the mineral |
| `cargo_lost[].quantity` | integer | Quantity lost |
| `plans_stolen` | array | Upgrade plans stolen from player |
| `plans_stolen[].plan` | string | Name of the stolen plan |
| `plans_stolen[].rarity` | string | Rarity level of the plan |
| `components_downgraded` | array | Ship components that were downgraded |
| `components_downgraded[].component` | string | Component type (weapons, shields, etc.) |
| `components_downgraded[].old_level` | integer | Previous level |
| `components_downgraded[].new_level` | integer | New level after downgrade |
| `upgrades_stolen` | array | Installed ship upgrades that were removed |
| `message` | string | Narrative description of what happened |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ERROR | Player has no active ship |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player or encounter not found |
| 422 | VALIDATION_ERROR | Invalid encounter_uuid parameter |

#### Warnings

- Surrendering avoids combat but guarantees losses
- More valuable cargo attracts more aggressive looting
- High-reputation pirate captains may take more than just cargo
- Surrendering is recorded and may affect faction reputation

---

### Engage Combat

Engage in combat with pirates.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/combat/engage`

**Description:** Initiates combat between the player's ship and a pirate fleet. Combat is resolved immediately with a detailed combat log showing each round. Victory yields XP and salvage; defeat results in ship destruction and respawn penalties.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player |
| `encounter_uuid` | string (UUID) | Yes | Body | UUID of the pirate encounter |

#### Request Body Example

```json
{
  "encounter_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

#### Victory Response

```json
{
  "success": true,
  "data": {
    "victory": true,
    "combat_log": [
      "Round 1: You fire your weapons dealing 450 damage to Crimson Blade!",
      "Round 1: Crimson Blade fires back dealing 280 damage!",
      "Round 2: You fire your weapons dealing 450 damage to Crimson Blade!",
      "Round 2: Crimson Blade is destroyed!",
      "Round 3: You fire your weapons dealing 450 damage to Shadow Runner!",
      "All enemy ships destroyed! Victory!"
    ],
    "rounds": 8,
    "xp_earned": 2500,
    "player_hull_remaining": 850,
    "salvage": {
      "minerals": [
        {
          "mineral_id": 5,
          "mineral_name": "Titanium",
          "quantity": 75,
          "value": 15000
        }
      ],
      "plans": [
        {
          "plan_id": 12,
          "plan_name": "Advanced Targeting Computer",
          "rarity": "rare"
        }
      ],
      "total_value": 45000
    },
    "message": "Victory! Pirates defeated."
  },
  "message": "Victory! Pirates defeated.",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "d50e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Defeat Response

```json
{
  "success": true,
  "data": {
    "victory": false,
    "combat_log": [
      "Round 1: You fire your weapons dealing 350 damage to Crimson Blade!",
      "Round 1: Crimson Blade fires back dealing 420 damage!",
      "Round 2: You fire your weapons dealing 350 damage to Crimson Blade!",
      "Round 2: Shadow Runner fires dealing 380 damage!",
      "Round 3: Your hull integrity critical!",
      "Round 3: Crimson Blade lands a devastating hit!",
      "Your ship has been destroyed!"
    ],
    "rounds": 3,
    "xp_earned": 0,
    "player_hull_remaining": 0,
    "death": {
      "respawn_location": "Alpha Centauri Station",
      "credits_lost": 25000,
      "cargo_lost": true,
      "ship_reset": true
    },
    "death_message": "Your ship was destroyed by pirates. You wake up in a medical bay at Alpha Centauri Station, your ship replaced by a basic starter vessel.",
    "message": "Defeat - Your ship was destroyed."
  },
  "message": "Defeat - Your ship was destroyed.",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "e50e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `victory` | boolean | Whether the player won the combat |
| `combat_log` | array | Array of combat messages showing what happened each round |
| `rounds` | integer | Number of combat rounds |
| `xp_earned` | integer | Experience points earned (0 if defeated) |
| `player_hull_remaining` | integer | Player's hull points after combat |
| `salvage` | object/null | Salvage available if victorious |
| `salvage.minerals` | array | Minerals that can be collected |
| `salvage.plans` | array | Upgrade plans that can be collected |
| `salvage.total_value` | integer | Total estimated value of salvage |
| `death` | object/null | Death information if defeated |
| `death.respawn_location` | string | Where the player respawns |
| `death.credits_lost` | integer | Credits lost due to death |
| `death.cargo_lost` | boolean | Whether cargo was lost |
| `death.ship_reset` | boolean | Whether ship was reset to starter |
| `death_message` | string | Narrative description of death |
| `message` | string | Overall result message |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ERROR | Player has no active ship |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player or encounter not found |
| 422 | VALIDATION_ERROR | Invalid encounter_uuid parameter |

#### Warnings

- Combat is resolved immediately and cannot be undone
- Defeat results in ship destruction and respawn penalties
- Salvage must be collected with a separate endpoint call
- XP is awarded immediately but salvage requires manual collection

---

### Collect Salvage

Collect salvage after a combat victory.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/combat/salvage`

**Description:** Allows the player to collect minerals and upgrade plans from defeated pirates. Player can selectively choose what to collect based on available cargo space.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player |
| `minerals` | array | No | Body | Array of minerals to collect |
| `minerals[].mineral_id` | integer | Yes | Body | ID of the mineral |
| `minerals[].quantity` | integer | Yes | Body | Quantity to collect (min: 1) |
| `plan_ids` | array | No | Body | Array of upgrade plan IDs to collect |

#### Request Body Example

```json
{
  "minerals": [
    {
      "mineral_id": 5,
      "quantity": 50
    },
    {
      "mineral_id": 8,
      "quantity": 25
    }
  ],
  "plan_ids": [12, 15]
}
```

#### Response Structure

```json
{
  "success": true,
  "data": {
    "minerals_collected": [
      {
        "mineral_id": 5,
        "mineral_name": "Titanium",
        "quantity": 50
      },
      {
        "mineral_id": 8,
        "mineral_name": "Platinum",
        "quantity": 25
      }
    ],
    "plans_collected": [
      {
        "plan_id": 12,
        "plan_name": "Advanced Targeting Computer",
        "rarity": "rare"
      },
      {
        "plan_id": 15,
        "plan_name": "Shield Harmonics Upgrade",
        "rarity": "uncommon"
      }
    ],
    "cargo_used": 675,
    "cargo_remaining": 325
  },
  "message": "Salvage collected successfully",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "f50e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `minerals_collected` | array | Minerals successfully added to cargo |
| `minerals_collected[].mineral_id` | integer | ID of the mineral |
| `minerals_collected[].mineral_name` | string | Name of the mineral |
| `minerals_collected[].quantity` | integer | Quantity collected |
| `plans_collected` | array | Upgrade plans successfully collected |
| `plans_collected[].plan_id` | integer | ID of the plan |
| `plans_collected[].plan_name` | string | Name of the plan |
| `plans_collected[].rarity` | string | Rarity level |
| `cargo_used` | integer | Current cargo space used |
| `cargo_remaining` | integer | Remaining cargo space available |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ERROR | Insufficient cargo space for selected salvage |
| 400 | ERROR | Player has no active ship |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player not found |
| 422 | VALIDATION_ERROR | Invalid mineral_id or plan_id |

#### Warnings

- Salvage collection is limited by available cargo space
- Uncollected salvage may be lost after a timeout period
- Upgrade plans do not consume cargo space
- You can make multiple collection calls to selectively choose salvage

---

## PvP Combat

### Issue Challenge

Issue a PvP combat challenge to another player.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/pvp/challenge`

**Description:** Challenges another player to PvP combat. The challenge includes an optional wager and message. Challenges expire after a set time period. Supports team combat with configurable team sizes.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the challenging player |
| `target_player_uuid` | string (UUID) | Yes | Body | UUID of the player being challenged |
| `message` | string | No | Body | Custom challenge message (max 500 chars) |
| `wager_credits` | integer | No | Body | Credits to wager on the match (0-1,000,000) |
| `max_team_size` | integer | No | Body | Maximum team size for this challenge (1-10, default: 1) |

#### Request Body Example

```json
{
  "target_player_uuid": "450e8400-e29b-41d4-a716-446655440000",
  "message": "Your reputation precedes you. Let's settle this in honorable combat!",
  "wager_credits": 50000,
  "max_team_size": 3
}
```

#### Response Structure

```json
{
  "success": true,
  "data": {
    "challenge": {
      "uuid": "150e8400-e29b-41d4-a716-446655440000",
      "target": {
        "uuid": "450e8400-e29b-41d4-a716-446655440000",
        "call_sign": "StarLord_42"
      },
      "message": "Your reputation precedes you. Let's settle this in honorable combat!",
      "wager_credits": 50000,
      "expires_at": "2026-02-16T22:30:00+00:00"
    }
  },
  "message": "Challenge issued successfully",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "250e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `challenge.uuid` | string | UUID of the created challenge |
| `challenge.target` | object | Information about the challenged player |
| `challenge.target.uuid` | string | UUID of the target player |
| `challenge.target.call_sign` | string | Call sign of the target player |
| `challenge.message` | string | Challenge message |
| `challenge.wager_credits` | integer | Wagered credits |
| `challenge.expires_at` | string | ISO 8601 timestamp when challenge expires |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | CHALLENGE_FAILED | Cannot challenge yourself |
| 400 | CHALLENGE_FAILED | Target player is offline or unavailable |
| 400 | CHALLENGE_FAILED | Insufficient credits for wager |
| 400 | CHALLENGE_FAILED | Target player has pending challenge from you |
| 400 | CHALLENGE_FAILED | Players must be in the same galaxy |
| 403 | FORBIDDEN | Player does not belong to authenticated user |
| 404 | NOT_FOUND | Player or target player not found |
| 422 | VALIDATION_ERROR | Invalid parameters |

#### Warnings

- Both players must be in the same galaxy
- Wagered credits are held in escrow until the challenge is resolved
- Challenges expire after 12 hours
- Only one pending challenge allowed per player pair
- If max_team_size > 1, this enables team combat mode

---

### List Challenges

Get all pending PvP challenges for a player (incoming and outgoing).

**HTTP Method:** `GET`

**URL:** `/api/players/{uuid}/pvp/challenges`

**Description:** Returns all active challenges where the player is either the challenger or the target. Expired challenges are automatically filtered out.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "incoming_challenges": [
      {
        "uuid": "150e8400-e29b-41d4-a716-446655440000",
        "type": "incoming",
        "challenger": {
          "uuid": "350e8400-e29b-41d4-a716-446655440000",
          "call_sign": "SpaceAce_99"
        },
        "message": "I challenge you to a duel!",
        "wager_credits": 25000,
        "expires_at": "2026-02-16T22:30:00+00:00"
      }
    ],
    "outgoing_challenges": [
      {
        "uuid": "250e8400-e29b-41d4-a716-446655440000",
        "type": "outgoing",
        "target": {
          "uuid": "450e8400-e29b-41d4-a716-446655440000",
          "call_sign": "StarLord_42"
        },
        "message": "Let's settle this!",
        "wager_credits": 50000,
        "expires_at": "2026-02-16T23:00:00+00:00"
      }
    ],
    "total_incoming": 1,
    "total_outgoing": 1
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `incoming_challenges` | array | Challenges where this player is the target |
| `outgoing_challenges` | array | Challenges issued by this player |
| `incoming_challenges[].uuid` | string | UUID of the challenge |
| `incoming_challenges[].type` | string | Always "incoming" |
| `incoming_challenges[].challenger` | object | Player who issued the challenge |
| `outgoing_challenges[].uuid` | string | UUID of the challenge |
| `outgoing_challenges[].type` | string | Always "outgoing" |
| `outgoing_challenges[].target` | object | Player being challenged |
| `total_incoming` | integer | Count of incoming challenges |
| `total_outgoing` | integer | Count of outgoing challenges |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Player not found |

---

### Accept Challenge

Accept a PvP challenge and immediately engage in combat.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/pvp/challenge/{challengeUuid}/accept`

**Description:** Accept a pending PvP challenge issued to you and immediately start combat. The combat is resolved automatically and results are returned.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player accepting the challenge |
| `challengeUuid` | string (UUID) | Yes | Path | UUID of the challenge to accept |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "combat_session": {
      "uuid": "450e8400-e29b-41d4-a716-446655440000"
    },
    "result": {
      "victor": {
        "uuid": "150e8400-e29b-41d4-a716-446655440000",
        "call_sign": "SpaceAce"
      },
      "victor_hull_remaining": 850,
      "loser": {
        "uuid": "250e8400-e29b-41d4-a716-446655440000",
        "call_sign": "RivalPilot"
      },
      "rounds": 5,
      "xp_earned": 500,
      "credits_earned": 10000,
      "combat_log": [
        "Round 1: SpaceAce attacks for 150 damage",
        "Round 1: RivalPilot attacks for 120 damage",
        "Round 2: SpaceAce attacks for 160 damage"
      ],
      "death_result": {
        "player_died": true,
        "credits_lost": 5000,
        "cargo_lost": [],
        "respawn_location": {
          "name": "Sol Station",
          "poi_uuid": "550e8400-e29b-41d4-a716-446655440000"
        }
      }
    }
  },
  "message": "Combat completed",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `combat_session.uuid` | string | UUID of the created combat session |
| `result.victor` | object | Player who won the combat |
| `result.victor.uuid` | string | UUID of the victor |
| `result.victor.call_sign` | string | Call sign of the victor |
| `result.victor_hull_remaining` | integer | Hull points remaining for the victor |
| `result.loser` | object | Player who lost the combat |
| `result.loser.uuid` | string | UUID of the loser |
| `result.loser.call_sign` | string | Call sign of the loser |
| `result.rounds` | integer | Number of combat rounds |
| `result.xp_earned` | integer | Experience points earned by the victor |
| `result.credits_earned` | integer | Credits earned by the victor (from wager) |
| `result.combat_log` | array | Detailed log of combat actions |
| `result.death_result` | object/null | Death information if loser died, null otherwise |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ACCEPT_FAILED | Challenge cannot be accepted (wrong player, expired, invalid status, etc.) |
| 404 | NOT_FOUND | Player or challenge not found |

#### Warnings & Caveats

- Only the target player can accept a challenge
- Challenge must be in "pending" status
- Challenge must not be expired (24 hour expiration)
- Both players must have active ships
- Combat is resolved immediately upon acceptance
- Loser may die if hull is depleted (see death mechanics)
- Wager credits are transferred to the victor

---

### Decline Challenge

Decline a PvP challenge issued to you.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/pvp/challenge/{challengeUuid}/decline`

**Description:** Decline a pending PvP challenge. The challenge status will be updated to "declined".

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player declining the challenge |
| `challengeUuid` | string (UUID) | Yes | Path | UUID of the challenge to decline |

#### Response Structure

```json
{
  "success": true,
  "data": {},
  "message": "Challenge declined",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| (empty) | object | Empty data object on successful decline |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | DECLINE_FAILED | Challenge cannot be declined (wrong player, already resolved, etc.) |
| 404 | NOT_FOUND | Player or challenge not found |

#### Warnings & Caveats

- Only the target player can decline a challenge
- Challenge must be in "pending" status
- No penalty for declining a challenge

---

### Cancel Challenge

Cancel an outgoing PvP challenge you issued.

**HTTP Method:** `DELETE`

**URL:** `/api/players/{uuid}/pvp/challenge/{challengeUuid}`

**Description:** Cancel a pending PvP challenge that you issued to another player.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player canceling the challenge |
| `challengeUuid` | string (UUID) | Yes | Path | UUID of the challenge to cancel |

#### Response Structure

```json
{
  "success": true,
  "data": {},
  "message": "Challenge cancelled",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| (empty) | object | Empty data object on successful cancellation |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | INVALID_STATUS | Only pending challenges can be cancelled |
| 403 | UNAUTHORIZED | You can only cancel your own challenges |
| 404 | NOT_FOUND | Player or challenge not found |

#### Warnings & Caveats

- Only the challenger can cancel a challenge
- Challenge must be in "pending" status
- No penalty for canceling a challenge

---

### Get Combat Session

Get detailed information about a completed combat session.

**HTTP Method:** `GET`

**URL:** `/api/combat-sessions/{uuid}`

**Description:** Retrieve comprehensive details about a combat session, including all participants, combat log, and final results.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the combat session |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "combat_session": {
      "uuid": "450e8400-e29b-41d4-a716-446655440000",
      "combat_type": "pvp",
      "status": "completed",
      "current_round": 5,
      "victor_type": "player",
      "started_at": "2026-02-16T10:00:00+00:00",
      "ended_at": "2026-02-16T10:05:00+00:00",
      "participants": [
        {
          "player": {
            "uuid": "150e8400-e29b-41d4-a716-446655440000",
            "call_sign": "SpaceAce"
          },
          "side": "attacker",
          "starting_hull": 1000,
          "current_hull": 850,
          "damage_dealt": 1200,
          "damage_taken": 150,
          "survived": true,
          "xp_earned": 500,
          "credits_earned": 10000
        },
        {
          "player": {
            "uuid": "250e8400-e29b-41d4-a716-446655440000",
            "call_sign": "RivalPilot"
          },
          "side": "defender",
          "starting_hull": 1200,
          "current_hull": 0,
          "damage_dealt": 150,
          "damage_taken": 1200,
          "survived": false,
          "xp_earned": 0,
          "credits_earned": 0
        }
      ],
      "combat_log": [
        "Round 1: SpaceAce attacks for 150 damage",
        "Round 1: RivalPilot attacks for 120 damage",
        "Round 2: SpaceAce attacks for 160 damage"
      ]
    }
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `combat_session.uuid` | string | UUID of the combat session |
| `combat_session.combat_type` | string | Type of combat (pvp, team_pvp, pve) |
| `combat_session.status` | string | Session status (active, completed) |
| `combat_session.current_round` | integer | Final round number |
| `combat_session.victor_type` | string | Type of victor (player, pirate, null if draw) |
| `combat_session.started_at` | string | ISO 8601 timestamp when combat started |
| `combat_session.ended_at` | string | ISO 8601 timestamp when combat ended |
| `combat_session.participants` | array | Array of all combat participants |
| `participants[].player` | object | Player information |
| `participants[].side` | string | Side in combat (attacker, defender) |
| `participants[].starting_hull` | integer | Hull at combat start |
| `participants[].current_hull` | integer | Hull at combat end |
| `participants[].damage_dealt` | integer | Total damage dealt to opponents |
| `participants[].damage_taken` | integer | Total damage received |
| `participants[].survived` | boolean | Whether the participant survived |
| `participants[].xp_earned` | integer | Experience points earned |
| `participants[].credits_earned` | integer | Credits earned from combat |
| `combat_log` | array | Detailed log of all combat actions |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Combat session not found |

#### Warnings & Caveats

- Combat sessions are permanent records
- Can be viewed by any authenticated player (no privacy restriction)
- Useful for reviewing past battles and statistics

---

## Team Combat

### Invite Ally

Invite another player to join your side in a PvP challenge.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/pvp/challenge/{challengeUuid}/invite`

**Description:** Invite an ally to join your team (attacker or defender side) in a pending PvP challenge. Team size is limited by the challenge's max_team_size setting.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player sending the invitation |
| `challengeUuid` | string (UUID) | Yes | Path | UUID of the challenge |

#### Request Body

```json
{
  "invitee_uuid": "350e8400-e29b-41d4-a716-446655440000",
  "side": "attacker"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `invitee_uuid` | string (UUID) | Yes | UUID of the player to invite |
| `side` | string | Yes | Side to join (attacker or defender) |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "invitation": {
      "id": 42,
      "invitee": {
        "uuid": "350e8400-e29b-41d4-a716-446655440000",
        "call_sign": "AllyPilot"
      },
      "side": "attacker",
      "status": "pending"
    }
  },
  "message": "Invitation sent to AllyPilot",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `invitation.id` | integer | ID of the team invitation |
| `invitation.invitee` | object | Player being invited |
| `invitation.invitee.uuid` | string | UUID of the invitee |
| `invitation.invitee.call_sign` | string | Call sign of the invitee |
| `invitation.side` | string | Side the invitee will join (attacker, defender) |
| `invitation.status` | string | Invitation status (pending) |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | INVITE_FAILED | Invitation failed (team full, invalid side, player not eligible, etc.) |
| 404 | NOT_FOUND | Player, challenge, or invitee not found |

#### Warnings & Caveats

- Only challenger and target can invite allies
- Team size limited by max_team_size setting
- Cannot invite same player multiple times
- Cannot invite players who are already in another active challenge
- Challenge must be in "pending" status

---

### List Team Invitations

Get all pending team invitations for a player.

**HTTP Method:** `GET`

**URL:** `/api/players/{uuid}/team-invitations`

**Description:** Retrieve all pending team combat invitations for the specified player.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "invitations": [
      {
        "id": 42,
        "challenge": {
          "uuid": "450e8400-e29b-41d4-a716-446655440000",
          "challenger": {
            "uuid": "150e8400-e29b-41d4-a716-446655440000",
            "call_sign": "SpaceAce"
          },
          "target": {
            "uuid": "250e8400-e29b-41d4-a716-446655440000",
            "call_sign": "RivalPilot"
          },
          "wager_credits": 10000,
          "expires_at": "2026-02-17T10:30:00+00:00"
        },
        "invited_by": {
          "uuid": "150e8400-e29b-41d4-a716-446655440000",
          "call_sign": "SpaceAce"
        },
        "side": "attacker"
      }
    ],
    "total": 1
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `invitations` | array | Array of pending team invitations |
| `invitations[].id` | integer | ID of the invitation |
| `invitations[].challenge` | object | Challenge details |
| `invitations[].challenge.uuid` | string | UUID of the challenge |
| `invitations[].challenge.challenger` | object | Player who issued the challenge |
| `invitations[].challenge.target` | object | Player being challenged |
| `invitations[].challenge.wager_credits` | integer | Credits wagered in the challenge |
| `invitations[].challenge.expires_at` | string | ISO 8601 timestamp of expiration |
| `invitations[].invited_by` | object | Player who sent the invitation |
| `invitations[].side` | string | Side to join (attacker, defender) |
| `total` | integer | Total number of pending invitations |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Player not found |

#### Warnings & Caveats

- Only shows pending invitations for non-expired challenges
- Automatically filters out expired challenges

---

### Accept Team Invitation

Accept a team combat invitation.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/team-invitations/{invitationId}/accept`

**Description:** Accept a pending team combat invitation and join the specified side of the challenge.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player accepting the invitation |
| `invitationId` | integer | Yes | Path | ID of the team invitation |

#### Response Structure

```json
{
  "success": true,
  "data": {},
  "message": "You have joined the attackers team",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| (empty) | object | Empty data object on successful acceptance |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ACCEPT_FAILED | Invitation cannot be accepted (expired, team full, invalid status, etc.) |
| 404 | NOT_FOUND | Player or invitation not found |

#### Warnings & Caveats

- Only the invitee can accept the invitation
- Invitation must be in "pending" status
- Challenge must not be expired
- Team must not be full (respects max_team_size)
- Player must have an active ship

---

### Decline Team Invitation

Decline a team combat invitation.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/team-invitations/{invitationId}/decline`

**Description:** Decline a pending team combat invitation.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player declining the invitation |
| `invitationId` | integer | Yes | Path | ID of the team invitation |

#### Response Structure

```json
{
  "success": true,
  "data": {},
  "message": "Invitation declined",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| (empty) | object | Empty data object on successful decline |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | DECLINE_FAILED | Invitation cannot be declined (wrong player, already resolved, etc.) |
| 404 | NOT_FOUND | Player or invitation not found |

#### Warnings & Caveats

- Only the invitee can decline the invitation
- Invitation must be in "pending" status
- No penalty for declining an invitation

---

### Get Team Composition

Get the current team composition for a challenge.

**HTTP Method:** `GET`

**URL:** `/api/pvp/challenge/{challengeUuid}/teams`

**Description:** Retrieve detailed information about the teams (attackers and defenders) for a specific PvP challenge.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `challengeUuid` | string (UUID) | Yes | Path | UUID of the challenge |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "challenge": {
      "uuid": "450e8400-e29b-41d4-a716-446655440000",
      "status": "pending",
      "max_team_size": 3,
      "wager_credits": 10000,
      "expires_at": "2026-02-17T10:30:00+00:00"
    },
    "attackers": [
      {
        "uuid": "150e8400-e29b-41d4-a716-446655440000",
        "call_sign": "SpaceAce",
        "ship": {
          "name": "USS Enterprise",
          "hull": 1000,
          "weapons": 8
        }
      },
      {
        "uuid": "350e8400-e29b-41d4-a716-446655440000",
        "call_sign": "AllyPilot",
        "ship": {
          "name": "Millennium Falcon",
          "hull": 900,
          "weapons": 7
        }
      }
    ],
    "defenders": [
      {
        "uuid": "250e8400-e29b-41d4-a716-446655440000",
        "call_sign": "RivalPilot",
        "ship": {
          "name": "Imperial Destroyer",
          "hull": 1200,
          "weapons": 9
        }
      }
    ],
    "attackers_count": 2,
    "defenders_count": 1
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `challenge.uuid` | string | UUID of the challenge |
| `challenge.status` | string | Challenge status (pending, active, completed, etc.) |
| `challenge.max_team_size` | integer | Maximum number of players per team |
| `challenge.wager_credits` | integer | Credits wagered per player |
| `challenge.expires_at` | string | ISO 8601 timestamp of expiration |
| `attackers` | array | Array of players on the attacking team |
| `attackers[].uuid` | string | Player UUID |
| `attackers[].call_sign` | string | Player call sign |
| `attackers[].ship` | object/null | Active ship details, null if no ship |
| `attackers[].ship.name` | string | Ship name |
| `attackers[].ship.hull` | integer | Ship hull level |
| `attackers[].ship.weapons` | integer | Ship weapons level |
| `defenders` | array | Array of players on the defending team |
| `attackers_count` | integer | Total number of attackers |
| `defenders_count` | integer | Total number of defenders |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 404 | NOT_FOUND | Challenge not found |

#### Warnings & Caveats

- Shows current team composition including accepted invitations
- Teams may be unbalanced (different sizes)
- Useful for reviewing team strength before accepting challenge

---

### Accept Team Challenge

Accept a team challenge and start team combat.

**HTTP Method:** `POST`

**URL:** `/api/players/{uuid}/pvp/challenge/{challengeUuid}/accept-team`

**Description:** Accept a team PvP challenge and immediately start team combat. All team members fight simultaneously, and the combat is resolved automatically.

**Authentication:** Required (Sanctum)

#### Parameters

| Parameter | Type | Required | Location | Description |
|-----------|------|----------|----------|-------------|
| `uuid` | string (UUID) | Yes | Path | UUID of the player accepting the challenge |
| `challengeUuid` | string (UUID) | Yes | Path | UUID of the challenge to accept |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "combat_session": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000"
    },
    "result": {
      "victor_team": "attackers",
      "victors": [
        {
          "uuid": "150e8400-e29b-41d4-a716-446655440000",
          "call_sign": "SpaceAce",
          "hull_remaining": 850,
          "xp_earned": 500,
          "credits_earned": 10000
        },
        {
          "uuid": "350e8400-e29b-41d4-a716-446655440000",
          "call_sign": "AllyPilot",
          "hull_remaining": 650,
          "xp_earned": 500,
          "credits_earned": 10000
        }
      ],
      "losers": [
        {
          "uuid": "250e8400-e29b-41d4-a716-446655440000",
          "call_sign": "RivalPilot",
          "hull_remaining": 0,
          "survived": false
        }
      ],
      "rounds": 8,
      "combat_log": [
        "Round 1: SpaceAce attacks RivalPilot for 150 damage",
        "Round 1: AllyPilot attacks RivalPilot for 130 damage",
        "Round 1: RivalPilot attacks SpaceAce for 120 damage"
      ],
      "death_results": [
        {
          "player_uuid": "250e8400-e29b-41d4-a716-446655440000",
          "player_died": true,
          "credits_lost": 5000,
          "cargo_lost": [],
          "respawn_location": {
            "name": "Sol Station",
            "poi_uuid": "650e8400-e29b-41d4-a716-446655440000"
          }
        }
      ]
    }
  },
  "message": "Team combat completed",
  "meta": {
    "timestamp": "2026-02-16T10:30:00+00:00",
    "request_id": "350e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `combat_session.uuid` | string | UUID of the created combat session |
| `result.victor_team` | string | Winning team (attackers or defenders) |
| `result.victors` | array | Array of all victorious players |
| `victors[].uuid` | string | Player UUID |
| `victors[].call_sign` | string | Player call sign |
| `victors[].hull_remaining` | integer | Hull remaining after combat |
| `victors[].xp_earned` | integer | Experience points earned |
| `victors[].credits_earned` | integer | Credits earned from wager |
| `result.losers` | array | Array of all defeated players |
| `losers[].uuid` | string | Player UUID |
| `losers[].call_sign` | string | Player call sign |
| `losers[].hull_remaining` | integer | Hull remaining (usually 0) |
| `losers[].survived` | boolean | Whether the player survived |
| `result.rounds` | integer | Number of combat rounds |
| `result.combat_log` | array | Detailed log of all combat actions |
| `result.death_results` | array | Death information for players who died |

#### Error Responses

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| 400 | ACCEPT_FAILED | Challenge cannot be accepted (wrong player, expired, invalid status, etc.) |
| 404 | NOT_FOUND | Player or challenge not found |

#### Warnings & Caveats

- Only the target player can accept a team challenge
- Challenge must be in "pending" status and not expired
- All team members must have active ships
- Combat is resolved immediately upon acceptance
- Each player on the losing team who dies triggers death mechanics independently
- Wager credits are distributed among all victors
- Teams can be unbalanced (different sizes)

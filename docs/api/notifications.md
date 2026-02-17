# Notifications API

This document provides comprehensive API documentation for all notification endpoints in Space Wars 3002.

## Base URL
All endpoints are prefixed with `/api/players/{playerUuid}/notifications`

## Response Structure
All API responses follow a standardized format provided by the `BaseApiController`.

### Success Response Format
```json
{
  "success": true,
  "data": { /* endpoint-specific data */ },
  "message": "Success message",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### Error Response Format
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

---

## Endpoints

### 1. Get Player's Notifications

**GET** `/api/players/{playerUuid}/notifications`

Retrieves all notifications for a player with optional filtering by read status and type. Notifications are ordered by creation date (newest first) and limited to a configurable maximum.

**Authentication Required:** Yes

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| playerUuid | string (UUID) | Yes | Player's unique identifier (in URL path) |
| read | boolean | No | Filter by read status (`true` for read, `false` for unread) |
| type | string | No | Filter by notification type (e.g., `combat`, `trading`, `colony`, `system`) |
| limit | integer | No | Maximum number of notifications to return (default: 50) |

#### Request Example
```
GET /api/players/550e8400-e29b-41d4-a716-446655440000/notifications?read=false&type=combat&limit=25
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "player": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "call_sign": "Maverick"
    },
    "total_notifications": 15,
    "unread_count": 8,
    "notifications": [
      {
        "id": 123,
        "type": "combat",
        "title": "Pirate Encounter!",
        "message": "You were ambushed by pirates near Sector 7. Hull damage sustained.",
        "data": {
          "damage": 45,
          "sector_id": 7,
          "pirate_captain_id": 42
        },
        "is_read": false,
        "priority": "high",
        "created_at": "2026-02-16T12:00:00+00:00",
        "read_at": null
      },
      {
        "id": 122,
        "type": "trading",
        "title": "Market Alert",
        "message": "Mineral prices have surged at Trading Hub Alpha-7",
        "data": {
          "trading_hub_id": 15,
          "mineral_type": "plutonium",
          "price_change": 25.5
        },
        "is_read": true,
        "priority": "normal",
        "created_at": "2026-02-16T11:30:00+00:00",
        "read_at": "2026-02-16T11:45:00+00:00"
      }
    ]
  },
  "message": "Notifications retrieved successfully",
  "meta": {
    "timestamp": "2026-02-16T12:05:00+00:00",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Response Data Fields

| Field | Type | Description |
|-------|------|-------------|
| `player.uuid` | string | Player's unique identifier |
| `player.call_sign` | string | Player's call sign (display name) |
| `total_notifications` | integer | Total number of notifications returned (respects limit parameter) |
| `unread_count` | integer | Total count of ALL unread notifications for this player (ignores filters and limit) |
| `notifications` | array | Array of notification objects |
| `notifications[].id` | integer | Notification's unique identifier |
| `notifications[].type` | string | Notification category (e.g., `combat`, `trading`, `colony`, `system`) |
| `notifications[].title` | string | Notification headline/subject |
| `notifications[].message` | string | Full notification message text |
| `notifications[].data` | object | Additional structured data specific to notification type (can be empty object) |
| `notifications[].is_read` | boolean | Whether the notification has been read |
| `notifications[].priority` | string | Priority level: `low`, `normal`, `high`, or `critical` (default: `normal`) |
| `notifications[].created_at` | string | ISO 8601 timestamp when notification was created |
| `notifications[].read_at` | string\|null | ISO 8601 timestamp when notification was read (null if unread) |

#### Error Responses

**404 Not Found** - Player not found
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Player not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:05:00+00:00",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

**401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Unauthorized access",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:05:00+00:00",
    "request_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  }
}
```

#### Warnings & Caveats

- The `total_notifications` field shows the count of notifications returned in the response (limited by the `limit` parameter), while `unread_count` shows the total count of ALL unread notifications regardless of filters or limits
- The default limit is 50 notifications. To retrieve more, explicitly set the `limit` parameter
- The `read` query parameter accepts string values that are converted to boolean: `"true"`, `"1"`, `"on"`, `"yes"` → `true`; all other values → `false`
- Notifications are always ordered by `created_at` in descending order (newest first)
- The `data` field structure varies by notification type and may contain arbitrary JSON data
- The `priority` field defaults to `"normal"` if not set on the notification

---

### 2. Get Unread Notification Count

**GET** `/api/players/{playerUuid}/notifications/unread`

Retrieves the total count of unread notifications and a breakdown by notification type.

**Authentication Required:** Yes

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| playerUuid | string (UUID) | Yes | Player's unique identifier (in URL path) |

#### Request Example
```
GET /api/players/550e8400-e29b-41d4-a716-446655440000/notifications/unread
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "player": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "call_sign": "Maverick"
    },
    "total_unread": 8,
    "unread_by_type": {
      "combat": 3,
      "trading": 2,
      "colony": 2,
      "system": 1
    }
  },
  "message": "Unread count retrieved successfully",
  "meta": {
    "timestamp": "2026-02-16T12:10:00+00:00",
    "request_id": "8d0f7890-8536-51ef-a05c-f18ed2g01bf8"
  }
}
```

#### Response Data Fields

| Field | Type | Description |
|-------|------|-------------|
| `player.uuid` | string | Player's unique identifier |
| `player.call_sign` | string | Player's call sign (display name) |
| `total_unread` | integer | Total count of all unread notifications |
| `unread_by_type` | object | Key-value pairs where keys are notification types and values are counts |

#### Error Responses

**404 Not Found** - Player not found
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Player not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:10:00+00:00",
    "request_id": "8d0f7890-8536-51ef-a05c-f18ed2g01bf8"
  }
}
```

**401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Unauthorized access",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:10:00+00:00",
    "request_id": "8d0f7890-8536-51ef-a05c-f18ed2g01bf8"
  }
}
```

#### Warnings & Caveats

- This endpoint is optimized for badge displays and UI counters
- The `unread_by_type` object only includes types that have unread notifications (types with 0 unread are omitted)
- If a player has no unread notifications, `unread_by_type` will be an empty object `{}`
- This endpoint does not paginate - it returns aggregate counts only

---

### 3. Mark Notification as Read

**POST** `/api/players/{playerUuid}/notifications/{notificationId}/read`

Marks a single notification as read and records the timestamp.

**Authentication Required:** Yes

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| playerUuid | string (UUID) | Yes | Player's unique identifier (in URL path) |
| notificationId | integer | Yes | Notification's unique identifier (in URL path) |

#### Request Body
None required (POST with empty body)

#### Request Example
```
POST /api/players/550e8400-e29b-41d4-a716-446655440000/notifications/123/read
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "notification_id": 123,
    "is_read": true,
    "read_at": "2026-02-16T12:15:00+00:00"
  },
  "message": "Notification marked as read",
  "meta": {
    "timestamp": "2026-02-16T12:15:00+00:00",
    "request_id": "9e1g8901-9647-62fg-b16d-g29fe3h12cg9"
  }
}
```

#### Response Data Fields

| Field | Type | Description |
|-------|------|-------------|
| `notification_id` | integer | The notification's unique identifier |
| `is_read` | boolean | Always `true` after successful operation |
| `read_at` | string | ISO 8601 timestamp when notification was marked as read |

#### Error Responses

**404 Not Found** - Player or notification not found
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Notification not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:15:00+00:00",
    "request_id": "9e1g8901-9647-62fg-b16d-g29fe3h12cg9"
  }
}
```

**401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Unauthorized access",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:15:00+00:00",
    "request_id": "9e1g8901-9647-62fg-b16d-g29fe3h12cg9"
  }
}
```

**403 Forbidden** - Notification belongs to a different player
```json
{
  "success": false,
  "error": {
    "code": "FORBIDDEN",
    "message": "Access forbidden",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:15:00+00:00",
    "request_id": "9e1g8901-9647-62fg-b16d-g29fe3h12cg9"
  }
}
```

#### Warnings & Caveats

- This endpoint is idempotent: marking an already-read notification as read has no effect (the original `read_at` timestamp is preserved)
- The notification must belong to the specified player, otherwise a 404 error is returned
- The `read_at` timestamp reflects when the notification was first marked as read (not updated on subsequent calls)

---

### 4. Mark All Notifications as Read

**POST** `/api/players/{playerUuid}/notifications/mark-all-read`

Marks all unread notifications for a player as read in a single operation.

**Authentication Required:** Yes

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| playerUuid | string (UUID) | Yes | Player's unique identifier (in URL path) |

#### Request Body
None required (POST with empty body)

#### Request Example
```
POST /api/players/550e8400-e29b-41d4-a716-446655440000/notifications/mark-all-read
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "notifications_marked": 8
  },
  "message": "All notifications marked as read",
  "meta": {
    "timestamp": "2026-02-16T12:20:00+00:00",
    "request_id": "0f2h9012-0758-73gh-c27e-h30gf4i23dh0"
  }
}
```

#### Response Data Fields

| Field | Type | Description |
|-------|------|-------------|
| `notifications_marked` | integer | Number of notifications that were marked as read (excludes already-read notifications) |

#### Error Responses

**404 Not Found** - Player not found
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Player not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:20:00+00:00",
    "request_id": "0f2h9012-0758-73gh-c27e-h30gf4i23dh0"
  }
}
```

**401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Unauthorized access",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:20:00+00:00",
    "request_id": "0f2h9012-0758-73gh-c27e-h30gf4i23dh0"
  }
}
```

#### Warnings & Caveats

- This operation only affects unread notifications - already-read notifications are not updated
- The returned count (`notifications_marked`) reflects only the notifications that were changed from unread to read
- If the player has no unread notifications, `notifications_marked` will be `0`
- All affected notifications receive the same `read_at` timestamp (the current time when the operation executes)
- This is a bulk operation and may be slower for players with thousands of unread notifications

---

### 5. Clear Read Notifications

**POST** `/api/players/{playerUuid}/notifications/clear-read`

Permanently deletes all read notifications for a player. This is useful for cleaning up the notification inbox.

**Authentication Required:** Yes

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| playerUuid | string (UUID) | Yes | Player's unique identifier (in URL path) |

#### Request Body
None required (POST with empty body)

#### Request Example
```
POST /api/players/550e8400-e29b-41d4-a716-446655440000/notifications/clear-read
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "notifications_cleared": 15
  },
  "message": "Read notifications cleared successfully",
  "meta": {
    "timestamp": "2026-02-16T12:25:00+00:00",
    "request_id": "1g3i0123-1869-84hi-d38f-i41hg5j34ei1"
  }
}
```

#### Response Data Fields

| Field | Type | Description |
|-------|------|-------------|
| `notifications_cleared` | integer | Number of read notifications that were permanently deleted |

#### Error Responses

**404 Not Found** - Player not found
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Player not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:25:00+00:00",
    "request_id": "1g3i0123-1869-84hi-d38f-i41hg5j34ei1"
  }
}
```

**401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Unauthorized access",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:25:00+00:00",
    "request_id": "1g3i0123-1869-84hi-d38f-i41hg5j34ei1"
  }
}
```

#### Warnings & Caveats

- **This operation is destructive and permanent** - deleted notifications cannot be recovered
- Only read notifications (`is_read = true`) are deleted - unread notifications are preserved
- If the player has no read notifications, `notifications_cleared` will be `0`
- This is a bulk delete operation and may be slower for players with thousands of read notifications
- Consider implementing a confirmation dialog in the UI before calling this endpoint
- This operation does not affect the player's unread notification count

---

### 6. Delete Single Notification

**DELETE** `/api/players/{playerUuid}/notifications/{notificationId}`

Permanently deletes a single notification, regardless of read status.

**Authentication Required:** Yes

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| playerUuid | string (UUID) | Yes | Player's unique identifier (in URL path) |
| notificationId | integer | Yes | Notification's unique identifier (in URL path) |

#### Request Body
None required

#### Request Example
```
DELETE /api/players/550e8400-e29b-41d4-a716-446655440000/notifications/123
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": null,
  "message": "Notification deleted successfully",
  "meta": {
    "timestamp": "2026-02-16T12:30:00+00:00",
    "request_id": "2h4j1234-2970-95ij-e49g-j52ih6k45fj2"
  }
}
```

#### Response Data Fields

The `data` field is `null` for successful deletion operations.

#### Error Responses

**404 Not Found** - Player or notification not found
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Notification not found",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:30:00+00:00",
    "request_id": "2h4j1234-2970-95ij-e49g-j52ih6k45fj2"
  }
}
```

**401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Unauthorized access",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:30:00+00:00",
    "request_id": "2h4j1234-2970-95ij-e49g-j52ih6k45fj2"
  }
}
```

**403 Forbidden** - Notification belongs to a different player
```json
{
  "success": false,
  "error": {
    "code": "FORBIDDEN",
    "message": "Access forbidden",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:30:00+00:00",
    "request_id": "2h4j1234-2970-95ij-e49g-j52ih6k45fj2"
  }
}
```

#### Warnings & Caveats

- **This operation is destructive and permanent** - deleted notifications cannot be recovered
- Works on both read and unread notifications
- The notification must belong to the specified player, otherwise a 404 error is returned
- This endpoint returns `null` in the `data` field on success (no notification data is returned)
- Consider implementing a confirmation dialog in the UI before calling this endpoint for important notifications

---

## Common Notification Types

The following notification types are commonly used throughout the game:

| Type | Description | Typical Data Fields |
|------|-------------|---------------------|
| `combat` | Combat encounters, battles, damage reports | `damage`, `attacker_id`, `sector_id`, `outcome` |
| `trading` | Market alerts, price changes, trade completions | `trading_hub_id`, `mineral_type`, `price_change`, `transaction_id` |
| `colony` | Colony events, production alerts, population changes | `colony_id`, `event_type`, `resource_type`, `quantity` |
| `system` | System announcements, maintenance, game updates | `severity`, `affected_systems`, `downtime` |
| `travel` | Journey completions, fuel alerts, navigation warnings | `from_poi_id`, `to_poi_id`, `fuel_consumed`, `distance` |
| `pirate` | Pirate encounters, faction changes, bounty alerts | `pirate_captain_id`, `faction_id`, `bounty_amount` |
| `achievement` | Achievements unlocked, milestones reached | `achievement_id`, `achievement_name`, `xp_awarded` |

## Priority Levels

Notifications can have the following priority levels:

| Priority | Usage | UI Suggestion |
|----------|-------|---------------|
| `critical` | Urgent alerts requiring immediate attention | Red badge, modal dialog, sound alert |
| `high` | Important events that should be reviewed soon | Orange/yellow badge, prominent display |
| `normal` | Standard notifications (default) | Standard display |
| `low` | Informational messages, minor events | Subtle display, can be auto-dismissed |

## Best Practices

1. **Polling**: Use the unread count endpoint for periodic polling (recommended: every 30-60 seconds) to update UI badges
2. **Pagination**: Use the `limit` parameter to paginate through large notification lists
3. **Filtering**: Combine `read` and `type` filters to create focused notification views (e.g., "unread combat alerts")
4. **Bulk Operations**: Use `mark-all-read` instead of individual requests when marking multiple notifications
5. **Cleanup**: Periodically prompt users to clear read notifications to improve performance
6. **Error Handling**: Always handle 404 errors gracefully - players or notifications may be deleted concurrently
7. **Timestamps**: All timestamps are in ISO 8601 format with timezone information
8. **Security**: Never expose other players' notifications - the API enforces player ownership

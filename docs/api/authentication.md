# Authentication API

This document provides comprehensive API documentation for all authentication endpoints in Space Wars 3002.

## Base URL
All endpoints are prefixed with `/api/auth`

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

### Validation Error Response Format
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "field_name": ["Validation error message"]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

---

## Endpoints

### 1. Register New User

**POST** `/api/auth/register`

Creates a new user account and returns an API access token.

**Authentication Required:** No

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | User's display name (max 255 characters) |
| email | string | Yes | User's email address (max 255 characters, must be unique) |
| password | string | Yes | User's password (must meet Password::defaults() requirements) |
| password_confirmation | string | Yes | Password confirmation (must match password field) |

#### Request Body Example
```json
{
  "name": "Captain Reynolds",
  "email": "mal@serenity.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

#### Success Response (201 Created)
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Captain Reynolds",
      "email": "mal@serenity.com",
      "email_verified_at": null,
      "created_at": "2026-02-16T12:00:00.000000Z"
    },
    "access_token": "1|abcdef1234567890abcdef1234567890",
    "token_type": "Bearer"
  },
  "message": "User registered successfully",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields Explained

- **user.id** (integer): Auto-incremented user ID
- **user.name** (string): User's display name
- **user.email** (string): User's email address
- **user.email_verified_at** (datetime|null): Timestamp when email was verified, null if not yet verified
- **user.created_at** (datetime): Timestamp when account was created
- **access_token** (string): Laravel Sanctum API token for authentication
- **token_type** (string): Always "Bearer" - indicates how to use the token

#### Error Responses

**Validation Error (422 Unprocessable Entity)**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "email": ["The email has already been taken."],
      "password": ["The password field confirmation does not match."]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Warnings & Caveats

- The `password` field uses Laravel's `Password::defaults()` validation rule. As of Laravel 11, the default requires:
  - Minimum 8 characters
  - No maximum length (unless configured)
  - Additional rules may be configured in `AppServiceProvider`
- The `password_confirmation` field is required due to the `confirmed` validation rule
- Email addresses must be unique across all users
- The token returned is a plaintext token and will not be accessible again - clients must store it securely
- Token abilities are set to `['*']`, granting full access to all API endpoints
- The token name is always `'api-token'`

---

### 2. Login User

**POST** `/api/auth/login`

Authenticates a user with email and password, returning an API access token.

**Authentication Required:** No

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| email | string | Yes | User's email address |
| password | string | Yes | User's password |

#### Request Body Example
```json
{
  "email": "mal@serenity.com",
  "password": "SecurePassword123!"
}
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Captain Reynolds",
      "email": "mal@serenity.com",
      "email_verified_at": null
    },
    "access_token": "2|xyz7890abcdef1234567890abcdef12",
    "token_type": "Bearer"
  },
  "message": "Login successful",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields Explained

- **user.id** (integer): User's unique ID
- **user.name** (string): User's display name
- **user.email** (string): User's email address
- **user.email_verified_at** (datetime|null): Email verification timestamp
- **access_token** (string): New API token for authentication
- **token_type** (string): Always "Bearer"

#### Error Responses

**Invalid Credentials (401 Unauthorized)**
```json
{
  "success": false,
  "error": {
    "code": "INVALID_CREDENTIALS",
    "message": "The provided credentials are incorrect.",
    "details": null
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

**Validation Error (422 Unprocessable Entity)**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "email": ["The email field is required."],
      "password": ["The password field is required."]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Warnings & Caveats

- **Token Revocation**: ALL existing tokens for the user are revoked on successful login to prevent token accumulation
- This means any other active sessions will be immediately logged out
- The same generic error message is returned whether the email doesn't exist or the password is wrong (security best practice)
- Password comparison uses `Hash::check()` for secure bcrypt verification
- Login does NOT return `created_at` timestamp (unlike register)

---

### 3. Logout User

**POST** `/api/auth/logout`

Revokes the current access token, effectively logging the user out.

**Authentication Required:** Yes

#### Request Parameters

None

#### Request Body Example
```json
{}
```

#### Request Headers
```
Authorization: Bearer 2|xyz7890abcdef1234567890abcdef12
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": null,
  "message": "Logged out successfully",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields Explained

- **data** (null): No data is returned on successful logout

#### Error Responses

**Unauthorized (401 Unauthorized)**
```json
{
  "message": "Unauthenticated."
}
```
*Note: This error response is generated by Laravel Sanctum middleware, not the BaseApiController, so it has a different format.*

#### Warnings & Caveats

- Only the **current token** is revoked (the one used in the Authorization header)
- Other tokens for the same user (if any exist) remain valid
- After logout, the revoked token cannot be used again
- The endpoint requires a valid Bearer token in the Authorization header
- If the token is already revoked or invalid, a 401 Unauthenticated error is returned

---

### 4. Refresh Token

**POST** `/api/auth/refresh`

Revokes the current token and issues a new one. Useful for token rotation security practices.

**Authentication Required:** Yes

#### Request Parameters

None

#### Request Body Example
```json
{}
```

#### Request Headers
```
Authorization: Bearer 2|xyz7890abcdef1234567890abcdef12
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "access_token": "3|newtoken1234567890abcdef1234567890",
    "token_type": "Bearer"
  },
  "message": "Token refreshed successfully",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields Explained

- **access_token** (string): New API token to use for subsequent requests
- **token_type** (string): Always "Bearer"

#### Error Responses

**Unauthorized (401 Unauthorized)**
```json
{
  "message": "Unauthenticated."
}
```

#### Warnings & Caveats

- The old token is **immediately revoked** before the new token is created
- There is a brief moment where the old token is invalid but the new token is not yet issued
- The new token has the same abilities (`['*']`) as the old token
- Clients should replace the stored token with the new one immediately upon receiving the response
- If the refresh request fails after token revocation, the user will need to log in again
- Token refresh does NOT extend any expiration time (Sanctum tokens don't expire by default unless configured)

---

### 5. Get Current User

**GET** `/api/auth/me`

Retrieves the currently authenticated user's information.

**Authentication Required:** Yes

#### Request Parameters

None

#### Request Headers
```
Authorization: Bearer 2|xyz7890abcdef1234567890abcdef12
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Captain Reynolds",
    "email": "mal@serenity.com",
    "email_verified_at": null,
    "created_at": "2026-02-16T12:00:00.000000Z",
    "updated_at": "2026-02-16T12:00:00.000000Z"
  },
  "message": "",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields Explained

- **id** (integer): User's unique ID
- **name** (string): User's display name
- **email** (string): User's email address
- **email_verified_at** (datetime|null): Email verification timestamp
- **created_at** (datetime): Account creation timestamp
- **updated_at** (datetime): Last account update timestamp

#### Error Responses

**Unauthorized (401 Unauthorized)**
```json
{
  "message": "Unauthenticated."
}
```

#### Warnings & Caveats

- The `password` and `remember_token` fields are hidden and will never be returned
- The `updated_at` timestamp changes whenever the user record is modified (name, email, password, etc.)
- This endpoint returns more detail than login/register responses (includes `updated_at`)
- No message is included in the success response (empty string)

---

### 6. Verify Email

**POST** `/api/auth/verify-email`

Verifies a user's email address using a verification code.

**Authentication Required:** No

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| email | string | Yes | User's email address to verify |
| verification_code | string | Yes | Verification code sent to the email |

#### Request Body Example
```json
{
  "email": "mal@serenity.com",
  "verification_code": "ABC123XYZ"
}
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": null,
  "message": "Email verification endpoint (not yet implemented)",
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Response Fields Explained

- **data** (null): No data is returned (endpoint is not fully implemented)

#### Error Responses

**Validation Error (422 Unprocessable Entity)**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "errors": {
      "email": ["The email field is required."],
      "verification_code": ["The verification code field is required."]
    }
  },
  "meta": {
    "timestamp": "2026-02-16T12:00:00+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Warnings & Caveats

- **ENDPOINT NOT FULLY IMPLEMENTED**: This is a placeholder endpoint (see TODO comment in controller line 169-174)
- The endpoint currently only validates the input and returns a success message
- No actual email verification logic is implemented yet
- In a production implementation, this endpoint should:
  1. Check the verification code against database/cache storage
  2. Verify the code hasn't expired
  3. Mark the user's `email_verified_at` field with the current timestamp
  4. Potentially send a confirmation email
- The endpoint does NOT require authentication, allowing users to verify their email before logging in
- No security measures are currently in place to prevent brute-force code guessing

---

## Authentication Flow

### Typical User Registration Flow
1. Client sends POST to `/api/auth/register` with user details
2. Server creates user account and returns access token
3. Client stores token for subsequent API requests
4. (Optional) Client sends POST to `/api/auth/verify-email` with verification code

### Typical User Login Flow
1. Client sends POST to `/api/auth/login` with credentials
2. Server validates credentials and revokes all existing tokens
3. Server creates new token and returns it
4. Client stores token for subsequent API requests

### Token Usage
All authenticated endpoints require the token in the Authorization header:
```
Authorization: Bearer {access_token}
```

### Token Management Best Practices
- Store tokens securely (e.g., httpOnly cookies, secure storage)
- Never expose tokens in URLs or logs
- Implement token refresh on a regular schedule
- Handle 401 errors by redirecting to login
- Revoke tokens on logout

---

## Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| VALIDATION_ERROR | 422 | Request validation failed |
| INVALID_CREDENTIALS | 401 | Email or password incorrect |
| UNAUTHORIZED | 401 | Missing or invalid authentication token |
| ERROR | 400 | Generic error (varies by endpoint) |

---

## Security Considerations

1. **Password Storage**: All passwords are hashed using bcrypt via Laravel's `Hash::make()`
2. **Token Security**: Tokens use Laravel Sanctum's secure token generation
3. **Credential Validation**: Generic error messages prevent user enumeration
4. **Token Revocation**: Login revokes all existing tokens to prevent accumulation
5. **HTTPS Required**: All authentication endpoints should be accessed over HTTPS in production
6. **Rate Limiting**: Consider implementing rate limiting on login/register endpoints
7. **Email Verification**: Email verification endpoint is not yet implemented with security measures

---

## Testing Authentication Endpoints

### Example: Register and Login with cURL

```bash
# Register a new user
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
  }'

# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePassword123!"
  }'

# Get current user (use token from login response)
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer {your_token_here}"

# Refresh token
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Authorization: Bearer {your_token_here}"

# Logout
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer {your_token_here}"
```

---

## Related Documentation

- [API Overview](/docs/api/README.md)
- [Laravel Sanctum Documentation](https://laravel.com/docs/11.x/sanctum)
- [Password Validation Rules](https://laravel.com/docs/11.x/validation#rule-password)

---

*Last updated: 2026-02-16*

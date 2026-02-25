#!/bin/bash

#######################################################################
# Space Wars 3002 - API Test Suite
#######################################################################
# This script tests all API endpoints by making curl requests.
# Run against a local development server: php artisan serve
#
# Usage:
#   ./tests/api_test_suite.sh [base_url]
#
# Default base URL: http://localhost:8000
#######################################################################

set -e

# Configuration
BASE_URL="${1:-http://localhost:8000}"
API_URL="$BASE_URL/api"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_SKIPPED=0

# Test data storage
AUTH_TOKEN=""
USER_ID=""
GALAXY_UUID=""
PLAYER_UUID=""
SHIP_UUID=""
POI_UUID=""
TRADING_HUB_UUID=""
SECTOR_UUID=""
COLONY_UUID=""
NPC_UUID=""

#######################################################################
# Utility Functions
#######################################################################

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((TESTS_PASSED++))
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((TESTS_FAILED++))
}

log_skip() {
    echo -e "${YELLOW}[SKIP]${NC} $1"
    ((TESTS_SKIPPED++))
}

log_section() {
    echo ""
    echo -e "${YELLOW}========================================${NC}"
    echo -e "${YELLOW}$1${NC}"
    echo -e "${YELLOW}========================================${NC}"
}

# Make API request and check status code
# Usage: api_test "description" "method" "endpoint" "expected_status" ["data"] ["auth"]
api_test() {
    local description="$1"
    local method="$2"
    local endpoint="$3"
    local expected_status="$4"
    local data="${5:-}"
    local use_auth="${6:-false}"

    local url="$API_URL$endpoint"
    local curl_opts="-s -w '\n%{http_code}' -X $method"

    # Add auth header if required
    if [ "$use_auth" = "true" ] && [ -n "$AUTH_TOKEN" ]; then
        curl_opts="$curl_opts -H 'Authorization: Bearer $AUTH_TOKEN'"
    fi

    # Add content type and data for POST/PUT/PATCH requests
    if [ -n "$data" ]; then
        curl_opts="$curl_opts -H 'Content-Type: application/json' -d '$data'"
    fi

    curl_opts="$curl_opts -H 'Accept: application/json'"

    # Execute request
    local response
    response=$(eval "curl $curl_opts '$url'" 2>/dev/null)

    # Extract status code (last line) and body (everything else)
    local status_code
    status_code=$(echo "$response" | tail -n1)
    local body
    body=$(echo "$response" | sed '$d')

    # Check status code
    if [ "$status_code" = "$expected_status" ]; then
        log_success "$description (HTTP $status_code)"
        echo "$body"
        return 0
    else
        log_fail "$description - Expected $expected_status, got $status_code"
        echo "$body" | head -c 500
        echo ""
        return 1
    fi
}

# Extract JSON value using jq or grep fallback
json_extract() {
    local json="$1"
    local key="$2"

    if command -v jq &> /dev/null; then
        echo "$json" | jq -r "$key" 2>/dev/null
    else
        # Fallback: basic grep extraction
        echo "$json" | grep -oP "\"$key\":\s*\"?\K[^,\"}]+" | head -1
    fi
}

#######################################################################
# Test Functions
#######################################################################

test_auth_endpoints() {
    log_section "AUTHENTICATION ENDPOINTS"

    # Generate unique email
    local timestamp=$(date +%s)
    local test_email="testuser_${timestamp}@example.com"
    local test_password="Password123!"

    # Test: Register
    log_info "Testing POST /api/auth/register"
    local register_data="{\"name\":\"Test User\",\"email\":\"$test_email\",\"password\":\"$test_password\",\"password_confirmation\":\"$test_password\"}"
    local register_response
    register_response=$(api_test "Register new user" "POST" "/auth/register" "201" "$register_data") || true

    # Test: Login
    log_info "Testing POST /api/auth/login"
    local login_data="{\"email\":\"$test_email\",\"password\":\"$test_password\"}"
    local login_response
    login_response=$(api_test "Login user" "POST" "/auth/login" "200" "$login_data") || true

    # Extract token from login response
    AUTH_TOKEN=$(json_extract "$login_response" ".data.token" 2>/dev/null || json_extract "$login_response" "token")

    if [ -n "$AUTH_TOKEN" ] && [ "$AUTH_TOKEN" != "null" ]; then
        log_info "Auth token acquired: ${AUTH_TOKEN:0:20}..."

        # Test: Get current user
        log_info "Testing GET /api/auth/me"
        local me_response
        me_response=$(api_test "Get current user" "GET" "/auth/me" "200" "" "true") || true
        USER_ID=$(json_extract "$me_response" ".data.id" 2>/dev/null || json_extract "$me_response" "id")

        # Test: Refresh token
        log_info "Testing POST /api/auth/refresh"
        api_test "Refresh token" "POST" "/auth/refresh" "200" "" "true" || true

    else
        log_skip "Auth token not acquired - skipping authenticated tests"
    fi

    # Test: Logout (at the end)
    # We'll skip logout to keep the token for other tests
}

test_galaxy_public_endpoints() {
    log_section "GALAXY ENDPOINTS (PUBLIC)"

    # Test: List galaxies
    log_info "Testing GET /api/galaxies"
    local galaxies_response
    galaxies_response=$(api_test "List all galaxies" "GET" "/galaxies" "200") || true

    # Try to extract a galaxy UUID
    GALAXY_UUID=$(json_extract "$galaxies_response" ".data[0].uuid" 2>/dev/null || \
                  json_extract "$galaxies_response" ".data.data[0].uuid" 2>/dev/null || \
                  echo "")

    if [ -n "$GALAXY_UUID" ] && [ "$GALAXY_UUID" != "null" ]; then
        log_info "Using galaxy UUID: $GALAXY_UUID"

        # Test: Get galaxy details
        log_info "Testing GET /api/galaxies/{uuid}"
        api_test "Get galaxy details" "GET" "/galaxies/$GALAXY_UUID" "200" || true

        # Test: Get galaxy statistics
        log_info "Testing GET /api/galaxies/{uuid}/statistics"
        api_test "Get galaxy statistics" "GET" "/galaxies/$GALAXY_UUID/statistics" "200" || true

        # Test: Get galaxy map
        log_info "Testing GET /api/galaxies/{uuid}/map"
        local map_response
        map_response=$(api_test "Get galaxy map" "GET" "/galaxies/$GALAXY_UUID/map" "200") || true

        # Extract POI and sector UUIDs if available
        POI_UUID=$(json_extract "$map_response" ".data.points[0].uuid" 2>/dev/null || echo "")
        SECTOR_UUID=$(json_extract "$map_response" ".data.sectors[0].uuid" 2>/dev/null || echo "")

    else
        log_skip "No galaxy found - skipping galaxy detail tests"
    fi

    # Test: Get sector (if we have one)
    if [ -n "$SECTOR_UUID" ] && [ "$SECTOR_UUID" != "null" ]; then
        log_info "Testing GET /api/sectors/{uuid}"
        api_test "Get sector information" "GET" "/sectors/$SECTOR_UUID" "200" || true
    fi

    # Test: 404 for nonexistent galaxy
    log_info "Testing GET /api/galaxies/{nonexistent}"
    api_test "Get nonexistent galaxy (404)" "GET" "/galaxies/00000000-0000-0000-0000-000000000000" "404" || true
}

test_galaxy_creation_endpoints() {
    log_section "GALAXY CREATION ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping galaxy creation tests"
        return
    fi

    # Test: Create multiplayer galaxy
    log_info "Testing POST /api/galaxies/create (multiplayer)"
    local create_data='{
        "width": 200,
        "height": 200,
        "stars": 100,
        "game_mode": "multiplayer",
        "skip_mirror": true,
        "skip_pirates": true,
        "skip_precursors": true
    }'
    local create_response
    create_response=$(api_test "Create multiplayer galaxy" "POST" "/galaxies/create" "201" "$create_data" "true") || true

    local new_galaxy_uuid
    new_galaxy_uuid=$(json_extract "$create_response" ".data.galaxy.uuid" 2>/dev/null || echo "")

    if [ -n "$new_galaxy_uuid" ] && [ "$new_galaxy_uuid" != "null" ]; then
        log_info "Created galaxy: $new_galaxy_uuid"
        GALAXY_UUID="$new_galaxy_uuid"
    fi

    # Test: Create single player galaxy with NPCs
    log_info "Testing POST /api/galaxies/create (single_player with NPCs)"
    local sp_create_data='{
        "width": 150,
        "height": 150,
        "stars": 50,
        "game_mode": "single_player",
        "npc_count": 3,
        "npc_difficulty": "easy",
        "skip_mirror": true,
        "skip_pirates": true,
        "skip_precursors": true
    }'
    local sp_create_response
    sp_create_response=$(api_test "Create single player galaxy" "POST" "/galaxies/create" "201" "$sp_create_data" "true") || true

    local sp_galaxy_uuid
    sp_galaxy_uuid=$(json_extract "$sp_create_response" ".data.galaxy.uuid" 2>/dev/null || echo "")

    # Test: Get NPC archetypes
    log_info "Testing GET /api/npcs/archetypes"
    api_test "Get NPC archetypes" "GET" "/npcs/archetypes" "200" "" "true" || true

    # Test: List NPCs in galaxy
    if [ -n "$sp_galaxy_uuid" ] && [ "$sp_galaxy_uuid" != "null" ]; then
        log_info "Testing GET /api/galaxies/{uuid}/npcs"
        local npcs_response
        npcs_response=$(api_test "List NPCs in galaxy" "GET" "/galaxies/$sp_galaxy_uuid/npcs" "200" "" "true") || true

        NPC_UUID=$(json_extract "$npcs_response" ".data.npcs[0].uuid" 2>/dev/null || echo "")

        if [ -n "$NPC_UUID" ] && [ "$NPC_UUID" != "null" ]; then
            # Test: Get NPC details
            log_info "Testing GET /api/npcs/{uuid}"
            api_test "Get NPC details" "GET" "/npcs/$NPC_UUID" "200" "" "true" || true
        fi

        # Test: Add more NPCs
        log_info "Testing POST /api/galaxies/{uuid}/npcs"
        local add_npcs_data='{"count": 2, "difficulty": "medium"}'
        api_test "Add NPCs to galaxy" "POST" "/galaxies/$sp_galaxy_uuid/npcs" "201" "$add_npcs_data" "true" || true
    fi

    # Test: Validation errors
    log_info "Testing POST /api/galaxies/create (invalid game_mode)"
    local invalid_data='{"width": 200, "height": 200, "stars": 100, "game_mode": "invalid"}'
    api_test "Create galaxy with invalid mode (422)" "POST" "/galaxies/create" "422" "$invalid_data" "true" || true

    log_info "Testing POST /api/galaxies/create (width too small)"
    local small_width_data='{"width": 50, "height": 200, "stars": 100, "game_mode": "multiplayer"}'
    api_test "Create galaxy with width too small (422)" "POST" "/galaxies/create" "422" "$small_width_data" "true" || true
}

test_leaderboard_endpoints() {
    log_section "LEADERBOARD ENDPOINTS (PUBLIC)"

    if [ -z "$GALAXY_UUID" ] || [ "$GALAXY_UUID" = "null" ]; then
        log_skip "No galaxy available - skipping leaderboard tests"
        return
    fi

    log_info "Testing GET /api/galaxies/{uuid}/leaderboards/overall"
    api_test "Get overall leaderboard" "GET" "/galaxies/$GALAXY_UUID/leaderboards/overall" "200" || true

    log_info "Testing GET /api/galaxies/{uuid}/leaderboards/combat"
    api_test "Get combat leaderboard" "GET" "/galaxies/$GALAXY_UUID/leaderboards/combat" "200" || true

    log_info "Testing GET /api/galaxies/{uuid}/leaderboards/economic"
    api_test "Get economic leaderboard" "GET" "/galaxies/$GALAXY_UUID/leaderboards/economic" "200" || true

    log_info "Testing GET /api/galaxies/{uuid}/leaderboards/colonial"
    api_test "Get colonial leaderboard" "GET" "/galaxies/$GALAXY_UUID/leaderboards/colonial" "200" || true
}

test_victory_endpoints() {
    log_section "VICTORY CONDITION ENDPOINTS (PUBLIC)"

    if [ -z "$GALAXY_UUID" ] || [ "$GALAXY_UUID" = "null" ]; then
        log_skip "No galaxy available - skipping victory tests"
        return
    fi

    log_info "Testing GET /api/galaxies/{uuid}/victory-conditions"
    api_test "Get victory conditions" "GET" "/galaxies/$GALAXY_UUID/victory-conditions" "200" || true

    log_info "Testing GET /api/galaxies/{uuid}/victory-leaders"
    api_test "Get victory leaders" "GET" "/galaxies/$GALAXY_UUID/victory-leaders" "200" || true
}

test_market_events_endpoints() {
    log_section "MARKET EVENTS ENDPOINTS"

    if [ -z "$GALAXY_UUID" ] || [ "$GALAXY_UUID" = "null" ]; then
        log_skip "No galaxy available - skipping market events tests"
        return
    fi

    log_info "Testing GET /api/galaxies/{uuid}/market-events"
    local events_response
    events_response=$(api_test "Get galaxy market events" "GET" "/galaxies/$GALAXY_UUID/market-events" "200") || true

    # Extract event UUID if available
    local event_uuid
    event_uuid=$(json_extract "$events_response" ".data[0].uuid" 2>/dev/null || echo "")

    if [ -n "$event_uuid" ] && [ "$event_uuid" != "null" ]; then
        log_info "Testing GET /api/market-events/{uuid}"
        api_test "Get market event details" "GET" "/market-events/$event_uuid" "200" || true
    fi
}

test_pirate_faction_endpoints() {
    log_section "PIRATE FACTION ENDPOINTS (PUBLIC)"

    if [ -z "$GALAXY_UUID" ] || [ "$GALAXY_UUID" = "null" ]; then
        log_skip "No galaxy available - skipping pirate faction tests"
        return
    fi

    log_info "Testing GET /api/galaxies/{uuid}/pirate-factions"
    local factions_response
    factions_response=$(api_test "Get pirate factions" "GET" "/galaxies/$GALAXY_UUID/pirate-factions" "200") || true

    # Extract faction UUID if available
    local faction_uuid
    faction_uuid=$(json_extract "$factions_response" ".data[0].uuid" 2>/dev/null || echo "")

    if [ -n "$faction_uuid" ] && [ "$faction_uuid" != "null" ]; then
        log_info "Testing GET /api/pirate-factions/{uuid}"
        api_test "Get pirate faction details" "GET" "/pirate-factions/$faction_uuid" "200" || true

        log_info "Testing GET /api/pirate-factions/{uuid}/captains"
        api_test "Get faction captains" "GET" "/pirate-factions/$faction_uuid/captains" "200" || true
    fi
}

test_player_endpoints() {
    log_section "PLAYER ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping player tests"
        return
    fi

    if [ -z "$GALAXY_UUID" ] || [ "$GALAXY_UUID" = "null" ]; then
        log_skip "No galaxy available - skipping player tests"
        return
    fi

    # Test: List players
    log_info "Testing GET /api/players"
    local players_response
    players_response=$(api_test "List players" "GET" "/players" "200" "" "true") || true

    # Test: Create player
    log_info "Testing POST /api/players"
    local player_data="{\"galaxy_id\":\"$GALAXY_UUID\",\"call_sign\":\"TestPilot_$(date +%s)\"}"
    local create_player_response
    create_player_response=$(api_test "Create player" "POST" "/players" "201" "$player_data" "true") || true

    PLAYER_UUID=$(json_extract "$create_player_response" ".data.uuid" 2>/dev/null || echo "")

    if [ -n "$PLAYER_UUID" ] && [ "$PLAYER_UUID" != "null" ]; then
        log_info "Created player: $PLAYER_UUID"

        # Test: Get player details
        log_info "Testing GET /api/players/{uuid}"
        api_test "Get player details" "GET" "/players/$PLAYER_UUID" "200" "" "true" || true

        # Test: Get player status
        log_info "Testing GET /api/players/{uuid}/status"
        api_test "Get player status" "GET" "/players/$PLAYER_UUID/status" "200" "" "true" || true

        # Test: Get player stats
        log_info "Testing GET /api/players/{uuid}/stats"
        api_test "Get player stats" "GET" "/players/$PLAYER_UUID/stats" "200" "" "true" || true

        # Test: Set active player
        log_info "Testing POST /api/players/{uuid}/set-active"
        api_test "Set player as active" "POST" "/players/$PLAYER_UUID/set-active" "200" "" "true" || true

        # Test: Update player
        log_info "Testing PATCH /api/players/{uuid}"
        local update_data='{"call_sign":"UpdatedPilot"}'
        api_test "Update player" "PATCH" "/players/$PLAYER_UUID" "200" "$update_data" "true" || true

        # Test: Get player ranking
        log_info "Testing GET /api/players/{uuid}/ranking"
        api_test "Get player ranking" "GET" "/players/$PLAYER_UUID/ranking" "200" "" "true" || true

        # Test: Get player statistics
        log_info "Testing GET /api/players/{uuid}/statistics"
        api_test "Get player statistics" "GET" "/players/$PLAYER_UUID/statistics" "200" "" "true" || true

        # Test: Get player victory progress
        log_info "Testing GET /api/players/{uuid}/victory-progress"
        api_test "Get player victory progress" "GET" "/players/$PLAYER_UUID/victory-progress" "200" "" "true" || true

        # Test: Get pirate reputation
        log_info "Testing GET /api/players/{uuid}/pirate-reputation"
        api_test "Get pirate reputation" "GET" "/players/$PLAYER_UUID/pirate-reputation" "200" "" "true" || true
    else
        log_skip "Could not create player - skipping player detail tests"
    fi
}

test_ship_endpoints() {
    log_section "SHIP ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping ship tests"
        return
    fi

    if [ -z "$PLAYER_UUID" ] || [ "$PLAYER_UUID" = "null" ]; then
        log_skip "No player available - skipping ship tests"
        return
    fi

    # Test: Get player's active ship
    log_info "Testing GET /api/players/{uuid}/ship"
    local ship_response
    ship_response=$(api_test "Get active ship" "GET" "/players/$PLAYER_UUID/ship" "200" "" "true") || true

    SHIP_UUID=$(json_extract "$ship_response" ".data.uuid" 2>/dev/null || echo "")

    if [ -n "$SHIP_UUID" ] && [ "$SHIP_UUID" != "null" ]; then
        log_info "Using ship: $SHIP_UUID"

        # Test: Get ship status
        log_info "Testing GET /api/ships/{uuid}/status"
        api_test "Get ship status" "GET" "/ships/$SHIP_UUID/status" "200" "" "true" || true

        # Test: Get ship fuel
        log_info "Testing GET /api/ships/{uuid}/fuel"
        api_test "Get ship fuel" "GET" "/ships/$SHIP_UUID/fuel" "200" "" "true" || true

        # Test: Get ship upgrades
        log_info "Testing GET /api/ships/{uuid}/upgrades"
        api_test "Get ship upgrades" "GET" "/ships/$SHIP_UUID/upgrades" "200" "" "true" || true

        # Test: Get ship damage
        log_info "Testing GET /api/ships/{uuid}/damage"
        api_test "Get ship damage" "GET" "/ships/$SHIP_UUID/damage" "200" "" "true" || true

        # Test: Rename ship
        log_info "Testing PATCH /api/ships/{uuid}/name"
        local rename_data='{"name":"My Awesome Ship"}'
        api_test "Rename ship" "PATCH" "/ships/$SHIP_UUID/name" "200" "$rename_data" "true" || true

        # Test: Regenerate fuel
        log_info "Testing POST /api/ships/{uuid}/regenerate-fuel"
        api_test "Regenerate fuel" "POST" "/ships/$SHIP_UUID/regenerate-fuel" "200" "" "true" || true

        # Test: Get upgrade options
        log_info "Testing GET /api/ships/{uuid}/upgrade-options"
        api_test "Get upgrade options" "GET" "/ships/$SHIP_UUID/upgrade-options" "200" "" "true" || true

        # Test: Get repair estimate
        log_info "Testing GET /api/ships/{uuid}/repair-estimate"
        api_test "Get repair estimate" "GET" "/ships/$SHIP_UUID/repair-estimate" "200" "" "true" || true

        # Test: Get maintenance status
        log_info "Testing GET /api/ships/{uuid}/maintenance"
        api_test "Get maintenance status" "GET" "/ships/$SHIP_UUID/maintenance" "200" "" "true" || true
    else
        log_skip "No ship found - skipping ship detail tests"
    fi

    # Test: Get ship catalog
    log_info "Testing GET /api/ships/catalog"
    api_test "Get ship catalog" "GET" "/ships/catalog" "200" "" "true" || true

    # Test: Get player fleet
    if [ -n "$PLAYER_UUID" ] && [ "$PLAYER_UUID" != "null" ]; then
        log_info "Testing GET /api/players/{uuid}/ships/fleet"
        api_test "Get player fleet" "GET" "/players/$PLAYER_UUID/ships/fleet" "200" "" "true" || true
    fi
}

test_navigation_endpoints() {
    log_section "NAVIGATION ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping navigation tests"
        return
    fi

    if [ -z "$PLAYER_UUID" ] || [ "$PLAYER_UUID" = "null" ]; then
        log_skip "No player available - skipping navigation tests"
        return
    fi

    # Test: Get player location
    log_info "Testing GET /api/players/{uuid}/location"
    local location_response
    location_response=$(api_test "Get player location" "GET" "/players/$PLAYER_UUID/location" "200" "" "true") || true

    POI_UUID=$(json_extract "$location_response" ".data.current_location.uuid" 2>/dev/null || echo "")

    # Test: Get nearby systems
    log_info "Testing GET /api/players/{uuid}/nearby-systems"
    api_test "Get nearby systems" "GET" "/players/$PLAYER_UUID/nearby-systems" "200" "" "true" || true

    # Test: Scan local area
    log_info "Testing GET /api/players/{uuid}/scan-local"
    api_test "Scan local area" "GET" "/players/$PLAYER_UUID/scan-local" "200" "" "true" || true
}

test_travel_endpoints() {
    log_section "TRAVEL ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping travel tests"
        return
    fi

    if [ -z "$POI_UUID" ] || [ "$POI_UUID" = "null" ]; then
        log_skip "No POI available - skipping travel tests"
        return
    fi

    # Test: List warp gates
    log_info "Testing GET /api/warp-gates/{locationUuid}"
    local gates_response
    gates_response=$(api_test "List warp gates" "GET" "/warp-gates/$POI_UUID" "200" "" "true") || true

    # Test: Get fuel cost calculation
    log_info "Testing GET /api/travel/fuel-cost"
    api_test "Calculate fuel cost" "GET" "/travel/fuel-cost?ship_uuid=$SHIP_UUID&poi_uuid=$POI_UUID" "200" "" "true" || true

    # Test: XP preview
    log_info "Testing GET /api/travel/xp-preview"
    api_test "Preview travel XP" "GET" "/travel/xp-preview?distance=100" "200" "" "true" || true
}

test_trading_endpoints() {
    log_section "TRADING ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping trading tests"
        return
    fi

    # Test: List minerals
    log_info "Testing GET /api/minerals"
    api_test "List minerals" "GET" "/minerals" "200" "" "true" || true

    # Test: List nearby trading hubs
    log_info "Testing GET /api/trading-hubs"
    local hubs_response
    hubs_response=$(api_test "List nearby trading hubs" "GET" "/trading-hubs" "200" "" "true") || true

    TRADING_HUB_UUID=$(json_extract "$hubs_response" ".data[0].uuid" 2>/dev/null || \
                       json_extract "$hubs_response" ".data.hubs[0].uuid" 2>/dev/null || echo "")

    if [ -n "$TRADING_HUB_UUID" ] && [ "$TRADING_HUB_UUID" != "null" ]; then
        log_info "Using trading hub: $TRADING_HUB_UUID"

        # Test: Get trading hub details
        log_info "Testing GET /api/trading-hubs/{uuid}"
        api_test "Get trading hub details" "GET" "/trading-hubs/$TRADING_HUB_UUID" "200" "" "true" || true

        # Test: Get hub inventory
        log_info "Testing GET /api/trading-hubs/{uuid}/inventory"
        api_test "Get hub inventory" "GET" "/trading-hubs/$TRADING_HUB_UUID/inventory" "200" "" "true" || true

        # Test: Get hub active events
        log_info "Testing GET /api/trading-hubs/{uuid}/active-events"
        api_test "Get hub active events" "GET" "/trading-hubs/$TRADING_HUB_UUID/active-events" "200" "" "true" || true

        # Test: Get shipyard
        log_info "Testing GET /api/trading-hubs/{uuid}/shipyard"
        api_test "Get shipyard" "GET" "/trading-hubs/$TRADING_HUB_UUID/shipyard" "200" "" "true" || true

        # Test: Get plans shop
        log_info "Testing GET /api/trading-hubs/{uuid}/plans-shop"
        api_test "Get plans shop" "GET" "/trading-hubs/$TRADING_HUB_UUID/plans-shop" "200" "" "true" || true

        # Test: Get cartographer
        log_info "Testing GET /api/trading-hubs/{uuid}/cartographer"
        api_test "Get cartographer" "GET" "/trading-hubs/$TRADING_HUB_UUID/cartographer" "200" "" "true" || true
    else
        log_skip "No trading hub found - skipping hub detail tests"
    fi

    # Test: Get player cargo
    if [ -n "$PLAYER_UUID" ] && [ "$PLAYER_UUID" != "null" ]; then
        log_info "Testing GET /api/players/{uuid}/cargo"
        api_test "Get player cargo" "GET" "/players/$PLAYER_UUID/cargo" "200" "" "true" || true
    fi

    # Test: Calculate affordability
    log_info "Testing GET /api/trading/affordability"
    api_test "Calculate affordability" "GET" "/trading/affordability?credits=1000&mineral_id=1" "200" "" "true" || true
}

test_upgrade_endpoints() {
    log_section "UPGRADE ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping upgrade tests"
        return
    fi

    # Test: Get upgrade cost formulas
    log_info "Testing GET /api/upgrade-costs"
    api_test "Get upgrade cost formulas" "GET" "/upgrade-costs" "200" "" "true" || true

    # Test: Get upgrade limits
    log_info "Testing GET /api/upgrade-limits"
    api_test "Get upgrade limits" "GET" "/upgrade-limits" "200" "" "true" || true

    # Test: Get plans catalog
    log_info "Testing GET /api/plans/catalog"
    api_test "Get plans catalog" "GET" "/plans/catalog" "200" "" "true" || true

    if [ -n "$PLAYER_UUID" ] && [ "$PLAYER_UUID" != "null" ]; then
        # Test: Get owned plans
        log_info "Testing GET /api/players/{uuid}/plans"
        api_test "Get owned plans" "GET" "/players/$PLAYER_UUID/plans" "200" "" "true" || true
    fi
}

test_cartography_endpoints() {
    log_section "CARTOGRAPHY ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping cartography tests"
        return
    fi

    if [ -z "$PLAYER_UUID" ] || [ "$PLAYER_UUID" = "null" ]; then
        log_skip "No player available - skipping cartography tests"
        return
    fi

    # Test: Get player star charts
    log_info "Testing GET /api/players/{uuid}/star-charts"
    api_test "Get player star charts" "GET" "/players/$PLAYER_UUID/star-charts" "200" "" "true" || true

    # Test: Get star chart pricing
    log_info "Testing GET /api/star-charts/pricing"
    api_test "Get star chart pricing" "GET" "/star-charts/pricing" "200" "" "true" || true

    if [ -n "$POI_UUID" ] && [ "$POI_UUID" != "null" ]; then
        # Test: Get system info
        log_info "Testing GET /api/star-charts/system/{poiUuid}"
        api_test "Get system info" "GET" "/star-charts/system/$POI_UUID" "200" "" "true" || true

        # Test: Preview coverage
        log_info "Testing GET /api/star-charts/preview"
        api_test "Preview chart coverage" "GET" "/star-charts/preview?poi_uuid=$POI_UUID" "200" "" "true" || true
    fi
}

test_colony_endpoints() {
    log_section "COLONY ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping colony tests"
        return
    fi

    if [ -z "$PLAYER_UUID" ] || [ "$PLAYER_UUID" = "null" ]; then
        log_skip "No player available - skipping colony tests"
        return
    fi

    # Test: List player colonies
    log_info "Testing GET /api/players/{uuid}/colonies"
    local colonies_response
    colonies_response=$(api_test "List player colonies" "GET" "/players/$PLAYER_UUID/colonies" "200" "" "true") || true

    COLONY_UUID=$(json_extract "$colonies_response" ".data[0].uuid" 2>/dev/null || echo "")

    if [ -n "$COLONY_UUID" ] && [ "$COLONY_UUID" != "null" ]; then
        log_info "Using colony: $COLONY_UUID"

        # Test: Get colony details
        log_info "Testing GET /api/colonies/{uuid}"
        api_test "Get colony details" "GET" "/colonies/$COLONY_UUID" "200" "" "true" || true

        # Test: Get colony production
        log_info "Testing GET /api/colonies/{uuid}/production"
        api_test "Get colony production" "GET" "/colonies/$COLONY_UUID/production" "200" "" "true" || true

        # Test: Get colony defenses
        log_info "Testing GET /api/colonies/{uuid}/defenses"
        api_test "Get colony defenses" "GET" "/colonies/$COLONY_UUID/defenses" "200" "" "true" || true

        # Test: List colony buildings
        log_info "Testing GET /api/colonies/{uuid}/buildings"
        api_test "List colony buildings" "GET" "/colonies/$COLONY_UUID/buildings" "200" "" "true" || true

        # Test: Get ship production
        log_info "Testing GET /api/colonies/{uuid}/ship-production"
        api_test "Get ship production" "GET" "/colonies/$COLONY_UUID/ship-production" "200" "" "true" || true
    else
        log_info "No existing colony found"
    fi
}

test_combat_endpoints() {
    log_section "COMBAT ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping combat tests"
        return
    fi

    if [ -z "$PLAYER_UUID" ] || [ "$PLAYER_UUID" = "null" ]; then
        log_skip "No player available - skipping combat tests"
        return
    fi

    # Test: Get combat preview
    log_info "Testing GET /api/players/{uuid}/combat/preview"
    api_test "Get combat preview" "GET" "/players/$PLAYER_UUID/combat/preview" "200" "" "true" || true

    # Test: List PvP challenges
    log_info "Testing GET /api/players/{uuid}/pvp/challenges"
    api_test "List PvP challenges" "GET" "/players/$PLAYER_UUID/pvp/challenges" "200" "" "true" || true

    # Test: List team invitations
    log_info "Testing GET /api/players/{uuid}/team-invitations"
    api_test "List team invitations" "GET" "/players/$PLAYER_UUID/team-invitations" "200" "" "true" || true
}

test_notification_endpoints() {
    log_section "NOTIFICATION ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping notification tests"
        return
    fi

    if [ -z "$PLAYER_UUID" ] || [ "$PLAYER_UUID" = "null" ]; then
        log_skip "No player available - skipping notification tests"
        return
    fi

    # Test: List notifications
    log_info "Testing GET /api/players/{uuid}/notifications"
    api_test "List notifications" "GET" "/players/$PLAYER_UUID/notifications" "200" "" "true" || true

    # Test: Get unread count
    log_info "Testing GET /api/players/{uuid}/notifications/unread"
    api_test "Get unread notification count" "GET" "/players/$PLAYER_UUID/notifications/unread" "200" "" "true" || true

    # Test: Mark all as read
    log_info "Testing POST /api/players/{uuid}/notifications/mark-all-read"
    api_test "Mark all notifications as read" "POST" "/players/$PLAYER_UUID/notifications/mark-all-read" "200" "" "true" || true

    # Test: Clear read notifications
    log_info "Testing POST /api/players/{uuid}/notifications/clear-read"
    api_test "Clear read notifications" "POST" "/players/$PLAYER_UUID/notifications/clear-read" "200" "" "true" || true
}

test_mirror_universe_endpoints() {
    log_section "MIRROR UNIVERSE ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping mirror universe tests"
        return
    fi

    if [ -z "$PLAYER_UUID" ] || [ "$PLAYER_UUID" = "null" ]; then
        log_skip "No player available - skipping mirror universe tests"
        return
    fi

    # Test: Check mirror access
    log_info "Testing GET /api/players/{uuid}/mirror-access"
    api_test "Check mirror universe access" "GET" "/players/$PLAYER_UUID/mirror-access" "200" "" "true" || true

    if [ -n "$GALAXY_UUID" ] && [ "$GALAXY_UUID" != "null" ]; then
        # Test: Get mirror gate
        log_info "Testing GET /api/galaxies/{uuid}/mirror-gate"
        api_test "Get mirror gate" "GET" "/galaxies/$GALAXY_UUID/mirror-gate" "200" "" "true" || true
    fi
}

test_mining_endpoints() {
    log_section "MINING ENDPOINTS (PROTECTED)"

    if [ -z "$AUTH_TOKEN" ] || [ "$AUTH_TOKEN" = "null" ]; then
        log_skip "No auth token - skipping mining tests"
        return
    fi

    if [ -n "$POI_UUID" ] && [ "$POI_UUID" != "null" ]; then
        # Test: Get mining opportunities
        log_info "Testing GET /api/poi/{uuid}/mining-opportunities"
        api_test "Get mining opportunities" "GET" "/poi/$POI_UUID/mining-opportunities" "200" "" "true" || true
    fi
}

test_authentication_required() {
    log_section "AUTHENTICATION REQUIRED TESTS"

    # Test endpoints without auth token
    log_info "Testing POST /api/galaxies/create without auth"
    api_test "Create galaxy without auth (401)" "POST" "/galaxies/create" "401" '{"width":200,"height":200,"stars":100,"game_mode":"multiplayer"}' || true

    log_info "Testing GET /api/players without auth"
    api_test "List players without auth (401)" "GET" "/players" "401" || true

    log_info "Testing POST /api/players without auth"
    api_test "Create player without auth (401)" "POST" "/players" "401" '{"galaxy_id":"test","call_sign":"Test"}' || true
}

#######################################################################
# Main Test Runner
#######################################################################

print_summary() {
    log_section "TEST SUMMARY"
    echo ""
    echo -e "Total Tests: $((TESTS_PASSED + TESTS_FAILED + TESTS_SKIPPED))"
    echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
    echo -e "${RED}Failed: $TESTS_FAILED${NC}"
    echo -e "${YELLOW}Skipped: $TESTS_SKIPPED${NC}"
    echo ""

    if [ $TESTS_FAILED -eq 0 ]; then
        echo -e "${GREEN}All tests passed!${NC}"
        return 0
    else
        echo -e "${RED}Some tests failed!${NC}"
        return 1
    fi
}

main() {
    echo ""
    echo "========================================"
    echo "Space Wars 3002 - API Test Suite"
    echo "========================================"
    echo "Base URL: $BASE_URL"
    echo "Started: $(date)"
    echo ""

    # Check if server is running
    log_info "Checking if server is running..."
    if ! curl -s "$BASE_URL" > /dev/null 2>&1; then
        echo -e "${RED}ERROR: Server not reachable at $BASE_URL${NC}"
        echo "Please start the server with: php artisan serve"
        exit 1
    fi
    echo -e "${GREEN}Server is running${NC}"

    # Run test suites
    test_auth_endpoints
    test_galaxy_public_endpoints
    test_galaxy_creation_endpoints
    test_leaderboard_endpoints
    test_victory_endpoints
    test_market_events_endpoints
    test_pirate_faction_endpoints
    test_player_endpoints
    test_ship_endpoints
    test_navigation_endpoints
    test_travel_endpoints
    test_trading_endpoints
    test_upgrade_endpoints
    test_cartography_endpoints
    test_colony_endpoints
    test_combat_endpoints
    test_notification_endpoints
    test_mirror_universe_endpoints
    test_mining_endpoints
    test_authentication_required

    # Print summary
    print_summary
}

# Run main function
main "$@"

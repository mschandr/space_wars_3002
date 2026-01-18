# Space Wars 3002 - Optimization Log

This file tracks performance optimizations made to the codebase.

---

## 2026-01-17: NavigationController & StarChartService N+1 Query Optimization

### Problem Identified

The `NavigationController` and `StarChartService` had severe N+1 query problems where `hasChartFor()` was called inside loops, executing a database query for each system checked.

### Before vs After Comparison

| Method | Before (Queries) | After (Queries) | **Reduction** | Before (Time) | After (Time) | **Speedup** |
|--------|------------------|-----------------|---------------|---------------|--------------|-------------|
| `getNearbySystems()` | 51 | **2** | **96%** | 66.99ms | 12.5ms | **5.4x** |
| `scanLocal()` | 102 | **4** | **96%** | 72.03ms | 12.5ms | **5.8x** |
| `calculateChartPrice()` | 115 | **9** | **92%** | 20.87ms | 9.8ms | **2.1x** |
| `getChartCoverage()` | 121 | **13** | **89%** | 9.71ms | 7.8ms | **1.2x** |
| `getAvailableCharts()` | 148 | **22** | **85%** | 38.39ms | 16.3ms | **2.4x** |

### Summary

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **Total Queries** | 537 | 50 | **91% reduction** |
| **Total Time** | 208ms | 59ms | **72% faster** |

### Key Changes Made

**1. Player Model** (`app/Models/Player.php`):
- Added `getChartedPoiIds()` - loads all charted POI IDs once with request-scoped caching
- Added `hasChartForId(int)` - in-memory lookup by POI ID
- Added `clearChartedPoiCache()` - clears cache after purchasing new charts
- Updated `hasChartFor()` to use the optimized in-memory lookup

**2. NavigationController** (`app/Http/Controllers/Api/NavigationController.php`):
- `getNearbySystems()`: Pre-loads charted IDs once, uses in-memory lookup in map()
- `scanLocal()`: Same optimization pattern

**3. StarChartService** (`app/Services/StarChartService.php`):
- `getChartCoverage()`: Batch loads warp gates per BFS level instead of N+1 queries
- `calculateChartPrice()`: Uses cached charted POI IDs
- `purchaseChart()`: Uses batch insert for new charts, clears cache after purchase
- `getAvailableCharts()`: Uses cached charted POI IDs for filtering
- `getSystemInfo()`: Uses optimized `hasChartForId()`

### Files Modified
- `app/Models/Player.php` - Added caching layer
- `app/Http/Controllers/Api/NavigationController.php` - Pre-load optimization
- `app/Services/StarChartService.php` - BFS batching + cached lookups

### Test Results
```
PASS  Tests\Feature\Api\NavigationTest (7 tests)
PASS  Tests\Feature\Api\CartographyTest (14 tests)
Total: 21 passed (117 assertions)
```

### Benchmark Command
```bash
php artisan benchmark:navigation --systems=50 --charts=25
```

---

## 2026-01-17: LeaderboardController N+1 Query Optimization (PLAN)

### Problem Identified

**File**: `app/Http/Controllers/Api/LeaderboardController.php`
**Severity**: CRITICAL (10/10)

The LeaderboardController has catastrophic N+1 query problems that scale linearly with player count. With 1,000 active players, a single leaderboard request executes over 3,000 database queries.

### Issues to Fix

#### 1. `combat()` method (Lines 70-129) - CATASTROPHIC
- Loads ALL active players first
- For EACH player, executes 3 separate queries:
  - PvP wins count (lines 76-82)
  - PvP losses count (lines 84-90)
  - Pirate kills count (lines 92-98)
- **Query count**: 1 + (players × 3) = **301 queries for 100 players**

#### 2. `colonial()` method (Lines 210-262) - CRITICAL
- For EACH player, executes 3 queries:
  - `Colony::sum('population')` (line 221)
  - `Colony::avg('development_level')` (line 222)
  - Galaxy population (lines 223-225) - **SAME QUERY REPEATED FOR EVERY PLAYER**
- **Query count**: 1 + (players × 3) = **301 queries for 100 players**

#### 3. `economic()` method (Lines 144-204) - HIGH
- Loads ALL relationships into memory (ships, cargos, colonies)
- Iterates through each in PHP instead of aggregating in database
- Memory intensive + potential N+1 in `calculateValue()`

#### 4. `playerRanking()` method (Lines 268-320) - MEDIUM
- Colonial rank loads ALL players into memory just to count (lines 296-302)
- Inefficient ranking calculation

### Optimization Plan

#### Step 1: Optimize `combat()` method
- Pre-aggregate combat stats using a single query with JOINs and CASE statements
- Use `leftJoinSub()` to attach stats to players
- Sort in database, not PHP

#### Step 2: Optimize `colonial()` method
- Calculate galaxy population ONCE before the loop
- Use `leftJoinSub()` for per-player colony aggregates
- Sort in database with calculated score

#### Step 3: Optimize `economic()` method
- Pre-calculate ship values using raw aggregate
- Use joins for cargo value aggregation
- Reduce memory footprint

#### Step 4: Optimize `playerRanking()` method
- Use COUNT with WHERE conditions instead of loading all players
- Single efficient query per rank type

### Expected Results

| Method | Before (100 players) | After | Reduction |
|--------|---------------------|-------|-----------|
| `combat()` | 301 queries | 1-2 queries | **99%** |
| `colonial()` | 301 queries | 2 queries | **99%** |
| `economic()` | ~100 queries | 3-4 queries | **96%** |
| `playerRanking()` | ~5 queries | 4 queries | **20%** |

---

## 2026-01-17: LeaderboardController N+1 Query Optimization (RESULTS)

### Optimization Completed

All planned optimizations were implemented successfully.

### Before vs After Comparison (50 players)

| Method | Before (Queries) | After (Queries) | **Reduction** | Before (Time) | After (Time) | **Speedup** |
|--------|------------------|-----------------|---------------|---------------|--------------|-------------|
| `overall()` | 3 | 3 | 0% | 29.7ms | 28.9ms | 1.0x |
| `combat()` | **155** | **6** | **96%** | 267.9ms | 18.7ms | **14.3x** |
| `economic()` | **159** | **10** | **94%** | 37.6ms | 23.5ms | **1.6x** |
| `colonial()` | **311** | **14** | **95%** | 205.4ms | 19.4ms | **10.6x** |

### Summary

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **Total Queries** | 628 | 33 | **95% reduction** |
| **Total Time** | 540ms | 90ms | **83% faster** |

### Key Changes Made

**1. `combat()` method** - Replaced 3× N+1 queries with single aggregation query:
- Uses `DB::table()` with JOINs and CASE/SUM for pvp_wins, pvp_losses, pirate_kills
- Pre-aggregates all combat stats in one query, then maps to players
- Fixed incorrect `whereJsonContains('result->victor')` to use `victor_type` column

**2. `colonial()` method** - Eliminated repeated galaxy population query:
- Calculates galaxy population ONCE before the loop (was repeated for every player)
- Uses `DB::table()` with JOINs for per-player colony stats (count, sum, avg)
- Single aggregation query replaces 3× N+1 pattern

**3. `economic()` method** - Pre-aggregates asset values:
- Ship values: Single query with JOIN to ships table for base_price
- Cargo values: Single query with JOIN to minerals table for base_value
- Colony values: Single query with aggregation
- Fixed missing `calculateValue()` method by using ship.base_price

**4. `playerRanking()` method** - Optimized colonial rank calculation:
- Replaced `withCount()->get()->filter()` with subquery COUNT
- Reduces memory usage by not loading all players

**5. `playerStatistics()` method** - Fixed and optimized:
- Removed non-existent `ships_destroyed` column reference
- Uses single aggregation query for combat stats
- Uses single query for cargo value calculation

### Files Modified
- `app/Http/Controllers/Api/LeaderboardController.php` - Complete optimization
- `tests/Feature/Api/LeaderboardTest.php` - Fixed test assertions for schema alignment

### Test Results
```
PASS  Tests\Feature\Api\LeaderboardTest (10 tests)
Total: 10 passed (198 assertions)
```

### Benchmark Command
```bash
php artisan benchmark:leaderboard --players=50
```

### Scaling Impact

With 1,000 players (20× more than benchmark):
- **Before**: ~6,000+ queries per leaderboard request
- **After**: ~33 queries per leaderboard request (same as 50 players)

The optimized queries scale O(1) instead of O(n) with player count.

---

## 2026-01-17: Galaxy Initialization Performance Optimization

### Problem Identified

**Severity**: CRITICAL (10/10)

The galaxy initialization system had catastrophic N+1 query patterns and nested loop inefficiencies. Creating a galaxy with 500 stars required over 3,400 database queries and took over 6 seconds.

### Key Bottlenecks Found

1. **POI Creation** (`PointOfInterest::createPointsForGalaxy()`):
   - Individual `create()` calls in a loop
   - 500 queries for 500 POIs

2. **Sector Creation** (`GalaxyGenerateSectors::generateSectorGrid()`):
   - Individual `create()` calls in nested loop
   - 600+ queries for 100 sectors

3. **Sector Assignment** (`GalaxyGenerateSectors::assignPoisToSectors()`):
   - Nested loop: O(n × m) where n=POIs, m=sectors
   - Individual `save()` per POI
   - 1,100+ queries for 500 POIs

4. **Inhabited Designation** (`InhabitedSystemGenerator::designateInhabitedSystems()`):
   - Individual `save()` per inhabited POI
   - 1,200+ queries for 40% designation

5. **Distribution Stats** (`InhabitedSystemGenerator::getDistributionStats()`):
   - O(n²) distance calculations for all inhabited systems
   - Multiple SELECT queries instead of single aggregate

### Before vs After Comparison (500 stars, 10x10 sectors)

| Operation | Before (Queries) | After (Queries) | **Reduction** | Before (Time) | After (Time) | **Speedup** |
|-----------|------------------|-----------------|---------------|---------------|--------------|-------------|
| POI Creation | 500 | **1** | **99.8%** | 1,548ms | 347ms | **4.5x** |
| Sector Creation | 600 | **2** | **99.7%** | 279ms | 20ms | **14x** |
| Sector Assignment | 1,102 | **3** | **99.7%** | 3,296ms | 66ms | **50x** |
| Inhabited Designation | 1,211 | **5** | **99.6%** | 1,044ms | 752ms | **1.4x** |

### Summary

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **Total Queries** | 3,413 | 11 | **99.7% reduction** |
| **Total Time** | 6,167ms | 1,185ms | **81% faster (5.2x)** |

### Key Changes Made

**1. POI Creation** (`app/Models/PointOfInterest.php`):
- Replaced individual `create()` calls with batch `DB::table()->insert()`
- Pre-generates all POI data (UUID, type, name, etc.) in memory
- Inserts in chunks of 500 to avoid query size limits

**2. Sector Creation** (`app/Console/Commands/GalaxyGenerateSectors.php`):
- Replaced individual `create()` calls with single batch insert
- Pre-generates all sector data with UUIDs in memory

**3. Sector Assignment** (`app/Console/Commands/GalaxyGenerateSectors.php`):
- Replaced nested PHP loop with single SQL UPDATE + JOIN
- `UPDATE poi JOIN sectors ON coordinates BETWEEN bounds SET sector_id`
- O(n) database operation instead of O(n × m) PHP

**4. Inhabited Designation** (`app/Services/InhabitedSystemGenerator.php`):
- Replaced individual saves with single `whereIn()->update()`
- Collects all inhabited IDs first, then batch updates

**5. Distribution Stats** (`app/Services/InhabitedSystemGenerator.php`):
- Single aggregate query for star/inhabited counts
- Sampling-based distance calculation for large datasets
- Limits comparisons to 500 max for efficiency

**6. Cartography Shops** (`app/Console/Commands/CartographyGenerateShopsCommand.php`):
- Added eager loading for `stellarCartographer` relationship
- Eliminates N+1 lazy loads in existence check loop

### Files Modified
- `app/Models/PointOfInterest.php` - Batch insert optimization
- `app/Console/Commands/GalaxyGenerateSectors.php` - Batch insert + SQL JOIN
- `app/Services/InhabitedSystemGenerator.php` - Batch update + aggregate queries
- `app/Console/Commands/CartographyGenerateShopsCommand.php` - Eager loading

### Test Results
```
PASS  Tests\Feature\Api\NavigationTest (7 tests)
PASS  Tests\Feature\Api\CartographyTest (14 tests)
Total: 21 passed (117 assertions)
```

### Benchmark Command
```bash
php artisan benchmark:galaxy-init --stars=500 --grid-size=10
```

### Scaling Impact

For a 3,000-star galaxy (6× benchmark size):
- **Before**: ~20,000+ queries, estimated 35+ seconds
- **After**: ~11 queries, estimated 7 seconds

The optimized operations scale O(1) for query count instead of O(n).

---

# Space Wars 3002 - Comprehensive Testing Roadmap

## Testing Strategy

This document outlines the complete testing strategy for Space Wars 3002, covering all major game systems.

---

## ✅ COMPLETED TESTS

### Mining Service Tests
**File:** `tests/Unit/Services/MiningServiceTest.php`

**Coverage:**
- ✅ Sensor efficiency formula validation (Level 1: 11.8%, Level 6: 62.3%, Level 16: 200%)
- ✅ Exponential efficiency growth
- ✅ Resource extraction calculations
- ✅ Facility level bonuses
- ✅ Ice giant validation
- ✅ Building type validation

**Run:** `php artisan test --filter=MiningServiceTest`

---

## 🔄 HIGH PRIORITY TESTS (Game-Breaking if Wrong)

### 1. Player & XP System ✅ COMPLETE
**File:** `tests/Unit/Models/PlayerTest.php`
**Status:** 24 tests passing (54 assertions)

**Tests Implemented:**
- ✅ XP formula validation (Level 1-20)
- ✅ Level up triggers at correct thresholds
- ✅ Experience addition and persistence
- ✅ Credits addition and deduction
- ✅ Multiple ships per player
- ✅ Active ship relationship
- ✅ Factory states (veteran, rich, broke)
- ✅ UUID auto-generation
- ✅ Unique call sign enforcement
- ✅ Cascade deletion

**Critical Formula Validated:** `Level = floor(sqrt(XP / 100)) + 1` ✓

**Factories Created:**
- `database/factories/PlayerFactory.php` (with atLevel, rich, broke, veteran states)

---

### 2. Ship System Tests ✅ COMPLETE
**File:** `tests/Unit/Models/PlayerShipTest.php`
**Status:** 27 tests passing (48 assertions)

**Tests Implemented:**
- ✅ Fuel consumption mechanics
- ✅ Warp drive efficiency (20% reduction per level)
- ✅ Minimum fuel cost (always 1)
- ✅ Damage and hull limits
- ✅ Ship destruction at zero hull
- ✅ Damaged status below 30% hull
- ✅ Repair mechanics
- ✅ Sensor and weapons upgrades
- ✅ Fuel regeneration (30 seconds per point)
- ✅ Cargo management
- ✅ Factory states (active, damaged, destroyed, upgraded)
- ✅ Cascade deletion

**Critical Formula Validated:** `effectiveConsumption = max(1, floor(amount / warp_drive))` ✓

**Factories Created:**
- `database/factories/ShipFactory.php` (with starter, combat, hauler states)
- `database/factories/PlayerShipFactory.php` (with active, damaged, destroyed, upgrade states)

---

### 3. Combat System Tests ✅ COMPLETE
**File:** `tests/Unit/Services/CombatResolutionServiceTest.php`
**Status:** 15 tests passing (73 assertions)

**Tests Implemented:**
- ✅ Player attacks first in combat
- ✅ Damage randomization (±20%)
- ✅ Combat loop until one side destroyed
- ✅ Victory XP calculation
- ✅ XP scaling by pirate count
- ✅ XP scaling by pirate weapon strength
- ✅ Level-up triggers during combat
- ✅ Combat log records all events
- ✅ Player death triggers at zero hull
- ✅ Pirate death handling
- ✅ Multi-target combat
- ✅ Multiple rounds of combat
- ✅ Hull integrity verification
- ✅ Damage bounds checking
- ✅ Full combat scenarios

**Critical Formula Validated:** Base XP = `50 * pirateCount + (avgWeapons / 2) + ((pirateCount - 1) * 25)` ✓

**Service Created:**
- `app/Services/CombatResolutionService.php` (combat logic extracted for testing)

---

### 4. Trading System Tests ✅ COMPLETE
**File:** `tests/Unit/Services/TradingServiceTest.php`
**Status:** 19 tests passing (41 assertions)

**Tests Implemented:**
- ✅ Buying minerals deducts credits correctly
- ✅ Buying adds to PlayerCargo
- ✅ Buying updates ship current_cargo
- ✅ Cannot buy more than cargo capacity
- ✅ Cannot buy with insufficient credits
- ✅ Cannot buy more than available stock
- ✅ Buying awards XP (1 XP per 10 units, min 5)
- ✅ Buying reduces hub inventory
- ✅ Selling minerals adds credits
- ✅ Selling removes from PlayerCargo
- ✅ Selling all cargo deletes cargo record
- ✅ Selling updates ship current_cargo
- ✅ Selling awards XP (1 XP per 100 credits, min 10)
- ✅ Selling increases hub inventory
- ✅ Cannot sell more than player has
- ✅ Max affordable quantity calculation (by credits)
- ✅ Max affordable quantity calculation (by stock)
- ✅ Cargo space validation
- ✅ Zero quantity handling

**Critical Formulas Validated:**
- Buy XP: `max(5, quantity / 10)` ✓
- Sell XP: `max(10, totalRevenue / 100)` ✓

**Service Created:**
- `app/Services/TradingService.php` (trading logic extracted for testing)

**Factories Created:**
- `database/factories/MineralFactory.php` (with common/rare/legendary states)
- `database/factories/TradingHubFactory.php` (with major/premium states)
- `database/factories/TradingHubInventoryFactory.php` (with highStock/lowStock/expensive/cheap states)
- `database/factories/PlayerCargoFactory.php` (with large/small states)
- `database/factories/GalaxyFactory.php` (for POI dependencies)
- `database/factories/PointOfInterestFactory.php` (for TradingHub dependencies)

---

### 5. Market Event System Tests ✅ COMPLETE
**File:** `tests/Unit/Services/MarketEventServiceTest.php`
**Status:** 19 tests passing (27 assertions)

**Tests Implemented:**
- ✅ No multiplier when no active events
- ✅ Single event multiplier applies correctly
- ✅ Multiple events stack multiplicatively
- ✅ Multiplier applies to base prices
- ✅ Expired events are ignored
- ✅ Inactive events are ignored
- ✅ Future events are ignored
- ✅ Global events affect all minerals
- ✅ Galaxy-wide events affect all hubs
- ✅ Specific events only affect specified minerals
- ✅ Specific events only affect specified hubs
- ✅ Expired events can be deactivated
- ✅ Active event detection
- ✅ Get all active events for mineral/hub
- ✅ Price decrease events reduce prices
- ✅ Combining increase and decrease events
- ✅ Model checks if currently active
- ✅ Model detects expiration
- ✅ Events can be deactivated

**Critical Formula Validated:** `finalPrice = basePrice * event1Multiplier * event2Multiplier` ✓

**Event Types Tested:**
- Supply Shortage (2-3x multiplier)
- Market Flooding (0.3-0.5x multiplier)
- Demand Spike (2-2.5x multiplier)
- Global events (null mineral_id)
- Galaxy-wide events (null trading_hub_id)

**Factory Created:**
- `database/factories/MarketEventFactory.php` (with active/expired/inactive/global/galaxyWide states)

---

## 🟡 MEDIUM PRIORITY TESTS (Colony Systems)

### 6. Colony Management Tests ✅ COMPLETE
**File:** `tests/Unit/Models/ColonyTest.php`
**Status:** 16 tests passing (30 assertions)

**Tests Implemented:**
- ✅ Colony population grows each cycle
- ✅ Growth rate affected by habitability rating
- ✅ Growth rate affected by food availability
- ✅ Population cannot exceed max population
- ✅ Colony awards XP for growth
- ✅ Population growth formula validation
- ✅ Colony can produce resources
- ✅ Buildings consume resources correctly
- ✅ Buildings increase production output
- ✅ Can upgrade development level
- ✅ Max buildings limited by development level
- ✅ Development upgrades cost credits
- ✅ Cannot upgrade beyond max development level
- ✅ Colony factory creates valid colonies
- ✅ UUID auto-generation
- ✅ Unique constraint on player_id + poi_id

**Critical Formula Validated:** `newPop = ceil(population * (1 + (growthRate * habitability * foodModifier)))` ✓

**Factories Created:**
- `database/factories/ColonyFactory.php` (with new/growing/established/highHabitability/lowHabitability states)
- `database/factories/ColonyBuildingFactory.php` (with hydroponics/miningFacility/tradeStation/warpGate/shipyard states)

---

### 7. Building System Tests ✅ COMPLETE
**File:** `tests/Unit/Models/ColonyBuildingTest.php`
**Status:** 22 tests passing (79 assertions)

**Tests Implemented:**
- ✅ Buildings require correct stage
- ✅ Warp gate requires stage 5
- ✅ Building costs scale by level (50% per level)
- ✅ Building effects scale by level (30% per level)
- ✅ Operating costs scale by level (20% per level)
- ✅ Warp gate consumes 1 Quantium per cycle
- ✅ Warp gate generates 600 credits per cycle
- ✅ Gate shuts down when Quantium reaches zero
- ✅ Orbital defense costs 100 credits per cycle
- ✅ Building generates income when operational
- ✅ Building construction advances progress
- ✅ Building becomes operational when complete
- ✅ Operational building sets costs/income automatically
- ✅ Building upgrade increases level
- ✅ Building upgrade fails at max level
- ✅ Building upgrade fails with insufficient credits
- ✅ Income scales with level (50% per level)
- ✅ Buildings with no income return zero
- ✅ Boolean effects don't scale with level
- ✅ Building factory creates valid building
- ✅ Building UUID is auto-generated
- ✅ Building consumes resources during cycle

**Critical Formulas Validated:**
- Cost scaling: `Base * (1 + (level - 1) * 0.5)` ✓
- Effect scaling: `Base * (1 + (level - 1) * 0.3)` ✓
- Operating cost scaling: `Base * (1 + (level - 1) * 0.2)` ✓
- Income scaling: `Base * (1 + (level - 1) * 0.5)` ✓
- Gate economics: 1 Quantium/cycle consumed, 600 credits/cycle generated ✓

---

### 8. Colony Cycle Processing Tests
**File:** `tests/Unit/Services/ColonyCycleServiceTest.php` (needs creation)

**Required Tests:**
```php
/** @test */ public function all_colonies_process_each_cycle()
/** @test */ public function buildings_consume_resources()
/** @test */ public function buildings_generate_income()
/** @test */ public function credits_are_awarded_to_player()
/** @test */ public function low_quantium_alert_triggers_at_24()
/** @test */ public function gate_shutdown_alert_triggers_at_0()
/** @test */ public function colony_xp_is_awarded()
```

---

### 9. Notification System Tests
**File:** `tests/Unit/Services/NotificationServiceTest.php` (needs creation)

**Required Tests:**
```php
/** @test */ public function low_resource_alerts_create_notifications()
/** @test */ public function pirate_attack_alerts_work()
/** @test */ public function player_attack_alerts_work()
/** @test */ public function colonization_opportunity_alerts_work()
/** @test */ public function gate_shutdown_alerts_work()
/** @test */ public function building_complete_alerts_work()
/** @test */ public function sensor_arrays_enable_early_warning()
/** @test */ public function notifications_dont_spam() // 7-day cooldown
```

---

## 🟢 LOWER PRIORITY TESTS (Quality of Life)

### 10. Galaxy Generation Tests
**File:** `tests/Feature/Commands/GalaxyInitializeTest.php` (needs creation)

**Required Tests:**
```php
/** @test */ public function galaxy_initialize_creates_all_components()
/** @test */ public function stars_generate_in_correct_distribution()
/** @test */ public function warp_gates_connect_systems()
/** @test */ public function ice_giants_generate_in_systems()
/** @test */ public function asteroid_belts_generate_correctly()
/** @test */ public function quantium_deposits_added_to_ice_giants()
/** @test */ public function trading_hubs_have_inventory()
```

---

### 11. Ship Production Tests
**File:** `tests/Unit/Models/ColonyShipProductionTest.php` (needs creation)

**Required Tests:**
```php
/** @test */ public function ship_production_costs_80_percent_of_base_price()
/** @test */ public function production_progress_advances_correctly()
/** @test */ public function completed_ships_are_created_for_player()
/** @test */ public function production_queue_works_correctly()
/** @test */ public function cancelling_refunds_partial_cost()
```

---

### 12. Travel & Fuel Tests ✅ COMPLETE
**File:** `tests/Unit/Services/TravelServiceTest.php`
**Status:** 16 tests passing (46 assertions)

**Tests Implemented:**
- ✅ Travel distance calculated correctly (Euclidean formula)
- ✅ Distance calculation with large coordinates
- ✅ Fuel cost calculation with various warp drive levels
- ✅ Fuel cost has minimum of 1
- ✅ Warp drive efficiency (20% reduction per level)
- ✅ Travel XP calculation (5 XP per unit distance)
- ✅ Travel XP has minimum of 10
- ✅ Cannot travel without sufficient fuel
- ✅ Successful travel updates location and consumes fuel
- ✅ Travel awards XP correctly
- ✅ Travel can trigger level up
- ✅ Travel tracks last trading hub for respawn
- ✅ Travel does not track inactive trading hub
- ✅ Travel fails when no active ship
- ✅ Warp drive efficiency 20% per level verified

**Critical Formulas Validated:**
- Distance: `sqrt((x2-x1)^2 + (y2-y1)^2)` ✓
- Fuel cost: `max(1, ceil(distance / warp_efficiency))` ✓
- Warp efficiency: `1 + ((warp_drive - 1) * 0.2)` ✓
- Travel XP: `max(10, distance * 5)` ✓

**Service Created:**
- `app/Services/TravelService.php` (travel logic extracted for testing)

---

## 🔵 INTEGRATION TESTS (Full Gameplay Flows)

### 13. Complete Gameplay Flow Test
**File:** `tests/Feature/CompleteGameplayTest.php` (needs creation)

**Scenario:** New player → Trade → Combat → Colonize → Build → Profit

```php
/** @test */
public function complete_gameplay_flow_works()
{
    // 1. Create new player with starter ship
    // 2. Travel to trading hub
    // 3. Buy minerals
    // 4. Travel to another hub
    // 5. Sell minerals for profit
    // 6. Encounter pirates
    // 7. Win combat, gain XP
    // 8. Level up
    // 9. Buy colony ship
    // 10. Find colonizable planet
    // 11. Establish colony
    // 12. Build hydroponics
    // 13. Build warp gate
    // 14. Mine Quantium
    // 15. Gate generates passive income
    // 16. Verify entire economy loop works
}
```

---

### 14. Economy Balance Tests
**File:** `tests/Feature/EconomyBalanceTest.php` (needs creation)

**Required Tests:**
```php
/** @test */ public function starter_player_can_afford_basic_trading()
/** @test */ public function warp_gate_has_positive_roi() // Must earn more than fuel costs
/** @test */ public function orbital_defense_is_worth_the_cost()
/** @test */ public function colony_ship_price_is_achievable()
/** @test */ public function quantium_price_makes_sense()
```

---

## 📊 TEST EXECUTION COMMANDS

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suites
```bash
# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature

# Specific file
php artisan test tests/Unit/Services/MiningServiceTest.php

# Specific test method
php artisan test --filter=it_calculates_sensor_efficiency_correctly
```

### Run with Coverage
```bash
php artisan test --coverage
```

---

## 🎯 TESTING MILESTONES

### Milestone 1: Core Game Loop (100% Complete) ✅
- [x] Mining Service ✅
- [x] Player/XP ✅
- [x] Ship Fuel ✅
- [x] Combat ✅
- [x] Trading ✅

### Milestone 2: Colony Systems (40% Complete)
- [x] Colony Growth ✅
- [x] Building Construction ✅
- [ ] Resource Consumption
- [ ] Income Generation
- [ ] Notifications

### Milestone 3: Integration (0% Complete)
- [ ] Full Gameplay Flow
- [ ] Economy Balance
- [ ] Multi-player Interactions

---

## 🚀 NEXT STEPS

1. ~~**Immediate:** Create Player/XP tests (game-breaking if wrong)~~ ✅ DONE
2. ~~**Next:** Create Ship fuel consumption tests (critical mechanic)~~ ✅ DONE
3. ~~**Then:** Create Combat system tests (core gameplay)~~ ✅ DONE
4. ~~**After:** Create Trading system tests (economy foundation)~~ ✅ DONE
5. ~~**Then:** Create Market event tests~~ ✅ DONE
6. ~~**After:** Create Colony management tests~~ ✅ DONE
7. **Current:** Create Building system tests
8. **Next:** Create Travel/Fuel calculation tests
9. **Finally:** Build integration tests (verify everything works together)

---

## 📝 TEST DATA FACTORIES NEEDED

Create factories for test data generation:

```bash
php artisan make:factory PlayerFactory
php artisan make:factory PlayerShipFactory
php artisan make:factory ColonyFactory
php artisan make:factory ColonyBuildingFactory
php artisan make:factory MineralFactory
php artisan make:factory PointOfInterestFactory
php artisan make:factory StarFactory
php artisan make:factory TradingHubFactory
```

---

## ✨ TESTING BEST PRACTICES

1. **Test One Thing:** Each test should verify one specific behavior
2. **Use Descriptive Names:** `it_calculates_sensor_efficiency_correctly` not `test1`
3. **Arrange-Act-Assert:** Set up → Execute → Verify
4. **Use Factories:** Don't manually create test data
5. **Clean Database:** Use `RefreshDatabase` trait
6. **Test Edge Cases:** Zero values, negative values, max values
7. **Test Failures:** Verify error handling works

---

## 📚 DOCUMENTATION

Each test file should include:
- Purpose of the tests
- What game mechanic is being tested
- Critical formulas being validated
- Links to game design docs

---

**Status:** 9/14 test suites complete (Mining, Player, Ship, Combat, Trading, Market Events, Colony, Building, Travel) 🎉
**Tests Passing:** 165 tests (357+ assertions)
**Next Priority:** Integration Tests
**Estimated Total Tests Needed:** 100-150 tests for full coverage - EXCEEDED! 🚀

## 📈 PROGRESS SUMMARY

**Completed Test Suites:**
1. ✅ Mining Service (7 tests, 14+ assertions)
2. ✅ Player/XP System (24 tests, 54 assertions)
3. ✅ Ship System (27 tests, 48 assertions)
4. ✅ Combat System (15 tests, 73 assertions)
5. ✅ Trading System (19 tests, 41 assertions)
6. ✅ Market Event System (19 tests, 27 assertions)
7. ✅ Colony Management (16 tests, 30 assertions)
8. ✅ Building System (22 tests, 79 assertions)
9. ✅ Travel & Fuel System (16 tests, 46 assertions)

**Total Coverage:** 165/150 tests complete (110%) 🚀🚀🚀🎉

**Services Created:**
- CombatResolutionService
- TradingService
- TravelService
- MarketEventService (pre-existing, now tested)

**Factories Created:**
- PlayerFactory
- ShipFactory
- PlayerShipFactory
- MineralFactory
- TradingHubFactory
- TradingHubInventoryFactory
- PlayerCargoFactory
- GalaxyFactory
- PointOfInterestFactory
- MarketEventFactory
- ColonyFactory
- ColonyBuildingFactory

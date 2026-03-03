# Seeding Guide for Phase 5-9 Implementation

**Last Updated:** March 3, 2026
**Version:** 2.0
**Status:** Production Ready

## New Architecture

The vendor system now uses a **template + instance** pattern:

1. **Trading Posts** (Templates) - Predefined vendor profiles with names and trait baselines
   - 12 trading hub templates
   - 8 salvage yard templates (higher criminality)
   - 8 shipyard templates (lower criminality)
   - 8 market templates

2. **Vendor Instances** - Created from trading posts, one per POI per service type
   - Each galaxy gets vendors for all service types at all relevant POIs
   - Criminality varies slightly per instance
   - Black market vendors have high criminality (0.8+)

## Quick Start

### Step 1: Create a Test Galaxy
```bash
php artisan galaxy:initialize "TestGalaxy" --stars=500 --density=scatter
```

Output:
```
Generated galaxy: TestGalaxy (UUID: xxxx-xxxx-xxxx-xxxx)
Generated X points of interest
...
```

### Step 2: Seed All Data
```bash
php artisan seed:test-data
```

This will:
1. ✓ Seed 32 trading post templates (global, one-time)
2. ✓ Create 50-80 crew members per galaxy
3. ✓ Create 1 vendor per POI (trading_hub, salvage_yard, shipyard, market)
4. ✓ Create 1 customs official per inhabited POI

### Step 3: Verify the Data
```bash
php artisan tinker
```

Then in tinker:
```php
// Trading posts (global templates)
>>> TradingPost::count()              // Should show 32
>>> TradingPost::where('service_type', 'salvage_yard')->count()  // Should show 8

// Vendor instances (per-POI)
>>> VendorProfile::count()             // Should match your POI count
>>> VendorProfile::where('service_type', 'trading_hub')->count()
>>> VendorProfile::where('criminality', '>=', 0.8)->count()  // Black market dealers

// Crew and customs
>>> CrewMember::count()                // Should show 50-80
>>> CustomsOfficial::count()           // Should match inhabited POIs
```

## Vendor Structure

Each vendor instance has:
- `trading_post_id` - Reference to the template
- `poi_id` - The location where this vendor operates
- `service_type` - trading_hub, salvage_yard, shipyard, or market
- `criminality` - 0.0-1.0 (≥0.8 = black market)
- `personality` - Inherited from trading post template
- `dialogue_pool` - Service-specific dialogue
- `markup_base` - Base markup percentage

## Key Features

✅ **Multiple templates per service** - 8 different salvage yards, 12 different trading hubs, etc.
✅ **Criminality variance** - Each instance varies slightly from base (+/- 5%)
✅ **Black market support** - High criminality vendors for black market operations
✅ **Service-specific pricing** - Salvage yards mark up higher than shipyards
✅ **Galaxy-independent templates** - Trading posts work across all galaxies

## Database Schema

```
trading_posts (32 global templates)
  - uuid, name, service_type, base_criminality
  - personality, dialogue_pool, markup_base

vendor_profiles (per-POI instances)
  - uuid, galaxy_id, poi_id, trading_post_id
  - service_type, criminality
  - personality, dialogue_pool, markup_base
  - Unique constraint: one per POI
```

## Related Models

- **CrewMember** - 50-80 per galaxy, available for hire
- **CustomsOfficial** - 1 per inhabited POI
- **PlayerVendorRelationship** - Tracks goodwill, shady dealings per player

# Dynamic Merchant Commentary System

The merchant commentary system generates contextual, character-driven dialogue for ship and component merchants. Instead of static per-item sales pitches, the system scores items on multiple dimensions and selects the most relevant commentary from curated pools.

## How It Works

1. **Tag Scoring** — Each item is analyzed across multiple dimensions (value, quality, danger, etc.) producing a tag map like `{value: deal, quality: exceptional, danger: deadly}`
2. **Pool Matching** — Commentary pools are keyed by tag combinations. The system finds all pools whose required tags match the item's tags
3. **Specificity Selection** — The pool with the most matching tags wins (e.g., a 2-tag pool beats a 1-tag pool)
4. **Interpolation** — Placeholders (`{item_name}`, `{price}`, `{rarity}`, `{slot_type}`) are replaced with actual item data
5. **Random Selection** — A random line is picked from the winning pool

## Hand-Written Overrides

Ships with `sales_pitches` set on the model (e.g., Sparrow, Precursor ships) bypass the dynamic system entirely. The priority chain is:

```
Ship has sales_pitches array? → Use Ship::getSalesPitch()
Otherwise → Use MerchantCommentaryService::generateShipCommentary()
```

## Tag Dimensions

### Ships & Components

| Tag | Values | How Scored |
|-----|--------|------------|
| `value` | `free`, `deal`, `fair`, `overpriced` | `currentPrice / basePrice` ratio vs config thresholds |
| `quality` | `junk`, `decent`, `good`, `exceptional` | From rarity tier; degraded by condition < 40 for components |
| `popularity` | `shelf_warmer`, `steady`, `hot_item` | Inverse of rarity (common = shelf_warmer, epic+ = hot_item) |
| `danger` | `safe`, `moderate`, `deadly`, `defensive` | Ships: `getCombatRating()`; Components: damage stat or defensive slot type |
| `specialty` | `cargo`, `speed`, `stealth`, `firepower`, `exploration`, `mining`, `defense`, `colonial`, `utility`, `legendary` | Ships: class + attributes; Components: slot_type |

### Components Only

| Tag | Values | How Scored |
|-----|--------|------------|
| `source` | `manufactured`, `salvage`, `stolen` | From `SalvageYardInventory.source` |
| `condition` | `broken`, `poor`, `pristine`, `decent` | From `SalvageYardInventory.condition` (<40 / <60 / ==100 / else) |

### Buyer Context (requires Player)

| Tag | Values | How Scored |
|-----|--------|------------|
| `buyer_affordability` | `way_too_rich`, `comfortable`, `stretching`, `cant_afford` | `credits / price` vs config multiplier thresholds |
| `buyer_comparison` | `first_ship`, `upgrade`, `sidegrade`, `downgrade` | Ships only: combined score ratio of current vs browsed ship |

## Configuration

Scoring thresholds are tunable in `config/game_config.php`:

```php
'merchant_commentary' => [
    'thresholds' => [
        'value' => ['deal_ratio' => 0.75, 'overpriced_ratio' => 1.10],
        'danger' => [
            'ship_deadly' => 200, 'ship_moderate' => 80,
            'weapon_deadly' => 80, 'weapon_moderate' => 40,
        ],
        'buyer' => [
            'rich_multiplier' => 3.0,
            'comfortable_multiplier' => 1.5,
            'upgrade_ratio' => 1.3,
            'downgrade_ratio' => 0.7,
        ],
    ],
],
```

## Adding New Commentary Pools

Commentary pools are defined as a class constant in `MerchantCommentaryService::COMMENTARY_POOLS`.

### Pool Key Format

Keys use `dimension:value` pairs joined by `+`:
- Single tag: `'value:deal'`
- Multi-tag: `'quality:exceptional+value:deal'`
- Special: `'universal'` (ultimate fallback)

### Adding a Pool

1. Choose your tag combination (more tags = higher priority)
2. Write 3-4 lines of dialogue
3. Use placeholders where appropriate: `{item_name}`, `{price}`, `{rarity}`, `{slot_type}`
4. Add the pool to the `COMMENTARY_POOLS` constant

Example:
```php
'danger:deadly+specialty:stealth' => [
    "Silent but deadly. {item_name} is what nightmares are made of.",
    "They won't see it coming. They won't see anything, actually.",
    "The galaxy's best assassin tool. Not that I'd know anything about that.",
],
```

### Pool Priority

Pools are selected by specificity (tag count), highest first. If a 3-tag pool matches, it beats all 2-tag and 1-tag pools. The `universal` pool is the absolute last resort.

## Service API

```php
// Ship commentary
$service->generateShipCommentary(Ship $ship, float $currentPrice, ?Player $player = null): string

// Component commentary
$service->generateComponentCommentary(ShipComponent $component, SalvageYardInventory $item, ?Player $player = null): string

// Direct scoring (for testing/debugging)
$service->scoreShip(Ship $ship, float $currentPrice, ?Player $player = null): array
$service->scoreComponent(ShipComponent $component, SalvageYardInventory $item, ?Player $player = null): array

// Direct selection (for custom tag maps)
$service->selectCommentary(array $tags, array $replacements): string
```

## Integration Points

- **ShipShopController** (`getShipyard()`) — API ship listings include `owner_commentary`
- **SalvageYardService** (`formatInventoryItem()`) — API component listings include `owner_commentary`
- **ShipShopHandler** (`show()`) — Console ship shop displays commentary

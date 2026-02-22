# Character Sheet Design Spec

> **Status**: Design Spec
> **Date**: 2026-02-21

## Base Attributes

All attributes start at **5/10** for a standard human. Minimum 1, maximum 10. Attributes improve through gameplay actions (not manual allocation).

| Attribute | Base | Affects | How It Improves |
|-----------|------|---------|-----------------|
| **Piloting** | 5 | Fuel efficiency (+1% per point above 5), evasion chance in combat, coordinate jump accuracy | Distance traveled, warp jumps made |
| **Commerce** | 5 | Buy/sell price bonus (+0.5% per point above 5), tax reduction at hubs, haggling success | Profitable trades, total trade volume |
| **Engineering** | 5 | Repair cost reduction (-2% per point), component upgrade success bonus, ship durability | Repairs performed, upgrades installed |
| **Gunnery** | 5 | Weapon damage bonus (+2% per point above 5), hit chance, critical strike chance | Pirates defeated, damage dealt |
| **Perception** | 5 | Scan detail bonus (stacks with ship sensors), hidden object detection, pirate ambush awareness | Systems scanned, objects discovered |
| **Leadership** | 5 | Crew effectiveness bonus, NPC trust detection, faction reputation gain rate | Crew hired, faction missions completed |
| **Fortune** | 5 | Loot quality rolls, rare mineral discovery, salvage quality, market event timing | Natural variance — slow passive gain from all activity |

### Attribute Progression

Attributes increase through use. Each attribute tracks hidden XP toward the next level. Diminishing returns — going from 5 to 6 is routine, going from 9 to 10 is exceptional.

| Level | Difficulty | Description |
|-------|-----------|-------------|
| 1-3 | Novice | Below average, noticeable penalties |
| 4-5 | Average | Standard human baseline |
| 6-7 | Skilled | Experienced spacer |
| 8-9 | Expert | Top 5% of pilots/traders/fighters |
| 10 | Legendary | One-in-a-million talent |

---

## Trade Ranks

Based on **lifetime trade profit** (total credits earned from selling minus total credits spent on buying). 15 ranks.

| Rank | Title | Lifetime Trade Profit | Flavor |
|------|-------|-----------------------|--------|
| 1 | Deckhand | 0 | Fresh off the shuttle |
| 2 | Peddler | 1,000 | Hawking trinkets at the docks |
| 3 | Haggler | 5,000 | Knows the difference between buy and sell price |
| 4 | Trader | 25,000 | Regular at the trading floor |
| 5 | Merchant | 100,000 | Recognized by the hub regulators |
| 6 | Broker | 500,000 | Moving serious volume |
| 7 | Magnate | 2,000,000 | Multiple trade routes locked down |
| 8 | Commodore of Commerce | 10,000,000 | Hubs adjust prices when you arrive |
| 9 | Trade Baron | 50,000,000 | Owns the supply chain, not just the cargo |
| 10 | Industrial Prince | 150,000,000 | Small colonies depend on your shipments |
| 11 | Market Sovereign | 400,000,000 | Your trades move sector-wide prices |
| 12 | Cartel Lord | 700,000,000 | Entire regions under your economic influence |
| 13 | Tycoon | 900,000,000 | The galaxy's wealthiest merchants know your name |
| 14 | Mogul Supreme | 950,000,000 | One deal away from owning it all |
| 15 | Emperor of Commerce | 1,000,000,000 | **Merchant Empire victory threshold** |

---

## Combat Ranks

Based on **total pirates defeated** (lifetime kill count). 15 ranks.

| Rank | Title | Pirates Defeated | Flavor |
|------|-------|------------------|--------|
| 1 | Harmless | 0 | Pirates laugh when they scan you |
| 2 | Mostly Harmless | 1 | Got lucky once |
| 3 | Scrapper | 5 | Survived a few scraps |
| 4 | Brawler | 15 | Takes a punch and gives one back |
| 5 | Competent | 35 | Knows which end of the cannon to point |
| 6 | Gunslinger | 75 | Pirate captains recognize your ship |
| 7 | Dangerous | 150 | Pirate fleets think twice before engaging |
| 8 | Deadly | 300 | Your name is on pirate bounty boards |
| 9 | Ace | 500 | Squadrons request you by name |
| 10 | Warlord | 750 | Entire pirate factions avoid your sector |
| 11 | Dreadnought | 1,000 | Your weapons fire is a local legend |
| 12 | Annihilator | 1,500 | Debris fields mark your flight path |
| 13 | Nemesis | 2,000 | The pirate council has a permanent bounty on you |
| 14 | Harbinger | 3,000 | Factions surrender at the sight of your signature |
| 15 | Elite | 5,000 | The deadliest pilot the galaxy has ever seen |

---

## Pirate Ranks

Based on **pirate reputation score** — earned by attacking traders, raiding colonies, seizing outlaw hubs, and running contraband. Separate progression from combat rank. A player can be Elite combat and low pirate rank if they only fight defensively.

| Rank | Title | Pirate Rep | Flavor |
|------|-------|------------|--------|
| 1 | Law-Abiding | 0 | Clean record, nothing to see here |
| 2 | Suspect | 50 | Authorities have noticed you |
| 3 | Petty Thief | 200 | Small-time cargo theft |
| 4 | Smuggler | 500 | Running contraband between hubs |
| 5 | Raider | 1,200 | Actively targeting merchant ships |
| 6 | Buccaneer | 3,000 | Known to the pirate underworld |
| 7 | Corsair | 6,000 | Pirate factions offer you contracts |
| 8 | Privateer | 12,000 | Operating with impunity in the outer systems |
| 9 | Scourge | 25,000 | Entire trade routes rerouted to avoid you |
| 10 | Dread Pirate | 50,000 | Your flag inspires terror across sectors |
| 11 | Warlord of the Void | 100,000 | Multiple pirate fleets answer to you |
| 12 | Black Flag Admiral | 200,000 | You command a shadow navy |
| 13 | Outlaw Sovereign | 400,000 | Pirate hubs pay tribute to you |
| 14 | Terror of the Lanes | 600,000 | Even the mirror universe fears your name |
| 15 | Pirate King | 700,000+ | **Pirate King victory threshold (~70% outlaw network)** |

---

## Exploration Ranks

Based on **unique systems visited**. Natural fit for a space game.

| Rank | Title | Systems Visited | Flavor |
|------|-------|-----------------|--------|
| 1 | Dockrat | 0 | Never left the station |
| 2 | Tourist | 5 | Took the guided tour |
| 3 | Wanderer | 20 | Getting restless |
| 4 | Scout | 50 | Mapping the neighborhood |
| 5 | Pathfinder | 100 | First footprints in empty systems |
| 6 | Surveyor | 200 | Cataloguing the unknown |
| 7 | Trailblazer | 400 | Opening routes nobody knew existed |
| 8 | Cartographer | 700 | Your charts are sold at trading hubs |
| 9 | Voidwalker | 1,000 | More time in the black than at port |
| 10 | Star Warden | 1,500 | The galaxy's geography is your expertise |
| 11 | Horizon Chaser | 2,000 | You've seen things others won't believe |
| 12 | Deep Space Pioneer | 2,500 | The outer reaches are your home |
| 13 | Galaxy Strider | 3,000 | You've walked the full breadth of known space |
| 14 | Cosmic Voyager | 4,000 | The void between stars is your domain |
| 15 | Omniscient | 5,000 | You have been everywhere |

---

## Lifetime Tracking Stats

Accumulated totals shown on the character sheet. Not ranked, just tracked.

| Stat | Description |
|------|-------------|
| **Total credits earned** | Lifetime income (sales + quest rewards + salvage) |
| **Total credits spent** | Lifetime expenditure (purchases + repairs + upgrades) |
| **Net trade profit** | Sell revenue minus buy cost |
| **Minerals traded** | Total units bought + sold |
| **Most profitable trade** | Single highest-profit transaction |
| **Distance traveled** | Total map units moved |
| **Warp jumps made** | Total warp gate uses |
| **Coordinate jumps made** | Total direct jumps |
| **Systems visited** | Unique POIs visited |
| **Systems charted** | Star charts owned |
| **Pirates defeated** | Total kills |
| **Pirates fled from** | Total retreats |
| **Ships owned** | Lifetime ship count |
| **Ships lost** | Total ship destructions |
| **Turns consumed** | Total actions taken |
| **Colonies founded** | Total colonies established |
| **Crew hired** | Total crew members recruited |
| **Time in mirror universe** | Total turns spent in the mirror dimension |

---

## Victory Progress

Always visible on the character sheet — percentage toward each of the 4 win conditions.

| Victory Path | Metric | Target |
|-------------|--------|--------|
| Merchant Empire | Current credits | 1,000,000,000 |
| Colonization | % of galactic population controlled | 50% |
| Conquest | % of star systems controlled | 60% |
| Pirate King | % of outlaw network seized | 70% |

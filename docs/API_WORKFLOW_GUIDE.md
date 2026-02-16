# Space Wars 3002 - API Workflow Guide

**The Narrative Journey Through the Galaxy**

This document describes the player journey and API workflow through Space Wars 3002, a turn-based space trading and conquest game inspired by the classic BBS door game Trade Wars 2002.

---

## Table of Contents

1. [Game Overview](#1-game-overview)
2. [New Player Journey](#2-new-player-journey)
3. [Core Gameplay Loop](#3-core-gameplay-loop)
4. [Victory Paths](#4-victory-paths)
5. [Detailed Workflows](#5-detailed-workflows)
6. [API Flow Diagrams](#6-api-flow-diagrams)

---

## 1. Game Overview

### The Setting

The year is 3002. Humanity has spread across the stars, establishing colonies, trade routes, and inevitably, conflict. Players take on the role of independent starship captains, starting with nothing but a small ship and a dream of galactic dominance.

### Core Mechanics

- **Exploration**: Discover new star systems, scan for resources, and chart the unknown
- **Trading**: Buy low, sell high across a dynamic market economy
- **Combat**: Battle pirates, rival players, and defend your colonies
- **Colonization**: Establish colonies on habitable worlds to generate passive income
- **Ship Management**: Upgrade your vessel, manage fuel, and install components

### The Legend of the Void Strider

Hidden somewhere in every galaxy is the legendary Precursor ship "Void Strider" - a half-million-year-old vessel with technology beyond anything humanity can build. Ship yard owners across the galaxy claim to know its location. They're all wrong. But collecting their rumors might help you find it...

---

## 2. New Player Journey

### Phase 1: Account Creation

```
┌─────────────────────────────────────────────────────────────────┐
│                    ENTERING THE GALAXY                          │
└─────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │  New User    │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐     POST /api/auth/register
    │   Register   │────────────────────────────►  Create account
    └──────┬───────┘                               Receive auth token
           │
           ▼
    ┌──────────────┐     GET /api/auth/me
    │  Logged In   │────────────────────────────►  Verify identity
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐
    │ Choose Game  │
    └──────────────┘
```

**Narrative**: A new pilot registers with the Galactic Trade Authority, receiving credentials to operate in civilized space.

---

### Phase 2: Joining a Galaxy

```
┌─────────────────────────────────────────────────────────────────┐
│                    CHOOSING YOUR DESTINY                        │
└─────────────────────────────────────────────────────────────────┘

                          ┌──────────────────┐
                          │  Authenticated   │
                          │      User        │
                          └────────┬─────────┘
                                   │
                    ┌──────────────┴──────────────┐
                    │                             │
                    ▼                             ▼
         ┌──────────────────┐          ┌──────────────────┐
         │   Join Existing  │          │  Create New      │
         │      Galaxy      │          │     Galaxy       │
         └────────┬─────────┘          └────────┬─────────┘
                  │                             │
                  │                             │
    GET /api/galaxies                POST /api/galaxies/create
    (Browse open games)              (Generate new universe)
                  │                             │
                  ▼                             ▼
         ┌──────────────────┐          ┌──────────────────┐
         │  Select Galaxy   │          │  Galaxy Created  │
         └────────┬─────────┘          │  (10-60 seconds) │
                  │                    └────────┬─────────┘
                  │                             │
                  └──────────────┬──────────────┘
                                 │
                                 ▼
                    ┌──────────────────────┐
                    │  POST /api/galaxies  │
                    │    /{uuid}/join      │
                    └──────────┬───────────┘
                               │
                               ▼
                    ┌──────────────────────┐
                    │   Player Created!    │
                    │  • Starting credits  │
                    │  • Starter ship      │
                    │  • Random location   │
                    │  • Initial star      │
                    │    charts            │
                    └──────────────────────┘
```

**Narrative**: The pilot reviews active galaxies looking for opportunity. Some galaxies are crowded but established; others are frontier territories with room to grow. Once a galaxy is chosen, the pilot registers their call sign and is assigned a starting location in civilized space.

---

### Phase 3: First Steps

```
┌─────────────────────────────────────────────────────────────────┐
│                    GETTING YOUR BEARINGS                        │
└─────────────────────────────────────────────────────────────────┘

         ┌──────────────────────────────────────────┐
         │            NEW PLAYER STATE              │
         │  • 10,000 credits                        │
         │  • Sparrow-class starter ship            │
         │  • Located at inhabited star system      │
         │  • 3 nearby star charts                  │
         └──────────────────────┬───────────────────┘
                                │
              ┌─────────────────┼─────────────────┐
              │                 │                 │
              ▼                 ▼                 ▼
    ┌─────────────────┐ ┌─────────────┐ ┌─────────────────┐
    │ Check Location  │ │ View Ship   │ │ Scan Nearby     │
    │                 │ │   Status    │ │   Systems       │
    └────────┬────────┘ └──────┬──────┘ └────────┬────────┘
             │                 │                  │
    GET /players/{id}  GET /ships/{id}   GET /players/{id}
        /location          /status         /nearby-systems
             │                 │                  │
             ▼                 ▼                  ▼
    ┌─────────────────────────────────────────────────────┐
    │                SITUATIONAL AWARENESS                │
    │  • Current star system details                      │
    │  • Trading hub availability                         │
    │  • Warp gate connections                            │
    │  • Ship fuel, hull, cargo status                    │
    │  • Nearby systems within sensor range               │
    └─────────────────────────────────────────────────────┘
```

**Narrative**: The new pilot takes stock of their situation. Their small Sparrow-class ship sits in dock at a bustling trading hub. The ship's sensors reveal several nearby star systems, though only a few are charted. It's time to decide: trade, explore, or something else entirely?

---

## 3. Core Gameplay Loop

### The Trading Loop

```
┌─────────────────────────────────────────────────────────────────┐
│                    THE MERCHANT'S PATH                          │
│         "Buy low, sell high, and watch for pirates"             │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────┐
    │ At Trading  │
    │    Hub      │
    └──────┬──────┘
           │
           ▼
    ┌─────────────────────┐
    │ Check Hub Inventory │  GET /trading-hubs/{uuid}/inventory
    │ • View buy prices   │
    │ • View sell prices  │
    │ • Check quantities  │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Make Purchase     │  POST /trading-hubs/{uuid}/buy
    │ • Buy minerals      │
    │ • Fill cargo hold   │  Parameters:
    │ • Spend credits     │  - mineral_uuid
    └──────────┬──────────┘  - quantity
               │
               ▼
    ┌─────────────────────┐
    │   Travel to New     │
    │   Trading Hub       │  (See Travel Workflow)
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │    Sell Cargo       │  POST /trading-hubs/{uuid}/sell
    │ • Compare prices    │
    │ • Maximize profit   │  Parameters:
    │ • Earn credits      │  - mineral_uuid
    └──────────┬──────────┘  - quantity
               │
               ▼
    ┌─────────────────────┐
    │   PROFIT MADE!      │
    │                     │
    │   Reinvest in:      │
    │   • More cargo      │
    │   • Ship upgrades   │
    │   • New ship        │
    └─────────────────────┘
```

**Narrative**: The galaxy's economy runs on minerals - Titanium for construction, Deuterium for fuel, rare Dilithium for warp drives. Each trading hub has different supply and demand, creating profit opportunities for clever traders. But beware - pirates lurk on the warp lanes between systems.

---

### The Travel Loop

```
┌─────────────────────────────────────────────────────────────────┐
│                    CROSSING THE VOID                            │
│           "Space is vast, and fuel is precious"                 │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────┐
    │ Current Location│
    └────────┬────────┘
             │
             ├──────────────────────────────────────┐
             │                                      │
             ▼                                      ▼
    ┌─────────────────┐                   ┌─────────────────┐
    │   WARP GATE     │                   │  DIRECT JUMP    │
    │    TRAVEL       │                   │   (Coordinate)  │
    └────────┬────────┘                   └────────┬────────┘
             │                                     │
             ▼                                     ▼
    GET /warp-gates/{uuid}               GET /travel/fuel-cost
    (List available gates)               (Calculate fuel needed)
             │                                     │
             ▼                                     │
    ┌─────────────────┐                           │
    │ Choose Gate     │                           │
    │ • View dest.    │                           │
    │ • Check fuel    │                           │
    │ • Check pirates │◄──────────────────────────┘
    └────────┬────────┘
             │
             ▼
    GET /warp-gates/{uuid}/pirates
    (Scout for danger)
             │
             ├────────────── Pirates Detected? ──────────────┐
             │                                               │
             ▼ No                                            ▼ Yes
    ┌─────────────────┐                           ┌─────────────────┐
    │   Safe Travel   │                           │ Risk Assessment │
    │                 │                           │ • Fight?        │
    └────────┬────────┘                           │ • Different     │
             │                                    │   route?        │
             │                                    │ • Wait?         │
             │                                    └────────┬────────┘
             │                                             │
             └──────────────────┬───────────────────────────┘
                                │
                                ▼
                   POST /players/{uuid}/travel/warp-gate
                   OR
                   POST /players/{uuid}/travel/coordinate
                                │
                                ▼
                   ┌─────────────────────┐
                   │   TRAVEL RESULT     │
                   │ • Fuel consumed     │
                   │ • XP earned         │
                   │ • Level up?         │
                   │ • Pirate encounter? │
                   └─────────────────────┘
```

**Narrative**: There are two ways to cross the void: warp gates (faster, cheaper, but limited destinations) or direct coordinate jumps (go anywhere, but expensive). Warp gates connect the civilized systems, but pirates love to ambush travelers on these predictable routes. Direct jumps can bypass danger but drain precious fuel.

---

### The Combat Loop

```
┌─────────────────────────────────────────────────────────────────┐
│                    WHEN PIRATES ATTACK                          │
│              "Fight, flee, or surrender"                        │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────────┐
    │  PIRATE ENCOUNTER!  │
    │  (During travel)    │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Combat Preview    │  GET /players/{uuid}/combat/preview
    │ • Your strength     │
    │ • Enemy strength    │
    │ • Win probability   │
    │ • Escape chance     │
    └──────────┬──────────┘
               │
               ├────────────┬─────────────┬─────────────┐
               │            │             │             │
               ▼            ▼             ▼             ▼
        ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
        │  FIGHT   │ │   FLEE   │ │SURRENDER │ │  BRIBE   │
        │          │ │          │ │          │ │ (future) │
        └────┬─────┘ └────┬─────┘ └────┬─────┘ └──────────┘
             │            │            │
             ▼            ▼            ▼
    POST /combat   POST /combat  POST /combat
       /engage        /escape      /surrender
             │            │            │
             │            │            │
             ▼            ▼            ▼
    ┌──────────────┐ ┌─────────┐ ┌─────────────┐
    │   VICTORY    │ │ ESCAPED │ │  CAPTURED   │
    │ • XP gained  │ │ • Some  │ │ • Lose all  │
    │ • Salvage    │ │   damage│ │   cargo     │
    │   available  │ │ • Fuel  │ │ • Some      │
    │              │ │   spent │ │   credits   │
    └──────┬───────┘ └─────────┘ │ • Ship      │
           │                     │   intact    │
           ▼                     └─────────────┘
    POST /combat/salvage
    (Collect the spoils)
```

**Narrative**: Pirates are a constant threat on the space lanes. When encountered, pilots must quickly assess the situation. A well-armed battleship might choose to fight and claim salvage. A cargo hauler laden with goods might try to flee. Sometimes, discretion is the better part of valor - surrender your cargo to save your ship.

---

### The Exploration Loop

```
┌─────────────────────────────────────────────────────────────────┐
│                    CHARTING THE UNKNOWN                         │
│           "Fortune favors the bold explorer"                    │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────┐
    │ Current System  │
    └────────┬────────┘
             │
             ▼
    ┌─────────────────────┐
    │   Scan Nearby       │  GET /players/{uuid}/nearby-systems
    │   Systems           │
    │ • Detect systems    │
    │ • Range = sensors   │
    │   × 100 units       │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────────────────────────┐
    │          For each system:               │
    │  ┌─────────────┐    ┌─────────────┐     │
    │  │ Has Chart?  │    │  No Chart   │     │
    │  │ • See name  │    │ • "Unknown" │     │
    │  │ • See coords│    │ • No coords │     │
    │  └─────────────┘    └──────┬──────┘     │
    │                            │            │
    │                            ▼            │
    │              ┌─────────────────────┐    │
    │              │  Buy Star Chart     │    │
    │              │  at Cartographer    │    │
    │              └─────────────────────┘    │
    └─────────────────────────────────────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Travel to System  │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Scan System       │  POST /players/{uuid}/scan-system
    │ • Discover planets  │
    │ • Find resources    │
    │ • Reveal anomalies  │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Get Local Bodies  │  GET /players/{uuid}/local-bodies
    │ • Planets           │
    │ • Moons             │
    │ • Asteroid belts    │
    │ • Stations          │
    │ • Orbital presence  │  (always visible)
    │ • Defenses          │  (sensor level 5+)
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────────────────────────┐
    │            DISCOVERIES                  │
    │  • Habitable planets for colonies       │
    │  • Mineral-rich asteroids for mining    │
    │  • Derelicts with salvage               │
    │  • Hidden locations                     │
    │  • Orbital defenses & threat levels     │
    └─────────────────────────────────────────┘
```

**Narrative**: Beyond the civilized core lies the frontier - uncharted systems full of opportunity and danger. Higher sensor levels reveal more of the unknown. Star charts can be purchased from cartographers, or earned through exploration. Some pilots make their fortune finding habitable worlds to colonize. Orbital structures — defense platforms, bases, mining rigs — are always visible in orbit, but a detailed defensive breakdown (garrison strength, fighter squadrons, threat level) requires sensor level 5 or higher.

---

### The Colony Loop

```
┌─────────────────────────────────────────────────────────────────┐
│                    BUILDING AN EMPIRE                           │
│           "From one colony, empires grow"                       │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────────┐
    │  Colony Ship with   │
    │    10,000 Colonists │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Find Habitable    │  GET /players/{uuid}/local-bodies
    │      Planet         │  (Look for habitable = true)
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Establish Colony  │  POST /players/{uuid}/colonies
    │ • Name the colony   │
    │ • Disembark         │
    │   colonists         │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────────────────────────────────────────────┐
    │                    COLONY MANAGEMENT                        │
    │                                                             │
    │   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
    │   │  Buildings  │  │ Production  │  │  Defense    │        │
    │   └──────┬──────┘  └──────┬──────┘  └──────┬──────┘        │
    │          │                │                │               │
    │          ▼                ▼                ▼               │
    │   POST /colonies   GET /colonies    POST /colonies         │
    │   /{uuid}/buildings /{uuid}/production /{uuid}/fortify     │
    │                                                             │
    │   Build:            Generates:        Protect:             │
    │   • Housing         • Credits/cycle   • Defense platforms  │
    │   • Mines           • Minerals        • Shield generators  │
    │   • Factories       • Ships           • Garrison troops    │
    │   • Research labs                                          │
    └─────────────────────────────────────────────────────────────┘
               │
               ▼
    ┌─────────────────────┐
    │   PASSIVE INCOME    │
    │ • Credits per cycle │
    │ • Resources         │
    │ • Ship production   │
    │ • Population growth │
    └─────────────────────┘
```

**Narrative**: The ultimate expression of power in Space Wars is colonization. A colony ship carries 10,000 souls ready to settle a new world. Once established, colonies grow, produce resources, and can even build ships. But undefended colonies are tempting targets for rival players...

---

## 4. Victory Paths

### The Four Paths to Glory

```
┌─────────────────────────────────────────────────────────────────┐
│                    PATHS TO VICTORY                             │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────────────────────────────────────────┐
    │                                                             │
    │   ╔═══════════════╗        ╔═══════════════╗               │
    │   ║   MERCHANT    ║        ║  COLONIZER    ║               │
    │   ║    EMPIRE     ║        ║               ║               │
    │   ╚═══════╤═══════╝        ╚═══════╤═══════╝               │
    │           │                        │                       │
    │   Accumulate 1 billion     Control >50% of                 │
    │   credits through          galactic population             │
    │   trade and commerce       through colonies                │
    │           │                        │                       │
    │   Key APIs:                Key APIs:                       │
    │   • /trading-hubs/*        • /colonies/*                   │
    │   • /players/{}/cargo      • /local-bodies                 │
    │   • Ship upgrades          • Colony ships                  │
    │     (cargo hold)                                           │
    │                                                             │
    │   ╔═══════════════╗        ╔═══════════════╗               │
    │   ║   CONQUEROR   ║        ║  PIRATE KING  ║               │
    │   ║               ║        ║               ║               │
    │   ╚═══════╤═══════╝        ╚═══════╤═══════╝               │
    │           │                        │                       │
    │   Control >60% of          Seize >70% of the               │
    │   star systems through     pirate network                  │
    │   military might           through conquest                │
    │           │                        │                       │
    │   Key APIs:                Key APIs:                       │
    │   • /combat/*              • /combat/*                     │
    │   • /pvp/*                 • /pirate-factions/*            │
    │   • Colony attacks         • Pirate reputation             │
    │   • Battleships                                            │
    │                                                             │
    └─────────────────────────────────────────────────────────────┘

    Check progress:  GET /players/{uuid}/victory-progress
    View leaders:    GET /galaxies/{uuid}/victory-leaders
```

---

## 5. Detailed Workflows

### Ship Progression

```
┌─────────────────────────────────────────────────────────────────┐
│                    SHIP PROGRESSION PATH                        │
└─────────────────────────────────────────────────────────────────┘

    STARTER                    SPECIALIZED               ENDGAME
    ───────                    ───────────               ───────

    ┌─────────┐
    │ Sparrow │  FREE
    │ (Start) │  Basic stats
    └────┬────┘
         │
         │  Earn credits through trading/missions
         │
         ├────────────────┬────────────────┬────────────────┐
         │                │                │                │
         ▼                ▼                ▼                ▼
    ┌─────────┐     ┌─────────┐     ┌─────────┐     ┌─────────┐
    │ Wraith  │     │  Titan  │     │Leviathan│     │  Viper  │
    │ 35,000  │     │ 200,000 │     │ 150,000 │     │ 18,000  │
    │Smuggler │     │  Cargo  │     │Battleshp│     │ Fighter │
    └────┬────┘     └────┬────┘     └────┬────┘     └─────────┘
         │               │               │
         │               │               │
         │               ▼               ▼
         │          ┌─────────┐     ┌─────────┐
         │          │Sovereign│     │  Exodus │
         │          │ 250,000 │     │ 500,000 │
         │          │ Carrier │     │ Colony  │
         │          └────┬────┘     └─────────┘
         │               │
         └───────────────┴───────────────┐
                                         │
                                         ▼
                              ┌───────────────────┐
                              │   VOID STRIDER    │
                              │    (Precursor)    │
                              │                   │
                              │  Cannot be bought │
                              │  Must be FOUND    │
                              └───────────────────┘

    Ship Purchase Flow:
    1. GET /trading-hubs/{uuid}/shipyard     (Check availability)
    2. GET /ships/catalog                     (Browse all ships)
    3. POST /players/{uuid}/ships/purchase   (Buy ship)
    4. POST /players/{uuid}/ships/switch     (Activate new ship)
```

---

### Component Installation

```
┌─────────────────────────────────────────────────────────────────┐
│                    SALVAGE YARD WORKFLOW                        │
│              "Upgrade your ship with components"                │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────┐
    │  At Trading Hub │
    │  with Salvage   │
    │      Yard       │
    └────────┬────────┘
             │
             ▼
    ┌─────────────────────┐
    │  Browse Components  │  GET /players/{uuid}/salvage-yard
    │                     │
    │  WEAPONS:           │  Returns:
    │  • Lasers           │  • inventory.weapons[]
    │  • Missiles         │  • inventory.utilities[]
    │  • Torpedoes        │
    │                     │
    │  UTILITIES:         │
    │  • Shield regen     │
    │  • Hull patches     │
    │  • Cargo expanders  │
    │  • Scanners         │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │  Check Ship Slots   │  GET /players/{uuid}/ship-components
    │                     │
    │  Weapon Slots: 2    │  Shows:
    │  └─ Slot 1: Empty   │  • components.weapon_slots
    │  └─ Slot 2: Laser   │  • components.utility_slots
    │                     │  • total slots available
    │  Utility Slots: 2   │
    │  └─ Slot 1: Shield  │
    │  └─ Slot 2: Empty   │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │  Purchase & Install │  POST /players/{uuid}/salvage-yard/purchase
    │                     │
    │  Parameters:        │
    │  • inventory_id     │  (Which item to buy)
    │  • slot_index       │  (Which slot to install)
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────┐
    │   Component Stats   │
    │                     │
    │  Effects applied:   │
    │  • +25 damage       │
    │  • +5 shield regen  │
    │  • +50 cargo        │
    └─────────────────────┘
```

---

### The Precursor Hunt

```
┌─────────────────────────────────────────────────────────────────┐
│                    HUNTING THE VOID STRIDER                     │
│           "Every shipyard owner thinks they know..."            │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────────────────────────────────────────┐
    │  THE TRUTH: The Precursor ship IS hidden in every galaxy.   │
    │  THE CATCH: All rumors about its location are WRONG.        │
    │  THE HOPE: Multiple wrong rumors might triangulate truth.   │
    └─────────────────────────────────────────────────────────────┘

    ┌─────────────────┐
    │  Visit Trading  │
    │      Hubs       │
    └────────┬────────┘
             │
             ▼
    ┌─────────────────────┐
    │   Check for Rumors  │  GET /players/{uuid}/precursor/check
    │                     │
    │   Response:         │
    │   • has_rumor: true │
    │   • owner: "Viktor" │
    │   • bribe_cost:     │
    │     25,000 credits  │
    └──────────┬──────────┘
               │
               ├────────────── Free ──────────────┐
               │                                  │
               ▼                                  ▼
    ┌─────────────────────┐          ┌─────────────────────┐
    │    Get Gossip       │          │   Pay for Rumor     │
    │      (Free)         │          │     (Costs $$)      │
    └──────────┬──────────┘          └──────────┬──────────┘
               │                                 │
    GET /precursor/gossip            POST /precursor/bribe
               │                                 │
               ▼                                 ▼
    ┌─────────────────────┐          ┌─────────────────────┐
    │   "I might know     │          │   RUMORED COORDS    │
    │   something...      │          │   x: 350, y: 420    │
    │   25,000 credits    │          │   confidence: 75%   │
    │   and I'll talk."   │          │                     │
    │                     │          │   (These are WRONG  │
    │   (No coordinates)  │          │    but might help)  │
    └─────────────────────┘          └──────────┬──────────┘
                                                │
                                                ▼
                                   ┌─────────────────────┐
                                   │   Collect Multiple  │
                                   │       Rumors        │
                                   └──────────┬──────────┘
                                              │
                                   GET /precursor/rumors
                                              │
                                              ▼
    ┌─────────────────────────────────────────────────────────────┐
    │                    TRIANGULATION                            │
    │                                                             │
    │     Rumor 1: (350, 420)  ──┐                               │
    │     Rumor 2: (380, 400)  ──┼──►  Cluster around (365, 410) │
    │     Rumor 3: (340, 415)  ──┘      Maybe search there?      │
    │                                                             │
    │     BUT REMEMBER: All rumors are WRONG by at least 50 units │
    │     The real location is somewhere ELSE                     │
    └─────────────────────────────────────────────────────────────┘
               │
               ▼
    ┌─────────────────────────────────────────────────────────────┐
    │   FINDING THE VOID STRIDER                                  │
    │                                                             │
    │   Requirements:                                             │
    │   • Sensor level 12+                                        │
    │   • Be within 10 units of actual location                   │
    │   • Jump to coordinates (not warp gate)                     │
    │                                                             │
    │   When found:                                               │
    │   • Most powerful ship in the game                          │
    │   • Ancient Precursor technology                            │
    │   • Ultimate bragging rights                                │
    └─────────────────────────────────────────────────────────────┘
```

---

## 6. API Flow Diagrams

### Complete Session Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                COMPLETE PLAYER SESSION FLOW                     │
└─────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │    START     │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐     ┌──────────────┐
    │    LOGIN     │────►│   GET /me    │
    │              │     │  (Verify)    │
    └──────┬───────┘     └──────────────┘
           │
           ▼
    ┌──────────────┐
    │ GET /galaxies│     List available games
    └──────┬───────┘
           │
           ├─────── Have player? ─────┐
           │            │             │
           ▼ No         │             ▼ Yes
    ┌──────────────┐    │    ┌──────────────┐
    │ POST /join   │    │    │GET /my-player│
    └──────┬───────┘    │    └──────┬───────┘
           │            │           │
           └────────────┴───────────┘
                        │
                        ▼
              ┌──────────────────┐
              │  GAME SESSION    │
              │                  │
              │  Repeat until    │
              │  done playing:   │
              └────────┬─────────┘
                       │
         ┌─────────────┼─────────────┬─────────────┐
         │             │             │             │
         ▼             ▼             ▼             ▼
    ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐
    │  TRADE  │  │ TRAVEL  │  │ UPGRADE │  │ EXPLORE │
    │         │  │         │  │         │  │         │
    │ Buy/Sell│  │Warp/Jump│  │Ship/    │  │Scan/    │
    │ minerals│  │ between │  │Component│  │Discover │
    │         │  │ systems │  │         │  │         │
    └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘
         │            │            │            │
         └────────────┴────────────┴────────────┘
                             │
                             ▼
                 ┌───────────────────┐
                 │  Check Progress   │
                 │                   │
                 │ GET /victory-     │
                 │     progress      │
                 └─────────┬─────────┘
                           │
                           ▼
                 ┌───────────────────┐
                 │    LOGOUT         │
                 │ POST /auth/logout │
                 └───────────────────┘
```

---

### API Dependency Graph

```
┌─────────────────────────────────────────────────────────────────┐
│                    API DEPENDENCY GRAPH                         │
│              "What you need before you can..."                  │
└─────────────────────────────────────────────────────────────────┘

    AUTHENTICATION LAYER
    ════════════════════

    /auth/register ──► /auth/login ──► Bearer Token
                                            │
                                            ▼
    ════════════════════════════════════════════════════════════
    ALL AUTHENTICATED ENDPOINTS REQUIRE TOKEN
    ════════════════════════════════════════════════════════════
                                            │
                    ┌───────────────────────┼───────────────────┐
                    │                       │                   │
                    ▼                       ▼                   ▼
            /galaxies/*             /players/*           /ships/*
                    │                       │                   │
                    │                       │                   │
    REQUIRES        │   REQUIRES            │   REQUIRES        │
    ────────        │   ────────            │   ────────        │
                    │                       │                   │
    Nothing ───────►├── Galaxy joined ─────►├── Player exists ─►│
                    │   (POST /join)        │   Active ship     │
                    │                       │                   │
                    ▼                       ▼                   ▼
              ┌───────────┐          ┌───────────┐       ┌───────────┐
              │ /create   │          │ /location │       │ /status   │
              │ /join     │          │ /travel/* │       │ /fuel     │
              │ /my-player│          │ /cargo    │       │ /upgrade  │
              │ /map      │          │ /colonies │       │ /repair   │
              └───────────┘          └───────────┘       └───────────┘


    LOCATION-DEPENDENT ENDPOINTS
    ════════════════════════════

    Must be at Trading Hub:
    ├── /trading-hubs/{uuid}/inventory
    ├── /trading-hubs/{uuid}/buy
    ├── /trading-hubs/{uuid}/sell
    ├── /trading-hubs/{uuid}/shipyard
    ├── /trading-hubs/{uuid}/cartographer
    ├── /salvage-yard
    └── /precursor/bribe

    Must be at Colonizable Body:
    └── POST /colonies (establish)

    Must own Colony:
    ├── /colonies/{uuid}/*
    └── /colonies/{uuid}/buildings/*
```

---

### State Machine: Player Status

```
┌─────────────────────────────────────────────────────────────────┐
│                    PLAYER STATE MACHINE                         │
└─────────────────────────────────────────────────────────────────┘

                         ┌─────────────┐
                         │   ACTIVE    │◄───────────────────┐
                         │             │                    │
                         └──────┬──────┘                    │
                                │                           │
            ┌───────────────────┼───────────────────┐       │
            │                   │                   │       │
            ▼                   ▼                   ▼       │
    ┌──────────────┐   ┌──────────────┐   ┌──────────────┐  │
    │   TRADING    │   │  TRAVELING   │   │   COMBAT     │  │
    │              │   │              │   │              │  │
    │ At hub       │   │ In transit   │   │ Engaged with │  │
    │ Buying/sell  │   │ Between      │   │ pirates or   │  │
    │              │   │ systems      │   │ players      │  │
    └──────┬───────┘   └──────┬───────┘   └──────┬───────┘  │
           │                  │                  │          │
           │                  │                  │          │
           └──────────────────┴──────────────────┘          │
                              │                             │
                              ▼                             │
                    ┌──────────────────┐                    │
                    │  Action Complete │────────────────────┘
                    └──────────────────┘
                              │
                              │ Ship destroyed?
                              ▼
                    ┌──────────────────┐
                    │    DESTROYED     │
                    │                  │
                    │ • Lose ship      │
                    │ • Lose cargo     │
                    │ • Keep credits   │
                    │ • Respawn option │
                    └──────────────────┘
```

---

### Recommended First Session

```
┌─────────────────────────────────────────────────────────────────┐
│                RECOMMENDED FIRST SESSION                        │
│              "A new pilot's first hour"                         │
└─────────────────────────────────────────────────────────────────┘

    Step 1: ORIENTATION (5 minutes)
    ════════════════════════════════
    1. POST /auth/register          Create account
    2. GET /galaxies                Browse games
    3. POST /galaxies/{uuid}/join   Join a game
    4. GET /players/{uuid}/status   Check your status
    5. GET /players/{uuid}/location See where you are

    Step 2: FIRST TRADE (10 minutes)
    ════════════════════════════════
    6. GET /trading-hubs/{uuid}/inventory   See what's for sale
    7. POST /trading-hubs/{uuid}/buy        Buy some minerals
    8. GET /players/{uuid}/cargo            Check your cargo
    9. GET /players/{uuid}/nearby-systems   Find another hub
    10. GET /warp-gates/{uuid}              Check travel options
    11. POST /players/{uuid}/travel/warp-gate  Travel!
    12. POST /trading-hubs/{uuid}/sell      Sell for profit!

    Step 3: UPGRADE (10 minutes)
    ════════════════════════════
    13. GET /ships/{uuid}/upgrade-options   See what you can upgrade
    14. POST /ships/{uuid}/upgrade/sensors  Upgrade sensors
    15. GET /players/{uuid}/nearby-systems  See further now!

    Step 4: EXPLORE (15 minutes)
    ════════════════════════════
    16. POST /players/{uuid}/scan-system    Scan current system
    17. GET /players/{uuid}/local-bodies    See planets, moons
    18. GET /trading-hubs/{uuid}/cartographer  Buy star charts
    19. POST /players/{uuid}/travel/coordinate  Jump to frontier!

    Step 5: GROW (ongoing)
    ══════════════════════
    20. Repeat trading loop to accumulate wealth
    21. Save for better ship
    22. Consider colonization
    23. Hunt for Precursor rumors
    24. Check victory progress
```

---

## Summary

Space Wars 3002 is a rich, interconnected game where every API endpoint serves a purpose in the player's journey from humble trader to galactic legend. The key workflows are:

1. **Trade Loop**: Buy → Travel → Sell → Profit → Repeat
2. **Upgrade Loop**: Earn → Upgrade Ship → Earn More
3. **Exploration Loop**: Scan → Discover → Chart → Expand
4. **Combat Loop**: Encounter → Assess → Fight/Flee → Salvage
5. **Colony Loop**: Find → Colonize → Build → Defend → Profit

The ultimate goals are the four victory paths:
- **Merchant Empire**: 1 billion credits
- **Colonizer**: 50% of galactic population
- **Conqueror**: 60% of star systems
- **Pirate King**: 70% of pirate network

And always, the legend of the **Void Strider** calls to those bold enough to seek it...

---

*"In the year 3002, the galaxy is your playground. What will you become?"*

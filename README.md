# Space Wars 3002 🚀

_A modern re-imagining of the classic BBS door game **Trade Wars 2002** — built in PHP (Laravel) with a sprinkle of Vue for UI._

---

## Nostalgia

Before MMOs and modern online games, there were **BBS door games**; text-based adventures you dialed into with a modem. Among the most legendary of these was **Trade Wars 2002**.

Released in the early 1990s, Trade Wars 2002 was the definitive space trading and conquest simulator for Bulletin Board Systems (BBS). Players took turns navigating a vast galaxy of numbered sectors, buying and selling commodities, upgrading ships, colonizing planets, and battling rivals. You had limited moves per day, so strategy and timing mattered as much as luck.

What made Trade Wars 2002 unforgettable:
- **Exploration** of a sprawling galaxy map, one sector at a time.
- **Trading** across spaceports with fluctuating commodity prices.
- **Pirates** and hostile encounters that could make or break a run.
- **Planetary colonies** you could found, defend, and expand into empires.
- **Alliances and rivalries** between players, often ending in dramatic wars.
- **Daily turns** (or “fuel”) that forced long-term planning instead of endless grinding.

For many who grew up on dial-up BBSs, Trade Wars 2002 was the first taste of multiplayer online strategy (a mix of capitalism, colonization, and combat) that left a lasting mark on gaming history.

**Space Wars 3002** is a modern homage: keeping the spirit of trading, piracy, and galactic conquest alive, while expanding it with procedural galaxies, asymmetric species, and richer mechanics — the kind of game we wish we had back then.


## Overview

Space Wars 3002 is a turn-based space trading and conquest game. Players explore a procedurally generated galaxy, trade ores across markets, build colonies, fight pirates, and pursue one of several victory conditions:

- **Colonization** - grow and sustain >50% of the galactic population.
- **Merchant Empire** - reach an immense credit total or dominate ore markets.
- **Conquest** - defeat rivals and secure the majority of star systems.
- **Pirate King** - seize control of the galaxy’s outlaw network.

Every galaxy is seeded and generated dynamically, so no two games play the same.

---

## Key Features

- **Procedural galaxies**
    - Config-driven generation (`config/game_config.php`)
    - ~3000 star systems on a 300×300 grid
    - Warp lanes form a connected graph (2–4 links per system)
    - Planets with distinct world types: very_hot, hot, mild, cold, very_cold

- **Species with asymmetry**
    - **Humans**: balanced traders, 1 colony, 100k credits start
    - **Lizards**: warlike, 2 colonies, strong starter ship
    - **Hive Mind**: expansionist, 3 colonies, fast growth

- **Markets & ores**
    - 20+ ore types with origin world biases
    - Dynamic prices based on local supply/demand
    - Arbitrage opportunities across the warp network

- **Ships & upgrades**
    - Classes (Scout, Freighter, Raider, Cutter, Battleship, Carrier, Seeder…)
    - Player-named vessels with upgradeable engines, weapons, defenses, cargo
    - Chassis caps ensure class identity (e.g. Scouts never tank like Battleships)

- **Colonies & defenses** (phase 2)
    - Found on eligible planets
    - Populations scale from thousands → trillions
    - Buy orbital defenses to resist pirates/raids

---

## Tech Stack

- **Backend:** PHP 8.x, Laravel 10/11
- **Frontend:** Vue (planned for UI), CLI for early testing
- **Database:** MariaDB/MySQL
- **Config:** Laravel’s `config/game_config.php` (snapshotted as JSON per galaxy)

---

## Installation

```bash
git clone https://github.com/youruser/space-wars-3002.git
cd space-wars-3002

composer install
cp .env.example .env
php artisan key:generate

# configure DB in .env (MariaDB/MySQL)
php artisan migrate

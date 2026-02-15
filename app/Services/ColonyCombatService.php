<?php

namespace App\Services;

use App\Models\Colony;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\Player;

class ColonyCombatService
{
    public function __construct(
        private readonly TeamCombatService $teamCombatService
    ) {}

    /**
     * Initiate an attack on a colony
     */
    public function initiateColonyAttack(
        Colony $colony,
        Player $attacker,
        array $allies = []
    ): array {
        // Validation
        if ($colony->player_id === $attacker->id) {
            return ['success' => false, 'message' => 'You cannot attack your own colony'];
        }

        // Check if attacker is at colony location
        if ($attacker->current_poi_id !== $colony->poi_id) {
            return ['success' => false, 'message' => 'You must be at the colony location to attack'];
        }

        // Check if attacker has active ship
        if (! $attacker->activeShip) {
            return ['success' => false, 'message' => 'You need an active ship to attack a colony'];
        }

        // Verify all allies at location and have ships
        $attackersTeam = collect([$attacker])->merge($allies);
        foreach ($attackersTeam as $player) {
            if ($player->current_poi_id !== $colony->poi_id) {
                return ['success' => false, 'message' => "{$player->call_sign} is not at the colony location"];
            }
            if (! $player->activeShip) {
                return ['success' => false, 'message' => "{$player->call_sign} does not have an active ship"];
            }
        }

        // Generate NPC defenders based on colony strength
        $defenders = $this->generateDefenders($colony);

        if (empty($defenders)) {
            // No defenses - instant capture
            return $this->captureUndefendedColony($colony, $attacker);
        }

        // Create combat session
        $combatSession = CombatSession::create([
            'combat_type' => 'colony_attack',
            'poi_id' => $colony->poi_id,
            'target_colony_id' => $colony->id,
        ]);

        // Add attacker participants
        foreach ($attackersTeam as $index => $player) {
            $ship = $player->activeShip;
            CombatParticipant::create([
                'combat_session_id' => $combatSession->id,
                'player_id' => $player->id,
                'player_ship_id' => $ship->id,
                'side' => $index === 0 ? 'attacker' : 'ally_attacker',
                'starting_hull' => $ship->hull,
                'current_hull' => $ship->hull,
            ]);
        }

        // Add NPC defender participants
        foreach ($defenders as $index => $defender) {
            CombatParticipant::create([
                'combat_session_id' => $combatSession->id,
                'player_id' => null, // NPC
                'player_ship_id' => null, // NPC
                'side' => $index === 0 ? 'defender' : 'ally_defender',
                'starting_hull' => $defender['hull'],
                'current_hull' => $defender['hull'],
            ]);
        }

        // Resolve colony combat
        $result = $this->resolveColonyCombat($combatSession, $colony, $attackersTeam, $defenders);

        return [
            'success' => true,
            'combat_session' => $combatSession,
            'result' => $result,
        ];
    }

    /**
     * Generate NPC defenders based on colony strength
     */
    private function generateDefenders(Colony $colony): array
    {
        $defenders = [];

        // Base defenders on development level and garrison
        $baseDefenders = (int) floor($colony->development_level / 2);
        $garrisonDefenders = (int) floor($colony->garrison_strength / 50);
        $totalDefenders = $baseDefenders + $garrisonDefenders;

        // Minimum 0, maximum 5 NPC ships
        $totalDefenders = max(0, min(5, $totalDefenders));

        for ($i = 0; $i < $totalDefenders; $i++) {
            // NPC ship strength based on colony development
            $baseHull = 50 + ($colony->development_level * 10);
            $baseWeapons = 15 + ($colony->development_level * 3);

            $defenders[] = [
                'name' => 'Defense Drone '.($i + 1),
                'hull' => $baseHull,
                'weapons' => $baseWeapons,
            ];
        }

        return $defenders;
    }

    /**
     * Resolve colony combat
     */
    private function resolveColonyCombat(
        CombatSession $session,
        Colony $colony,
        $attackersTeam,
        array $npcDefenders
    ): array {
        $attackers = $session->attackers()->with(['player', 'playerShip'])->get();
        $defenders = $session->defenders()->get();

        $combatLog = [];
        $round = 1;

        $combatLog[] = ['type' => 'header', 'message' => '⚔️  COLONY SIEGE INITIATED  ⚔️'];
        $combatLog[] = ['type' => 'info', 'message' => "Target: {$colony->name} (Owned by {$colony->player->call_sign})"];
        $combatLog[] = ['type' => 'info', 'message' => 'ATTACKERS:'];
        foreach ($attackers as $attacker) {
            $combatLog[] = [
                'type' => 'info',
                'message' => "  • {$attacker->player->call_sign}: {$attacker->playerShip->name} (Hull: {$attacker->current_hull})",
            ];
        }
        $combatLog[] = ['type' => 'info', 'message' => 'DEFENDERS:'];
        foreach ($defenders as $index => $defender) {
            $defenderData = $npcDefenders[$index];
            $combatLog[] = [
                'type' => 'info',
                'message' => "  • {$defenderData['name']} (Hull: {$defender->current_hull})",
            ];
        }
        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        // Combat loop
        while ($attackers->where('current_hull', '>', 0)->count() > 0 &&
               $defenders->where('current_hull', '>', 0)->count() > 0 &&
               $round <= 100) {

            $combatLog[] = ['type' => 'round', 'message' => "\n🔹 ROUND {$round}"];

            // Attackers' turn
            foreach ($attackers->where('current_hull', '>', 0) as $attacker) {
                $target = $defenders->where('current_hull', '>', 0)->sortBy('current_hull')->first();

                if (! $target) {
                    break;
                }

                $damage = $this->calculateDamage($attacker->playerShip->weapons);
                $target->takeDamage($damage);
                $attacker->recordDamageDealt($damage);

                $targetIndex = $defenders->search(fn ($d) => $d->id === $target->id);
                $targetName = $npcDefenders[$targetIndex]['name'] ?? 'Defense Drone';

                $combatLog[] = [
                    'type' => 'attack',
                    'message' => "  ➜ {$attacker->player->call_sign} fires at {$targetName} for {$damage} damage! (Hull: {$target->current_hull})",
                ];

                if (! $target->isAlive()) {
                    $combatLog[] = [
                        'type' => 'destroyed',
                        'message' => "  💥 {$targetName} DESTROYED!",
                    ];
                }
            }

            if ($defenders->where('current_hull', '>', 0)->count() === 0) {
                break;
            }

            // Defenders' turn (NPC)
            foreach ($defenders->where('current_hull', '>', 0) as $defenderIndex => $defender) {
                $target = $attackers->where('current_hull', '>', 0)->sortBy('current_hull')->first();

                if (! $target) {
                    break;
                }

                $defenderData = $npcDefenders[$defenderIndex];
                $damage = $this->calculateDamage($defenderData['weapons']);
                $target->takeDamage($damage);
                $defender->recordDamageDealt($damage);

                $combatLog[] = [
                    'type' => 'attack',
                    'message' => "  ⬅ {$defenderData['name']} fires at {$target->player->call_sign} for {$damage} damage! (Hull: {$target->current_hull})",
                ];

                if (! $target->isAlive()) {
                    $combatLog[] = [
                        'type' => 'destroyed',
                        'message' => "  💥 {$target->player->call_sign}'s ship DESTROYED!",
                    ];
                }
            }

            $round++;
        }

        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        // Determine outcome
        $attackersWon = $attackers->where('current_hull', '>', 0)->count() > 0;

        if ($attackersWon) {
            $combatLog[] = [
                'type' => 'victory',
                'message' => '🏆 ATTACKERS BREACHED THE DEFENSES!',
            ];

            // Apply damage to colony
            $buildingDamageResult = $this->applyColonyDamage($colony, $attackers);
            $combatLog = array_merge($combatLog, $buildingDamageResult['log']);

            // Transfer ownership if colony was captured
            $captureResult = $this->attemptColonyCapture($colony, $attackers->first()->player);
            $combatLog = array_merge($combatLog, $captureResult['log']);

            // Update ship hulls for survivors
            foreach ($attackers->where('current_hull', '>', 0) as $attacker) {
                $attacker->playerShip->update(['hull' => $attacker->current_hull]);
            }

            // Award rewards to attackers
            $baseXP = 200; // Colony siege XP
            $xpPerAttacker = (int) ceil($baseXP / $attackers->where('current_hull', '>', 0)->count());

            foreach ($attackers->where('current_hull', '>', 0) as $attacker) {
                $attacker->awardRewards($xpPerAttacker, 0);
            }

            $combatLog[] = [
                'type' => 'rewards',
                'message' => "⭐ Each surviving attacker earned: {$xpPerAttacker} XP",
            ];
        } else {
            $combatLog[] = [
                'type' => 'defeat',
                'message' => '🛡️ DEFENSES HELD! Attackers repelled!',
            ];

            // Update defense rating
            $colony->defense_rating += 10;
            $colony->last_attacked_at = now();
            $colony->save();
        }

        // Complete combat session
        $session->update([
            'combat_log' => $combatLog,
            'current_round' => $round,
        ]);
        $session->complete(
            $attackersWon ? 'attacker' : 'defender',
            $attackersWon ? $attackers->first()->player_id : null
        );

        // Update colony status
        $colony->last_attacked_at = now();
        $colony->save();

        return [
            'victor' => $attackersWon ? 'attackers' : 'defenders',
            'rounds' => $round,
            'combat_log' => $combatLog,
            'attackers_survived' => $attackers->where('current_hull', '>', 0)->count(),
            'colony_captured' => $captureResult['captured'] ?? false,
            'buildings_damaged' => $buildingDamageResult['buildings_damaged'] ?? 0,
        ];
    }

    /**
     * Apply damage to colony buildings and population
     */
    private function applyColonyDamage(Colony $colony, $attackers): array
    {
        $log = [];
        $buildingsDamaged = 0;

        // Damage based on number of surviving attackers and their weapons
        $totalDamage = $attackers->where('current_hull', '>', 0)
            ->sum(fn ($a) => $a->playerShip->weapons);

        // Reduce population (10-30% casualties)
        $populationLoss = (int) ($colony->population * (rand(10, 30) / 100));
        $colony->population = max(0, $colony->population - $populationLoss);

        $log[] = [
            'type' => 'damage',
            'message' => "💀 Colony population reduced by {$populationLoss}",
        ];

        // Damage random buildings
        $buildings = $colony->buildings()->where('status', 'operational')->get();
        $buildingsToDamage = min((int) floor($totalDamage / 50), $buildings->count());

        foreach ($buildings->random(min($buildingsToDamage, $buildings->count())) as $building) {
            $building->update(['status' => 'damaged']);
            $buildingsDamaged++;

            $log[] = [
                'type' => 'damage',
                'message' => "🏚️ {$building->name} was damaged",
            ];
        }

        // Reduce garrison
        $colony->garrison_strength = max(0, $colony->garrison_strength - 50);
        $colony->defense_rating = max(0, $colony->defense_rating - 20);

        $colony->save();

        return [
            'log' => $log,
            'buildings_damaged' => $buildingsDamaged,
            'population_loss' => $populationLoss,
        ];
    }

    /**
     * Attempt to capture colony (if defenses completely destroyed)
     */
    private function attemptColonyCapture(Colony $colony, Player $newOwner): array
    {
        $log = [];

        // Colony is captured if population is low enough or defenses completely gone
        $canCapture = $colony->population < 500 || ($colony->defense_rating <= 0 && $colony->garrison_strength <= 0);

        if ($canCapture) {
            $oldOwner = $colony->player;
            $colony->player_id = $newOwner->id;
            $colony->status = 'establishing'; // Needs to be re-established
            $colony->save();

            $log[] = [
                'type' => 'capture',
                'message' => "🏴 {$colony->name} has been captured by {$newOwner->call_sign}!",
            ];

            return [
                'captured' => true,
                'old_owner' => $oldOwner,
                'new_owner' => $newOwner,
                'log' => $log,
            ];
        }

        $log[] = [
            'type' => 'info',
            'message' => '⚔️ Colony damaged but not captured. Defenses remain.',
        ];

        return [
            'captured' => false,
            'log' => $log,
        ];
    }

    /**
     * Capture an undefended colony instantly
     */
    private function captureUndefendedColony(Colony $colony, Player $attacker): array
    {
        $oldOwner = $colony->player;
        $colony->player_id = $attacker->id;
        $colony->status = 'establishing';
        $colony->last_attacked_at = now();
        $colony->save();

        return [
            'success' => true,
            'instant_capture' => true,
            'message' => 'Colony had no defenses and was captured instantly',
            'old_owner' => $oldOwner,
            'new_owner' => $attacker,
        ];
    }

    /**
     * Calculate damage with ±20% randomization
     */
    private function calculateDamage(int $weaponsPower): int
    {
        $variance = $weaponsPower * 0.20;
        $min = (int) floor($weaponsPower - $variance);
        $max = (int) ceil($weaponsPower + $variance);

        return rand($min, $max);
    }
}

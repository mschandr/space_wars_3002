<?php

namespace App\Console\Shops;

use App\Models\Player;
use App\Models\WarpLanePirate;
use App\Services\PirateEncounterService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class PirateEncounterHandler extends BaseShopHandler
{
    private PirateEncounterService $encounterService;

    public function __construct(Command $command, int $termWidth = 120)
    {
        parent::__construct($command, $termWidth);
        $this->encounterService = app(PirateEncounterService::class);
    }

    /**
     * Handle the pirate encounter
     *
     * @return string Player's choice: 'fight', 'run', 'surrender', or 'dead'
     */
    public function handleEncounter(Player $player, WarpLanePirate $encounter): string
    {
        $this->resetTerminal();

        // Generate the pirate fleet
        $pirateFleet = $this->encounterService->generateFleet($encounter);
        $encounterDetails = $this->encounterService->getEncounterDetails($encounter, $pirateFleet);

        // Show encounter screen and get player choice
        $choice = $this->showEncounterScreen($player, $encounterDetails, $pirateFleet);

        // Record the encounter
        $this->encounterService->recordEncounter($encounter);

        return $choice;
    }

    /**
     * Show the initial encounter screen
     */
    private function showEncounterScreen(Player $player, array $details, Collection $pirateFleet): string
    {
        $ship = $player->activeShip;

        while (true) {
            $this->clearScreen();

            // Header
            $this->renderShopHeader('⚔️  PIRATE ENCOUNTER  ⚔️');

            // Pirate info
            $this->line($this->colorize('  INTERCEPTED BY:', 'header'));
            $this->line('  '.$this->colorize($details['captain_title'].' ', 'pirate').
                       $this->colorize($details['captain_name'], 'pirate'));
            $this->line('  '.$this->colorize($details['faction_name'], 'dim'));
            $this->newLine();

            // Fleet composition
            $this->line($this->colorize('  ENEMY FLEET:', 'header'));
            foreach ($details['fleet'] as $index => $ship_data) {
                $this->line('  '.$this->colorize('▸', 'pirate').' '.
                           $this->colorize($ship_data['name'], 'pirate').
                           $this->colorize(" ({$ship_data['class']})", 'dim'));
                $this->line('    '.$this->colorize('Hull: ', 'dim').
                           $this->colorize("{$ship_data['hull']}/{$ship_data['max_hull']}", 'highlight').
                           $this->colorize('  Weapons: ', 'dim').
                           $this->colorize($ship_data['weapons'], 'highlight'));
            }
            $this->newLine();

            // Difficulty indicator
            $difficultyStars = str_repeat('★', $details['difficulty_tier']).
                             str_repeat('☆', 5 - $details['difficulty_tier']);
            $this->line('  '.$this->colorize('Threat Level: ', 'label').
                       $this->colorize($difficultyStars, 'pirate'));
            $this->newLine();

            // Your ship info
            $this->line($this->colorize('  YOUR SHIP:', 'header'));
            $this->line('  '.$this->colorize($ship->name, 'highlight').
                       $this->colorize(" ({$ship->ship->class})", 'dim'));
            $this->line('    '.$this->colorize('Hull: ', 'dim').
                       $this->colorize("{$ship->hull}/{$ship->max_hull}", 'highlight').
                       $this->colorize('  Weapons: ', 'dim').
                       $this->colorize($ship->weapons, 'highlight'));
            $this->line('    '.$this->colorize('Speed: ', 'dim').
                       $this->colorize($ship->ship->speed, 'highlight').
                       $this->colorize('  Warp Drive: ', 'dim').
                       $this->colorize($ship->warp_drive, 'highlight'));
            $this->newLine();

            // Combat preview
            $preview = $this->encounterService->getCombatPreview($ship, $pirateFleet);
            $this->line('  '.$this->colorize('Combat Assessment: ', 'label').
                       $this->colorize($preview['difficulty'], 'pirate').
                       $this->colorize(" (Est. {$preview['estimated_win_chance']}% win chance)", 'dim'));
            $this->newLine();

            // Escape analysis
            $escapeAnalysis = $this->encounterService->getEscapeAnalysis($ship, $pirateFleet);
            $canEscape = $escapeAnalysis['can_escape'];
            $escapeText = $canEscape ?
                $this->colorize('✓ Possible', 'highlight') :
                $this->colorize('✗ Impossible', 'pirate');
            $this->line('  '.$this->colorize('Escape Chance: ', 'label').$escapeText);
            if (! $canEscape) {
                $reasons = [];
                if ($escapeAnalysis['speed_advantage'] <= 0) {
                    $reasons[] = "Speed too low ({$ship->speed} vs {$escapeAnalysis['their_max_speed']})";
                }
                if ($escapeAnalysis['warp_advantage'] <= 0) {
                    $reasons[] = "Warp drive too low ({$ship->warp_drive} vs {$escapeAnalysis['their_max_warp']})";
                }
                $this->line('    '.$this->colorize(implode(', ', $reasons), 'dim'));
            }
            $this->newLine();

            // Options
            $this->renderSeparator();
            $this->newLine();
            $this->line('  '.$this->colorize('[F]', 'highlight').' Fight - Engage in combat');
            $this->line('  '.$this->colorize('[R]', 'highlight').' Run - Attempt to escape');
            $this->line('  '.$this->colorize('[S]', 'highlight').' Surrender - Jettison cargo and hope for mercy');
            $this->newLine();
            $this->line('  '.$this->colorize('Choose your action:', 'label'));

            // Get input
            $choice = strtolower($this->command->ask(''));

            if (in_array($choice, ['f', 'r', 's'])) {
                return match ($choice) {
                    'f' => $this->handleFight($player, $pirateFleet),
                    'r' => $this->handleRun($player, $pirateFleet),
                    's' => $this->handleSurrender($player, $pirateFleet),
                };
            }

            $this->command->error('Invalid choice. Please enter F, R, or S.');
            sleep(1);
        }
    }

    /**
     * Handle fight choice
     */
    private function handleFight(Player $player, Collection $pirateFleet): string
    {
        return $this->showCombatScreen($player, $pirateFleet);
    }

    /**
     * Handle run choice
     */
    private function handleRun(Player $player, Collection $pirateFleet): string
    {
        $ship = $player->activeShip;
        $escapeResult = $this->encounterService->attemptEscape($ship, $pirateFleet);

        $this->clearScreen();
        $this->renderShopHeader('ESCAPE ATTEMPT');

        if ($escapeResult['success']) {
            $this->line('  '.$this->colorize('✓ SUCCESS!', 'highlight'));
            $this->newLine();
            $this->line('  '.$escapeResult['message']);
            $this->newLine();
            $this->line('  '.$this->colorize('Press any key to continue...', 'dim'));
            $this->command->ask('');

            return 'escaped';
        } else {
            $this->line('  '.$this->colorize('✗ FAILED!', 'pirate'));
            $this->newLine();
            $this->line('  '.$escapeResult['message']);
            $this->newLine();
            $this->line('  '.$this->colorize('You are forced into combat!', 'pirate'));
            $this->newLine();
            $this->line('  '.$this->colorize('Press any key to continue...', 'dim'));
            $this->command->ask('');

            // Forced into combat
            return $this->showCombatScreen($player, $pirateFleet);
        }
    }

    /**
     * Handle surrender choice
     */
    private function handleSurrender(Player $player, Collection $pirateFleet): string
    {
        $ship = $player->activeShip;
        $surrenderResult = $this->encounterService->processSurrender($player, $ship, $pirateFleet);

        $this->clearScreen();
        $this->renderShopHeader('SURRENDER');

        // Display surrender message
        foreach (explode("\n", $surrenderResult['message']) as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
        $this->line('  '.$this->colorize('Press any key to continue...', 'dim'));
        $this->command->ask('');

        return 'surrendered';
    }

    /**
     * Show combat screen with live log
     */
    private function showCombatScreen(Player $player, Collection $pirateFleet): string
    {
        $ship = $player->activeShip;

        $this->clearScreen();
        $this->renderShopHeader('⚔️  COMBAT ENGAGED  ⚔️');

        // Initiate combat
        $combatResult = $this->encounterService->initiateCombat($player, $ship, $pirateFleet);

        // Display combat log with dramatic delays
        foreach ($combatResult['log'] as $logEntry) {
            $color = match ($logEntry['type']) {
                'header' => 'header',
                'round' => 'label',
                'player_attack' => 'highlight',
                'enemy_attack' => 'pirate',
                'enemy_destroyed' => 'highlight',
                'player_destroyed' => 'pirate',
                'victory' => 'highlight',
                'defeat' => 'pirate',
                'divider' => 'border',
                default => 'reset',
            };

            $this->line('  '.$this->colorize($logEntry['message'], $color));

            // Add delay for drama (except for dividers and headers)
            if (! in_array($logEntry['type'], ['divider', 'header', 'info'])) {
                usleep(300000); // 0.3 seconds
            }
        }

        $this->newLine();

        if ($combatResult['victory']) {
            // Victory! Show salvage screen
            $this->line('  '.$this->colorize('Press any key to collect salvage...', 'dim'));
            $this->command->ask('');

            if (! empty($combatResult['salvage']['minerals']->toArray()) ||
                ! empty($combatResult['salvage']['plans']->toArray())) {
                $this->showSalvageScreen($player, $combatResult['salvage']);
            } else {
                $this->line('  '.$this->colorize('No salvage found.', 'dim'));
                $this->command->ask('Press any key to continue...');
            }

            return 'victory';
        } else {
            // Defeat - show death screen
            $this->showDeathScreen($combatResult['death']);

            return 'dead';
        }
    }

    /**
     * Show salvage screen (placeholder for now)
     */
    private function showSalvageScreen(Player $player, array $salvage): void
    {
        // TODO: Implement multi-select salvage screen
        $this->clearScreen();
        $this->renderShopHeader('SALVAGE COLLECTION');

        $this->line('  '.$this->colorize('Salvage available:', 'label'));
        $this->newLine();

        // Show minerals
        if ($salvage['minerals']->isNotEmpty()) {
            $this->line('  '.$this->colorize('MINERALS:', 'header'));
            foreach ($salvage['minerals'] as $mineralData) {
                $mineral = $mineralData['mineral'];
                $quantity = $mineralData['total_quantity'];
                $this->line('    '.$this->colorize('▸', 'highlight').' '.
                           $mineral->name.': '.$quantity.' units');
            }
            $this->newLine();
        }

        // Show plans
        if ($salvage['plans']->isNotEmpty()) {
            $this->line('  '.$this->colorize('UPGRADE PLANS:', 'header'));
            foreach ($salvage['plans'] as $planCargo) {
                $plan = $planCargo->plan;
                $this->line('    '.$this->colorize('▸', 'highlight').' '.$plan->name);
            }
            $this->newLine();
        }

        $this->line('  '.$this->colorize('[Auto-collecting all salvage]', 'dim'));
        $this->newLine();

        // Auto-collect for now (TODO: make this interactive)
        $selectedMinerals = [];
        foreach ($salvage['minerals'] as $mineralData) {
            $selectedMinerals[$mineralData['mineral']->id] = $mineralData['total_quantity'];
        }

        $selectedPlans = $salvage['plans']->pluck('plan_id')->toArray();

        $transferResult = $this->encounterService->transferSalvage(
            $player,
            $player->activeShip,
            $selectedMinerals,
            $selectedPlans
        );

        foreach ($transferResult['messages'] as $message) {
            $this->line('  '.$this->colorize($message, 'dim'));
        }

        $this->newLine();
        $this->line('  '.$this->colorize('Salvage collected!', 'highlight'));
        $this->newLine();
        $this->line('  '.$this->colorize('Press any key to continue...', 'dim'));
        $this->command->ask('');
    }

    /**
     * Show death screen
     */
    private function showDeathScreen(array $deathResult): void
    {
        $this->clearScreen();

        $message = app(\App\Services\PlayerDeathService::class)->generateDeathMessage($deathResult);

        foreach (explode("\n", $message) as $line) {
            if (str_contains($line, '═')) {
                $this->line($this->colorize($line, 'border'));
            } elseif (str_contains($line, 'DESTROYED') || str_contains($line, 'ESCAPE POD')) {
                $this->line($this->colorize($line, 'pirate'));
            } elseif (str_contains($line, 'LOSSES:') || str_contains($line, 'RETAINED:')) {
                $this->line($this->colorize($line, 'header'));
            } else {
                $this->line($line);
            }
        }

        $this->newLine();
        $this->command->ask('Press any key to continue...');
    }
}

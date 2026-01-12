<?php

namespace App\Console\Renderers;

use App\Console\Traits\ConsoleColorizer;
use App\Models\Galaxy;
use App\Models\Sector;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SectorRenderer
{
    use ConsoleColorizer;

    public function __construct(
        private readonly Command $command,
        private readonly int $termWidth,
        private readonly int $termHeight
    ) {}

    public function render(
        Galaxy $galaxy,
        Collection $sectors,
        array &$sectorMap
    ): void {
        $this->clearScreen();
        $this->renderHeader($galaxy, $sectors);

        // Group sectors by grid position
        $gridSize = (int) sqrt($sectors->count());
        $grid = [];

        foreach ($sectors as $sector) {
            $grid[$sector->grid_y][$sector->grid_x] = $sector;
        }

        // Use compact table rendering for better visibility
        $this->renderCompactGrid($grid, $gridSize, $sectorMap);

        // Render legend
        $this->renderLegend();
    }

    private function renderHeader(Galaxy $galaxy, Collection $sectors): void
    {
        $this->command->line($this->colorize('╔'.str_repeat('═', $this->termWidth - 2).'╗', 'border'));

        $title = "  GALAXY: {$galaxy->name} - SECTOR OVERVIEW  ";
        $stats = "Sectors: {$sectors->count()} | Grid: ".(int) sqrt($sectors->count()).'x'.(int) sqrt($sectors->count()).'  ';
        $padding = $this->termWidth - strlen($title) - strlen($stats) - 2;

        $this->command->line(
            $this->colorize('║', 'border').
            $this->colorize($title, 'header').
            str_repeat(' ', max(0, $padding)).
            $this->colorize($stats, 'dim').
            $this->colorize('║', 'border')
        );

        $this->command->line($this->colorize('╚'.str_repeat('═', $this->termWidth - 2).'╝', 'border'));
    }

    private function renderCompactGrid(array $grid, int $gridSize, array &$sectorMap): void
    {
        $sectorIndex = 0;

        for ($y = 0; $y < $gridSize; $y++) {
            $rowOutput = '';

            for ($x = 0; $x < $gridSize; $x++) {
                $sector = $grid[$y][$x] ?? null;

                if (! $sector) {
                    $rowOutput .= str_pad('', 12);

                    continue;
                }

                // Get sector stats
                $stats = $sector->getStats();
                $dangerLevel = $sector->getDangerLevel();

                // Determine cell color: bright for sectors with stars, dim for empty
                if ($stats['star_count'] > 0) {
                    // Sectors with stars - color by danger level
                    $cellColor = match ($dangerLevel) {
                        'high' => 'pirate',
                        'medium' => 'highlight',
                        'low' => 'header',  // Bright color for populated sectors
                        default => 'header'
                    };
                } else {
                    // Empty sectors - dim gray
                    $cellColor = 'dim';
                }

                // Assign sector index for selection (support up to 100 sectors)
                $char = $this->indexToChar($sectorIndex);
                $sectorMap[$sectorIndex] = $sector;
                $sectorIndex++;

                // Format: [key] Name ★count
                $stars = $stats['star_count'];
                $pirates = $stats['pirate_count'];

                $cellText = '['.$this->colorize($char, 'header').'] ';
                $cellText .= $this->colorize(str_pad(substr($sector->name, 0, 6), 6), $cellColor);

                if ($pirates > 0) {
                    $cellText .= $this->colorize('☠', 'pirate');
                } else {
                    $cellText .= ' ';
                }

                $rowOutput .= $cellText.' ';
            }

            $this->command->line($rowOutput);
        }
    }

    private function indexToChar(int $index): string
    {
        // Support 100+ sectors with single and double character codes
        // 0-9 (10), a-z minus g,h,r,t (22) = 32 single chars
        // Then aa, ab, ac, ... (double chars for remaining)

        $singleChars = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $reservedLetters = ['g', 'h', 'r', 't'];

        // Add letters a-z excluding reserved ones
        for ($i = 0; $i < 26; $i++) {
            $letter = chr(ord('a') + $i);
            if (! in_array($letter, $reservedLetters)) {
                $singleChars[] = $letter;
            }
        }

        $singleCharCount = count($singleChars);

        // If index fits in single chars, use it
        if ($index < $singleCharCount) {
            return $singleChars[$index];
        }

        // Otherwise, use double chars: aa, ab, ac, etc.
        $doubleIndex = $index - $singleCharCount;
        $letters = [];
        for ($i = 0; $i < 26; $i++) {
            $letter = chr(ord('a') + $i);
            if (! in_array($letter, $reservedLetters)) {
                $letters[] = $letter;
            }
        }

        $letterCount = count($letters);
        $first = $letters[(int) floor($doubleIndex / $letterCount)];
        $second = $letters[$doubleIndex % $letterCount];

        return $first.$second;
    }

    private function renderLegend(): void
    {
        $this->command->newLine();
        $this->command->line($this->colorize('  LEGEND:', 'header'));

        $legends = [
            ['★', 'highlight', 'Star Count'],
            ['☠', 'pirate', 'Pirate Encounters'],
            ['[0-z]', 'header', 'Single char selector'],
            ['[aa-zz]', 'header', 'Double char selector'],
            ['Bright', 'header', 'Sector has stars'],
            ['Dim', 'dim', 'Empty sector'],
            ['Red', 'pirate', 'High danger (pirates)'],
            ['Yellow', 'highlight', 'Medium danger'],
        ];

        foreach ($legends as $legend) {
            [$char, $color, $desc] = $legend;
            $this->command->line('  '.$this->colorize($char, $color).' = '.$desc);
        }

        $this->command->newLine();
        $this->command->line($this->colorize('  Type sector code (e.g. 0, a, aa, ab) then press ENTER to view sector', 'dim'));
        $this->command->line($this->colorize('  Press [h] to toggle hidden | [g] to toggle gates | [q] to quit', 'dim'));
    }
}

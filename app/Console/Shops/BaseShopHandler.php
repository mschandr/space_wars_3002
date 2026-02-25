<?php

namespace App\Console\Shops;

use App\Console\Traits\ConsoleBoxRenderer;
use App\Console\Traits\ConsoleColorizer;
use App\Console\Traits\TerminalInputHandler;
use Illuminate\Console\Command;

abstract class BaseShopHandler
{
    use ConsoleBoxRenderer;
    use ConsoleColorizer;
    use TerminalInputHandler;

    protected Command $command;

    protected int $termWidth;

    public function __construct(Command $command, int $termWidth = 120)
    {
        $this->command = $command;
        $this->termWidth = $termWidth;
    }

    protected function line(string $text): void
    {
        $this->command->line($text);
    }

    protected function newLine(int $count = 1): void
    {
        $this->command->newLine($count);
    }

    protected function error(string $text): void
    {
        $this->command->error($text);
    }

    /**
     * Render a standard shop header with border lines and title.
     */
    protected function renderShopHeader(string $title): void
    {
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize("  {$title}", 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();
    }

    /**
     * Render a separator line.
     */
    protected function renderSeparator(): void
    {
        $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
    }

    /**
     * Render a border line (double line).
     */
    protected function renderBorder(): void
    {
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
    }

    /**
     * Wait for the user to press any key.
     */
    protected function waitForAnyKey(string $message = 'Press any key to continue...'): void
    {
        $this->line($this->colorize("  {$message}", 'dim'));
        system('stty -icanon -echo');
        fgetc(STDIN);
    }

    /**
     * Initialize terminal to sane state.
     */
    protected function resetTerminal(): void
    {
        system('stty sane');
    }

    /**
     * Set terminal to raw single-char input mode.
     */
    protected function setRawInputMode(): void
    {
        system('stty -icanon -echo');
    }

    /**
     * Read a single character from STDIN in raw mode.
     */
    protected function readChar(): string
    {
        $this->setRawInputMode();

        return fgetc(STDIN);
    }

    /**
     * Show a success result screen.
     */
    protected function showSuccess(string $message): void
    {
        $this->renderBorder();
        $this->line($this->colorize("  {$message}", 'trade'));
        $this->renderBorder();
        $this->newLine();
    }

    /**
     * Show a failure result screen.
     */
    protected function showFailure(string $message): void
    {
        $this->renderBorder();
        $this->line($this->colorize("  {$message}", 'dim'));
        $this->renderBorder();
        $this->newLine();
    }

    /**
     * Display an insufficient credits error and wait.
     */
    protected function showInsufficientCredits(float $required, float $available): void
    {
        $this->clearScreen();
        $this->error('  Insufficient credits');
        $this->newLine();
        $this->line('  Required: '.number_format($required, 2).' credits');
        $this->line('  You have: '.number_format($available, 2).' credits');
        $this->newLine();
        $this->waitForAnyKey();
    }

    /**
     * Check if the user exits via quit/escape key.
     */
    protected function isQuitKey(string $char): bool
    {
        return $char === 'q' || $char === "\033";
    }

    /**
     * Word-wrap text to fit within a given width, indenting continuation lines.
     */
    protected function wrapText(string $text, int $maxWidth, string $indent = '      '): string
    {
        $wrapped = wordwrap($text, $maxWidth, "\n", true);

        return str_replace("\n", "\n{$indent}", $wrapped);
    }
}

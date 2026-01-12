<?php

namespace App\Console\Traits;

trait ModalDisplay
{
    use TerminalInputHandler;

    /**
     * Display a modal with title and content, wait for key press
     *
     * @param  string  $title  The modal title
     * @param  callable  $contentBuilder  Callback function to render content
     */
    protected function showModal(string $title, callable $contentBuilder): void
    {
        $this->disableRawTerminal();
        $this->clearScreen();

        // Render header
        $this->renderHorizontalLine();
        $this->line($this->colorize('  '.$title, 'header'));
        $this->renderHorizontalLine();
        $this->newLine();

        // Build content
        $contentBuilder();

        // Footer
        $this->newLine();
        $this->renderHorizontalLine();
        $this->line($this->colorize('  Press any key to continue...', 'dim'));

        // Wait for input
        $this->enableRawTerminal();
        fgetc(STDIN);
        $this->refreshInterface();
    }
}

<?php

namespace App\Console\Traits;

trait TerminalInputHandler
{
    /**
     * Read a single character from STDIN in non-blocking mode
     *
     * @return string|false The character read, or false if no input available
     */
    private function readChar()
    {
        $read = [STDIN];
        $write = null;
        $except = null;
        $result = stream_select($read, $write, $except, 0, 100000);

        if ($result === false) {
            return false;
        }

        if ($result > 0) {
            return fgetc(STDIN);
        }

        return false;
    }

    /**
     * Enable raw terminal mode (non-canonical, no echo)
     */
    protected function enableRawTerminal(): void
    {
        system('stty -icanon -echo');
    }

    /**
     * Disable raw terminal mode and restore to sane defaults
     */
    protected function disableRawTerminal(): void
    {
        system('stty sane');
    }
}

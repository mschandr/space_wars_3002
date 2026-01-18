<?php

namespace App\Console\Traits;

trait ConsoleBoxRenderer
{
    /**
     * Format a line within a box with proper padding
     */
    protected function formatBoxLine(string $content, int $boxWidth = 58): string
    {
        $plain = preg_replace('/\033\[[0-9;]*m/', '', $content);
        $padding = $boxWidth - mb_strlen($plain);

        return $this->colorize('║', 'border').' '.$content.
               str_repeat(' ', $padding).$this->colorize('║', 'border')."\n";
    }

    /**
     * Render a box header with title
     */
    protected function renderBoxHeader(string $title, int $width = 60): string
    {
        $titlePlain = preg_replace('/\033\[[0-9;]*m/', '', $title);
        $padding = $width - 4 - mb_strlen($titlePlain);

        return $this->colorize('╔'.str_repeat('═', $width - 2).'╗', 'border')."\n".
               $this->colorize('║', 'border').' '.$title.
               str_repeat(' ', $padding).$this->colorize('║', 'border')."\n".
               $this->colorize('╠'.str_repeat('═', $width - 2).'╣', 'border')."\n";
    }

    /**
     * Render a box footer
     */
    protected function renderBoxFooter(int $width = 60): string
    {
        return $this->colorize('╚'.str_repeat('═', $width - 2).'╝', 'border')."\n";
    }

    /**
     * Render a horizontal line
     */
    protected function renderHorizontalLine(int $width = 60, string $style = '═'): void
    {
        $this->line($this->colorize(str_repeat($style, $width), 'border'));
    }
}

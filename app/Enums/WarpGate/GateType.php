<?php

namespace App\Enums\WarpGate;

enum GateType: string
{
    case STANDARD = 'standard';
    case MIRROR_ENTRY = 'mirror_entry';
    case MIRROR_RETURN = 'mirror_return';
    case DEAD_END = 'dead_end';        // Future expansion
    case JACKPOT = 'jackpot';          // Future expansion

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard Warp Gate',
            self::MIRROR_ENTRY => 'Mirror Universe Portal',
            self::MIRROR_RETURN => 'Return Gate',
            self::DEAD_END => 'Dead End Gate',
            self::JACKPOT => 'Jackpot Gate',
        };
    }

    public function isSpecial(): bool
    {
        return $this !== self::STANDARD;
    }

    public function isMirrorGate(): bool
    {
        return in_array($this, [self::MIRROR_ENTRY, self::MIRROR_RETURN]);
    }

    public static function fromString(string $type): self
    {
        return match (strtolower($type)) {
            'mirror_entry', 'mirror entry', 'entry' => self::MIRROR_ENTRY,
            'mirror_return', 'mirror return', 'return' => self::MIRROR_RETURN,
            'dead_end', 'dead end', 'deadend' => self::DEAD_END,
            'jackpot' => self::JACKPOT,
            default => self::STANDARD,
        };
    }
}

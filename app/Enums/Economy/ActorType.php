<?php

namespace App\Enums\Economy;

enum ActorType: string
{
    case PLAYER = 'PLAYER';
    case NPC = 'NPC';
    case SYSTEM = 'SYSTEM';

    public function label(): string
    {
        return match ($this) {
            self::PLAYER => 'Player',
            self::NPC => 'NPC',
            self::SYSTEM => 'System',
        };
    }
}

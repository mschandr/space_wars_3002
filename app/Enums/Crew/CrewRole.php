<?php

namespace App\Enums\Crew;

enum CrewRole: string
{
    case SCIENCE_OFFICER = 'science_officer';
    case TACTICAL_OFFICER = 'tactical_officer';
    case CHIEF_ENGINEER = 'chief_engineer';
    case LOGISTICS_OFFICER = 'logistics_officer';
    case HELMS_OFFICER = 'helms_officer';

    /**
     * Get human-readable label for this role
     */
    public function label(): string
    {
        return match ($this) {
            self::SCIENCE_OFFICER => 'Science Officer',
            self::TACTICAL_OFFICER => 'Tactical Officer',
            self::CHIEF_ENGINEER => 'Chief Engineer',
            self::LOGISTICS_OFFICER => 'Logistics Officer',
            self::HELMS_OFFICER => 'Helms Officer',
        };
    }

    /**
     * Get description of what this role does
     */
    public function description(): string
    {
        return match ($this) {
            self::SCIENCE_OFFICER => 'Enhances scanning and sensor capabilities',
            self::TACTICAL_OFFICER => 'Improves combat effectiveness and weapon systems',
            self::CHIEF_ENGINEER => 'Reduces ship repair costs and improves efficiency',
            self::LOGISTICS_OFFICER => 'Improves trading discounts and cargo efficiency',
            self::HELMS_OFFICER => 'Enhances navigation and fuel efficiency',
        };
    }
}

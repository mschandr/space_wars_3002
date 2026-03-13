<?php

namespace Database\Seeders;

use App\Enums\Vendor\VendorArchetype;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

/**
 * Seed global vendor persona templates
 *
 * Creates a global pool of vendor personas that can be sampled
 * when instantiating galaxies. These are NOT galaxy-specific.
 * Galaxy instances are created by GalaxyVendorProfileSeeder.
 */
class VendorProfileSeeder extends Seeder
{
    private static array $vendorNames = [
        'Kovac\'s Trading Post',
        'The Wandering Merchant',
        'Starlight Bazaar',
        'Port Authority Exchange',
        'Chen\'s Emporium',
        'Black Nova Trading',
        'The Silent Broker',
        'Fortuna\'s Wheel',
        'The Rusty Bolt',
        'Void Commerce Ltd',
        'The Gilded Gate',
        'Crimson Tide Supplies',
        'Neutral Ground',
        'The Honest Scale',
        'Twilight Trading Company',
        'The Phantom Exchange',
        'Cargo Collective',
        'Stellar Goods & Services',
        'Interstellar Imports',
        'The Shadowed Market',
        'Fortune\'s Warehouse',
        'Corporate Exchange',
        'The Explorer\'s Hub',
        'Pirate\'s Rest',
        'Underground Market',
        'The Gear Smithy',
        'Social Hub Trading',
        'The Corporate Liaison',
        'Outpost Alpha',
        'The Quick Buck',
        'Salvage Central',
        'The Vault',
    ];

    public function run(): void
    {
        $this->command->info('Seeding vendor persona templates...');

        $totalCreated = 0;
        $usedNames = [];

        // Create diverse vendor personas covering all archetypes
        $archetypes = VendorArchetype::cases();

        foreach ($archetypes as $archetype) {
            try {
                // Create 3-4 vendors per archetype
                $count = match ($archetype) {
                    VendorArchetype::HONEST_BROKER => 4,
                    VendorArchetype::HARD_BARGAINER => 4,
                    VendorArchetype::FENCE => 3,
                    VendorArchetype::CORPORATE_AGENT => 3,
                    VendorArchetype::EXPLORER_OUTFITTER => 3,
                    VendorArchetype::PIRATE_CONTACT => 3,
                    VendorArchetype::BLACK_MARKET_DEALER => 3,
                    VendorArchetype::GRUFF_MECHANIC => 3,
                    VendorArchetype::SOCIALITE => 3,
                };

                for ($i = 0; $i < $count; $i++) {
                    // Pick unique name
                    $name = null;
                    do {
                        $name = $this->vendorNameForArchetype($archetype, $usedNames);
                    } while (in_array($name, $usedNames));
                    $usedNames[] = $name;

                    // Random service type
                    $serviceType = fake()->randomElement(['trading_hub', 'salvage_yard', 'shipyard', 'market']);

                    // Criminality based on archetype
                    $baseCriminality = $this->baseCriminalityForArchetype($archetype);
                    $criminality = max(0, min(1, $baseCriminality + random_int(-10, 10) / 100));

                    VendorProfile::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'name' => $name,
                        'archetype' => $archetype->value,
                        'service_type' => $serviceType,
                        'criminality' => $criminality,
                        'personality' => [
                            'honesty' => $this->personalityForArchetype($archetype, 'honesty'),
                            'greed' => $this->personalityForArchetype($archetype, 'greed'),
                            'risk_tolerance' => $this->personalityForArchetype($archetype, 'risk_tolerance'),
                            'charm' => $this->personalityForArchetype($archetype, 'charm'),
                            'ego_drive' => $this->personalityForArchetype($archetype, 'ego_drive'),
                            'empathy' => $this->personalityForArchetype($archetype, 'empathy'),
                            'curiosity' => $this->personalityForArchetype($archetype, 'curiosity'),
                        ],
                        'dialogue_pool' => $this->generateDialoguePool($archetype),
                        'markup_base' => $archetype->baseMarkup(),
                    ]);

                    $totalCreated++;
                }
            } catch (\Exception $e) {
                $this->command->error("  Error creating vendor for {$archetype->label()}: {$e->getMessage()}");
            }
        }

        $this->command->info("✓ Total vendor persona templates created: {$totalCreated}");
    }

    private function vendorNameForArchetype(VendorArchetype $archetype, array $usedNames): string
    {
        $availableNames = array_diff(self::$vendorNames, $usedNames);
        if (empty($availableNames)) {
            return "{$archetype->label()} #{$availableNames}";
        }
        return array_values($availableNames)[array_rand(array_values($availableNames))];
    }

    private function baseCriminalityForArchetype(VendorArchetype $archetype): float
    {
        return match ($archetype) {
            VendorArchetype::HONEST_BROKER => 0.1,
            VendorArchetype::HARD_BARGAINER => 0.3,
            VendorArchetype::FENCE => 0.7,
            VendorArchetype::CORPORATE_AGENT => 0.15,
            VendorArchetype::EXPLORER_OUTFITTER => 0.2,
            VendorArchetype::PIRATE_CONTACT => 0.6,
            VendorArchetype::BLACK_MARKET_DEALER => 0.9,
            VendorArchetype::GRUFF_MECHANIC => 0.1,
            VendorArchetype::SOCIALITE => 0.25,
        };
    }

    private function personalityForArchetype(VendorArchetype $archetype, string $trait): float
    {
        return match ($archetype) {
            VendorArchetype::HONEST_BROKER => match ($trait) {
                'honesty' => 0.9,
                'greed' => 0.2,
                'risk_tolerance' => 0.3,
                'charm' => 0.6,
                'ego_drive' => 0.4,
                'empathy' => 0.8,
                'curiosity' => 0.5,
            },
            VendorArchetype::HARD_BARGAINER => match ($trait) {
                'honesty' => 0.5,
                'greed' => 0.8,
                'risk_tolerance' => 0.6,
                'charm' => 0.5,
                'ego_drive' => 0.8,
                'empathy' => 0.3,
                'curiosity' => 0.5,
            },
            VendorArchetype::FENCE => match ($trait) {
                'honesty' => 0.2,
                'greed' => 0.7,
                'risk_tolerance' => 0.8,
                'charm' => 0.4,
                'ego_drive' => 0.5,
                'empathy' => 0.2,
                'curiosity' => 0.3,
            },
            VendorArchetype::CORPORATE_AGENT => match ($trait) {
                'honesty' => 0.6,
                'greed' => 0.6,
                'risk_tolerance' => 0.3,
                'charm' => 0.7,
                'ego_drive' => 0.7,
                'empathy' => 0.5,
                'curiosity' => 0.7,
            },
            VendorArchetype::EXPLORER_OUTFITTER => match ($trait) {
                'honesty' => 0.7,
                'greed' => 0.4,
                'risk_tolerance' => 0.8,
                'charm' => 0.6,
                'ego_drive' => 0.6,
                'empathy' => 0.4,
                'curiosity' => 0.9,
            },
            VendorArchetype::PIRATE_CONTACT => match ($trait) {
                'honesty' => 0.3,
                'greed' => 0.8,
                'risk_tolerance' => 0.9,
                'charm' => 0.5,
                'ego_drive' => 0.9,
                'empathy' => 0.2,
                'curiosity' => 0.6,
            },
            VendorArchetype::BLACK_MARKET_DEALER => match ($trait) {
                'honesty' => 0.1,
                'greed' => 0.9,
                'risk_tolerance' => 0.9,
                'charm' => 0.3,
                'ego_drive' => 0.6,
                'empathy' => 0.1,
                'curiosity' => 0.2,
            },
            VendorArchetype::GRUFF_MECHANIC => match ($trait) {
                'honesty' => 0.8,
                'greed' => 0.4,
                'risk_tolerance' => 0.5,
                'charm' => 0.2,
                'ego_drive' => 0.3,
                'empathy' => 0.3,
                'curiosity' => 0.8,
            },
            VendorArchetype::SOCIALITE => match ($trait) {
                'honesty' => 0.4,
                'greed' => 0.5,
                'risk_tolerance' => 0.6,
                'charm' => 0.9,
                'ego_drive' => 0.7,
                'empathy' => 0.8,
                'curiosity' => 0.7,
            },
        };
    }

    private function generateDialoguePool(VendorArchetype $archetype): array
    {
        $archetype_key = $archetype->value;
        $pools = [
            'honest_broker' => [
                'greeting' => [
                    'Welcome! We offer fair prices for everything.',
                    'Greetings. Let me show you what we have.',
                    'Come in, come in. You\'ll find good deals here.',
                ],
                'deal_accepted' => [
                    'A pleasure doing business with you.',
                    'Excellent choice. Fair and square.',
                    'I appreciate your patronage.',
                ],
            ],
            'hard_bargainer' => [
                'greeting' => [
                    'Looking for a deal, eh? I got what you need.',
                    'You come to the right place. Everything must go.',
                    'Welcome back... or first time?',
                ],
                'deal_accepted' => [
                    'Good business today. Come again?',
                    'You got yourself a bargain there.',
                    'Not bad, not bad. We\'ll do business again.',
                ],
            ],
            'fence' => [
                'greeting' => [
                    'No questions asked, no names either.',
                    'I deal in... specialty items.',
                    'You look like someone who appreciates discretion.',
                ],
                'deal_accepted' => [
                    'Pleasure doing discrete business.',
                    'You understand the value of silence.',
                    'This never happened.',
                ],
            ],
        ];

        if (isset($pools[$archetype_key])) {
            return $pools[$archetype_key];
        }

        // Generic dialogue pool
        return [
            'greeting' => [
                'Welcome to my shop.',
                'Looking for something?',
                'Come in, don\'t be shy.',
            ],
            'deal_accepted' => [
                'Great choice.',
                'Pleasure doing business.',
                'Thank you for your patronage.',
            ],
            'farewell' => [
                'Come again soon.',
                'Safe travels.',
                'Until next time.',
            ],
        ];
    }
}

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
 *
 * One vendor per archetype (24 total), grouped by service_type.
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

        $categoryArchetypes = [
            'shipyard' => [
                VendorArchetype::CORPORATE_SHIPBUILDER,
                VendorArchetype::LUXURY_SHIPWRIGHT,
                VendorArchetype::MILITARY_CONTRACTOR,
                VendorArchetype::INDEPENDENT_BUILDER,
            ],
            'salvage_yard' => [
                VendorArchetype::HONEST_SCRAPPER,
                VendorArchetype::GREEDY_JUNK_DEALER,
                VendorArchetype::BLACK_MARKET_SALVAGER,
                VendorArchetype::BATTLEFIELD_SCAVENGER,
            ],
            'trading_hub' => [
                VendorArchetype::COMMODITY_BROKER,
                VendorArchetype::MARKET_SHARK,
                VendorArchetype::INDUSTRIAL_CONTRACT_BUYER,
                VendorArchetype::SPECULATOR,
            ],
            'repair_yard' => [
                VendorArchetype::MASTER_ENGINEER,
                VendorArchetype::PATCHWORK_MECHANIC,
                VendorArchetype::CORPORATE_MAINTENANCE,
                VendorArchetype::BATTLEFIELD_TECHNICIAN,
            ],
            'bartender' => [
                VendorArchetype::FRIENDLY_LISTENER,
                VendorArchetype::CYNICAL_VETERAN,
                VendorArchetype::GOSSIP_MILL,
                VendorArchetype::STATION_FIXER,
            ],
            'information_broker' => [
                VendorArchetype::ANALYST,
                VendorArchetype::SHADOW_BROKER,
                VendorArchetype::ARCHIVIST,
                VendorArchetype::RUMOUR_MERCHANT,
            ],
        ];

        foreach ($categoryArchetypes as $serviceType => $archetypes) {
            foreach ($archetypes as $archetype) {
                try {
                    // Pick unique name
                    $name = null;
                    do {
                        $name = $this->vendorNameForArchetype($archetype, $usedNames);
                    } while (in_array($name, $usedNames));
                    $usedNames[] = $name;

                    $personality = $archetype->generatePersonality();

                    VendorProfile::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'name' => $name,
                        'archetype' => $archetype->value,
                        'service_type' => $serviceType,
                        'criminality' => $personality['criminality'],
                        'personality' => $personality,
                        'markup_base' => $archetype->baseMarkup(),
                    ]);

                    $totalCreated++;
                } catch (\Exception $e) {
                    $this->command->error("  Error creating vendor for {$archetype->label()}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info("✓ Total vendor persona templates created: {$totalCreated}");
    }

    private function vendorNameForArchetype(VendorArchetype $archetype, array $usedNames): string
    {
        $availableNames = array_diff(self::$vendorNames, $usedNames);
        if (empty($availableNames)) {
            return "{$archetype->label()} #" . count($usedNames);
        }
        return array_values($availableNames)[array_rand(array_values($availableNames))];
    }
}

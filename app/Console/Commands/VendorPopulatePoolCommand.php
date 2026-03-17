<?php

namespace App\Console\Commands;

use App\Enums\Vendor\VendorArchetype;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk-populate the global vendor_profiles pool.
 *
 * Two intended runs:
 *   php artisan vendor:populate-pool              → 10 000 non-bartender vendors (500/archetype × 20)
 *   php artisan vendor:populate-pool --bartenders → 10 000 bartender vendors (2 500/archetype × 4)
 *
 * Options:
 *   --bartenders      Generate bartender archetypes only (instead of all other archetypes)
 *   --per-archetype=N Override how many records to create per archetype (default auto-calculated)
 *   --clear           Truncate existing records before inserting (scoped to selected archetypes)
 *   --chunk=N         Insert chunk size (default 500, increase for faster inserts if RAM allows)
 */
class VendorPopulatePoolCommand extends Command
{
    protected $signature = 'vendor:populate-pool
                            {--bartenders : Generate bartender archetypes only}
                            {--per-archetype= : Override records per archetype}
                            {--clear : Truncate existing vendor profiles before inserting}
                            {--chunk=500 : Bulk insert chunk size}';

    protected $description = 'Bulk-populate the global vendor_profiles pool';

    // ──────────────────────────────────────────────
    //  Name building blocks
    // ──────────────────────────────────────────────

    private const PREFIXES = [
        'Black', 'Silver', 'Iron', 'Void', 'Star', 'Deep', 'Nova', 'Red', 'Crimson', 'Shadow',
        'Steel', 'Rust', 'Dark', 'Swift', 'Crystal', 'Galactic', 'Cosmic', 'Quantum', 'Blazing', 'Frozen',
        'Golden', 'Azure', 'Scarlet', 'Obsidian', 'Ember', 'Hollow', 'Brass', 'Carbon', 'Neon', 'Phantom',
    ];

    private const MIDDLES = [
        'Wolf', 'Hawk', 'Forge', 'Anvil', 'Tower', 'Gate', 'Vault', 'Chain', 'Bridge', 'Ridge',
        'Bolt', 'Dragon', 'Phoenix', 'Serpent', 'Titan', 'Atlas', 'Vega', 'Nexus', 'Apex', 'Horizon',
        'Drift', 'Pulse', 'Flux', 'Shard', 'Crest', 'Spire', 'Trench', 'Wake', 'Reach', 'Core',
    ];

    private const SUFFIXES = [
        'Trading Co.', 'Exchange', 'Industries', 'Works', 'Holdings', 'Merchants', 'Emporium',
        'Bazaar', 'Depot', 'Station', 'Post', 'Hub', 'Supply', 'Commerce', 'Trade',
        'Ventures', 'Exports', 'Collective', 'Associates', 'Services',
        'Systems', 'Logistics', 'Operations', 'Division', 'Enterprise',
        'Group', 'Network', 'Outpost', 'Procurement', 'Consortium',
    ];

    // Bartender-specific first + last names for more flavourful bartender personas
    private const FIRST_NAMES = [
        'Mira', 'Kell', 'Dax', 'Sable', 'Tor', 'Vex', 'Ryn', 'Orin', 'Zara', 'Cass',
        'Bryn', 'Jace', 'Nyx', 'Fane', 'Quill', 'Tarn', 'Sethe', 'Lyra', 'Crux', 'Brix',
        'Voss', 'Elden', 'Maris', 'Pell', 'Holt', 'Soren', 'Deva', 'Korr', 'Zell', 'Nira',
    ];

    private const LAST_NAMES = [
        'Strand', 'Korrath', 'Vance', 'Tael', 'Dorn', 'Ashfeld', 'Crowe', 'Sable', 'Voss',
        'Nighthollow', 'Ironfist', 'Graymere', 'Coldsteel', 'Blackgate', 'Starfield',
        'Hollowell', 'Driftmark', 'Voidborn', 'Ironside', 'Emberveil',
        'Crestfall', 'Nexholm', 'Torval', 'Ashcroft', 'Keldris', 'Wrenmore',
        'Dunharrow', 'Pellmere', 'Quickthorn', 'Starloch',
    ];

    private const BAR_SUFFIXES = [
        "'s Bar", "'s Tap", "'s Cantina", "'s Place", "'s Corner",
        "'s Lounge", "'s Bottle", "'s Den", "'s Rest", "'s Parlour",
    ];

    /** Bartender archetype enum cases */
    private const BARTENDER_ARCHETYPES = [
        VendorArchetype::FRIENDLY_LISTENER,
        VendorArchetype::CYNICAL_VETERAN,
        VendorArchetype::GOSSIP_MILL,
        VendorArchetype::STATION_FIXER,
    ];

    // ──────────────────────────────────────────────

    public function handle(): int
    {
        $isBartenders  = $this->option('bartenders');
        $chunkSize     = (int) $this->option('chunk');
        $shouldClear   = $this->option('clear');

        $archetypes = $isBartenders
            ? self::BARTENDER_ARCHETYPES
            : $this->nonBartenderArchetypes();

        $defaultPerArchetype = $isBartenders ? 2500 : 500;
        $perArchetype = (int) ($this->option('per-archetype') ?? $defaultPerArchetype);

        $totalExpected = count($archetypes) * $perArchetype;
        $mode          = $isBartenders ? 'bartender' : 'standard (non-bartender)';

        $this->info("Mode          : {$mode}");
        $this->info("Archetypes    : " . count($archetypes));
        $this->info("Per archetype : {$perArchetype}");
        $this->info("Total to gen  : {$totalExpected}");
        $this->info("Insert chunk  : {$chunkSize}");

        if ($shouldClear) {
            $types = collect($archetypes)->map(fn ($a) => $a->serviceType())->unique()->values()->toArray();
            DB::table('vendor_profiles')->whereIn('service_type', $types)->delete();
            $this->line("Cleared existing records for service types: " . implode(', ', $types));
        }

        $usedNames   = [];
        $nameBuilder = $isBartenders
            ? fn () => $this->bartenderName($usedNames)
            : fn () => $this->businessName($usedNames);

        $totalInserted = 0;
        $now           = now();

        foreach ($archetypes as $archetype) {
            $this->line("  Generating {$perArchetype} × {$archetype->label()} ...");

            $buffer = [];

            for ($i = 0; $i < $perArchetype; $i++) {
                $personality = $archetype->generatePersonality();
                $name        = $nameBuilder();
                $usedNames[] = $name;

                $buffer[] = [
                    'uuid'         => Str::uuid()->toString(),
                    'name'         => $name,
                    'archetype'    => $archetype->value,
                    'service_type' => $archetype->serviceType(),
                    'criminality'  => $personality['criminality'],
                    'personality'  => json_encode($personality),
                    'markup_base'  => $archetype->baseMarkup(),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];

                if (count($buffer) >= $chunkSize) {
                    DB::table('vendor_profiles')->insert($buffer);
                    $totalInserted += count($buffer);
                    $buffer = [];
                }
            }

            if (!empty($buffer)) {
                DB::table('vendor_profiles')->insert($buffer);
                $totalInserted += count($buffer);
            }
        }

        $this->info("✓ Inserted {$totalInserted} vendor profiles.");

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────
    //  Name generators
    // ──────────────────────────────────────────────

    private function businessName(array &$used): string
    {
        $attempts = 0;
        do {
            $name = self::PREFIXES[array_rand(self::PREFIXES)]
                . ' ' . self::MIDDLES[array_rand(self::MIDDLES)]
                . ' ' . self::SUFFIXES[array_rand(self::SUFFIXES)];
            $attempts++;
        } while (in_array($name, $used, true) && $attempts < 20);

        // If we somehow exhaust combinations, append a counter
        if (in_array($name, $used, true)) {
            $name .= ' #' . (count($used) + 1);
        }

        return $name;
    }

    private function bartenderName(array &$used): string
    {
        $attempts = 0;
        do {
            $first  = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last   = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $suffix = self::BAR_SUFFIXES[array_rand(self::BAR_SUFFIXES)];
            $name   = $last . $suffix . ' (run by ' . $first . ')';
            $attempts++;
        } while (in_array($name, $used, true) && $attempts < 20);

        if (in_array($name, $used, true)) {
            $name .= ' #' . (count($used) + 1);
        }

        return $name;
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function nonBartenderArchetypes(): array
    {
        $bartenderValues = array_map(fn ($a) => $a->value, self::BARTENDER_ARCHETYPES);

        return array_values(
            array_filter(
                VendorArchetype::cases(),
                fn ($a) => !in_array($a->value, $bartenderValues, true)
            )
        );
    }
}

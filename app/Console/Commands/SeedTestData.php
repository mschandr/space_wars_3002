<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use Database\Seeders\CrewMemberSeeder;
use Database\Seeders\CustomsOfficialSeeder;
use Database\Seeders\TradingPostSeeder;
use Database\Seeders\VendorProfileSeeder;
use Illuminate\Console\Command;

class SeedTestData extends Command
{
    protected $signature = 'seed:test-data {--galaxy-uuid=? : Optional existing galaxy UUID}';
    protected $description = 'Seed crew, vendor, and customs data for Phase 5-7 implementation testing';

    public function handle(): int
    {
        $this->info('Starting test data seeding...');

        // Get or create a galaxy
        $galaxyUuid = $this->option('galaxy-uuid');

        if ($galaxyUuid) {
            $galaxy = Galaxy::where('uuid', $galaxyUuid)->first();
            if (!$galaxy) {
                $this->error("Galaxy {$galaxyUuid} not found");
                return 1;
            }
            $this->info("Using existing galaxy: {$galaxy->name}");
        } else {
            // Use the first available galaxy or create a test one
            $galaxy = Galaxy::first();
            if (!$galaxy) {
                $this->error('No galaxies found. Create a galaxy first using the galaxy:initialize command.');
                return 1;
            }
            $this->info("Using first available galaxy: {$galaxy->name}");
        }

        // Verify the galaxy has POIs
        $poiCount = $galaxy->pointsOfInterest()->count();
        if ($poiCount === 0) {
            $this->error("Galaxy {$galaxy->name} has no POIs. Cannot seed data without POIs.");
            return 1;
        }
        $this->info("Galaxy has {$poiCount} points of interest");

        // Seed trading post templates (once globally)
        $this->info('');
        $this->info('--- Seeding Trading Post Templates ---');
        $seeder = new TradingPostSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Seed crew members
        $this->info('');
        $this->info('--- Seeding Crew Members ---');
        $seeder = new CrewMemberSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Seed vendor instances
        $this->info('');
        $this->info('--- Seeding Vendor Instances ---');
        $seeder = new VendorProfileSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Seed customs officials
        $this->info('');
        $this->info('--- Seeding Customs Officials ---');
        $seeder = new CustomsOfficialSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Verify data
        $this->info('');
        $this->info('=== Seeding Complete ===');

        $tradingPostCount = \App\Models\TradingPost::count();
        $crewCount = \App\Models\CrewMember::count();
        $vendorCount = \App\Models\VendorProfile::count();
        $customsCount = \App\Models\CustomsOfficial::count();

        $this->info("✓ Trading post templates: {$tradingPostCount}");
        $this->info("✓ Crew members: {$crewCount}");
        $this->info("✓ Vendor instances: {$vendorCount}");
        $this->info("✓ Customs officials: {$customsCount}");

        return 0;
    }
}

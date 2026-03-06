<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use Database\Seeders\CrewAssignmentSeeder;
use Database\Seeders\CrewMemberSeeder;
use Database\Seeders\CustomsOfficialSeeder;
use Database\Seeders\GalaxyCustomsRecordSeeder;
use Database\Seeders\GalaxyVendorStateSeeder;
use Database\Seeders\ReservePolicySeeder;
use Database\Seeders\TradingPostSeeder;
use Database\Seeders\VendorProfileSeeder;
use Illuminate\Console\Command;

class SeedTestData extends Command
{
    protected $signature = 'seed:test-data';
    protected $description = 'Seed crew, vendor, and customs data for Phase 5-7 implementation testing. Seeds globally across all galaxies.';

    public function handle(): int
    {
        $this->info('Starting test data seeding...');

        // Get the first available galaxy (for POI validation)
        $galaxy = Galaxy::first();
        if (!$galaxy) {
            $this->error('No galaxies found. Create a galaxy first using the galaxy:initialize command.');
            return 1;
        }

        // Verify at least one galaxy has POIs
        $poiCount = $galaxy->pointsOfInterest()->count();
        if ($poiCount === 0) {
            $this->error("Galaxy {$galaxy->name} has no POIs. Cannot seed data without POIs.");
            return 1;
        }
        $this->info("Seeding across {$poiCount} points of interest in {$galaxy->name} and other galaxies...");

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

        // Seed reserve policies (Phase 4)
        $this->info('');
        $this->info('--- Seeding Reserve Policies ---');
        $seeder = new ReservePolicySeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Seed galaxy-specific states (for crew, vendors, and customs)
        $this->info('');
        $this->info('--- Seeding Galaxy Crew Assignments ---');
        $seeder = new CrewAssignmentSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('');
        $this->info('--- Seeding Galaxy Vendor States ---');
        $seeder = new GalaxyVendorStateSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('');
        $this->info('--- Seeding Galaxy Customs Records ---');
        $seeder = new GalaxyCustomsRecordSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Verify data
        $this->info('');
        $this->info('=== Seeding Complete ===');

        $tradingPostCount = \App\Models\TradingPost::count();
        $crewCount = \App\Models\CrewMember::count();
        $vendorCount = \App\Models\VendorProfile::count();
        $customsCount = \App\Models\CustomsOfficial::count();
        $reservePolicyCount = \App\Models\ReservePolicy::count();
        $crewAssignmentCount = \App\Models\CrewAssignment::count();
        $vendorStateCount = \App\Models\GalaxyVendorState::count();
        $customsRecordCount = \App\Models\GalaxyCustomsRecord::count();

        $this->info('');
        $this->info('Permanent Global Templates:');
        $this->info("  ✓ Trading post templates: {$tradingPostCount}");
        $this->info("  ✓ Crew members (pool): {$crewCount}");
        $this->info("  ✓ Vendor profiles (templates): {$vendorCount}");
        $this->info("  ✓ Customs officials (templates): {$customsCount}");
        $this->info("  ✓ Reserve policies: {$reservePolicyCount}");

        $this->info('');
        $this->info('Galaxy-Specific State:');
        $this->info("  ✓ Crew assignments: {$crewAssignmentCount}");
        $this->info("  ✓ Vendor states: {$vendorStateCount}");
        $this->info("  ✓ Customs records: {$customsRecordCount}");

        return 0;
    }
}

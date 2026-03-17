<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Expands the service_type enum on vendor_profiles, galaxy_vendor_profiles,
     * and trading_posts to include repair_yard, bartender, and information_broker.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE vendor_profiles MODIFY COLUMN service_type ENUM('trading_hub','salvage_yard','shipyard','market','repair_yard','bartender','information_broker') NOT NULL");
        DB::statement("ALTER TABLE galaxy_vendor_profiles MODIFY COLUMN service_type ENUM('trading_hub','salvage_yard','shipyard','market','repair_yard','bartender','information_broker') NOT NULL");
        DB::statement("ALTER TABLE trading_posts MODIFY COLUMN service_type ENUM('trading_hub','salvage_yard','shipyard','market','repair_yard','bartender','information_broker') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * Removes any rows using the new service types, then reverts the enum to the
     * original 4 values.
     */
    public function down(): void
    {
        DB::statement("DELETE FROM vendor_profiles WHERE service_type IN ('repair_yard','bartender','information_broker')");
        DB::statement("DELETE FROM galaxy_vendor_profiles WHERE service_type IN ('repair_yard','bartender','information_broker')");
        DB::statement("DELETE FROM trading_posts WHERE service_type IN ('repair_yard','bartender','information_broker')");

        DB::statement("ALTER TABLE vendor_profiles MODIFY COLUMN service_type ENUM('trading_hub','salvage_yard','shipyard','market') NOT NULL");
        DB::statement("ALTER TABLE galaxy_vendor_profiles MODIFY COLUMN service_type ENUM('trading_hub','salvage_yard','shipyard','market') NOT NULL");
        DB::statement("ALTER TABLE trading_posts MODIFY COLUMN service_type ENUM('trading_hub','salvage_yard','shipyard','market') NOT NULL");
    }
};

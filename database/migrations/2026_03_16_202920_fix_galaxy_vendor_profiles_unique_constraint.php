<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galaxy_vendor_profiles', function (Blueprint $table) {
            $table->dropUnique(['galaxy_id', 'poi_id']);
            $table->unique(['galaxy_id', 'poi_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::table('galaxy_vendor_profiles', function (Blueprint $table) {
            $table->dropUnique(['galaxy_id', 'poi_id', 'service_type']);
            $table->unique(['galaxy_id', 'poi_id']);
        });
    }
};

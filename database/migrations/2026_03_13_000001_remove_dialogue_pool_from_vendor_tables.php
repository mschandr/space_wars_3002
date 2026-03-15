<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropColumn('dialogue_pool');
        });

        Schema::table('trading_posts', function (Blueprint $table) {
            $table->dropColumn('dialogue_pool');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->json('dialogue_pool')->nullable();
        });

        Schema::table('trading_posts', function (Blueprint $table) {
            $table->json('dialogue_pool')->nullable();
        });
    }
};

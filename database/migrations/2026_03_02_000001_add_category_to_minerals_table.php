<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('minerals', function (Blueprint $table) {
            $table->enum('category', ['civilian', 'industrial', 'black'])->default('civilian')->after('rarity');
            $table->boolean('is_illegal')->default(false)->after('category');
            $table->integer('min_reputation')->nullable()->after('is_illegal');
            $table->integer('min_sector_security')->nullable()->after('min_reputation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('minerals', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_illegal', 'min_reputation', 'min_sector_security']);
        });
    }
};

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
        Schema::table('combat_participants', function (Blueprint $table) {
            $table->string('result')->nullable()->after('survived'); // victory, defeat, draw
            $table->index('result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('combat_participants', function (Blueprint $table) {
            $table->dropIndex(['result']);
            $table->dropColumn('result');
        });
    }
};

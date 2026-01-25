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
        Schema::table('warp_gates', function (Blueprint $table) {
            // Activation requirements for dormant gates
            // JSON: {type: 'sensor_level'|'item'|'credits', value: int|string, description: string}
            $table->json('activation_requirements')->nullable()->after('gate_type');

            // Track which players have discovered this gate
            // JSON array of player IDs
            $table->json('discovered_by')->nullable()->after('activation_requirements');

            // Timestamp when gate was activated
            $table->timestamp('activated_at')->nullable()->after('discovered_by');

            // Index for status-based queries
            $table->index(['galaxy_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warp_gates', function (Blueprint $table) {
            $table->dropIndex(['galaxy_id', 'status']);
            $table->dropColumn([
                'activation_requirements',
                'discovered_by',
                'activated_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Galaxy-specific crew assignments: tracks which crew are stationed at which trading hub
     * This allows the same crew pool to be reused across galaxies with different assignments
     */
    public function up(): void
    {
        Schema::create('crew_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_member_id')->constrained('crew_members')->cascadeOnDelete();
            $table->foreignId('trading_hub_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint: one assignment per crew per galaxy
            $table->unique(['galaxy_id', 'crew_member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crew_assignments');
    }
};

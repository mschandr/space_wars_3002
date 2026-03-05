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
        Schema::create('customs_officials', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('poi_id')->unique()->constrained('points_of_interest')->cascadeOnDelete();

            // Officer personality
            $table->string('name', 100);
            $table->unsignedDecimal('honesty', 3, 2);        // 0.0=corrupt, 1.0=incorruptible
            $table->unsignedDecimal('severity', 3, 2);       // 0.0=lenient, 1.0=maximum enforcement
            $table->unsignedInteger('bribe_threshold');      // Minimum credits to attempt bribe

            // Search capability
            $table->unsignedDecimal('detection_skill', 3, 2); // How well they find hidden cargo

            $table->timestamps();

            // Index
            $table->index('poi_id');
            $table->index('honesty');
            $table->index('severity');
        });

        // Add DB-level CHECK constraints for data integrity
        \DB::statement('ALTER TABLE customs_officials ADD CONSTRAINT honesty_range CHECK (honesty >= 0 AND honesty <= 1)');
        \DB::statement('ALTER TABLE customs_officials ADD CONSTRAINT severity_range CHECK (severity >= 0 AND severity <= 1)');
        \DB::statement('ALTER TABLE customs_officials ADD CONSTRAINT detection_skill_range CHECK (detection_skill >= 0 AND detection_skill <= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop constraints before dropping table (for cleaner rollback)
        \DB::statement('ALTER TABLE customs_officials DROP CONSTRAINT IF EXISTS honesty_range');
        \DB::statement('ALTER TABLE customs_officials DROP CONSTRAINT IF EXISTS severity_range');
        \DB::statement('ALTER TABLE customs_officials DROP CONSTRAINT IF EXISTS detection_skill_range');

        Schema::dropIfExists('customs_officials');
    }
};

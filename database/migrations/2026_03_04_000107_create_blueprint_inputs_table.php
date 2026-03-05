<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprint_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->unsignedDecimal('qty_required', 12, 4); // Units needed

            $table->timestamps();

            // Unique constraint: one requirement per blueprint+commodity
            $table->unique(['blueprint_id', 'commodity_id']);
        });

        // Add DB-level CHECK constraint for data integrity
        \DB::statement('ALTER TABLE blueprint_inputs ADD CONSTRAINT qty_required_positive CHECK (qty_required >= 0)');
    }

    public function down(): void
    {
        // Drop constraint before dropping table (for cleaner rollback)
        \DB::statement('ALTER TABLE blueprint_inputs DROP CONSTRAINT IF EXISTS qty_required_positive');

        Schema::dropIfExists('blueprint_inputs');
    }
};

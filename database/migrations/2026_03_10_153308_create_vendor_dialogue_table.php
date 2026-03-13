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
        Schema::create('vendor_dialogue', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('vendor_profile_id');

            $table->enum('line_type', [
                'greeting',
                'inventory_pitch',
                'deal_accepted',
                'deal_rejected',
                'farewell',
            ]);

            $table->enum('interaction_bucket', [
                'first_visit',
                'second_visit',
                'third_visit',
                'repeat_customer',
            ]);

            $table->string('inventory_context', 64)->nullable();

            $table->string('line_text', 255);

            $table->decimal('weight', 5, 4)->default(1.0000);

            $table->unsignedInteger('generation_version')->default(1);

            $table->timestamps();

            // Indexes
            $table->index(['vendor_profile_id', 'line_type'], 'idx_vendor_line_type');
            $table->index(['vendor_profile_id', 'line_type', 'interaction_bucket'], 'idx_vendor_lookup');
            $table->index(['vendor_profile_id', 'inventory_context'], 'idx_inventory_context');

            // Foreign key
            $table->foreign('vendor_profile_id')
                ->references('id')
                ->on('vendor_profiles')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_dialogue');
    }
};

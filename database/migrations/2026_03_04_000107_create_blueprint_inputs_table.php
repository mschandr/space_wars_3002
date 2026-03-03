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
            $table->decimal('qty_required', 12, 4); // Units needed

            $table->timestamps();

            // Unique constraint: one requirement per blueprint+commodity
            $table->unique(['blueprint_id', 'commodity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_inputs');
    }
};

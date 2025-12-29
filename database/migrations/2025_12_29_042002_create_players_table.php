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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('call_sign')->unique();
            $table->decimal('credits', 15, 2)->default(1000.00);
            $table->integer('experience')->default(0);
            $table->integer('level')->default(1);
            $table->foreignId('current_poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null');
            $table->string('status')->default('active'); // active, docked, destroyed, etc.
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};

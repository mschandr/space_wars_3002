<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Precursor ship rumor fields to trading hubs.
     *
     * Every ship yard has heard rumors about where the legendary Precursor ship is hidden.
     * Every ship yard thinks they know where it is.
     * Every ship yard is wrong.
     *
     * Players can bribe the ship yard owner to get their (incorrect) location.
     */
    public function up(): void
    {
        Schema::table('trading_hubs', function (Blueprint $table) {
            // The rumored (wrong) location this ship yard believes the Precursor ship is at
            $table->integer('precursor_rumor_x')->nullable()->after('attributes');
            $table->integer('precursor_rumor_y')->nullable()->after('precursor_rumor_x');

            // The ship yard's confidence in their rumor (affects bribe cost)
            $table->decimal('precursor_rumor_confidence', 3, 2)->default(0.50)->after('precursor_rumor_y');

            // The bribe amount required to get the rumor
            $table->integer('precursor_bribe_cost')->default(10000)->after('precursor_rumor_confidence');

            // The ship yard owner's name (for flavor)
            $table->string('shipyard_owner_name')->nullable()->after('precursor_bribe_cost');

            // Flavor text for the rumor
            $table->text('precursor_rumor_flavor')->nullable()->after('shipyard_owner_name');
        });

        // Track which players have received which rumors (to avoid paying twice)
        Schema::create('player_precursor_rumors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('trading_hub_id')->constrained()->onDelete('cascade');
            $table->integer('rumor_x');
            $table->integer('rumor_y');
            $table->integer('bribe_paid');
            $table->timestamps();

            $table->unique(['player_id', 'trading_hub_id'], 'player_hub_rumor_unique');
            $table->index('player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_precursor_rumors');

        Schema::table('trading_hubs', function (Blueprint $table) {
            $table->dropColumn([
                'precursor_rumor_x',
                'precursor_rumor_y',
                'precursor_rumor_confidence',
                'precursor_bribe_cost',
                'shipyard_owner_name',
                'precursor_rumor_flavor',
            ]);
        });
    }
};

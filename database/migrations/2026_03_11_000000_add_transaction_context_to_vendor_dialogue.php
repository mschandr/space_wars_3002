<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingColumns = Schema::getColumnListing('vendor_dialogue');

        $existingIndexes = collect(DB::select('SHOW INDEX FROM vendor_dialogue'))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        $hasFk = str_contains(
            DB::select('SHOW CREATE TABLE vendor_dialogue')[0]->{'Create Table'},
            'FOREIGN KEY'
        );

        // Fix inventory_context nulls before NOT NULL change
        DB::table('vendor_dialogue')->whereNull('inventory_context')->update(['inventory_context' => 'none']);

        // Drop FK before touching indexes (MariaDB won't drop indexes used by FKs)
        if ($hasFk) {
            Schema::table('vendor_dialogue', function (Blueprint $table) {
                $table->dropForeign(['galaxy_vendor_profile_id']);
            });
        }

        Schema::table('vendor_dialogue', function (Blueprint $table) use ($existingColumns, $existingIndexes) {
            $table->string('inventory_context', 64)->default('none')->nullable(false)->change();

            if (!in_array('transaction_context', $existingColumns)) {
                $table->enum('transaction_context', ['neutral', 'vendor_selling', 'vendor_buying'])
                    ->default('neutral')
                    ->after('interaction_bucket');
            }

            foreach (['idx_vendor_line_type', 'idx_vendor_lookup', 'idx_inventory_context'] as $idx) {
                if (in_array($idx, $existingIndexes)) {
                    $table->dropIndex($idx);
                }
            }

            $table->index(['galaxy_vendor_profile_id', 'line_type'], 'idx_vendor_line_type');
            $table->index(
                ['galaxy_vendor_profile_id', 'line_type', 'interaction_bucket', 'transaction_context', 'inventory_context'],
                'idx_vendor_lookup'
            );
            $table->index(
                ['galaxy_vendor_profile_id', 'transaction_context', 'inventory_context'],
                'idx_vendor_context'
            );

            $table->foreign('galaxy_vendor_profile_id')
                ->references('id')
                ->on('galaxy_vendor_profiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_dialogue', function (Blueprint $table) {
            $table->dropIndex('idx_vendor_line_type');
            $table->dropIndex('idx_vendor_lookup');
            $table->dropIndex('idx_vendor_context');

            $table->dropColumn('transaction_context');

            $table->string('inventory_context', 64)->nullable()->change();

            $table->index(['galaxy_vendor_profile_id', 'line_type'], 'idx_vendor_line_type');
            $table->index(['galaxy_vendor_profile_id', 'line_type', 'interaction_bucket'], 'idx_vendor_lookup');
            $table->index(['galaxy_vendor_profile_id', 'inventory_context'], 'idx_inventory_context');
        });
    }
};

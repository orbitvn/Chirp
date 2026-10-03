<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-council support: every RAMM-derived table (and the FWP edits) gets a
 * council slug. Existing rows are all Hastings. road_id / treatment code are
 * only unique within one RAMM database, so keys become (council, ...).
 */
return new class extends Migration
{
    private array $tables = [
        'ramm_roads', 'ramm_surfacings', 'ramm_treatment_lengths',
        'ramm_fwp', 'ramm_fwp_treatments', 'fwp_overrides',
    ];

    public function up(): void
    {
        // Backfill existing rows as Hastings, then drop the default so any
        // insert that forgets the council fails loudly instead of mislabelling.
        foreach ($this->tables as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->string('council', 32)->default('hastings')->first());
            Schema::table($t, fn (Blueprint $table) => $table->string('council', 32)->change());
        }

        Schema::table('ramm_roads', fn (Blueprint $table) => $table->dropPrimary());
        Schema::table('ramm_roads', fn (Blueprint $table) => $table->primary(['council', 'road_id']));

        Schema::table('ramm_fwp_treatments', fn (Blueprint $table) => $table->dropPrimary());
        Schema::table('ramm_fwp_treatments', fn (Blueprint $table) => $table->primary(['council', 'code']));

        foreach (['ramm_surfacings', 'ramm_treatment_lengths', 'ramm_fwp'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->index(['council', 'road_id']));
        }

        Schema::table('fwp_overrides', function (Blueprint $table) {
            $table->dropUnique(['road_id', 'treat_length_id']);
            $table->unique(['council', 'road_id', 'treat_length_id']);
        });
    }

    public function down(): void
    {
        // Only safe while a single council's data is stored.
        Schema::table('fwp_overrides', function (Blueprint $table) {
            $table->dropUnique(['council', 'road_id', 'treat_length_id']);
            $table->unique(['road_id', 'treat_length_id']);
        });

        foreach (['ramm_surfacings', 'ramm_treatment_lengths', 'ramm_fwp'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropIndex(['council', 'road_id']));
        }

        Schema::table('ramm_fwp_treatments', fn (Blueprint $table) => $table->dropPrimary());
        Schema::table('ramm_fwp_treatments', fn (Blueprint $table) => $table->primary('code'));

        Schema::table('ramm_roads', fn (Blueprint $table) => $table->dropPrimary());
        Schema::table('ramm_roads', fn (Blueprint $table) => $table->primary('road_id'));

        foreach ($this->tables as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('council'));
        }
    }
};

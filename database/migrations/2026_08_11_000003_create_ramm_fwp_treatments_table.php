<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The FWP treatment vocabulary (RAMM ud_fwp_treatments) — the list the
        // drive-mode "cycle treatment" button steps through, plus rates for cost.
        Schema::create('ramm_fwp_treatments', function (Blueprint $table) {
            $table->string('code')->primary();     // description, e.g. AWPT, CS, TAC
            $table->string('category')->nullable(); // RHAB, RS ...
            $table->string('asset_type')->nullable(); // Pavement, Surface
            $table->double('ra1_rate')->nullable();
            $table->double('ra2_rate')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('seq')->nullable(); // display_sequence
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramm_fwp_treatments');
    }
};

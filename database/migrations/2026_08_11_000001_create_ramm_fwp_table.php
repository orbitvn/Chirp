<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RAMM's baseline Forward Works Programme, one row per programmed segment
        // (source: ud_fwp_works). This is read-only reference data; user edits live
        // in fwp_overrides.
        Schema::create('ramm_fwp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('road_id')->index();
            $table->unsignedBigInteger('treat_length_id')->nullable()->index();
            $table->unsignedBigInteger('works_id')->nullable();   // ud_fwp_works.system_id
            $table->double('start_m')->nullable();                // road RP
            $table->double('end_m')->nullable();
            $table->string('treatment_id')->nullable();           // code, e.g. TAC, CS
            $table->string('treatment')->nullable();              // display name (= code)
            $table->string('category')->nullable();               // e.g. RS, RHAB
            $table->string('fw_year')->nullable();                // financial year "2026/27"
            $table->unsignedSmallInteger('year_start')->nullable(); // 2026 (for sort/shift)
            $table->text('reason')->nullable();   // RAMM descriptions can be long
            $table->double('rank_score')->nullable();
            $table->double('cost')->nullable();
            $table->double('coverage_pct')->nullable();
            $table->boolean('locked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramm_fwp');
    }
};

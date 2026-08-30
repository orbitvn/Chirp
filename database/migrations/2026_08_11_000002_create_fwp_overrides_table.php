<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User edits to the Forward Works Programme made in the field. Never written
        // back to RAMM automatically — reviewed and exported for a deliberate update.
        Schema::create('fwp_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('road_id')->index();
            $table->unsignedBigInteger('treat_length_id')->nullable()->index();
            $table->double('start_m')->nullable();   // used when there is no RAMM segment
            $table->double('end_m')->nullable();

            // Snapshot of the RAMM baseline at the time of the edit (for diffing/review).
            $table->string('base_year')->nullable();
            $table->string('base_treatment')->nullable();

            // The adjusted values.
            $table->string('year')->nullable();          // "2028/29"
            $table->unsignedSmallInteger('year_start')->nullable();
            $table->string('treatment_id')->nullable();  // code
            $table->string('treatment')->nullable();

            $table->string('note')->nullable();
            $table->string('user')->nullable();
            $table->timestamp('synced_at')->nullable();  // set when pushed to RAMM
            $table->timestamps();

            // One active override per RAMM segment; from-scratch (null tl) keyed by RP in app.
            $table->unique(['road_id', 'treat_length_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fwp_overrides');
    }
};

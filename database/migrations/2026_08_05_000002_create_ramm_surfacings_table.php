<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ramm_surfacings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('road_id')->index();
            $table->double('start_m')->nullable();       // road RP (m)
            $table->double('end_m')->nullable();
            $table->double('surf_offset')->nullable();   // 0 = main carriageway
            $table->date('surface_date')->nullable();
            $table->string('surf_material')->nullable(); // expanded name, e.g. "Two Coat Seal"
            $table->string('surf_function')->nullable(); // expanded name, e.g. "Reseal"
            $table->string('chip_size', 20)->nullable();
            $table->string('chip_2nd_size', 20)->nullable();
            $table->double('surf_width')->nullable();
            $table->integer('life')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramm_surfacings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ramm_treatment_lengths', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('road_id')->index();
            $table->unsignedBigInteger('tl_id')->nullable(); // treat_length_id
            $table->string('tl_name')->nullable();
            $table->double('start_m')->nullable();           // road RP (m)
            $table->double('end_m')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramm_treatment_lengths');
    }
};

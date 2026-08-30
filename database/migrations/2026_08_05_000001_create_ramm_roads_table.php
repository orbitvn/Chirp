<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ramm_roads', function (Blueprint $table) {
            $table->unsignedBigInteger('road_id')->primary();  // RAMM road number
            $table->string('road_name')->nullable();
            $table->double('total_rp')->nullable();            // max carriageway end RP (m)
            $table->json('line');                              // RP-ordered [[lng,lat], ...]
            $table->json('sections')->nullable();              // carriageway sections meta
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramm_roads');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Long RAMM trt_reason descriptions overflow VARCHAR(255) — widen to TEXT.
    public function up(): void
    {
        Schema::table('ramm_fwp', function (Blueprint $table) {
            $table->text('reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ramm_fwp', function (Blueprint $table) {
            $table->string('reason')->nullable()->change();
        });
    }
};

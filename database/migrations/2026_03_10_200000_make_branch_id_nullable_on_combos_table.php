<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite que un combo esté disponible en todas las sucursales (branch_id = null).
     */
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->unsignedInteger('branch_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->unsignedInteger('branch_id')->nullable(false)->change();
        });
    }
};

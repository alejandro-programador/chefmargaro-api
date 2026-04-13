<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Rolls mixtos: combo normal vs combo con topping.
     */
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            if (! Schema::hasColumn('combos', 'has_topping')) {
                $table->boolean('has_topping')->default(false)->after('includes_drink');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            if (Schema::hasColumn('combos', 'has_topping')) {
                $table->dropColumn('has_topping');
            }
        });
    }
};

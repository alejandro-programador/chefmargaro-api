<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reemplaza price_usd por price_eur y elimina price_bs en products, combos y extras.
     */
    public function up(): void
    {
        foreach (['products', 'combos', 'extras'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'price_usd')) {
                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE `{$table}` CHANGE `price_usd` `price_eur` DECIMAL(10,2) NOT NULL");
                } else {
                    Schema::table($table, function (Blueprint $t) {
                        $t->renameColumn('price_usd', 'price_eur');
                    });
                }
            }
            if (Schema::hasColumn($table, 'price_bs')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('price_bs');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['products', 'combos', 'extras'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'price_eur')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('price_bs', 10, 2)->default(0)->after('price_eur');
                });
                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE `{$table}` CHANGE `price_eur` `price_usd` DECIMAL(10,2) NOT NULL");
                } else {
                    Schema::table($table, function (Blueprint $t) {
                        $t->renameColumn('price_eur', 'price_usd');
                    });
                }
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extras', function (Blueprint $table) {
            if (! Schema::hasColumn('extras', 'xetux_product_id')) {
                $table->unsignedInteger('xetux_product_id')->nullable()->after('branch_id');
                $table->unsignedInteger('xetux_item_id')->nullable()->after('xetux_product_id');
                $table->unsignedSmallInteger('xetux_family_id')->nullable()->after('xetux_item_id');
                $table->unique('xetux_product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('extras', function (Blueprint $table) {
            if (Schema::hasColumn('extras', 'xetux_product_id')) {
                $table->dropUnique(['xetux_product_id']);
                $table->dropColumn(['xetux_product_id', 'xetux_item_id', 'xetux_family_id']);
            }
        });
    }
};

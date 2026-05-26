<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'xetux_order_id')) {
                $table->unsignedBigInteger('xetux_order_id')->nullable()->after('tracking_token');
                $table->string('xetux_tracking_number', 50)->nullable()->after('xetux_order_id');
                $table->text('notes')->nullable()->after('delivery_type');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'extra_id')) {
                $table->unsignedBigInteger('extra_id')->nullable()->after('combo_id');
            }
        });

        if (
            Schema::hasColumn('order_items', 'extra_id')
            && ! $this->foreignKeyExists('order_items', 'order_items_extra_id_foreign')
        ) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('extra_id')->references('extra_id')->on('extras')->nullOnDelete();
            });
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $db = Schema::getConnection()->getDatabaseName();

        return (bool) DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$db, $table, $name, 'FOREIGN KEY']
        );
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'extra_id')) {
                $table->dropForeign(['extra_id']);
                $table->dropColumn('extra_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'xetux_order_id')) {
                $table->dropColumn(['xetux_order_id', 'xetux_tracking_number', 'notes']);
            }
        });
    }
};

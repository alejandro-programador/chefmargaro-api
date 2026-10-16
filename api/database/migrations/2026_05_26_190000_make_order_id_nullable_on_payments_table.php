<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('fk_payment_order');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY order_id INT(11) NULL');
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedInteger('order_id')->nullable()->change();
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('order_id', 'fk_payment_order')
                ->references('order_id')
                ->on('orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('fk_payment_order');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY order_id INT(11) NOT NULL');
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedInteger('order_id')->nullable(false)->change();
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('order_id', 'fk_payment_order')
                ->references('order_id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }
};

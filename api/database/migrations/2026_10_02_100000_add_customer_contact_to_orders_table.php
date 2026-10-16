<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_phone')) {
                $table->string('customer_phone', 30)->nullable();
            }
            if (! Schema::hasColumn('orders', 'customer_cedula')) {
                $table->string('customer_cedula', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'customer_phone')) {
                $table->dropColumn('customer_phone');
            }
            if (Schema::hasColumn('orders', 'customer_cedula')) {
                $table->dropColumn('customer_cedula');
            }
        });
    }
};

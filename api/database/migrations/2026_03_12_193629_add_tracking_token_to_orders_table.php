<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds tracking_token field to orders table for unique order tracking links.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'tracking_token')) {
                $table->string('tracking_token', 64)->nullable()->unique()->after('order_status');
                $table->index('tracking_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'tracking_token')) {
                $table->dropIndex(['tracking_token']);
                $table->dropColumn('tracking_token');
            }
        });
    }
};

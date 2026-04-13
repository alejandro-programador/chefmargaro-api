<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Assigns each payment to a branch for filtering (set from order on create).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'branch_id')) {
                $table->unsignedInteger('branch_id')->nullable()->after('order_id');
                $table->index('branch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'branch_id')) {
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};

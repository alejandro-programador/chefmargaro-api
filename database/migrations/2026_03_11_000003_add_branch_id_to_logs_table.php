<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Assigns each log to a branch for filtering (set from X-Branch-Id or user on create).
     */
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            if (! Schema::hasColumn('logs', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('user_id');
                $table->index('branch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            if (Schema::hasColumn('logs', 'branch_id')) {
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'id') && ! Schema::hasColumn('users', 'user_id')) {
            DB::statement('ALTER TABLE users CHANGE id user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'user_id') && ! Schema::hasColumn('users', 'id')) {
            DB::statement('ALTER TABLE users CHANGE user_id id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('extras')) {
            return;
        }

        Schema::table('extras', function (Blueprint $table) {
            if (! Schema::hasColumn('extras', 'image_url')) {
                $table->string('image_url')->nullable()->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('extras')) {
            return;
        }

        Schema::table('extras', function (Blueprint $table) {
            if (Schema::hasColumn('extras', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};

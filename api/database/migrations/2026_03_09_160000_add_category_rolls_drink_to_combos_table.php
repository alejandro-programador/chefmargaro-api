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
        Schema::table('combos', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('description'); // 'rolls-mixtos' | 'pollo-crispy'
            $table->unsignedTinyInteger('rolls_count')->nullable()->after('category');
            $table->boolean('includes_drink')->default(false)->after('rolls_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn(['category', 'rolls_count', 'includes_drink']);
        });
    }
};

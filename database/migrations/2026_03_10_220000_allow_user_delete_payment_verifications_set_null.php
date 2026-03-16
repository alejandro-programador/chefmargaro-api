<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * When a user is deleted, payment_verifications.verifier_id is set to NULL
     * so the verification record is kept for audit.
     */
    public function up(): void
    {
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->dropForeign('fk_verification_user');
        });

        DB::statement('ALTER TABLE payment_verifications MODIFY verifier_id INT NULL');

        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->foreign('verifier_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->dropForeign(['verifier_id']);
        });

        DB::statement('ALTER TABLE payment_verifications MODIFY verifier_id INT NOT NULL');

        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->foreign('verifier_id', 'fk_verification_user')
                ->references('user_id')
                ->on('users')
                ->onDelete('restrict');
        });
    }
};

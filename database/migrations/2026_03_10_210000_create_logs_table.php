<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE logs (log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, action_description VARCHAR(500) NOT NULL, timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, INDEX idx_timestamp (timestamp), INDEX idx_user_id (user_id), CONSTRAINT logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};

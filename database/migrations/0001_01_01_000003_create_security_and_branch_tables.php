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
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->increments('branch_id');
                $table->string('name');
                $table->string('address')->nullable();
                $table->string('phone', 50)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->increments('role_id');
                $table->string('role_name')->unique();
                $table->string('description')->nullable();
                $table->text('permissions')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->increments('permission_id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('role_id');
                $table->unsignedInteger('permission_id');
                $table->timestamps();

                $table->unique(['role_id', 'permission_id']);
                $table->foreign('role_id')->references('role_id')->on('user_roles')->cascadeOnDelete();
                $table->foreign('permission_id')->references('permission_id')->on('permissions')->cascadeOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'user_id') && Schema::hasColumn('users', 'id')) {
                $table->renameColumn('id', 'user_id');
            }

            if (! Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 50)->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'role_id')) {
                $table->unsignedInteger('role_id')->nullable()->after('user_type');
            }

            if (! Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable()->after('remember_token');
            }
        });

        if (Schema::hasColumn('users', 'role_id')) {
            DB::statement('ALTER TABLE `users` MODIFY `role_id` INT(11) NULL');

            DB::statement(
                'UPDATE `users` AS u
                 LEFT JOIN `user_roles` AS r ON u.`role_id` = r.`role_id`
                 SET u.`role_id` = NULL
                 WHERE u.`role_id` IS NOT NULL AND r.`role_id` IS NULL'
            );
        }

        if (
            Schema::hasColumn('users', 'role_id')
            && Schema::hasTable('user_roles')
            && ! $this->foreignKeyExists('users', 'role_id')
        ) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('role_id')->references('role_id')->on('user_roles')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('user_branch_access')) {
            Schema::create('user_branch_access', function (Blueprint $table) {
                $table->increments('access_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('branch_id');
                $table->timestamps();

                $table->unique(['user_id', 'branch_id']);
                $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
                $table->foreign('branch_id')->references('branch_id')->on('branches')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id') && $this->foreignKeyExists('users', 'role_id')) {
                $table->dropForeign(['role_id']);
            }

            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropColumn('role_id');
            }

            if (Schema::hasColumn('users', 'last_login')) {
                $table->dropColumn('last_login');
            }

            if (Schema::hasColumn('users', 'password_hash')) {
                $table->dropColumn('password_hash');
            }

            if (Schema::hasColumn('users', 'user_type')) {
                $table->dropColumn('user_type');
            }
        });

        Schema::dropIfExists('user_branch_access');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('branches');
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$table, $column]
        ) !== null;
    }
};

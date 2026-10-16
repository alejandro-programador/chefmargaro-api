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
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->increments('customer_id');
                $table->string('email')->unique();
                $table->string('name');
                $table->unsignedInteger('branch_id');
                $table->date('signup_date')->nullable();
                $table->timestamps();

                $table->foreign('branch_id')->references('branch_id')->on('branches')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->increments('order_id');
                $table->unsignedInteger('customer_id');
                $table->dateTime('order_date')->nullable();
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->string('payment_status', 50)->default('pending');
                $table->string('delivery_type', 50)->nullable();
                $table->string('order_status', 50)->default('pending');
                $table->timestamps();

                $table->foreign('customer_id')->references('customer_id')->on('customers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->increments('order_item_id');
                $table->unsignedInteger('order_id');
                $table->unsignedInteger('product_id')->nullable();
                $table->unsignedInteger('combo_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->timestamps();

                $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
                $table->foreign('product_id')->references('product_id')->on('products')->nullOnDelete();
                $table->foreign('combo_id')->references('combo_id')->on('combos')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->increments('payment_id');
                $table->unsignedInteger('order_id');
                $table->string('payment_method', 50);
                $table->string('payment_status', 50)->default('pending');
                $table->dateTime('payment_date')->nullable();
                $table->string('proof_image_url')->nullable();
                $table->timestamps();

                $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payment_verifications')) {
            Schema::create('payment_verifications', function (Blueprint $table) {
                $table->increments('verification_id');
                $table->unsignedInteger('payment_id');
                $table->unsignedBigInteger('verifier_id');
                $table->string('verification_status', 50)->default('pending');
                $table->dateTime('verification_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('payment_id')->references('payment_id')->on('payments')->cascadeOnDelete();
                $table->foreign('verifier_id', 'fk_verification_user')
                    ->references('user_id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_verifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};

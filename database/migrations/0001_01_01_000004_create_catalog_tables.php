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
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->increments('product_id');
                $table->unsignedInteger('branch_id');
                $table->string('name');
                $table->decimal('price_eur', 10, 2);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('branch_id')->references('branch_id')->on('branches')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('combos')) {
            Schema::create('combos', function (Blueprint $table) {
                $table->increments('combo_id');
                $table->unsignedInteger('branch_id');
                $table->string('name');
                $table->decimal('price_eur', 10, 2);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('branch_id')->references('branch_id')->on('branches')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('extras')) {
            Schema::create('extras', function (Blueprint $table) {
                $table->increments('extra_id');
                $table->unsignedInteger('branch_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('price_eur', 10, 2);
                $table->integer('quantity')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('branch_id')->references('branch_id')->on('branches')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('product_extra')) {
            Schema::create('product_extra', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('extra_id');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'extra_id']);
                $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
                $table->foreign('extra_id')->references('extra_id')->on('extras')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('combo_extra')) {
            Schema::create('combo_extra', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('combo_id');
                $table->unsignedInteger('extra_id');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['combo_id', 'extra_id']);
                $table->foreign('combo_id')->references('combo_id')->on('combos')->cascadeOnDelete();
                $table->foreign('extra_id')->references('extra_id')->on('extras')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combo_extra');
        Schema::dropIfExists('product_extra');
        Schema::dropIfExists('extras');
        Schema::dropIfExists('combos');
        Schema::dropIfExists('products');
    }
};

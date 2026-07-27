<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Productos incluidos en combos (sin costo adicional), distintos de los extras de pago.
     * Ejemplo: 4 salsas a elegir + 1 refresco 1.5L a elegir sabor.
     */
    public function up(): void
    {
        if (! Schema::hasTable('combo_included_groups')) {
            Schema::create('combo_included_groups', function (Blueprint $table) {
                $table->increments('id');
                // Debe coincidir con combos.combo_id (int signed en la BD real)
                $table->integer('combo_id');
                $table->string('type', 20); // sauce | drink
                $table->string('name', 100);
                $table->unsignedInteger('max_quantity')->default(1);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('combo_id')
                    ->references('combo_id')
                    ->on('combos')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('combo_included_products')) {
            Schema::create('combo_included_products', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('group_id');
                $table->unsignedInteger('xetux_product_id');
                $table->unsignedInteger('xetux_item_id');
                $table->unsignedInteger('xetux_family_id');
                $table->string('product_name', 255);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['group_id', 'xetux_product_id']);
                $table->foreign('group_id')
                    ->references('id')
                    ->on('combo_included_groups')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combo_included_products');
        Schema::dropIfExists('combo_included_groups');
    }
};

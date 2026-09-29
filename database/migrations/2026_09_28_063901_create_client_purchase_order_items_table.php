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
        Schema::create('client_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_id')->nullable()->constrained('product_services')->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 50);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['client_purchase_order_id', 'sort_order'], 'cpo_items_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_purchase_order_items');
    }
};

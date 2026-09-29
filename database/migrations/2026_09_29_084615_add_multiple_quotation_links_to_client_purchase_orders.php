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
        Schema::create('client_purchase_order_quotation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_purchase_order_id', 'quotation_id'], 'cpo_quotation_unique');
        });

        Schema::table('client_purchase_order_items', function (Blueprint $table) {
            $table->foreignId('quotation_item_id')
                ->nullable()
                ->after('product_service_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_item_id');
        });

        Schema::dropIfExists('client_purchase_order_quotation');
    }
};

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
        Schema::create('product_services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type')->index();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->string('unit', 50);
            $table->decimal('default_price', 15, 2)->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['status', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_services');
    }
};

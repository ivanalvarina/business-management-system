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
        Schema::table('product_services', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->after('default_price');
            $table->string('public_token', 64)->nullable()->unique()->after('status');
            $table->boolean('is_public')->default(false)->index()->after('public_token');
        });

        DB::table('product_services')
            ->select(['id'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $productService): void {
                DB::table('product_services')
                    ->where('id', $productService->id)
                    ->update(['public_token' => bin2hex(random_bytes(32))]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_services', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['quantity', 'public_token', 'is_public']);
        });
    }
};

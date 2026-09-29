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
        Schema::table('client_purchase_orders', function (Blueprint $table) {
            $table->timestamp('fulfilled_at')->nullable()->after('received_at');
            $table->timestamp('cancelled_at')->nullable()->after('fulfilled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['fulfilled_at', 'cancelled_at']);
        });
    }
};

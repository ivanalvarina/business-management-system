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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('purchasing_assistant_name')->nullable()->after('logo');
            $table->string('corporate_sales_manager_name')->nullable()->after('purchasing_assistant_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'purchasing_assistant_name',
                'corporate_sales_manager_name',
            ]);
        });
    }
};

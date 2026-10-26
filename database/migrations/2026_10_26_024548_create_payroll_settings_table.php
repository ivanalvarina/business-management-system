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
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('hours_per_day', 5, 2)->default(8);
            $table->decimal('annual_workdays_divisor', 6, 2)->default(261);
            $table->decimal('regular_ot_multiplier', 5, 2)->default(1.25);
            $table->decimal('special_holiday_multiplier', 5, 2)->default(1.30);
            $table->decimal('special_holiday_ot_multiplier', 5, 2)->default(1.69);
            $table->decimal('regular_holiday_multiplier', 5, 2)->default(2.00);
            $table->decimal('regular_holiday_ot_multiplier', 5, 2)->default(2.60);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};

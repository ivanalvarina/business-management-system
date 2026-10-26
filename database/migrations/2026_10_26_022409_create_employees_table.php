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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 50);
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date');
            $table->string('department')->nullable();
            $table->string('job_title');
            $table->string('employment_type')->default('regular');
            $table->string('pay_frequency')->default('semi-monthly');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('tin', 50)->nullable();
            $table->string('sss_number', 50)->nullable();
            $table->string('philhealth_number', 50)->nullable();
            $table->string('pagibig_number', 50)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'employee_number']);
            $table->index(['company_id', 'status']);
            $table->index(['last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

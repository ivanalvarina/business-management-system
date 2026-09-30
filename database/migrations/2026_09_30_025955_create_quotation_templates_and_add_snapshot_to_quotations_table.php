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
        Schema::create('quotation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('file_type', 20)->default('pdf');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('quotation_template_id')
                ->nullable()
                ->after('terms_conditions')
                ->constrained('quotation_templates')
                ->nullOnDelete();
            $table->json('quotation_template_snapshot')->nullable()->after('quotation_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_template_id');
            $table->dropColumn('quotation_template_snapshot');
        });

        Schema::dropIfExists('quotation_templates');
    }
};

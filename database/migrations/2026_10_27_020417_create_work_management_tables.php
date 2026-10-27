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
        Schema::create('work_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('blue');
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('work_board_user', function (Blueprint $table) {
            $table->foreignId('work_board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['work_board_id', 'user_id']);
        });

        Schema::create('work_board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('slate');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->unique(['work_board_id', 'name']);
            $table->index(['work_board_id', 'position']);
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('slate');
            $table->timestamps();

            $table->unique(['work_board_id', 'name']);
        });

        Schema::create('work_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_board_column_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['work_board_column_id', 'position']);
            $table->index(['assigned_to', 'due_date']);
        });

        Schema::create('task_label_work_task', function (Blueprint $table) {
            $table->foreignId('task_label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_task_id')->constrained()->cascadeOnDelete();

            $table->primary(['task_label_id', 'work_task_id']);
        });

        Schema::create('eod_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->text('summary')->nullable();
            $table->text('blockers')->nullable();
            $table->text('next_plan')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'work_date']);
            $table->index(['company_id', 'work_date', 'status']);
        });

        Schema::create('eod_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eod_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_task_id')->constrained()->restrictOnDelete();
            $table->text('accomplishment');
            $table->unsignedSmallInteger('minutes_spent')->nullable();
            $table->timestamps();

            $table->unique(['eod_report_id', 'work_task_id']);
        });

        Schema::create('eod_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eod_report_item_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eod_attachments');
        Schema::dropIfExists('eod_report_items');
        Schema::dropIfExists('eod_reports');
        Schema::dropIfExists('task_label_work_task');
        Schema::dropIfExists('work_tasks');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('work_board_columns');
        Schema::dropIfExists('work_board_user');
        Schema::dropIfExists('work_boards');
    }
};

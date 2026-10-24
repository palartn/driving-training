<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->enum('exam_type', ['theory', 'practical']);
            $table->unsignedSmallInteger('attempt_no');
            $table->enum('result', ['pending', 'passed', 'failed'])->default('pending');
            $table->decimal('retake_fee_amount', 10, 2)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'enrollment_id']);
            $table->unique(['enrollment_id', 'exam_type', 'attempt_no']);
        });

        DB::statement(
            'ALTER TABLE exam_attempts ADD CONSTRAINT exam_attempts_no_check CHECK (attempt_no >= 1)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};

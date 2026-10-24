<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->date('enrolled_at');
            $table->enum('status', ['active', 'completed', 'withdrawn', 'cancelled'])->default('active');
            $table->string('status_reason', 255)->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            // Equals student_id while the enrollment is active, otherwise NULL.
            // The UNIQUE index guarantees one active enrollment per student.
            $table->unsignedBigInteger('active_student_id')
                ->nullable()
                ->storedAs("CASE WHEN `status` = 'active' THEN `student_id` END")
                ->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};

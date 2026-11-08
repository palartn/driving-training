<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->enum('status', ['present', 'absent', 'excused']);
            $table->string('notes', 255)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['training_session_id', 'enrollment_id']);
            $table->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};

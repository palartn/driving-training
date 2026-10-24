<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->string('reason', 255);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['enrollment_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_overrides');
    }
};

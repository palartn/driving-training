<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_subject_instructors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('instructor_id')->constrained('instructors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'subject_id', 'instructor_id'], 'csi_unique');

            // لا إسناد لمادة ليست ضمن مواد الدورة (مفتاح أجنبي مركّب).
            $table->foreign(['course_id', 'subject_id'], 'csi_course_subject_fk')
                ->references(['course_id', 'subject_id'])
                ->on('course_subjects')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_subject_instructors');
    }
};

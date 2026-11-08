<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('instructor_id')->nullable()->constrained('instructors')->restrictOnDelete();
            $table->date('session_date');
            $table->time('starts_at');
            $table->time('ends_at');
            // مدة الجلسة بالدقائق، مولَّدة من الوقتين.
            $table->unsignedSmallInteger('duration_minutes')
                ->storedAs('(TIME_TO_SEC(`ends_at`) - TIME_TO_SEC(`starts_at`)) DIV 60');
            $table->string('location', 150)->nullable();
            $table->enum('type', ['theory', 'practical']);
            $table->enum('status', ['scheduled', 'held', 'cancelled'])->default('scheduled');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // الجلسة لمادة من مواد الدورة فقط.
            $table->foreign(['course_id', 'subject_id'], 'ts_course_subject_fk')
                ->references(['course_id', 'subject_id'])
                ->on('course_subjects')
                ->restrictOnDelete();

            $table->index(['course_id', 'session_date']);
            $table->index(['instructor_id', 'session_date']);
        });

        DB::statement('ALTER TABLE training_sessions ADD CONSTRAINT training_sessions_time_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};

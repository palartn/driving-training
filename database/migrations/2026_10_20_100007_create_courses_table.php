<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_type_id')->constrained('course_types')->restrictOnDelete();
            $table->foreignId('center_id')->constrained('centers')->restrictOnDelete();
            $table->unsignedInteger('course_sequence');
            $table->unsignedSmallInteger('course_year');
            $table->string('course_number', 12)
                ->storedAs("CONCAT(`course_sequence`, '/', `course_year`)")
                ->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('seats')->nullable();
            $table->enum('status', ['upcoming', 'running', 'finished', 'cancelled'])->default('upcoming');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['course_sequence', 'course_year']);
            $table->index(['center_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE courses ADD CONSTRAINT courses_dates_check CHECK (end_date >= start_date)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

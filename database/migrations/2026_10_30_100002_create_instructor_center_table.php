<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المراكز التي يدرّس فيها المدرب (قد يكون أكثر من مركز).
        Schema::create('instructor_center', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('instructors')->restrictOnDelete();
            $table->foreignId('center_id')->constrained('centers')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['instructor_id', 'center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_center');
    }
};

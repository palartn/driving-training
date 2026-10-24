<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('tariff_id')->constrained('subject_tariffs')->restrictOnDelete();
            // Snapshot of the tariff at enrollment time.
            $table->unsignedSmallInteger('lectures_count');
            $table->decimal('price_amount', 10, 2);
            $table->timestamps();

            $table->unique(['enrollment_id', 'subject_id']);
        });

        DB::statement(
            'ALTER TABLE enrollment_subjects ADD CONSTRAINT enrollment_subjects_price_check CHECK (price_amount >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_subjects');
    }
};

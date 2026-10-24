<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Exactly one of the two references is set: a subject fee or a retake fee.
            $table->foreignId('enrollment_subject_id')->nullable()->unique()
                ->constrained('enrollment_subjects')->restrictOnDelete();
            $table->foreignId('exam_attempt_id')->nullable()->unique()
                ->constrained('exam_attempts')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('paid_at');
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_target_check CHECK ('
            . '(enrollment_subject_id IS NOT NULL AND exam_attempt_id IS NULL)'
            . ' OR (enrollment_subject_id IS NULL AND exam_attempt_id IS NOT NULL)'
            . ')'
        );
        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retake_fees', function (Blueprint $table) {
            $table->id();
            $table->enum('exam_type', ['theory', 'practical']);
            $table->decimal('amount', 10, 2);
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['exam_type', 'effective_from']);
        });

        DB::statement(
            'ALTER TABLE retake_fees ADD CONSTRAINT retake_fees_amount_check CHECK (amount >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_fees');
    }
};

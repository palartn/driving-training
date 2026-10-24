<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_subject_id')->unique()
                ->constrained('enrollment_subjects')->restrictOnDelete();
            $table->decimal('exemption_amount', 10, 2);
            $table->string('reason', 255);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE exemptions ADD CONSTRAINT exemptions_amount_check CHECK (exemption_amount > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('exemptions');
    }
};

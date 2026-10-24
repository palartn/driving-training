<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->enum('pricing_mode', ['fixed', 'per_lecture']);
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->decimal('lecture_price', 10, 2)->nullable();
            $table->unsignedSmallInteger('lectures_count');
            $table->decimal('total_price', 10, 2)
                ->storedAs("CASE WHEN `pricing_mode` = 'fixed' THEN `fixed_price` ELSE `lecture_price` * `lectures_count` END");
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['subject_id', 'effective_from']);
        });

        DB::statement(
            "ALTER TABLE subject_tariffs ADD CONSTRAINT subject_tariffs_mode_check CHECK ("
            . "(pricing_mode = 'fixed' AND fixed_price IS NOT NULL AND fixed_price >= 0 AND lecture_price IS NULL)"
            . " OR (pricing_mode = 'per_lecture' AND lecture_price IS NOT NULL AND lecture_price >= 0 AND fixed_price IS NULL)"
            . ")"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_tariffs');
    }
};

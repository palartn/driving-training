<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->char('national_id', 9)->unique();
            $table->string('first_name', 50);
            $table->string('second_name', 50);
            $table->string('third_name', 50);
            $table->string('fourth_name', 50);
            $table->string('full_name', 220)
                ->storedAs("CONCAT_WS(' ', `first_name`, `second_name`, `third_name`, `fourth_name`)");
            $table->string('phone', 20);
            $table->string('phone_secondary', 20)->nullable();
            $table->string('address', 255);
            $table->foreignId('governorate_id')->constrained('governorates')->restrictOnDelete();
            $table->foreignId('center_id')->constrained('centers')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('full_name');
            $table->index('phone');
        });

        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT students_national_id_digits CHECK (national_id REGEXP '^[0-9]{9}$')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

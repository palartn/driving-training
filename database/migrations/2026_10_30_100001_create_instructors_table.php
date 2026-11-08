<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->char('national_id', 9)->unique();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('specialization', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });

        DB::statement(
            "ALTER TABLE instructors ADD CONSTRAINT instructors_national_id_digits CHECK (national_id REGEXP '^[0-9]{9}$')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};

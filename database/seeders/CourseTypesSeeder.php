<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseTypesSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['عمومي', 'تراكتور', 'حمولة', 'مدرب سياقة', 'محاضر سياقة'];

        $now = now();
        $rows = [];
        foreach ($names as $name) {
            $rows[] = [
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('course_types')->upsert($rows, ['name'], ['updated_at']);
    }
}

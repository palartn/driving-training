<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GovernoratesSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'جنين', 'طوباس', 'طولكرم', 'نابلس', 'قلقيلية', 'سلفيت',
            'رام الله والبيرة', 'أريحا والأغوار', 'القدس', 'بيت لحم', 'الخليل',
            'شمال غزة', 'غزة', 'دير البلح', 'خان يونس', 'رفح',
        ];

        $now = now();
        $rows = [];
        foreach ($names as $index => $name) {
            $rows[] = [
                'name' => $name,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('governorates')->upsert($rows, ['name'], ['sort_order', 'updated_at']);
    }
}

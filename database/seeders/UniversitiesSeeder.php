<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UniversitiesSeeder extends Seeder
{
    public function run(): void
    {
        $universities = [
            ['name' => 'Université Gamal Abdel Nasser de Conakry', 'short_name' => 'UGANC', 'code' => 'UGANC', 'country' => 'Guinée', 'city' => 'Conakry'],
            ['name' => 'Université Général Lansana Conté de Sonfonia', 'short_name' => 'UGLC-S', 'code' => 'UGLC-SONFONIA', 'country' => 'Guinée', 'city' => 'Conakry'],
            ['name' => 'Université de Sonfonia', 'short_name' => 'US', 'code' => 'US-CONAKRY', 'country' => 'Guinée', 'city' => 'Conakry'],
            ['name' => 'Université Mahatma Gandhi de Conakry', 'short_name' => 'UMGC', 'code' => 'UMGC', 'country' => 'Guinée', 'city' => 'Conakry'],
            ['name' => 'Université de Labé', 'short_name' => 'UL', 'code' => 'UL-LABE', 'country' => 'Guinée', 'city' => 'Labé'],
        ];

        foreach ($universities as $university) {
            DB::table('universities')->updateOrInsert(
                ['code' => $university['code']],
                [
                    'id' => (string) Str::uuid(),
                    ...$university,
                    'website' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}

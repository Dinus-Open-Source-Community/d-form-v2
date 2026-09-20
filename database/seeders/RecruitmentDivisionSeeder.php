<?php

namespace Database\Seeders;

use App\Models\Recruitment\RecruitmentDivision;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecruitmentDivisionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $divisions = [
            ['code' => 'programming', 'name' => 'Pemrograman / Programming', 'sort_order' => 1],
            ['code' => 'medcrev', 'name' => 'Kreatif / Creative', 'sort_order' => 2],
            ['code' => 'network', 'name' => 'Jaringan / Network', 'sort_order' => 3],
            ['code' => 'data', 'name' => 'Data', 'sort_order' => 4],
            ['code' => 'humas', 'name' => 'Humas / Public Relations', 'sort_order' => 5],
        ];

        foreach ($divisions as $division) {
            RecruitmentDivision::query()->updateOrCreate(
                ['code' => $division['code']],
                [
                    'name' => $division['name'],
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $division['sort_order'],
                ],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'RKT',
                'description' => 'Projek RKT'
            ],
            [
                'name' => 'Non RKT',
                'description' => 'Projek Non RKT'
            ],
            [
                'name' => 'Recruitment',
                'description' => 'Recruitment member & Anggota aktif'
            ],
        ];

        foreach ($categories as $category) {
            EventCategory::create($category);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Artificial Intelligence',
                'slug' => 'artificial-intelligence',
                'description' => 'AI, machine learning, and deep learning topics',
            ],
            [
                'name' => 'Big Data',
                'slug' => 'big-data',
                'description' => 'Data engineering, analytics, and large-scale processing',
            ],
            [
                'name' => 'Cloud Computing',
                'slug' => 'cloud-computing',
                'description' => 'Cloud platforms, infrastructure, and services',
            ],
            [
                'name' => 'Software Engineering',
                'slug' => 'software-engineering',
                'description' => 'Development practices, architecture, and tooling',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                ]
            );
        }
    }
}

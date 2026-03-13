<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Meals',     'icon' => '🍱'],
            ['name' => 'Snacks',    'icon' => '🍟'],
            ['name' => 'Beverages', 'icon' => '🥤'],
            ['name' => 'Desserts',  'icon' => '🍨'],
            ['name' => 'Combos',    'icon' => '🍽️'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }
    }
}

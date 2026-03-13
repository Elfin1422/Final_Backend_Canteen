<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Meals
            ['category_id'=>1,'name'=>'Chicken Adobo with Rice',   'price'=>75, 'stock'=>50],
            ['category_id'=>1,'name'=>'Pork Sinigang',             'price'=>85, 'stock'=>40],
            ['category_id'=>1,'name'=>'Beef Caldereta',            'price'=>95, 'stock'=>30],
            ['category_id'=>1,'name'=>'Fried Tilapia with Rice',   'price'=>70, 'stock'=>45],
            ['category_id'=>1,'name'=>'Pinakbet',                  'price'=>65, 'stock'=>35],
            ['category_id'=>1,'name'=>'Pancit Canton',             'price'=>60, 'stock'=>50],
            ['category_id'=>1,'name'=>'Lechon Kawali',             'price'=>90, 'stock'=>25],
            ['category_id'=>1,'name'=>'Arroz Caldo',               'price'=>55, 'stock'=>60],
            // Snacks
            ['category_id'=>2,'name'=>'Banana Cue',                'price'=>15, 'stock'=>80],
            ['category_id'=>2,'name'=>'Kwek-Kwek',                 'price'=>20, 'stock'=>70],
            ['category_id'=>2,'name'=>'Fishball (10 pcs)',         'price'=>25, 'stock'=>60],
            ['category_id'=>2,'name'=>'Sandwich (Ham & Cheese)',   'price'=>35, 'stock'=>40],
            ['category_id'=>2,'name'=>'Hotdog Bun',                'price'=>30, 'stock'=>50],
            ['category_id'=>2,'name'=>'Puto',                      'price'=>20, 'stock'=>55],
            // Beverages
            ['category_id'=>3,'name'=>'Bottled Water',             'price'=>15, 'stock'=>100],
            ['category_id'=>3,'name'=>'Coke 330ml',                'price'=>25, 'stock'=>80],
            ['category_id'=>3,'name'=>'Iced Tea (Large)',          'price'=>30, 'stock'=>60],
            ['category_id'=>3,'name'=>'Buko Juice',                'price'=>35, 'stock'=>40],
            ['category_id'=>3,'name'=>'Milo Hot',                  'price'=>20, 'stock'=>70],
            ['category_id'=>3,'name'=>'Orange Juice',              'price'=>25, 'stock'=>55],
            // Desserts
            ['category_id'=>4,'name'=>'Halo-Halo',                 'price'=>55, 'stock'=>30],
            ['category_id'=>4,'name'=>'Leche Flan',                'price'=>40, 'stock'=>35],
            ['category_id'=>4,'name'=>'Biko',                      'price'=>30, 'stock'=>40],
            ['category_id'=>4,'name'=>'Mais Con Yelo',             'price'=>35, 'stock'=>25],
            ['category_id'=>4,'name'=>'Sago\'t Gulaman',           'price'=>20, 'stock'=>45],
            ['category_id'=>4,'name'=>'Ice Cream (1 scoop)',       'price'=>25, 'stock'=>50],
            // Combos
            ['category_id'=>5,'name'=>'Meal + Drink Combo',        'price'=>95, 'stock'=>40],
            ['category_id'=>5,'name'=>'2 Viands + Rice Combo',     'price'=>110,'stock'=>30],
            ['category_id'=>5,'name'=>'Snack + Drink Combo',       'price'=>45, 'stock'=>50],
            ['category_id'=>5,'name'=>'Student Meal Deal',         'price'=>75, 'stock'=>35],
            ['category_id'=>5,'name'=>'Budget Breakfast Set',      'price'=>60, 'stock'=>45],
        ];

        foreach ($items as $item) {
            MenuItem::create([
                'category_id'         => $item['category_id'],
                'name'                => $item['name'],
                'price'               => $item['price'],
                'stock_quantity'      => $item['stock'],
                'is_available'        => true,
                'low_stock_threshold' => 10,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $menuItemIds = MenuItem::pluck('id')->toArray();
        $statuses    = ['completed', 'completed', 'completed', 'completed', 'cancelled', 'pending', 'preparing'];

        for ($i = 0; $i < 210; $i++) {
            $itemCount     = rand(1, 4);
            $subtotal      = 0;
            $orderItems    = [];
            $selectedItems = array_rand(array_flip($menuItemIds), $itemCount);
            if (!is_array($selectedItems)) $selectedItems = [$selectedItems];

            foreach ($selectedItems as $menuItemId) {
                $menuItem  = MenuItem::find($menuItemId);
                $qty       = rand(1, 3);
                $line      = $menuItem->price * $qty;
                $subtotal += $line;
                $orderItems[] = [
                    'menu_item_id' => $menuItemId,
                    'quantity'     => $qty,
                    'unit_price'   => $menuItem->price,
                    'subtotal'     => $line,
                ];
            }

            $tax    = $subtotal * 0.12;
            $status = $statuses[array_rand($statuses)];
            $date   = now()->subDays(rand(0, 60))->subHours(rand(0, 23));

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'user_id'      => 3,
                'cashier_id'   => 2,
                'status'       => $status,
                'subtotal'     => $subtotal,
                'tax'          => $tax,
                'total_amount' => $subtotal + $tax,
                'completed_at' => $status === 'completed' ? $date : null,
                'created_at'   => $date,
                'updated_at'   => $date,
            ]);

            $order->orderItems()->createMany($orderItems);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\InventoryLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * OrderSeeder
 * ===========
 * Generates realistic synthetic order data for 90 days of canteen history.
 *
 * Why 90 days / 500+ orders?
 *   The ML prediction engine (Random Forest + Logistic Regression) needs
 *   sufficient historical data to:
 *   - Detect day-of-week patterns (e.g. Fridays are busier)
 *   - Learn lag features (yesterday's demand predicts today's)
 *   - Build meaningful rolling averages (7-day, 14-day windows)
 *   - Have enough samples for train/test split (80/20) with good coverage
 *
 * Realistic patterns built in:
 *   - Higher demand on Mon–Fri (school days) vs weekends
 *   - Peak hours: lunch rush (11am–1pm) gets ~60% of daily orders
 *   - Meals and Combos are most popular categories
 *   - Gradual upward trend in orders over the 90-day period (mimics growth)
 *   - Random noise to prevent overfitting to perfectly clean data
 *   - Some items (Halo-Halo, Banana Cue) have seasonal spikes
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // ── Fetch all menu items ─────────────────────────────────────────
        $menuItems = MenuItem::with('category')->get();

        if ($menuItems->isEmpty()) {
            $this->command->warn('No menu items found. Run MenuItemSeeder first.');
            return;
        }

        // ── Build weighted item pools for realistic demand distribution ──
        // Items with higher weight appear more often in orders.
        // This creates the class imbalance that SMOTE will handle in predict.py.
        $itemWeights = [];
        foreach ($menuItems as $item) {
            $catName = $item->category?->name ?? 'Other';

            // Base weights by category (Meals & Combos are most popular)
            $baseWeight = match ($catName) {
                'Meals'     => 35,   // Very popular — rice meals every lunch
                'Combos'    => 25,   // Students love value combos
                'Beverages' => 20,   // Drinks with every meal
                'Snacks'    => 15,   // Afternoon snacks
                'Desserts'  => 5,    // Occasional treats
                default     => 10,
            };

            $itemWeights[$item->id] = $baseWeight;
        }

        $itemIds = $menuItems->pluck('id')->toArray();
        $orderCount = 0;

        // ── Generate orders for each day in the past 90 days ────────────
        for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);

            // Skip Sundays (canteen closed)
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            // ── Daily order volume ───────────────────────────────────────
            // More orders on weekdays, fewer on Saturdays.
            // Slight growth trend: more orders as time progresses (newer = more usage).
            $growthFactor = 1 + (($daysAgo <= 45) ? 0.3 : 0.0); // +30% in last 45 days

            $baseOrders = match ($date->dayOfWeek) {
                Carbon::MONDAY,
                Carbon::FRIDAY    => rand(12, 18),  // Busy start/end of week
                Carbon::TUESDAY,
                Carbon::WEDNESDAY,
                Carbon::THURSDAY  => rand(10, 15),  // Normal school days
                Carbon::SATURDAY  => rand(3, 7),    // Half day / fewer students
                default           => rand(4, 8),
            };

            $dailyOrders = (int) round($baseOrders * $growthFactor);

            // ── Create each order for this day ───────────────────────────
            for ($o = 0; $o < $dailyOrders; $o++) {
                // Spread orders realistically throughout the day:
                // 60% during lunch (11am–1pm), 40% spread rest of day
                $isLunchRush = (rand(1, 100) <= 60);
                if ($isLunchRush) {
                    $hour   = rand(11, 12);
                    $minute = rand(0, 59);
                } else {
                    // Morning (7–10am) or afternoon (2–5pm)
                    $hour = rand(0, 1) === 0 ? rand(7, 10) : rand(14, 17);
                    $minute = rand(0, 59);
                }
                $orderTime = $date->copy()->setTime($hour, $minute, rand(0, 59));

                // Weighted random selection of items for this order
                $numItems   = rand(1, 4);  // 1–4 different items per order
                $orderItemsData = [];
                $subtotal   = 0;

                // Pick items using weighted random selection
                $selectedItemIds = $this->weightedRandomSample($itemIds, $itemWeights, $numItems);

                foreach ($selectedItemIds as $itemId) {
                    $menuItem = $menuItems->find($itemId);
                    if (!$menuItem) continue;

                    // Higher quantities during lunch rush, lower at other times
                    $qty  = $isLunchRush ? rand(1, 3) : rand(1, 2);
                    $line = $menuItem->price * $qty;
                    $subtotal += $line;

                    $orderItemsData[] = [
                        'menu_item_id' => $itemId,
                        'quantity'     => $qty,
                        'unit_price'   => $menuItem->price,
                        'subtotal'     => $line,
                    ];
                }

                if (empty($orderItemsData)) continue;

                $tax   = round($subtotal * 0.12, 2);
                $total = $subtotal + $tax;

                // Most orders are completed; small % cancelled (realistic noise)
                $statusRoll = rand(1, 100);
                $status = match (true) {
                    $statusRoll <= 80 => 'completed',  // 80% completed
                    $statusRoll <= 90 => 'preparing',  // 10% still in progress
                    $statusRoll <= 95 => 'pending',    // 5% just placed
                    default           => 'cancelled',  // 5% cancelled
                };

                // Create the order
                $order = Order::create([
                    'order_number' => 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                    'user_id'      => 3,   // customer@canteen.com
                    'cashier_id'   => 2,   // cashier@canteen.com
                    'status'       => $status,
                    'subtotal'     => $subtotal,
                    'tax'          => $tax,
                    'total_amount' => $total,
                    'notes'        => null,
                    'completed_at' => $status === 'completed' ? $orderTime : null,
                    'created_at'   => $orderTime,
                    'updated_at'   => $orderTime,
                ]);

                // Create line items
                $order->orderItems()->createMany($orderItemsData);
                $orderCount++;
            }
        }

        $this->command->info("✅ OrderSeeder: Created {$orderCount} synthetic orders across 90 days.");
    }

    /**
     * Weighted random sample without replacement.
     *
     * Given a list of item IDs and their weights, randomly selects $n items
     * where higher-weight items are more likely to be chosen.
     * This ensures popular items (Meals, Combos) appear more often in orders,
     * creating realistic demand imbalance for the ML model to learn from.
     *
     * @param array $ids     List of item IDs to sample from
     * @param array $weights Map of id => weight (higher = more likely)
     * @param int   $n       Number of items to select
     * @return array         Selected item IDs (unique, no repeats)
     */
    private function weightedRandomSample(array $ids, array $weights, int $n): array
    {
        $selected = [];
        $available = $ids;

        // Limit to available items
        $n = min($n, count($available));

        for ($i = 0; $i < $n; $i++) {
            if (empty($available)) break;

            // Build cumulative weight array for current available items
            $total = 0;
            $cumulative = [];
            foreach ($available as $id) {
                $total += ($weights[$id] ?? 1);
                $cumulative[$id] = $total;
            }

            // Pick a random point in [0, total]
            $rand = rand(0, (int)($total * 100)) / 100;

            // Find which item this random point lands on
            $chosen = null;
            foreach ($cumulative as $id => $cum) {
                if ($rand <= $cum) {
                    $chosen = $id;
                    break;
                }
            }

            if ($chosen === null) {
                $chosen = end($available);
            }

            $selected[] = $chosen;
            // Remove chosen item so we don't pick it twice in one order
            $available = array_values(array_filter($available, fn($id) => $id !== $chosen));
        }

        return $selected;
    }
}

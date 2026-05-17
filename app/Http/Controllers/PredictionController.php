<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * PredictionController
 * =====================
 * Feeds order history to the Python ML script (predict.py) via subprocess.
 * Uses Random Forest Regressor + Linear Regression to:
 *   1. Predict next-7-day demand per menu item
 *   2. Rank items by predicted popularity
 *   3. Advise which items need restocking based on predicted demand vs current stock
 *
 * Results are cached for 1 hour to avoid re-training on every page load.
 */
class PredictionController extends Controller
{
    /**
     * Run the ML prediction pipeline and return results.
     * Cached for 60 minutes — force refresh with ?refresh=1
     */
    public function predict(Request $request)
    {
        $cacheKey = 'ml_predictions_v1';

        // Allow admin to force a fresh run
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $result = Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return $this->runPrediction();
        });

        return response()->json($result);
    }

    /**
     * Gather data from DB and pass it to the Python ML script.
     */
    private function runPrediction(): array
    {
        // Fetch last 90 days of completed orders with items
        $orders = Order::with(['orderItems.menuItem.category'])
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(90))
            ->orderBy('created_at')
            ->get()
            ->map(function ($order) {
                return [
                    'id'          => $order->id,
                    'status'      => $order->status,
                    'created_at'  => $order->created_at->toDateTimeString(),
                    'order_items' => $order->orderItems->map(fn($oi) => [
                        'menu_item_id' => $oi->menu_item_id,
                        'quantity'     => $oi->quantity,
                    ])->toArray(),
                ];
            })
            ->toArray();

        // Fetch all menu items with category
        $items = MenuItem::with('category')
            ->get()
            ->map(fn($item) => [
                'id'                  => $item->id,
                'name'                => $item->name,
                'price'               => $item->price,
                'stock_quantity'      => $item->stock_quantity,
                'low_stock_threshold' => $item->low_stock_threshold,
                'is_available'        => $item->is_available,
                'category'            => [
                    'id'   => $item->category?->id,
                    'name' => $item->category?->name,
                ],
            ])
            ->toArray();

        $payload = json_encode(['orders' => $orders, 'items' => $items]);

        // Locate the Python script relative to Laravel base path
        $scriptPath = base_path('../ml/predict.py');

        // Run Python script, pass payload via stdin
        $process = proc_open(
            'python3 ' . escapeshellarg($scriptPath),
            [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ],
            $pipes
        );

        if (!is_resource($process)) {
            return ['error' => 'Failed to start prediction engine.'];
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (!$output) {
            return ['error' => 'Prediction engine returned no output. ' . $errors];
        }

        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid output from prediction engine: ' . $output];
        }

        return $result;
    }
}

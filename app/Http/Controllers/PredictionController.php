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
 *
 * Models used inside predict.py:
 *   1. Random Forest Regressor  — predicts exact order quantities (regression)
 *   2. Logistic Regression      — classifies demand into LOW/MEDIUM/HIGH/VERY_HIGH
 *
 * Results are cached for 60 minutes to avoid retraining on every page load.
 * Admin can force a fresh run with ?refresh=1
 */
class PredictionController extends Controller
{
    /**
     * Run the ML prediction pipeline and return JSON results.
     * Cached for 60 minutes — pass ?refresh=1 to force retrain.
     */
    public function predict(Request $request)
    {
        $cacheKey = 'ml_predictions_v2';

        // Allow admin to force a fresh prediction run
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $result = Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return $this->runPrediction();
        });

        return response()->json($result);
    }

    /**
     * Gather order + menu data from the database and pass to Python ML script.
     * Uses proc_open() to pipe JSON to stdin and read JSON from stdout.
     */
    private function runPrediction(): array
    {
        // Fetch last 90 days of completed orders with their line items
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

        // Fetch all menu items with their category and stock info
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

        // Path to the Python ML script (ml/ folder sits next to backend/)
        $scriptPath = base_path('../ml/predict.py');

        // On Windows use 'python', on Linux/Mac use 'python3'
        $pythonCmd = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';

        // Run Python script, passing order data via stdin
        $process = proc_open(
            $pythonCmd . ' ' . escapeshellarg($scriptPath),
            [
                0 => ['pipe', 'r'],  // stdin  — we write the JSON payload here
                1 => ['pipe', 'w'],  // stdout — Python writes results here
                2 => ['pipe', 'w'],  // stderr — capture any Python errors
            ],
            $pipes
        );

        if (!is_resource($process)) {
            return ['error' => 'Failed to start the ML prediction engine. Check that Python is installed.'];
        }

        // Send JSON payload to Python via stdin
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        // Read output and errors
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (!$output) {
            return ['error' => 'Prediction engine returned no output. Error: ' . $errors];
        }

        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON from prediction engine: ' . substr($output, 0, 300)];
        }

        return $result;
    }
}

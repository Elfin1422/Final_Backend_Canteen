<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\MenuItem;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manages order lifecycle: creation, status transitions, and listing.
 * Orders flow: pending → preparing → ready → completed (or cancelled).
 * Inventory is deducted when an order moves to 'preparing'.
 * All DB writes are wrapped in transactions to ensure consistency.
 */
class OrderController extends Controller
{
    /**
     * List orders.
     * Admins/cashiers see all orders; customers see only their own.
     * Supports optional ?status= filter.
     */
    public function index(Request $request)
    {
        $query = Order::with(['orderItems.menuItem', 'user'])
            ->orderByDesc('created_at');

        // Customers can only view their own orders
        if ($request->user()->role === 'customer') {
            $query->where('user_id', $request->user()->id);
        }

        // Optional status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Create a new order.
     * Validates that all menu items exist, are available, and have sufficient stock.
     * Calculates subtotal, 12% VAT, and total_amount.
     * Wrapped in a DB transaction — rolls back on any failure.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'items'             => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity'     => 'required|integer|min:1|max:100',
            'notes'             => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $subtotal   = 0;
            $orderItems = [];

            foreach ($data['items'] as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);

                // Ensure item is available
                if (!$menuItem->is_available) {
                    abort(422, "{$menuItem->name} is currently unavailable.");
                }

                // Ensure sufficient stock
                if ($menuItem->stock_quantity < $item['quantity']) {
                    abort(422, "Insufficient stock for {$menuItem->name}.");
                }

                $line      = $menuItem->price * $item['quantity'];
                $subtotal += $line;

                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $menuItem->price,
                    'subtotal'     => $line,
                ];
            }

            $tax   = round($subtotal * 0.12, 2);
            $total = $subtotal + $tax;

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'user_id'      => $request->user()->id,
                'cashier_id'   => in_array($request->user()->role, ['cashier','admin'])
                                  ? $request->user()->id : null,
                'status'       => 'pending',
                'subtotal'     => $subtotal,
                'tax'          => $tax,
                'total_amount' => $total,
                'notes'        => isset($data['notes']) ? strip_tags($data['notes']) : null,
            ]);

            $order->orderItems()->createMany($orderItems);

            return response()->json($order->load('orderItems.menuItem'), 201);
        });
    }

    /** Show a single order (customers can only view their own). */
    public function show(Request $request, Order $order)
    {
        if ($request->user()->role === 'customer' && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return response()->json($order->load('orderItems.menuItem'));
    }

    /**
     * Advance or cancel an order.
     * Valid transitions: pending→preparing, preparing→ready, ready→completed, any→cancelled.
     * Inventory is deducted when status moves to 'preparing'.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'required|in:preparing,ready,completed,cancelled',
        ]);

        $allowed = [
            'pending'   => ['preparing', 'cancelled'],
            'preparing' => ['ready',     'cancelled'],
            'ready'     => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        if (!in_array($data['status'], $allowed[$order->status] ?? [])) {
            return response()->json([
                'message' => "Cannot transition from '{$order->status}' to '{$data['status']}'.",
            ], 422);
        }

        DB::transaction(function () use ($order, $data) {
            // Deduct inventory when kitchen starts preparing
            if ($data['status'] === 'preparing') {
                foreach ($order->orderItems as $item) {
                    $menuItem = $item->menuItem;
                    $menuItem->decrement('stock_quantity', $item->quantity);

                    // Log the inventory deduction
                    InventoryLog::create([
                        'menu_item_id'    => $menuItem->id,
                        'quantity_change' => -$item->quantity,
                        'type'            => 'sale',
                        'reason'          => "Order #{$order->order_number}",
                        'user_id'         => $order->cashier_id,
                    ]);
                }
            }

            $order->update([
                'status'       => $data['status'],
                'completed_at' => $data['status'] === 'completed' ? now() : $order->completed_at,
            ]);
        });

        return response()->json($order->fresh()->load('orderItems.menuItem'));
    }
}

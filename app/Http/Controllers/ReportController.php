<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(Request $request)
    {
        $start = $request->get('start', now()->startOfMonth());
        $end   = $request->get('end', now());

        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'completed');

        return response()->json([
            'total_sales'       => $orders->sum('total_amount'),
            'total_orders'      => $orders->count(),
            'average_order'     => $orders->avg('total_amount') ?? 0,
            'total_items_sold'  => OrderItem::whereHas('order', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])->where('status', 'completed');
            })->sum('quantity'),
        ]);
    }

    public function dailySales(Request $request)
    {
        $days = $request->get('days', 30);

        $data = Order::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function weeklySales()
    {
        $data = Order::where('status', 'completed')
            ->where('created_at', '>=', now()->subWeeks(12))
            ->selectRaw('YEARWEEK(created_at) as week, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        return response()->json($data);
    }

    public function topItems(Request $request)
    {
        $limit = $request->get('limit', 10);

        $data = OrderItem::whereHas('order', fn($q) => $q->where('status', 'completed'))
            ->with('menuItem.category')
            ->selectRaw('menu_item_id, SUM(quantity) as total_quantity, SUM(subtotal) as total_revenue')
            ->groupBy('menu_item_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    public function categoryBreakdown(Request $request)
    {
        $start = $request->get('start', now()->startOfMonth());
        $end   = $request->get('end', now());

        $data = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('categories', 'menu_items.category_id', '=', 'categories.id')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('categories.name, SUM(order_items.subtotal) as revenue, SUM(order_items.quantity) as quantity')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        return response()->json($data);
    }

    public function orderTrend(Request $request)
    {
        $days = $request->get('days', 30);

        $data = Order::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, status')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }
}

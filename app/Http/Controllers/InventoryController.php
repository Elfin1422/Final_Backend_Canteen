<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuItem::with('category')->orderBy('name');

        if ($request->has('low_stock')) {
            $query->lowStock();
        }

        return response()->json($query->paginate(50));
    }

    public function adjust(Request $request, MenuItem $menuItem)
    {
        $data = $request->validate([
            'quantity_change' => 'required|integer',
            'type'            => 'required|in:restock,adjustment,waste',
            'reason'          => 'nullable|string',
        ]);

        $before = $menuItem->stock_quantity;
        $after  = max(0, $before + $data['quantity_change']);

        $menuItem->update(['stock_quantity' => $after]);

        InventoryLog::create([
            'menu_item_id'    => $menuItem->id,
            'user_id'         => $request->user()->id,
            'quantity_change' => $data['quantity_change'],
            'quantity_before' => $before,
            'quantity_after'  => $after,
            'type'            => $data['type'],
            'reason'          => $data['reason'],
        ]);

        return response()->json($menuItem->fresh());
    }

    public function bulkRestock(Request $request)
    {
        $data = $request->validate([
            'items'                   => 'required|array',
            'items.*.menu_item_id'    => 'required|exists:menu_items,id',
            'items.*.quantity_change' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['items'] as $item) {
                $menuItem = MenuItem::find($item['menu_item_id']);
                $before = $menuItem->stock_quantity;
                $menuItem->increment('stock_quantity', $item['quantity_change']);

                InventoryLog::create([
                    'menu_item_id'    => $menuItem->id,
                    'user_id'         => $request->user()->id,
                    'quantity_change' => $item['quantity_change'],
                    'quantity_before' => $before,
                    'quantity_after'  => $before + $item['quantity_change'],
                    'type'            => 'restock',
                    'reason'          => 'Bulk restock',
                ]);
            }
        });

        return response()->json(['message' => 'Bulk restock completed.']);
    }

    public function logs(Request $request)
    {
        $query = InventoryLog::with(['menuItem', 'user'])->orderBy('created_at', 'desc');

        if ($request->has('menu_item_id')) {
            $query->where('menu_item_id', $request->menu_item_id);
        }

        return response()->json($query->paginate(50));
    }
}

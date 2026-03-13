<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Manages menu items: CRUD, image uploads, availability toggling.
 * All inputs are validated with Laravel's validator.
 * Images are stored on the 'public' disk and served via storage:link.
 */
class MenuController extends Controller
{
    /**
     * List menu items with optional filters.
     * Supports: category_id, search (name), available (boolean flag).
     * Results are paginated (20 per page).
     */
    public function index(Request $request)
    {
        $query = MenuItem::with('category');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        // Search by name — uses parameterized binding, safe from SQL injection
        if ($request->filled('search')) {
            $search = strip_tags($request->search);
            $query->where('name', 'like', '%' . $search . '%');
        }

        // Show only available items (for customer/cashier views)
        if ($request->has('available')) {
            $query->available();
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Create a new menu item.
     * Validates all fields; image must be an actual image file (max 2MB).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'         => 'required|exists:categories,id',
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:1000',
            'price'               => 'required|numeric|min:0|max:99999',
            'image'               => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_available'        => 'boolean',
            'stock_quantity'      => 'integer|min:0',
            'low_stock_threshold' => 'integer|min:0',
        ]);

        // Sanitize text fields
        $data['name']        = strip_tags($data['name']);
        $data['description'] = isset($data['description']) ? strip_tags($data['description']) : null;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu', 'public');
        }

        $item = MenuItem::create($data);
        return response()->json($item->load('category'), 201);
    }

    /** Show a single menu item. */
    public function show(MenuItem $menuItem)
    {
        return response()->json($menuItem->load('category'));
    }

    /**
     * Update an existing menu item.
     * Uses 'sometimes' rules so partial updates are allowed.
     */
    public function update(Request $request, MenuItem $menuItem)
    {
        $data = $request->validate([
            'category_id'         => 'sometimes|exists:categories,id',
            'name'                => 'sometimes|string|max:255',
            'description'         => 'nullable|string|max:1000',
            'price'               => 'sometimes|numeric|min:0|max:99999',
            'image'               => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_available'        => 'boolean',
            'stock_quantity'      => 'integer|min:0',
            'low_stock_threshold' => 'integer|min:0',
        ]);

        if (isset($data['name']))        $data['name']        = strip_tags($data['name']);
        if (isset($data['description'])) $data['description'] = strip_tags($data['description']);

        if ($request->hasFile('image')) {
            // Delete old image from storage before replacing
            if ($menuItem->image) Storage::disk('public')->delete($menuItem->image);
            $data['image'] = $request->file('image')->store('menu', 'public');
        }

        $menuItem->update($data);
        return response()->json($menuItem->load('category'));
    }

    /** Delete a menu item and its associated image. */
    public function destroy(MenuItem $menuItem)
    {
        if ($menuItem->image) Storage::disk('public')->delete($menuItem->image);
        $menuItem->delete();
        return response()->json(['message' => 'Item deleted.']);
    }

    /** Toggle is_available flag on a menu item. */
    public function toggleAvailability(MenuItem $menuItem)
    {
        $menuItem->update(['is_available' => !$menuItem->is_available]);
        return response()->json($menuItem);
    }

    /** List all active categories with their item count. */
    public function categories()
    {
        return response()->json(Category::where('is_active', true)->withCount('menuItems')->get());
    }
}

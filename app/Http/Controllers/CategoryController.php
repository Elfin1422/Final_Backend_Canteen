<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Category::withCount('menuItems')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:categories',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:10',
        ]);

        return response()->json(Category::create($data), 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:10',
            'is_active'   => 'boolean',
        ]);

        $category->update($data);
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        if ($category->menuItems()->exists()) {
            return response()->json(['message' => 'Cannot delete category with menu items.'], 422);
        }
        $category->delete();
        return response()->json(['message' => 'Category deleted.']);
    }
}

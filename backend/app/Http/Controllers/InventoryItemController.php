<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(InventoryItem::paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:150',
            'category' => 'required|string|max:80',
            'stock_level' => 'integer|min:0|max:2147483647',
            'unit_price' => 'numeric|decimal:0,2|min:0|max:99999999.99',
            'vendor' => 'required|string|max:150',
            'status' => ['required', Rule::in(['In Stock', 'Low Stock', 'Out of Stock'])],
        ]);

        $item = InventoryItem::create($validated);
        AuditLogService::log('Added inventory item', 'Inventory', $item->id);

        return response()->json($item, 201);
    }

    public function show(InventoryItem $item): JsonResponse
    {
        return response()->json($item);
    }

    public function update(Request $request, InventoryItem $item): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => 'string|max:150',
            'category' => 'string|max:80',
            'stock_level' => 'integer|min:0|max:2147483647',
            'unit_price' => 'numeric|decimal:0,2|min:0|max:99999999.99',
            'vendor' => 'string|max:150',
            'status' => [Rule::in(['In Stock', 'Low Stock', 'Out of Stock'])],
        ]);

        $item->update($validated);
        AuditLogService::log('Updated inventory item', 'Inventory', $item->id);

        return response()->json($item);
    }

    public function destroy(InventoryItem $item): JsonResponse
    {
        $item->delete();
        AuditLogService::log('Deleted inventory item', 'Inventory', $item->id);
        return response()->json(null, 204);
    }
}

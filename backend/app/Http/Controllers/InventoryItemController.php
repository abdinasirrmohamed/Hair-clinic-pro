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
            'item_name' => 'required|string',
            'category' => 'required|string',
            'stock_level' => 'integer|min:0',
            'unit_price' => 'numeric|min:0',
            'vendor' => 'required|string',
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
            'item_name' => 'string',
            'category' => 'string',
            'stock_level' => 'integer|min:0',
            'unit_price' => 'numeric|min:0',
            'vendor' => 'string',
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

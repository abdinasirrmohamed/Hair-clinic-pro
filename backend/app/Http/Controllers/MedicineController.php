<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Medicine::paginate(15));
    }

    public function search(Request $request): JsonResponse
    {
        $query = Medicine::query();
        if ($request->has('q')) {
            $query->where('medicine_name', 'like', '%' . $request->q . '%');
        }
        
        $medicines = $query->take(20)->get()->map(function($med) {
            return [
                'id' => $med->id,
                'text' => $med->medicine_name,
                'quantity' => $med->quantity,
                'unit_price' => $med->unit_price
            ];
        });

        return response()->json(['results' => $medicines]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'medicine_name' => 'required|string',
            'generic_name' => 'nullable|string',
            'category' => 'required|string',
            'batch_number' => 'nullable|string',
            'barcode' => 'nullable|string',
            'quantity' => 'integer|min:0',
            'unit_price' => 'numeric|min:0',
            'expiry_date' => 'required|date',
            'reorder_level' => 'integer|min:0',
            'supplier' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $medicine = Medicine::create($validated);
        AuditLogService::log('Added medicine', 'Inventory', $medicine->id);

        return response()->json($medicine, 201);
    }

    public function show(Medicine $medicine): JsonResponse
    {
        $medicine->load('movements.supplier');
        return response()->json($medicine);
    }

    public function update(Request $request, Medicine $medicine): JsonResponse
    {
        $validated = $request->validate([
            'medicine_name' => 'string',
            'generic_name' => 'nullable|string',
            'category' => 'string',
            'batch_number' => 'nullable|string',
            'barcode' => 'nullable|string',
            'quantity' => 'integer|min:0',
            'unit_price' => 'numeric|min:0',
            'expiry_date' => 'date',
            'reorder_level' => 'integer|min:0',
            'supplier' => 'string',
            'description' => 'nullable|string',
        ]);

        $medicine->update($validated);
        AuditLogService::log('Updated medicine', 'Inventory', $medicine->id);

        return response()->json($medicine);
    }

    public function destroy(Medicine $medicine): JsonResponse
    {
        $medicine->delete();
        AuditLogService::log('Deleted medicine', 'Inventory', $medicine->id);
        return response()->json(null, 204);
    }
}

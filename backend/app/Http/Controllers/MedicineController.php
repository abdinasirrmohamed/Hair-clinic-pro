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
        $query = Medicine::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('medicine_name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'low_stock') {
                $query->whereColumn('quantity', '<=', 'reorder_level');
            } elseif ($request->status === 'expired') {
                $query->whereDate('expiry_date', '<', now()->toDateString());
            } elseif ($request->status === 'active') {
                $query->whereColumn('quantity', '>', 'reorder_level')
                    ->whereDate('expiry_date', '>=', now()->toDateString());
            }
        }

        $sort = in_array($request->sort, ['medicine_name', 'brand', 'category', 'quantity', 'buying_price', 'unit_price', 'expiry_date', 'supplier'], true)
            ? $request->sort
            : 'medicine_name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        return response()->json($query->orderBy($sort, $direction)->paginate($request->integer('per_page', 15)));
    }

    public function search(Request $request): JsonResponse
    {
        $query = Medicine::query();
        if ($request->has('q')) {
            $query->where(function ($subQuery) use ($request) {
                $subQuery->where('medicine_name', 'like', '%' . $request->q . '%')
                    ->orWhere('generic_name', 'like', '%' . $request->q . '%')
                    ->orWhere('barcode', 'like', '%' . $request->q . '%');
            });
        }
        
        $medicines = $query->take(20)->get()->map(function($med) {
            return [
                'id' => $med->id,
                'text' => $med->medicine_name,
                'barcode' => $med->barcode,
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
            'brand' => 'nullable|string',
            'category' => 'required|string',
            'batch_number' => 'nullable|string',
            'barcode' => 'nullable|string|unique:medicines,barcode',
            'quantity' => 'integer|min:0',
            'buying_price' => 'nullable|numeric|min:0',
            'unit_price' => 'numeric|min:0',
            'expiry_date' => 'required|date|after_or_equal:today',
            'reorder_level' => 'integer|min:0',
            'supplier' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('medicines', 'public');
        }

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
            'brand' => 'nullable|string',
            'category' => 'string',
            'batch_number' => 'nullable|string',
            'barcode' => 'nullable|string|unique:medicines,barcode,' . $medicine->id,
            'quantity' => 'integer|min:0',
            'buying_price' => 'nullable|numeric|min:0',
            'unit_price' => 'numeric|min:0',
            'expiry_date' => 'date|after_or_equal:today',
            'reorder_level' => 'integer|min:0',
            'supplier' => 'string',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('medicines', 'public');
        }

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

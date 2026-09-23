<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Supplier::when($request->filled('search'), fn ($q) => $q->where('company_name', 'like', '%'.$request->search.'%'))->orderBy('company_name')->paginate(min(100, max(1, $request->integer('per_page', 25)))));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|unique:suppliers|max:150',
            'contact_person' => 'nullable|string|max:150',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
        ]);

        $supplier = Supplier::create($validated);
        AuditLogService::log('Added supplier', 'Inventory', $supplier->id);

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('suppliers')->ignore($supplier->id)],
            'contact_person' => 'nullable|string|max:150',
            'phone' => 'string|max:40',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
        ]);

        $supplier->update($validated);
        AuditLogService::log('Updated supplier', 'Inventory', $supplier->id);

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        abort_if(\Illuminate\Support\Facades\DB::table('pharmacy_orders')->where('supplier_id', $supplier->id)->exists() || \Illuminate\Support\Facades\DB::table('pharmacy_purchases')->where('supplier_id', $supplier->id)->exists() || \App\Models\InventoryMovement::where('supplier_id', $supplier->id)->exists(), 422, 'Suppliers with purchase or stock history must be retained.');
        $supplier->delete();
        AuditLogService::log('Deleted supplier', 'Inventory', $supplier->id);
        return response()->json(null, 204);
    }
}

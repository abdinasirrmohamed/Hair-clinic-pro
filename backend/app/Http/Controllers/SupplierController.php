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
        return response()->json(Supplier::paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|unique:suppliers',
            'contact_person' => 'nullable|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
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
            'company_name' => ['string', Rule::unique('suppliers')->ignore($supplier->id)],
            'contact_person' => 'nullable|string',
            'phone' => 'string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        $supplier->update($validated);
        AuditLogService::log('Updated supplier', 'Inventory', $supplier->id);

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        AuditLogService::log('Deleted supplier', 'Inventory', $supplier->id);
        return response()->json(null, 204);
    }
}

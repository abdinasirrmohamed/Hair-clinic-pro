<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['medicine', 'supplier', 'user']);

        if ($request->has('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        return response()->json($query->paginate(15));
    }

    public function stockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'batch_number' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'invoice' => 'nullable|image|max:3072'
        ]);

        DB::beginTransaction();
        try {
            $medicine = Medicine::findOrFail($validated['medicine_id']);
            $oldQuantity = $medicine->quantity;
            
            $medicine->quantity += $validated['quantity'];
            $medicine->unit_price = $validated['unit_cost']; // Updating to latest unit cost
            if (!empty($validated['batch_number'])) $medicine->batch_number = $validated['batch_number'];
            if (!empty($validated['expiry_date'])) $medicine->expiry_date = $validated['expiry_date'];
            if (!empty($validated['supplier_id'])) $medicine->supplier_id = $validated['supplier_id'];
            
            $medicine->save();

            $movementData = [
                'transaction_number' => 'STKIN-' . date('Ymd') . '-' . rand(1000, 9999),
                'medicine_id' => $medicine->id,
                'movement_type' => 'Stock In',
                'quantity' => $validated['quantity'],
                'old_quantity' => $oldQuantity,
                'new_quantity' => $medicine->quantity,
                'unit_cost' => $validated['unit_cost'],
                'total_cost' => $validated['unit_cost'] * $validated['quantity'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'issued_by' => auth()->id(),
            ];

            if ($request->hasFile('invoice')) {
                $movementData['invoice_path'] = $request->file('invoice')->store('inventory_invoices', 'public');
            }

            $movement = InventoryMovement::create($movementData);

            DB::commit();
            AuditLogService::log('Stock In', 'Inventory', $movement->id);

            return response()->json($movement, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }

    public function stockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'quantity' => 'required|integer|min:1',
            'department' => 'nullable|string',
            'purpose' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $medicine = Medicine::findOrFail($validated['medicine_id']);
            $oldQuantity = $medicine->quantity;
            
            if ($medicine->quantity < $validated['quantity']) {
                throw new \Exception('Insufficient stock.');
            }

            $medicine->decrement('quantity', $validated['quantity']);

            $movement = InventoryMovement::create([
                'transaction_number' => 'STKOUT-' . date('Ymd') . '-' . rand(1000, 9999),
                'medicine_id' => $medicine->id,
                'movement_type' => 'Stock Out',
                'quantity' => $validated['quantity'],
                'old_quantity' => $oldQuantity,
                'new_quantity' => $medicine->quantity,
                'unit_cost' => $medicine->unit_price,
                'total_cost' => $medicine->unit_price * $validated['quantity'],
                'department' => $validated['department'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'issued_by' => auth()->id(),
            ]);

            DB::commit();
            AuditLogService::log('Stock Out', 'Inventory', $movement->id);

            return response()->json($movement, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }
}

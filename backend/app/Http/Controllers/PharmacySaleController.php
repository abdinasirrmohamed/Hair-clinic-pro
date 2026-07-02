<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\PharmacySale;
use App\Models\PharmacySaleMedicine;
use App\Models\InventoryMovement;
use App\Models\Prescription;
use App\Services\AuditLogService;
use App\Services\WaafiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PharmacySaleController extends Controller
{
    private WaafiPaymentService $waafi;

    public function __construct(WaafiPaymentService $waafi)
    {
        $this->waafi = $waafi;
    }

    public function index(Request $request): JsonResponse
    {
        $query = PharmacySale::with(['creator', 'patient']);
        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string',
            'patient_id' => 'nullable|exists:patients,id',
            'payment_method' => ['required', Rule::in(['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'])],
            'discount_type' => ['required', Rule::in(['None', 'Fixed', 'Percentage'])],
            'discount_value' => 'numeric|min:0',
            'tax_percent' => 'numeric|min:0',
            'notes' => 'nullable|string',
            'account_no' => 'required_if:payment_method,EVC Plus|required_if:payment_method,Sahal',
            'medicines' => 'required|array|min:1',
            'medicines.*.medicine_id' => 'required|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $saleMedicinesData = [];
            $movementsData = [];

            // Calculate totals and lock medicines
            foreach ($validated['medicines'] as $medInput) {
                $medicine = Medicine::where('id', $medInput['medicine_id'])->lockForUpdate()->first();
                
                if ($medicine->quantity < $medInput['quantity']) {
                    throw new \Exception("Insufficient stock for {$medicine->medicine_name}.");
                }

                $lineTotal = $medicine->unit_price * $medInput['quantity'];
                $subtotal += $lineTotal;

                $saleMedicinesData[] = [
                    'medicine_id' => $medicine->id,
                    'quantity' => $medInput['quantity'],
                    'unit_price' => $medicine->unit_price,
                    'subtotal' => $lineTotal,
                    'medicine_model' => $medicine, // Temp storage for deduct step
                ];
            }

            // Calculate discount
            $discountAmount = 0;
            if ($validated['discount_type'] === 'Fixed') {
                $discountAmount = min($validated['discount_value'], $subtotal);
            } elseif ($validated['discount_type'] === 'Percentage') {
                $discountAmount = $subtotal * (min($validated['discount_value'], 100) / 100);
            }

            $afterDiscount = $subtotal - $discountAmount;
            
            // Calculate tax
            $taxAmount = $afterDiscount * ($validated['tax_percent'] / 100);
            
            $totalAmount = $afterDiscount + $taxAmount;

            // Handle Mobile Payment via Waafi
            if (in_array($validated['payment_method'], ['EVC Plus', 'Sahal'])) {
                $waafiResult = $this->waafi->charge(
                    $totalAmount, 
                    $validated['account_no'], 
                    'PHR-' . time(), 
                    'INV-' . time(),
                    'Pharmacy Sale'
                );
                
                if (!$waafiResult['success']) {
                    throw new \Exception($waafiResult['message']);
                }
            }

            // Create Sale Record
            $sale = PharmacySale::create([
                'sale_number' => 'SALE-' . date('Ymd') . '-' . rand(1000, 9999),
                'customer_name' => $validated['customer_name'] ?? null,
                'patient_id' => $validated['patient_id'] ?? null,
                'medicine_count' => count($validated['medicines']),
                'subtotal' => $subtotal,
                'discount_type' => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'discount_amount' => $discountAmount,
                'tax_percent' => $validated['tax_percent'],
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'Paid',
                'status' => 'Paid',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Deduct stock and create lines/movements
            foreach ($saleMedicinesData as $data) {
                PharmacySaleMedicine::create([
                    'sale_id' => $sale->id,
                    'medicine_id' => $data['medicine_id'],
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                    'subtotal' => $data['subtotal'],
                ]);

                $medicine = $data['medicine_model'];
                $medicine->decrement('quantity', $data['quantity']);

                InventoryMovement::create([
                    'transaction_number' => 'MV-' . time() . rand(10, 99),
                    'medicine_id' => $medicine->id,
                    'movement_type' => 'Pharmacy Sales',
                    'quantity' => $data['quantity'],
                    'unit_cost' => $data['unit_price'],
                    'total_cost' => $data['subtotal'],
                    'reference_type' => 'Pharmacy Sale',
                    'reference_id' => $sale->id,
                    'issued_by' => auth()->id(),
                ]);
            }

            DB::commit();
            AuditLogService::log('Created pharmacy sale', 'Pharmacy', $sale->id);

            return response()->json($sale, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error processing sale: ' . $e->getMessage()], 422);
        }
    }

    public function show(PharmacySale $sale): JsonResponse
    {
        $sale->load(['medicines.medicine', 'patient', 'creator']);
        return response()->json($sale);
    }

    public function receipt(PharmacySale $sale): JsonResponse
    {
        $sale->load(['medicines.medicine', 'patient']);
        return response()->json($sale);
    }

    public function returnSale(Request $request, PharmacySale $sale): JsonResponse
    {
        if ($sale->status === 'Returned') {
            return response()->json(['message' => 'Sale is already returned.'], 400);
        }

        $request->validate(['return_reason' => 'required|string']);

        DB::beginTransaction();
        try {
            $sale->update([
                'status' => 'Returned',
                'returned_at' => now(),
                'return_reason' => $request->return_reason,
            ]);

            $saleMedicines = PharmacySaleMedicine::where('sale_id', $sale->id)->get();
            
            foreach ($saleMedicines as $item) {
                Medicine::where('id', $item->medicine_id)->increment('quantity', $item->quantity);
                
                InventoryMovement::create([
                    'transaction_number' => 'RET-' . time() . rand(10, 99),
                    'medicine_id' => $item->medicine_id,
                    'movement_type' => 'Stock In', // Or 'Return' depending on enums
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_price,
                    'total_cost' => $item->subtotal,
                    'reference_type' => 'Pharmacy Return',
                    'reference_id' => $sale->id,
                    'issued_by' => auth()->id(),
                ]);
            }

            DB::commit();
            AuditLogService::log('Returned pharmacy sale', 'Pharmacy', $sale->id);

            return response()->json(['message' => 'Sale returned successfully.', 'sale' => $sale]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function dispense(Request $request): JsonResponse
    {
        $request->validate([
            'prescription_id' => 'required|exists:prescriptions,id'
        ]);

        $prescription = Prescription::with('medicines')->findOrFail($request->prescription_id);
        
        if ($prescription->status === 'Dispensed' || $prescription->status === 'Completed') {
            return response()->json(['message' => 'Prescription already dispensed.'], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($prescription->medicines as $med) {
                $medicine = Medicine::lockForUpdate()->findOrFail($med->medicine_id);
                if ($medicine->quantity < $med->quantity) {
                    throw new \Exception("Insufficient stock for {$medicine->medicine_name}.");
                }
                $medicine->decrement('quantity', $med->quantity);

                InventoryMovement::create([
                    'transaction_number' => 'DISP-' . time() . rand(10, 99),
                    'medicine_id' => $medicine->id,
                    'movement_type' => 'Pharmacy Sales',
                    'quantity' => $med->quantity,
                    'unit_cost' => $medicine->unit_price,
                    'total_cost' => $medicine->unit_price * $med->quantity,
                    'reference_type' => 'Prescription Dispense',
                    'reference_id' => $prescription->id,
                    'issued_by' => auth()->id(),
                ]);
            }

            $prescription->update(['status' => 'Dispensed']);

            DB::commit();
            AuditLogService::log('Dispensed prescription', 'Pharmacy', $prescription->id);

            return response()->json(['message' => 'Prescription dispensed successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }
}

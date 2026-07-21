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
        $query = PharmacySale::with(['creator', 'patient', 'prescription', 'medicines.medicine']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('sale_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($patientQuery) => $patientQuery->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('prescription', fn ($prescriptionQuery) => $prescriptionQuery->where('prescription_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->latest('created_at')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string',
            'patient_id' => 'nullable|exists:patients,id',
            'prescription_id' => 'nullable|exists:prescriptions,id',
            'payment_method' => ['required', Rule::in(['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer', 'Mixed Payment'])],
            'discount_type' => ['required', Rule::in(['None', 'Fixed', 'Percentage'])],
            'discount_value' => 'numeric|min:0',
            'tax_percent' => 'numeric|min:0',
            'amount_paid' => 'nullable|numeric|decimal:0,2|min:0',
            'notes' => 'nullable|string',
            'account_no' => 'required_if:payment_method,EVC Plus|required_if:payment_method,Zaad|required_if:payment_method,Sahal',
            'medicines' => 'required|array|min:1',
            'medicines.*.medicine_id' => 'required|distinct|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1',
            'medicines.*.prescription_medicine_id' => 'nullable|exists:prescription_medicines,id',
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
                    'prescription_medicine_id' => $medInput['prescription_medicine_id'] ?? null,
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
            $amountPaid = array_key_exists('amount_paid', $validated)
                ? round((float) $validated['amount_paid'], 2)
                : round((float) $totalAmount, 2);
            if ($amountPaid <= 0 || $amountPaid > $totalAmount) {
                throw new \Exception('Amount paid must be greater than zero and cannot exceed the sale total.');
            }
            $remainingBalance = round($totalAmount - $amountPaid, 2);

            $prescription = null;
            if (!empty($validated['prescription_id'])) {
                $prescription = Prescription::with('medicines')->lockForUpdate()->findOrFail($validated['prescription_id']);
                if (!$validated['patient_id'] || $prescription->patient_id !== (int) $validated['patient_id']) {
                    throw new \Exception('The selected prescription does not belong to this patient.');
                }
                foreach ($validated['medicines'] as $line) {
                    $rxLine = $prescription->medicines->firstWhere('id', $line['prescription_medicine_id'] ?? 0);
                    if (!$rxLine || $rxLine->medicine_id !== (int) $line['medicine_id']) {
                        throw new \Exception('A sale medicine does not belong to the selected prescription.');
                    }
                    if ($rxLine->dispensed_quantity + (int) $line['quantity'] > $rxLine->quantity) {
                        throw new \Exception('Dispensed quantity cannot exceed the prescribed quantity.');
                    }
                }
            }

            // Handle Mobile Payment via Waafi
            if (in_array($validated['payment_method'], ['EVC Plus', 'Zaad', 'Sahal'])) {
                $waafiResult = $this->waafi->charge(
                    $amountPaid,
                    $validated['account_no'], 
                    'PHR-' . uniqid(), 
                    'INV-' . uniqid(),
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
                'prescription_id' => $validated['prescription_id'] ?? null,
                'medicine_count' => count($validated['medicines']),
                'subtotal' => $subtotal,
                'discount_type' => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'discount_amount' => $discountAmount,
                'tax_percent' => $validated['tax_percent'],
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'remaining_balance' => $remainingBalance,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $remainingBalance > 0 ? 'Partial Paid' : 'Full Paid',
                'status' => $remainingBalance > 0 ? 'Partial Paid' : 'Paid',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Deduct stock and create lines/movements
            foreach ($saleMedicinesData as $data) {
                PharmacySaleMedicine::create([
                    'sale_id' => $sale->id,
                    'medicine_id' => $data['medicine_id'],
                    'prescription_medicine_id' => $data['prescription_medicine_id'],
                    'quantity' => $data['quantity'],
                    'frequency' => $prescription?->medicines->firstWhere('medicine_id', $data['medicine_id'])?->frequency,
                    'instructions' => $prescription?->medicines->firstWhere('medicine_id', $data['medicine_id'])?->instructions,
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

            if (!empty($validated['prescription_id'])) {
                foreach ($validated['medicines'] as $line) {
                    $prescription->medicines->firstWhere('id', $line['prescription_medicine_id'])
                        ->increment('dispensed_quantity', (int) $line['quantity']);
                }
                $prescription->refresh()->load('medicines');
                $fullyDispensed = $prescription->medicines->every(
                    fn ($item) => $item->dispensed_quantity >= $item->quantity
                );
                $prescription->update(['status' => $fullyDispensed ? 'Dispensed' : 'Partially Dispensed']);
            }

            DB::commit();
            AuditLogService::log('Created pharmacy sale', 'Pharmacy', $sale->id);

            return response()->json($sale->load(['medicines.medicine', 'patient', 'prescription.doctor']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error processing sale: ' . $e->getMessage()], 422);
        }
    }

    public function show(PharmacySale $sale): JsonResponse
    {
        $sale->load(['medicines.medicine', 'patient', 'creator', 'prescription.doctor']);
        return response()->json($sale);
    }

    public function receipt(PharmacySale $sale): JsonResponse
    {
        $sale->load(['medicines.medicine', 'patient', 'prescription.doctor']);
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

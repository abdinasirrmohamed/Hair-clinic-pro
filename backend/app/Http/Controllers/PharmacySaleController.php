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
            'customer_name' => 'nullable|string|max:150',
            'customer_id' => 'integer|nullable|exists:pharmacy_customers,id',
            'patient_id' => 'integer|nullable|exists:patients,id',
            'prescription_id' => 'integer|nullable|exists:prescriptions,id',
            'payment_method' => ['required', Rule::in(['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer', 'Mixed Payment'])],
            'discount_type' => ['required', Rule::in(['None', 'Fixed', 'Percentage'])],
            'discount_value' => 'required|numeric|decimal:0,2|min:0|max:99999999.99',
            'tax_percent' => 'required|numeric|min:0|max:100',
            'amount_paid' => 'nullable|numeric|decimal:0,2|min:0',
            'notes' => 'nullable|string',
            'account_no' => 'nullable|string|max:30|required_if:payment_method,EVC Plus|required_if:payment_method,Zaad|required_if:payment_method,Sahal',
            'medicines' => 'required|array|min:1',
            'medicines.*.medicine_id' => 'integer|required|distinct|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1|max:2147483647',
            'medicines.*.prescription_medicine_id' => 'integer|nullable|exists:prescription_medicines,id',
        ]);

        DB::beginTransaction();
        try {
            if (!empty($validated['patient_id'])) {
                \App\Models\Patient::lockForUpdate()->findOrFail($validated['patient_id'])->assertCanReceivePrescription();
            }
            if (!empty($validated['prescription_id'])) {
                Prescription::findOrFail($validated['prescription_id'])->patient()->lockForUpdate()->first()?->assertCanReceivePrescription();
            }
            $registerId = \App\Services\PharmacyWorkflowService::registerId();
            $subtotal = 0;
            $saleMedicinesData = [];
            $movementsData = [];

            // Calculate totals and lock medicines
            foreach (\App\Services\PharmacyWorkflowService::allocate($validated['medicines']) as $medInput) {
                $medicine = Medicine::where('id', $medInput['medicine_id'])->lockForUpdate()->first();
                
                if ($medicine->quantity < $medInput['quantity']) {
                    throw new \Exception("Insufficient stock for {$medicine->medicine_name}.");
                }

                if ($medicine->expiry_date && substr((string) $medicine->expiry_date, 0, 10) < today()->toDateString()) {
                    throw new \Exception("Cannot sell expired medicine: {$medicine->medicine_name}.");
                }

                $lineTotal = $medicine->unit_price * $medInput['quantity'];
                $subtotal += $lineTotal;

                $saleMedicinesData[] = [
                    'medicine_id' => $medicine->id,
                    'prescription_medicine_id' => $medInput['prescription_medicine_id'] ?? null,
                    'quantity' => $medInput['quantity'],
                    'unit_price' => $medicine->unit_price,
                    'unit_cost' => $medicine->buying_price,
                    'subtotal' => $lineTotal,
                    'medicine_model' => $medicine, // Temp storage for deduct step
                ];
            }

            // Calculate discount
            if ($validated['discount_type'] === 'Percentage' && $validated['discount_value'] > 100) {
                throw new \Exception('Percentage discount cannot exceed 100%.');
            }
            if ($validated['discount_type'] === 'Fixed' && $validated['discount_value'] > $subtotal) {
                throw new \Exception('Discount cannot exceed the subtotal.');
            }
            if ($subtotal > 99999999.99) throw new \Exception('Sale subtotal exceeds the supported maximum.');
            $discountAmount = 0;
            if ($validated['discount_type'] === 'Fixed') {
                $discountAmount = min($validated['discount_value'], $subtotal);
            } elseif ($validated['discount_type'] === 'Percentage') {
                $discountAmount = $subtotal * (min($validated['discount_value'], 100) / 100);
            }

            $afterDiscount = $subtotal - $discountAmount;
            
            // Calculate tax
            $taxAmount = $afterDiscount * ($validated['tax_percent'] / 100);
            
            $totalAmount = round($afterDiscount + $taxAmount, 2);
            if ($totalAmount > 99999999.99) throw new \Exception('Sale total exceeds the supported maximum.');
            $amountPaid = isset($validated['amount_paid'])
                ? round((float) $validated['amount_paid'], 2)
                : round((float) $totalAmount, 2);
            if ($amountPaid <= 0 || $amountPaid > $totalAmount) {
                throw new \Exception('Amount paid must be greater than zero and cannot exceed the sale total.');
            }
            $remainingBalance = round($totalAmount - $amountPaid, 2);
            if ($remainingBalance > 0 && empty($validated['customer_id']) && empty($validated['patient_id'])) {
                throw new \Exception('Select a registered customer or patient for a partial payment.');
            }

            $prescription = null;
            if (!empty($validated['prescription_id'])) {
                $prescription = Prescription::with('medicines')->lockForUpdate()->findOrFail($validated['prescription_id']);
                if (($prescription->customer_id && (int) $prescription->customer_id !== (int) ($validated['customer_id'] ?? 0)) || ($prescription->patient_id && (int) $prescription->patient_id !== (int) ($validated['patient_id'] ?? 0))) {
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

            if (!$prescription && collect($validated['medicines'])->contains(fn ($line) => !empty($line['prescription_medicine_id']))) {
                throw new \Exception('Select a prescription before dispensing its medicines.');
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
                'sale_number' => 'SALE-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(16)),
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => !empty($validated['customer_id']) ? \App\Models\PharmacyCustomer::findOrFail($validated['customer_id'])->full_name : ($validated['customer_name'] ?? null),
                'patient_id' => $validated['patient_id'] ?? null,
                'prescription_id' => $validated['prescription_id'] ?? null,
                'medicine_count' => count($saleMedicinesData),
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

            DB::table('pharmacy_sale_payments')->insert([
                'sale_id' => $sale->id, 'register_id' => $registerId, 'amount' => $amountPaid, 'payment_method' => $validated['payment_method'],
                'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            // Deduct stock and create lines/movements
            foreach ($saleMedicinesData as $data) {
                PharmacySaleMedicine::create([
                    'sale_id' => $sale->id,
                    'medicine_id' => $data['medicine_id'],
                    'prescription_medicine_id' => $data['prescription_medicine_id'],
                    'quantity' => $data['quantity'],
                    'frequency' => $prescription?->medicines->firstWhere('id', $data['prescription_medicine_id'])?->frequency,
                    'instructions' => $prescription?->medicines->firstWhere('id', $data['prescription_medicine_id'])?->instructions,
                    'unit_price' => $data['unit_price'],
                    'unit_cost' => $data['unit_cost'],
                    'subtotal' => $data['subtotal'],
                ]);

                $medicine = $data['medicine_model']->fresh();
                $medicine->decrement('quantity', $data['quantity']);

                InventoryMovement::create([
                    'transaction_number' => 'MV-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(16)),
                    'medicine_id' => $medicine->id,
                    'movement_type' => 'Pharmacy Sales',
                    'quantity' => $data['quantity'],
                    'old_quantity' => $medicine->quantity + $data['quantity'],
                    'new_quantity' => $medicine->quantity,
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
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
            $registerId = \App\Services\PharmacyWorkflowService::registerId();
            $sale = PharmacySale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->status === 'Returned') { throw new \Exception('Sale is already returned.'); }
            $sale->update([
                'status' => 'Returned',
                'returned_at' => now(),
                'return_reason' => $request->return_reason,
            ]);

            // Cash refunds affect the drawer that processes the return, even for older sales.
            $cashRefund = DB::table('pharmacy_sale_payments')->where('sale_id',$sale->id)->where('payment_method','Cash')->sum('amount');
            if ($cashRefund > 0) DB::table('pharmacy_refunds')->insert(['sale_id'=>$sale->id,'register_id'=>$registerId,
                'amount'=>$cashRefund,'payment_method'=>'Cash','created_at'=>now()]);
            $saleMedicines = PharmacySaleMedicine::where('sale_id', $sale->id)->get();
            
            foreach ($saleMedicines as $item) {
                Medicine::where('id', $item->medicine_id)->increment('quantity', $item->quantity);
                
                InventoryMovement::create([
                    'transaction_number' => 'RET-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(16)),
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

            if ($sale->prescription_id) {
                $rx = Prescription::lockForUpdate()->findOrFail($sale->prescription_id);
                foreach ($saleMedicines as $item) {
                    if ($item->prescription_medicine_id) {
                        $rx->medicines()->where('id', $item->prescription_medicine_id)->decrement('dispensed_quantity', $item->quantity);
                    }
                }
                $rx->load('medicines');
                $rx->update(['status' => $rx->medicines->sum('dispensed_quantity') > 0 ? 'Partially Dispensed' : 'Pending']);
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
            'prescription_id' => 'integer|required|exists:prescriptions,id'
        ]);

        $prescription = Prescription::with('medicines')->findOrFail($request->prescription_id);
        
        if ($prescription->status === 'Dispensed' || $prescription->status === 'Completed') {
            return response()->json(['message' => 'Prescription already dispensed.'], 400);
        }

        DB::beginTransaction();
        try {
            $prescription = Prescription::lockForUpdate()->findOrFail($prescription->id);
            $prescription->patient()->lockForUpdate()->first()?->assertCanReceivePrescription();
            $prescription->load('medicines');
            if (in_array($prescription->status, ['Dispensed', 'Completed'])) { throw new \Exception('Prescription already dispensed.'); }
            foreach ($prescription->medicines as $med) {
                $remaining = $med->quantity - $med->dispensed_quantity;
                if ($remaining <= 0) { continue; }
                foreach (\App\Services\PharmacyWorkflowService::allocate([['medicine_id'=>$med->medicine_id,'quantity'=>$remaining]]) as $allocation) {
                    $medicine = Medicine::lockForUpdate()->findOrFail($allocation['medicine_id']);
                    $old = $medicine->quantity;
                    $medicine->decrement('quantity', $allocation['quantity']);
                    InventoryMovement::create([
                    'transaction_number' => 'DISP-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(16)),
                    'medicine_id' => $medicine->id,
                    'movement_type' => 'Pharmacy Sales',
                    'quantity' => $allocation['quantity'],
                    'old_quantity' => $old,
                    'new_quantity' => $medicine->quantity,
                    'unit_cost' => $medicine->unit_price,
                    'total_cost' => $medicine->unit_price * $allocation['quantity'],
                    'reference_type' => 'Prescription Dispense',
                    'reference_id' => $prescription->id,
                    'issued_by' => auth()->id(),
                ]);
                }
                $med->increment('dispensed_quantity', $remaining);
            }

            $prescription->update(['status' => 'Dispensed']);

            DB::commit();
            AuditLogService::log('Dispensed prescription', 'Pharmacy', $prescription->id);

            return response()->json(['message' => 'Prescription dispensed successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }
}

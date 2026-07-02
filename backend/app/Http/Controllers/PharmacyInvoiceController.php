<?php

namespace App\Http\Controllers;

use App\Models\PharmacyInvoice;
use App\Models\PharmacyInvoiceItem;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PharmacyInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PharmacyInvoice::with(['patient', 'creator']);
        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'payment_method' => ['required', Rule::in(['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'])],
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += ($item['quantity'] * $item['unit_price']);
            }

            $invoice = PharmacyInvoice::create([
                'invoice_number' => 'INV-PH-' . date('Ymd') . '-' . rand(1000, 9999),
                'patient_id' => $validated['patient_id'],
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'Pending', // Adjust based on business logic
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                PharmacyInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'medicine_id' => $item['medicine_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();
            AuditLogService::log('Created pharmacy invoice', 'Pharmacy', $invoice->id);

            return response()->json($invoice, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }

    public function show(PharmacyInvoice $invoice): JsonResponse
    {
        $invoice->load(['patient', 'items.medicine', 'payments']);
        return response()->json($invoice);
    }
}

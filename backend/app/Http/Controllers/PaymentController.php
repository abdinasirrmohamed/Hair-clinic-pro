<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentGatewayLog;
use App\Models\Receipt;
use App\Services\AuditLogService;
use App\Services\WaafiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private WaafiPaymentService $waafi;

    public function __construct(WaafiPaymentService $waafi)
    {
        $this->waafi = $waafi;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['patient', 'appointment']);
        return response()->json($query->paginate(15));
    }

    public function gatewayLogs(): JsonResponse
    {
        return response()->json(
            PaymentGatewayLog::with('creator:id,full_name')
                ->latest()
                ->paginate(25)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::in(['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'])],
            'payment_status' => ['required', Rule::in(['Paid', 'Partial', 'Outstanding'])],
            'reference_number' => 'nullable|string',
            'account_no' => 'required_if:payment_method,EVC Plus|required_if:payment_method,Sahal',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            if (in_array($validated['payment_method'], ['EVC Plus', 'Sahal'])) {
                $waafiResult = $this->waafi->charge(
                    $validated['amount'], 
                    $validated['account_no'], 
                    'REF-' . time(), 
                    'INV-' . time()
                );
                
                if (!$waafiResult['success']) {
                    throw new \Exception($waafiResult['message']);
                }
                
                $validated['reference_number'] = $waafiResult['transaction_id'];
                $validated['payment_status'] = 'Paid';
            }

            $validated['created_by'] = auth()->id();
            $payment = Payment::create($validated);

            Receipt::create([
                'payment_id' => $payment->id,
                'receipt_number' => 'RCP-' . date('Ymd') . '-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT),
            ]);

            DB::commit();
            AuditLogService::log('Recorded payment', 'Payments', $payment->id);

            return response()->json($payment, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['patient', 'appointment', 'receipt', 'creator']);
        return response()->json($payment);
    }

    public function receipt(Payment $payment): JsonResponse
    {
        $payment->load(['patient', 'appointment', 'receipt']);
        return response()->json($payment->receipt);
    }
}

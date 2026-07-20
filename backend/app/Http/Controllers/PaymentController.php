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
use Illuminate\Support\Str;
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
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:99999999.99',
            'payment_method' => ['required', Rule::in(['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer'])],
            'payment_status' => ['required', Rule::in(['Paid', 'Partial', 'Outstanding'])],
            'reference_number' => 'nullable|string',
            'account_no' => 'nullable|required_if:payment_method,EVC Plus|required_if:payment_method,Zaad|required_if:payment_method,Sahal|string|max:30',
            'notes' => 'nullable|string',
        ]);

        if ($validated['appointment_id'] ?? null) {
            $belongsToPatient = \App\Models\Appointment::whereKey($validated['appointment_id'])
                ->where('patient_id', $validated['patient_id'])
                ->exists();
            if (!$belongsToPatient) {
                return response()->json(['message' => 'The selected appointment does not belong to this patient.'], 422);
            }
        }

        $mobilePayment = in_array($validated['payment_method'], ['EVC Plus', 'Zaad', 'Sahal'], true);
        if ($mobilePayment && $validated['payment_status'] !== 'Paid') {
            return response()->json(['message' => 'Mobile wallet payments must be fully paid before they can be recorded.'], 422);
        }

        $referenceId = 'PAY-' . Str::uuid();
        if ($mobilePayment) {
            $waafiResult = $this->waafi->charge(
                (float) $validated['amount'],
                $validated['account_no'],
                $referenceId,
                'INV-' . Str::uuid(),
                'Patient payment'
            );

            if (!$waafiResult['success']) {
                return response()->json([
                    'message' => $waafiResult['message'],
                    'code' => $waafiResult['response_code'],
                ], 422);
            }

            $validated['reference_number'] = $waafiResult['transaction_id'] ?: $referenceId;
        } elseif (empty($validated['reference_number'])) {
            $validated['reference_number'] = $referenceId;
        }

        unset($validated['account_no']);

        DB::beginTransaction();
        try {
            $validated['created_by'] = auth()->id();
            $validated['paid_at'] = in_array($validated['payment_status'], ['Paid', 'Partial'], true) ? now() : null;
            $payment = Payment::create($validated);

            Receipt::create([
                'payment_id' => $payment->id,
                'receipt_number' => 'RCP-' . date('Ymd') . '-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT),
            ]);

            DB::commit();
            AuditLogService::log('Recorded payment', 'Payments', $payment->id);

            return response()->json([
                'message' => 'Payment recorded successfully.',
                'payment' => $payment->load(['patient', 'appointment', 'receipt']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'The payment could not be saved. No local transaction was recorded.'], 500);
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

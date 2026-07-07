<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PatientController extends Controller
{
    private function scopeForDoctor($query)
    {
        if (auth()->check() && auth()->user()->role === 'Doctor') {
            $doctorId = auth()->user()->doctor?->id;
            $query->where('assigned_doctor_id', $doctorId ?? 0);
        }
        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Patient::query();
        $query = $this->scopeForDoctor($query);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string',
            'phone' => 'required|string',
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'age' => 'nullable|integer|min:0|max:120',
            'email' => 'nullable|email',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'medical_notes' => 'nullable|string',
            'assigned_doctor_id' => 'nullable|exists:doctors,id'
        ]);

        if (auth()->user()->role === 'Doctor') {
            $validated['assigned_doctor_id'] = auth()->user()->doctor?->id;
        }

        $patient = Patient::create($validated);
        AuditLogService::log('Created patient', 'Patients', $patient->id);

        return response()->json($patient, 201);
    }

    public function show(Patient $patient): JsonResponse
    {
        if (auth()->user()->role === 'Doctor' && $patient->assigned_doctor_id !== auth()->user()->doctor?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $patient->load(['appointments.doctor', 'treatments', 'followups', 'payments', 'prescriptions']);
        return response()->json($patient);
    }

    public function timeline(Patient $patient): JsonResponse
    {
        if (auth()->user()->role === 'Doctor' && $patient->assigned_doctor_id !== auth()->user()->doctor?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $events = collect();

        $patient->appointments()->with(['doctor', 'payment'])->get()->each(function ($appointment) use ($events) {
            $events->push([
                'date' => $appointment->appointment_date,
                'type' => 'Appointment',
                'title' => $appointment->doctor?->full_name,
                'description' => "{$appointment->appointment_time} - {$appointment->status}",
                'amount' => $appointment->fee_at_booking,
            ]);
        });

        $patient->payments()->get()->each(function ($payment) use ($events) {
            $events->push([
                'date' => Carbon::parse($payment->paid_at ?? $payment->created_at)->toDateString(),
                'type' => 'Payment',
                'title' => $payment->payment_method,
                'description' => $payment->payment_status,
                'amount' => $payment->amount,
            ]);
        });

        $patient->prescriptions()->get()->each(function ($prescription) use ($events) {
            $events->push([
                'date' => optional($prescription->created_at)->toDateString(),
                'type' => 'Prescription',
                'title' => $prescription->prescription_number,
                'description' => $prescription->status,
                'amount' => null,
            ]);
        });

        $patient->treatments()->get()->each(function ($treatment) use ($events) {
            $events->push([
                'date' => $treatment->treatment_date,
                'type' => 'Treatment',
                'title' => $treatment->treatment_name,
                'description' => $treatment->progress,
                'amount' => $treatment->cost,
            ]);
        });

        return response()->json([
            'patient' => $patient,
            'events' => $events->sortByDesc('date')->values(),
        ]);
    }

    public function update(Request $request, Patient $patient): JsonResponse
    {
        if (auth()->user()->role === 'Doctor' && $patient->assigned_doctor_id !== auth()->user()->doctor?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'full_name' => 'string',
            'phone' => 'string',
            'gender' => [Rule::in(['Male', 'Female', 'Other'])],
            'age' => 'nullable|integer|min:0|max:120',
            'email' => 'nullable|email',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'medical_notes' => 'nullable|string',
            'assigned_doctor_id' => 'nullable|exists:doctors,id'
        ]);

        $patient->update($validated);
        AuditLogService::log('Updated patient', 'Patients', $patient->id);

        return response()->json($patient);
    }

    public function destroy(Patient $patient): JsonResponse
    {
        if (auth()->user()->role === 'Doctor') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $patient->delete();
        AuditLogService::log('Deleted patient', 'Patients', $patient->id);
        return response()->json(null, 204);
    }
}

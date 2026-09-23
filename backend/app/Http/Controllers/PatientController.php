<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\LabRequest;
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
                if (preg_match('/^(?:PAT-)?0*(\d+)$/i', trim($search), $matches)) {
                    $q->orWhereKey((int) $matches[1]);
                }
            });
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:30',
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'age' => 'nullable|integer|min:0|max:120',
            'email' => 'nullable|email|max:150',
            'date_of_birth' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subYears(121)->toDateString(),
            'address' => 'required|string|max:255',
            'medical_notes' => 'nullable|string',
            'assigned_doctor_id' => 'integer|required|exists:doctors,id',
            'status' => 'prohibited',
        ], $this->registrationMessages());
        $validated['age'] = $this->resolveAge($validated['date_of_birth'] ?? null, $validated['age'] ?? null);

        if (auth()->user()->role === 'Doctor') {
            abort_unless(auth()->user()->doctor, 422, 'Your account must have a doctor profile before registering patients.');
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

        $patient->load(['appointments.doctor', 'followups', 'payments', 'prescriptions']);
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

        LabRequest::with('test')->where('patient_id', $patient->id)->get()->each(function ($lab) use ($events) {
            $events->push([
                'date' => $lab->request_date,
                'type' => 'Lab Test',
                'title' => $lab->test?->test_name,
                'description' => $lab->status,
                'amount' => $lab->test?->price,
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
            'full_name' => 'string|max:150',
            'phone' => 'string|max:30',
            'gender' => [Rule::in(['Male', 'Female'])],
            'age' => 'nullable|integer|min:0|max:120',
            'email' => 'nullable|email|max:150',
            'date_of_birth' => 'sometimes|required|date|before_or_equal:today|after_or_equal:' . now()->subYears(121)->toDateString(),
            'address' => 'sometimes|required|string|max:255',
            'medical_notes' => 'nullable|string',
            'assigned_doctor_id' => 'integer|sometimes|required|exists:doctors,id',
            'status' => ['sometimes', 'required', Rule::in(['Pending', 'Accepted', 'Approved'])],
        ], $this->registrationMessages());
        if (isset($validated['status']) && $validated['status'] !== $patient->status) {
            abort_unless(in_array(auth()->user()->role, ['Administrator', 'Doctor'], true), 403, 'Only a doctor or administrator can change patient approval status.');
        }
        if ($request->exists('date_of_birth')) {
            $validated['age'] = $this->resolveAge($validated['date_of_birth'] ?? null, null);
        } elseif ($patient->date_of_birth) {
            // Never accept a manually supplied age when a date of birth is already stored.
            $validated['age'] = $this->resolveAge($patient->date_of_birth->toDateString(), null);
        }

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

    private function resolveAge(?string $dateOfBirth, ?int $fallbackAge): ?int
    {
        return $dateOfBirth
            ? Carbon::parse($dateOfBirth)->startOfDay()->age
            : $fallbackAge;
    }

    private function registrationMessages(): array
    {
        return [
            'date_of_birth.required' => 'Date of Birth is required.',
            'address.required' => 'Address is required.',
            'assigned_doctor_id.required' => 'Assigned Doctor is required.',
            'assigned_doctor_id.exists' => 'Please select a valid Assigned Doctor.',
            'status.prohibited' => 'New patients start as Pending and must be approved after registration.',
        ];
    }
}

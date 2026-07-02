<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    private function isDoctorSlotAvailable($doctorId, $date, $time, $excludeId = null): array
    {
        $dayOfWeek = Carbon::parse($date)->format('l');
        $schedule = DoctorSchedule::where('doctor_id', $doctorId)
                                  ->where('day_of_week', $dayOfWeek)
                                  ->first();

        if (!$schedule || !$schedule->is_working) {
            return [false, 'Doctor is not working on this day.'];
        }

        // Simplistic check for availability
        $exists = Appointment::where('doctor_id', $doctorId)
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->whereIn('status', ['Pending', 'Approved'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($exists) {
            return [false, 'Slot is already booked.'];
        }

        return [true, 'Available'];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Appointment::with(['patient', 'doctor']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('patient', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_mode' => 'required|in:existing,new',
            'patient_id' => 'required_if:patient_mode,existing|exists:patients,id',
            'new_full_name' => 'required_if:patient_mode,new|string',
            'new_phone' => 'required_if:patient_mode,new|string',
            'new_gender' => ['required_if:patient_mode,new', Rule::in(['Male', 'Female', 'Other'])],
            'doctor_id' => 'required|exists:doctors,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'reason' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $patientId = $request->patient_id;

            if ($request->patient_mode === 'new') {
                $patient = Patient::create([
                    'full_name' => $request->new_full_name,
                    'phone' => $request->new_phone,
                    'gender' => $request->new_gender,
                ]);
                $patientId = $patient->id;
            }

            [$available, $msg] = $this->isDoctorSlotAvailable($request->doctor_id, $request->appointment_date, $request->appointment_time);
            
            if (!$available) {
                DB::rollBack();
                return response()->json(['message' => $msg], 422);
            }

            $appointment = Appointment::create([
                'patient_id' => $patientId,
                'doctor_id' => $request->doctor_id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'reason' => $request->reason,
                'status' => 'Pending',
            ]);

            DB::commit();
            AuditLogService::log('Booked appointment', 'Appointments', $appointment->id);

            return response()->json($appointment, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error booking appointment: ' . $e->getMessage()], 500);
        }
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load(['patient', 'doctor']);
        return response()->json($appointment);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'appointment_date' => 'date',
            'appointment_time' => 'string',
            'reason' => 'string',
            'status' => [Rule::in(['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'])],
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        if ($request->has('status') && $request->status !== $appointment->status) {
            if ($request->status === 'Approved') $validated['approved_at'] = now();
            if ($request->status === 'Rejected') $validated['rejected_at'] = now();
            if ($request->status === 'Completed') $validated['completed_at'] = now();
        }

        $appointment->update($validated);
        AuditLogService::log('Updated appointment', 'Appointments', $appointment->id);

        return response()->json($appointment);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();
        AuditLogService::log('Deleted appointment', 'Appointments', $appointment->id);
        return response()->json(null, 204);
    }

    public function reminders(): JsonResponse
    {
        $appointments = Appointment::with('patient')
            ->where('reminder_sent', 0)
            ->whereIn('status', ['Approved'])
            ->whereBetween('appointment_date', [today(), today()->addDay()])
            ->get();
            
        return response()->json($appointments);
    }
}

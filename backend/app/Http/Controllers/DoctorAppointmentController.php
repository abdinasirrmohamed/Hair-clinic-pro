<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorAppointmentController extends Controller
{
    private function getDoctorId()
    {
        return Doctor::where('user_id', auth()->id())->value('id');
    }

    private function slotAvailable(int $doctorId, string $date, string $time, ?int $excludeId = null): array
    {
        $dayOfWeek = Carbon::parse($date)->format('l');
        $schedule = DoctorSchedule::where('doctor_id', $doctorId)->where('day_of_week', $dayOfWeek)->first();

        if (!$schedule || !$schedule->is_working) {
            return [false, 'Doctor is not working on this day.'];
        }

        $slot = Carbon::parse($time);
        if ($slot->lt(Carbon::parse($schedule->start_time)) || $slot->gte(Carbon::parse($schedule->end_time))) {
            return [false, 'Selected time is outside doctor working hours.'];
        }

        $exists = Appointment::where('doctor_id', $doctorId)
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->whereIn('status', ['Pending', 'Approved'])
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();

        return $exists ? [false, 'Slot is already booked.'] : [true, 'Available'];
    }

    public function index(Request $request): JsonResponse
    {
        $doctorId = $this->getDoctorId();
        
        if (!$doctorId) {
            return response()->json(['message' => 'Doctor profile not found. Ask admin to link this user to a doctor profile.'], 404);
        }

        $query = Appointment::with('patient')->where('doctor_id', $doctorId);

        if ($request->filter === 'today') {
            $query->whereDate('appointment_date', today());
        } elseif ($request->filter === 'pending') {
            $query->where('status', 'Pending');
        }

        $appointments = $query->paginate(15);
        $myPatients = Patient::where('assigned_doctor_id', $doctorId)->get();

        return response()->json([
            'appointments' => $appointments,
            'my_patients' => $myPatients,
            'counts' => [
                'today' => Appointment::where('doctor_id', $doctorId)->whereDate('appointment_date', today())->count(),
                'pending' => Appointment::where('doctor_id', $doctorId)->where('status', 'Pending')->count(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $doctorId = $this->getDoctorId();

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
            'reason' => 'required|string',
        ]);

        $validated['doctor_id'] = $doctorId;
        $validated['status'] = 'Approved';
        $validated['approved_at'] = now();

        [$available, $msg] = $this->slotAvailable($doctorId, $validated['appointment_date'], $validated['appointment_time']);
        if (!$available) {
            return response()->json(['message' => $msg], 422);
        }

        $appointment = Appointment::create($validated);
        AuditLogService::log('Created doctor appointment', 'Doctor Appointments', $appointment->id);

        return response()->json($appointment, 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->doctor_id !== $this->getDoctorId()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'appointment_date' => 'date|after_or_equal:today',
            'appointment_time' => 'string',
            'reason' => 'string',
            'notes' => 'nullable|string',
        ]);

        $date = $validated['appointment_date'] ?? $appointment->appointment_date;
        $time = $validated['appointment_time'] ?? $appointment->appointment_time;
        [$available, $msg] = $this->slotAvailable($appointment->doctor_id, $date, $time, $appointment->id);
        if (!$available) {
            return response()->json(['message' => $msg], 422);
        }

        $appointment->update($validated);
        AuditLogService::log('Updated doctor appointment', 'Doctor Appointments', $appointment->id);

        return response()->json($appointment);
    }

    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->doctor_id !== $this->getDoctorId()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected,Completed',
            'remarks' => 'nullable|string',
        ]);

        if ($validated['status'] === 'Approved') $validated['approved_at'] = now();
        if ($validated['status'] === 'Rejected') $validated['rejected_at'] = now();
        if ($validated['status'] === 'Completed') $validated['completed_at'] = now();

        $appointment->update($validated);
        AuditLogService::log('Status changed', 'Doctor Appointments', $appointment->id);

        return response()->json($appointment);
    }

    public function cancel(Appointment $appointment): JsonResponse
    {
        if ($appointment->doctor_id !== $this->getDoctorId()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appointment->update(['status' => 'Cancelled']);
        AuditLogService::log('Cancelled appointment', 'Doctor Appointments', $appointment->id);

        return response()->json($appointment);
    }
}

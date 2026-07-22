<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\AuditLogService;
use App\Services\WaafiPaymentService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    private function isDoctorSlotAvailable($doctorId, $date, $time, $excludeId = null): array
    {
        $dayOfWeek = Carbon::parse($date)->format('l');
        $schedule = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_working', true)
            ->whereTime('start_time', '<=', $time)
            ->whereTime('end_time', '>', $time)
            ->first();

        if (!$schedule) {
            return [false, 'Selected time is outside the doctor registered working schedule.'];
        }

        $slotTime = Carbon::parse($time);
        $startTime = Carbon::parse($schedule->start_time);
        $endTime = Carbon::parse($schedule->end_time);
        if ($slotTime->lt($startTime) || $slotTime->gte($endTime)) {
            return [false, 'Selected time is outside doctor working hours.'];
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
        $query = Appointment::with(['patient', 'doctor', 'payment.patient', 'payment.receipt', 'payment.creator']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('patient', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function availableSlots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
        ]);

        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');
        $schedules = DoctorSchedule::where('doctor_id', $validated['doctor_id'])
            ->where('day_of_week', $dayOfWeek)
            ->where('is_working', true)
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json([
                'slots' => [],
                'message' => 'Doctor is not working on this day.',
            ]);
        }

        $booked = Appointment::where('doctor_id', $validated['doctor_id'])
            ->where('appointment_date', $validated['appointment_date'])
            ->whereIn('status', ['Pending', 'Approved'])
            ->pluck('appointment_time')
            ->map(fn ($time) => Carbon::parse($time)->format('H:i'))
            ->all();

        $slots = [];
        $workingHours = [];
        foreach ($schedules as $schedule) {
            $cursor = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);
            $minutes = max(5, (int) $schedule->slot_minutes);
            $shiftSlots = 0;
            while ($cursor->lt($end)) {
                $time = $cursor->format('H:i');
                if (!in_array($time, $booked, true)) {
                    $slots[] = [
                        'time' => $time,
                        'label' => "{$schedule->shift} - ".$cursor->format('h:i A'),
                        'shift' => $schedule->shift,
                    ];
                    $shiftSlots++;
                }
                $cursor->addMinutes($minutes);
            }
            $totalMinutes = max(0, Carbon::parse($schedule->start_time)->diffInMinutes(Carbon::parse($schedule->end_time)));
            $workingHours[] = [
                'shift' => $schedule->shift,
                'start' => Carbon::parse($schedule->start_time)->format('H:i'),
                'end' => Carbon::parse($schedule->end_time)->format('H:i'),
                'slot_minutes' => $minutes,
                'capacity' => intdiv($totalMinutes, $minutes),
                'available' => $shiftSlots,
            ];
        }

        return response()->json([
            'slots' => $slots,
            'working_hours' => $workingHours,
        ]);
    }

    public function doctorSchedules(Doctor $doctor): JsonResponse
    {
        abort_unless($doctor->status === 'Active', 404, 'Doctor is not active.');

        return response()->json([
            'schedules' => $doctor->schedules()
                ->where('is_working', true)
                ->orderByRaw("CASE day_of_week WHEN 'Saturday' THEN 1 WHEN 'Sunday' THEN 2 WHEN 'Monday' THEN 3 WHEN 'Tuesday' THEN 4 WHEN 'Wednesday' THEN 5 WHEN 'Thursday' THEN 6 WHEN 'Friday' THEN 7 ELSE 8 END")
                ->orderBy('start_time')
                ->get(['id', 'doctor_id', 'day_of_week', 'shift', 'start_time', 'end_time', 'slot_minutes', 'is_working']),
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        $appointments = Appointment::with(['patient', 'doctor', 'payment'])
            ->whereBetween('appointment_date', [$from, $to])
            ->when($request->filled('doctor_id'), fn ($query) => $query->where('doctor_id', $request->doctor_id))
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();

        return response()->json($appointments->map(fn ($appointment) => [
            'id' => $appointment->id,
            'date' => $appointment->appointment_date,
            'time' => substr($appointment->appointment_time, 0, 5),
            'status' => $appointment->status,
            'patient' => $appointment->patient?->full_name,
            'doctor' => $appointment->doctor?->full_name,
            'fee' => (float) $appointment->fee_at_booking,
            'payment_status' => $appointment->payment?->payment_status,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_mode' => 'required|in:existing,new',
            'patient_id' => 'required_if:patient_mode,existing|exists:patients,id',
            'new_full_name' => 'required_if:patient_mode,new|string',
            'new_phone' => 'required_if:patient_mode,new|string',
            'new_gender' => ['required_if:patient_mode,new', Rule::in(['Male', 'Female'])],
            'doctor_id' => 'required|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
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
            $this->notifications->appointmentConfirmation($appointment);

            return response()->json($appointment, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error booking appointment: ' . $e->getMessage()], 500);
        }
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_mode' => 'required|in:existing,new',
            'patient_id' => 'nullable|required_if:patient_mode,existing|exists:patients,id',
            'patient_name' => 'nullable|required_if:patient_mode,new|string|max:150',
            'patient_phone' => 'nullable|required_if:patient_mode,new|string|max:30',
            'gender' => ['nullable', 'required_if:patient_mode,new', Rule::in(['Male', 'Female'])],
            'age' => 'nullable|integer|min:0|max:120',
            'address' => 'nullable|string|max:255',
            'doctor_id' => 'required|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'payment_method' => ['required', Rule::in(['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer'])],
            'payment_status' => ['required', Rule::in(['Full Paid', 'Partial Paid'])],
            'paid_amount' => 'nullable|required_if:payment_status,Partial Paid|numeric|decimal:0,2|min:0.01',
            'account_no' => 'nullable|string|max:30',
            'payment_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $doctor = Doctor::where('status', 'Active')->find($validated['doctor_id']);
            if (!$doctor) {
                DB::rollBack();
                return response()->json(['message' => 'Selected doctor is not active or does not exist.'], 422);
            }

            $fee = (float) $doctor->consultation_fee;
            if ($fee <= 0) {
                DB::rollBack();
                return response()->json(['message' => 'Selected doctor does not have a consultation fee configured.'], 422);
            }
            $paidAmount = $validated['payment_status'] === 'Full Paid'
                ? $fee
                : round((float) $validated['paid_amount'], 2);
            if ($paidAmount <= 0 || $paidAmount > $fee) {
                DB::rollBack();
                return response()->json(['message' => 'Paid amount must be greater than zero and cannot exceed the appointment total.'], 422);
            }
            if ($validated['payment_status'] === 'Partial Paid' && $paidAmount >= $fee) {
                DB::rollBack();
                return response()->json(['message' => 'Partial Paid amount must be less than the appointment total.'], 422);
            }
            $remainingAmount = round($fee - $paidAmount, 2);

            [$available, $msg] = $this->isDoctorSlotAvailable(
                $doctor->id,
                $validated['appointment_date'],
                $validated['appointment_time']
            );

            if (!$available) {
                DB::rollBack();
                return response()->json(['message' => $msg], 422);
            }

            if ($validated['patient_mode'] === 'new') {
                $patient = Patient::create([
                    'full_name' => $validated['patient_name'],
                    'phone' => $validated['patient_phone'],
                    'gender' => $validated['gender'],
                    'age' => $validated['age'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'assigned_doctor_id' => $doctor->id,
                ]);
            } else {
                $patient = Patient::findOrFail($validated['patient_id']);
                $patient->update(['assigned_doctor_id' => $doctor->id]);
            }

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_date' => $validated['appointment_date'],
                'appointment_time' => $validated['appointment_time'],
                'fee_at_booking' => $fee,
                'reason' => 'Consultation booking',
                'status' => 'Pending',
            ]);

            $referenceNumber = 'APT-' . date('Ymd') . '-' . str_pad($appointment->id, 5, '0', STR_PAD_LEFT);
            $mobileMethods = ['EVC Plus', 'Zaad', 'Sahal'];
            if (in_array($validated['payment_method'], $mobileMethods, true)) {
                if (empty($validated['account_no'])) {
                    DB::rollBack();
                    return response()->json(['message' => 'Mobile account number is required for paid mobile payments.'], 422);
                }

                $waafiResult = app(WaafiPaymentService::class)->charge(
                    $paidAmount,
                    $validated['account_no'],
                    $referenceNumber,
                    'INV-' . $appointment->id,
                    'Appointment booking fee'
                );

                if (!$waafiResult['success']) {
                    DB::rollBack();
                    return response()->json(['message' => $waafiResult['message']], 422);
                }

                $referenceNumber = $waafiResult['transaction_id'] ?: $referenceNumber;
            }

            $payment = Payment::create([
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'amount' => $paidAmount,
                'total_amount' => $fee,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'],
                'paid_at' => now(),
                'reference_number' => $referenceNumber,
                'notes' => $validated['payment_notes'] ?? 'Appointment booking fee',
                'created_by' => auth()->id(),
            ]);

            Receipt::create([
                'payment_id' => $payment->id,
                'receipt_number' => 'RCP-' . date('Ymd') . '-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT),
            ]);

            DB::commit();
            AuditLogService::log('Booked appointment with payment', 'Appointments', $appointment->id);
            $this->notifications->appointmentConfirmation($appointment);
            $this->notifications->paymentConfirmation($payment);

            return response()->json([
                'message' => 'Appointment booked successfully.',
                'patient' => $patient,
                'appointment' => $appointment->load(['patient', 'doctor', 'payment.receipt']),
                'payment' => $payment->load(['patient', 'appointment', 'receipt', 'creator']),
            ], 201);
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
            'appointment_date' => 'date|after_or_equal:today',
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

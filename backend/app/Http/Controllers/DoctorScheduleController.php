<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorBlockedDate;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DoctorScheduleController extends Controller
{
    public function index(Doctor $doctor): JsonResponse
    {
        return response()->json([
            'schedules' => $doctor->schedules,
            'blocked_dates' => $doctor->blockedDates,
        ]);
    }

    public function update(Request $request, Doctor $doctor): JsonResponse
    {
        $validated = $request->validate([
            'schedules' => 'required|array',
            'schedules.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('doctor_schedules', 'id')->where('doctor_id', $doctor->id)],
            'schedules.*.day_of_week' => ['required', Rule::in(['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'])],
            'schedules.*.shift' => ['required', Rule::in(['Morning', 'Afternoon'])],
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
            'schedules.*.slot_minutes' => 'required|integer|min:5|max:240',
            'schedules.*.is_working' => 'required|boolean',
        ]);

        DB::transaction(function () use ($doctor, $validated) {
            $keys = collect($validated['schedules'])->map(fn ($row) => $row['day_of_week'].'|'.$row['shift']);
            if ($keys->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['schedules' => 'The same working day and shift cannot be added more than once.']);
            }
            foreach ($validated['schedules'] as $row) {
                $duplicate = $doctor->schedules()->where('day_of_week', $row['day_of_week'])->where('shift', $row['shift'])
                    ->when(!empty($row['id']), fn ($q) => $q->whereKeyNot($row['id']))->exists();
                if ($duplicate) throw ValidationException::withMessages(['schedules' => 'A schedule already exists for this working day and shift.']);
                if (\Carbon\Carbon::parse($row['start_time'])->diffInMinutes(\Carbon\Carbon::parse($row['end_time'])) < $row['slot_minutes']) {
                    throw ValidationException::withMessages(['schedules' => 'The shift must be long enough for at least one appointment slot.']);
                }
            }
            foreach ($validated['schedules'] as $scheduleData) {
                $schedule = !empty($scheduleData['id'])
                    ? $doctor->schedules()->findOrFail($scheduleData['id'])
                    : new DoctorSchedule(['doctor_id' => $doctor->id]);
                $schedule->fill($scheduleData)->save();
            }
        });

        AuditLogService::log('Updated schedules', 'Doctors', $doctor->id);
        return response()->json(['message' => 'Schedules updated successfully.']);
    }

    public function store(Request $request, Doctor $doctor): JsonResponse
    {
        $data = $this->validateSchedule($request);
        $schedule = $doctor->schedules()->create($data);
        AuditLogService::log('Created schedule', 'Doctors', $doctor->id);
        return response()->json($schedule, 201);
    }

    public function updateOne(Request $request, Doctor $doctor, DoctorSchedule $schedule): JsonResponse
    {
        abort_unless($schedule->doctor_id === $doctor->id, 404);
        $schedule->update($this->validateSchedule($request, $schedule));
        AuditLogService::log('Updated schedule', 'Doctors', $doctor->id);
        return response()->json($schedule);
    }

    public function destroy(Doctor $doctor, DoctorSchedule $schedule): JsonResponse
    {
        abort_unless($schedule->doctor_id === $doctor->id, 404);
        $schedule->delete();
        AuditLogService::log('Deleted schedule', 'Doctors', $doctor->id);
        return response()->json(null, 204);
    }

    private function validateSchedule(Request $request, ?DoctorSchedule $schedule = null): array
    {
        $validated = $request->validate([
            'day_of_week' => ['required', Rule::in(['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'])],
            'shift' => [
                'required',
                Rule::in(['Morning', 'Afternoon']),
                Rule::unique('doctor_schedules')->where(fn ($query) => $query
                    ->where('doctor_id', $request->route('doctor')->id)
                    ->where('day_of_week', $request->day_of_week))->ignore($schedule?->id),
            ],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_minutes' => 'required|integer|min:5|max:240',
            'is_working' => 'required|boolean',
        ]);
        if (\Carbon\Carbon::parse($validated['start_time'])->diffInMinutes(\Carbon\Carbon::parse($validated['end_time'])) < $validated['slot_minutes']) {
            throw ValidationException::withMessages(['slot_minutes' => 'The shift must be long enough for at least one appointment slot.']);
        }
        return $validated;
    }

    public function blockedDates(Doctor $doctor): JsonResponse
    {
        return response()->json($doctor->blockedDates);
    }

    public function addBlockedDate(Request $request, Doctor $doctor): JsonResponse
    {
        $validated = $request->validate([
            'block_date' => 'required|date',
            'block_type' => 'required|string|in:Leave,Blocked',
            'reason' => 'nullable|string|max:255',
        ]);

        $validated['doctor_id'] = $doctor->id;
        $blockedDate = DoctorBlockedDate::create($validated);

        AuditLogService::log('Added blocked date', 'Doctors', $doctor->id);
        return response()->json($blockedDate, 201);
    }

    public function removeBlockedDate(Doctor $doctor, DoctorBlockedDate $blockedDate): JsonResponse
    {
        if ($blockedDate->doctor_id !== $doctor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blockedDate->delete();
        AuditLogService::log('Removed blocked date', 'Doctors', $doctor->id);
        return response()->json(null, 204);
    }
}

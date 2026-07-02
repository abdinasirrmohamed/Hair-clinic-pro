<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorBlockedDate;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'schedules.*.day_of_week' => 'required|string',
            'schedules.*.start_time' => 'required',
            'schedules.*.end_time' => 'required',
            'schedules.*.slot_minutes' => 'required|integer',
            'schedules.*.is_working' => 'required|boolean',
        ]);

        foreach ($validated['schedules'] as $scheduleData) {
            DoctorSchedule::updateOrCreate(
                ['doctor_id' => $doctor->id, 'day_of_week' => $scheduleData['day_of_week']],
                $scheduleData
            );
        }

        AuditLogService::log('Updated schedules', 'Doctors', $doctor->id);
        return response()->json(['message' => 'Schedules updated successfully.']);
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
            'reason' => 'nullable|string',
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

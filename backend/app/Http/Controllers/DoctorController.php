<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $doctors = Doctor::with('user')->paginate(15);
        return response()->json($doctors);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string',
            'specialization' => 'required|string',
            'qualification' => 'nullable|string',
            'phone' => 'required|string',
            'consultation_fee' => 'nullable|numeric|min:0',
            'email' => 'nullable|email',
            'license_number' => 'required|string|unique:doctors',
            'experience_years' => 'integer|min:0',
            'bio' => 'nullable|string',
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'user_id' => 'nullable|exists:users,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072'
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('doctors', 'public');
        }

        DB::beginTransaction();
        try {
            $doctor = Doctor::create($validated);

            // Auto-create default weekly schedule
            $days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
            foreach ($days as $day) {
                DoctorSchedule::create([
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'slot_minutes' => 30,
                    'is_working' => in_array($day, ['Saturday','Sunday','Monday','Tuesday','Wednesday']),
                ]);
            }

            DB::commit();
            AuditLogService::log('Created doctor', 'Doctors', $doctor->id);

            return response()->json($doctor, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating doctor: ' . $e->getMessage()], 500);
        }
    }

    public function show(Doctor $doctor): JsonResponse
    {
        $doctor->load(['user', 'schedules', 'blockedDates']);
        return response()->json($doctor);
    }

    public function update(Request $request, Doctor $doctor): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'string',
            'specialization' => 'string',
            'qualification' => 'nullable|string',
            'phone' => 'string',
            'consultation_fee' => 'nullable|numeric|min:0',
            'email' => 'nullable|email',
            'license_number' => ['string', Rule::unique('doctors')->ignore($doctor->id)],
            'experience_years' => 'integer|min:0',
            'bio' => 'nullable|string',
            'status' => [Rule::in(['Active', 'Inactive'])],
            'user_id' => 'nullable|exists:users,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072'
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('doctors', 'public');
        }

        $doctor->update($validated);
        AuditLogService::log('Updated doctor', 'Doctors', $doctor->id);

        return response()->json($doctor);
    }

    public function destroy(Doctor $doctor): JsonResponse
    {
        $doctor->delete();
        AuditLogService::log('Deleted doctor', 'Doctors', $doctor->id);
        return response()->json(null, 204);
    }
}

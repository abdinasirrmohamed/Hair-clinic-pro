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
    private array $specializations = [
        'Hair Transplant Surgeon',
        'FUE Hair Transplant Specialist',
        'DHI Hair Transplant Specialist',
        'Dermatologist',
        'Hair Loss Specialist',
        'PRP Specialist',
        'Scalp Treatment Specialist',
        'Cosmetic Hair Transplant Surgeon',
    ];

    private array $qualifications = [
        'MBBS',
        'MBBS, MD Dermatology',
        'MD Dermatology',
        'Fellowship in Hair Restoration',
        'Board Certified Dermatologist',
        'Cosmetic Surgery Fellowship',
    ];

    public function index(Request $request): JsonResponse
    {
        $doctors = Doctor::with('user')->paginate(15);
        return response()->json($doctors);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:150',
            'specialization' => ['required', Rule::in($this->specializations)],
            'qualification' => ['nullable', Rule::in($this->qualifications)],
            'phone' => 'required|string|max:30',
            'consultation_fee' => 'nullable|numeric|decimal:0,2|min:0|max:99999999.99',
            'email' => 'nullable|email|max:150',
            'license_number' => ['required', 'string', 'max:80', Rule::unique('doctors')->ignore(Doctor::where('user_id', $request->integer('user_id'))->value('id'))],
            'experience_years' => 'required|integer|min:0|max:80',
            'bio' => 'nullable|string',
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'user_id' => [
                'integer',
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'Doctor')),
            ],
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072'
        ]);

        $validated['full_name'] = User::findOrFail($validated['user_id'])->full_name;
        if (array_key_exists('status', $validated)) $validated['status'] ??= 'Active';

        if (array_key_exists('consultation_fee', $validated)) $validated['consultation_fee'] ??= 0;

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('doctors', 'public');
        }

        DB::beginTransaction();
        try {
            // A Doctor user gets a basic profile when the account is created.
            // Reuse that profile instead of attempting to insert a duplicate user_id.
            $doctor = !empty($validated['user_id'])
                ? Doctor::where('user_id', $validated['user_id'])->first()
                : null;

            if ($doctor) {
                $licenseOwner = Doctor::where('license_number', $validated['license_number'])
                    ->whereKeyNot($doctor->id)
                    ->exists();

                if ($licenseOwner) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'The license number has already been taken.',
                        'errors' => ['license_number' => ['The license number has already been taken.']],
                    ], 422);
                }

                $doctor->update($validated);
            } else {
                $doctor = Doctor::create($validated);
            }

            // Auto-create default weekly schedule
            if ($doctor->schedules()->count() === 0) {
                $days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
                foreach ($days as $day) {
                    DoctorSchedule::create([
                        'doctor_id' => $doctor->id,
                        'day_of_week' => $day,
                        'start_time' => '08:00:00',
                        'end_time' => '11:00:00',
                        'slot_minutes' => 24,
                        'is_working' => in_array($day, ['Saturday','Sunday','Monday','Tuesday','Wednesday']),
                    ]);
                }
            }

            DB::commit();
            AuditLogService::log('Created doctor', 'Doctors', $doctor->id);

            return response()->json($doctor->fresh('user'), 201);
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
            'full_name' => 'string|max:150',
            'specialization' => [Rule::in($this->specializations)],
            'qualification' => ['nullable', Rule::in($this->qualifications)],
            'phone' => 'string|max:30',
            'consultation_fee' => 'nullable|numeric|decimal:0,2|min:0|max:99999999.99',
            'email' => 'nullable|email|max:150',
            'license_number' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('doctors')->ignore($doctor->id)],
            'experience_years' => 'sometimes|required|integer|min:0|max:80',
            'bio' => 'nullable|string',
            'status' => [Rule::in(['Active', 'Inactive'])],
            'user_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->where('role', 'Doctor'), Rule::unique('doctors', 'user_id')->ignore($doctor->id)],
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072'
        ]);

        if (!empty($validated['user_id'])) {
            $validated['full_name'] = User::findOrFail($validated['user_id'])->full_name;
        }

        if (array_key_exists('consultation_fee', $validated)) $validated['consultation_fee'] ??= 0;

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

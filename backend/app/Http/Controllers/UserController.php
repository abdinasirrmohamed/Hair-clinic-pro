<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private array $roles = ['Administrator', 'Receptionist', 'Doctor', 'Inventory Officer', 'Pharmacy User', 'Lab User'];

    private function allowedModules(): array
    {
        return collect(config('roles.module_permissions'))
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    private function cleanPermissions(?array $permissions): ?array
    {
        if ($permissions === null) {
            return null;
        }

        $allowed = $this->allowedModules();
        return collect($permissions)
            ->filter(fn ($module) => in_array($module, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function ensureDoctorProfile(User $user): void
    {
        if ($user->role !== 'Doctor') {
            return;
        }

        $doctor = Doctor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->full_name,
                'specialization' => 'Hair Loss Specialist',
                'qualification' => 'MBBS',
                'phone' => $user->username,
                'consultation_fee' => 25,
                'license_number' => 'HC-USER-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'experience_years' => 0,
                'status' => $user->status === 'Active' ? 'Active' : 'Inactive',
            ]
        );

        $doctor->update([
            'full_name' => $user->full_name,
            'status' => $user->status === 'Active' ? 'Active' : 'Inactive',
        ]);

        if ($doctor->schedules()->count() === 0) {
            foreach (['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
                DoctorSchedule::create([
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '11:00:00',
                    'slot_minutes' => 24,
                    'is_working' => in_array($day, ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday'], true),
                ]);
            }
        }
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::paginate(15);
        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users',
            'full_name' => 'required|string',
            'role' => ['required', Rule::in($this->roles)],
            'password' => 'required|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&]/',
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'module_permissions' => 'nullable|array',
            'module_permissions.*' => [Rule::in($this->allowedModules())],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = $validated['status'] ?? 'Active';
        $validated['module_permissions'] = $this->cleanPermissions($validated['module_permissions'] ?? null)
            ?? (config('roles.module_permissions')[$validated['role']] ?? []);

        $user = User::create($validated);
        $this->ensureDoctorProfile($user);

        AuditLogService::log('Created user', 'Users', $user->id);

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['string', Rule::unique('users')->ignore($user->id)],
            'full_name' => 'string',
            'role' => [Rule::in($this->roles)],
            'password' => 'nullable|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&]/',
            'status' => [Rule::in(['Active', 'Inactive'])],
            'module_permissions' => 'nullable|array',
            'module_permissions.*' => [Rule::in($this->allowedModules())],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (array_key_exists('module_permissions', $validated)) {
            $validated['module_permissions'] = $this->cleanPermissions($validated['module_permissions']);
        }

        $user->update($validated);
        $this->ensureDoctorProfile($user);

        AuditLogService::log('Updated user', 'Users', $user->id);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        AuditLogService::log('Deleted user', 'Users', $user->id);
        return response()->json(null, 204);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'full_name' => 'required|string',
            'old_password' => 'nullable|string',
            'password' => 'nullable|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&]/',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        if (isset($validated['password'])) {
            if (!Hash::check($validated['old_password'], $user->password)) {
                return response()->json(['message' => 'Incorrect old password.'], 400);
            }
            $user->password = Hash::make($validated['password']);
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $user->full_name = $validated['full_name'];
        $user->save();

        AuditLogService::log('Updated profile', 'Users', $user->id);

        return response()->json($user->fresh());
    }
}

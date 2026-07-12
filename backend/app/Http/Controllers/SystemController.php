<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\LabTest;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $permissions = $user->effectiveModulePermissions();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role,
                'status' => $user->status,
                'profile_photo_path' => $user->profile_photo_path,
                'profile_photo_url' => $user->profile_photo_url,
                'module_permissions' => $user->module_permissions,
            ],
            'permissions' => $permissions,
            'role_permissions' => config('roles.module_permissions'),
            'lookups' => [
                'patients' => Patient::orderBy('created_at', 'desc')->get([
                    'id', 'full_name', 'phone', 'gender', 'age', 'address',
                ]),
                'doctors' => Doctor::where('status', 'Active')->orderBy('full_name')->get([
                    'id', 'full_name', 'specialization', 'phone', 'consultation_fee',
                ]),
                'medicines' => Medicine::orderBy('medicine_name')->get([
                    'id', 'medicine_name', 'quantity', 'unit_price', 'reorder_level',
                ]),
                'lab_tests' => LabTest::where('status', 'Active')->orderBy('test_name')->get([
                    'id', 'test_name', 'category', 'price', 'sample_type',
                ]),
                'treatments' => Treatment::latest('treatment_date')->get(['id', 'patient_id', 'treatment_name']),
                'appointments' => Appointment::latest('appointment_date')->get(['id', 'patient_id', 'appointment_date']),
                'users' => User::orderBy('full_name')->get(['id', 'full_name', 'role', 'status']),
                'doctor_users' => User::where('role', 'Doctor')->where('status', 'Active')
                    ->orderBy('full_name')->get(['id', 'full_name']),
            ],
        ]);
    }
}
